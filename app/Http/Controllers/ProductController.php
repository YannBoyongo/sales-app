<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\Department;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    use RespectsUserBranch;

    public function index(): View
    {
        $query = Product::query()
            ->with('department')
            ->orderBy('name');
        $this->applyProductBranchScope($query);

        $products = $query->paginate(20);

        return view('products.index', compact('products'));
    }

    public function create(): View
    {
        abort_unless(! auth()->user()?->isInventoryReadOnly(), 403);

        $departments = Department::orderBy('name')->get();

        return view('products.create', compact('departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(! $request->user()?->isInventoryReadOnly(), 403);

        $data = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku'],
            'description' => ['nullable', 'string'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'minimum_stock' => ['nullable', 'integer', 'min:0'],
        ]);

        Product::create($data);

        return redirect()->route('products.index')->with('success', 'Produit créé.');
    }

    public function edit(Product $product): View
    {
        abort_unless(! auth()->user()?->isInventoryReadOnly(), 403);

        $this->ensureProductAccessibleForBranchUser($product);

        $departments = Department::orderBy('name')->get();

        return view('products.edit', compact('product', 'departments'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        abort_unless(! $request->user()?->isInventoryReadOnly(), 403);

        $this->ensureProductAccessibleForBranchUser($product);

        $data = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku,'.$product->id],
            'description' => ['nullable', 'string'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'minimum_stock' => ['nullable', 'integer', 'min:0'],
        ]);

        $product->update($data);

        return redirect()->route('products.index')->with('success', 'Produit mis à jour.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        abort_unless(! auth()->user()?->isInventoryReadOnly(), 403);

        $this->ensureProductAccessibleForBranchUser($product);

        $product->delete();

        return redirect()->route('products.index')->with('success', 'Produit supprimé.');
    }

    public function exportPdf(): Response
    {
        $products = $this->productsForExport();

        return Pdf::loadView('products.export_pdf', compact('products'))
            ->download('produits-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * Modèle Excel (.xlsx) pour import.
     */
    public function importSample(): StreamedResponse
    {
        $depts = Department::query()->orderBy('name')->limit(2)->pluck('name')->all();
        $d1 = $depts[0] ?? 'Département exemple';
        $d2 = $depts[1] ?? $d1;

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Nom', 'Code', 'Département', 'Description', 'Prix unitaire', 'Seuil min.'],
            ['Exemple produit A', 'EX-A-001', $d1, 'Produit de démonstration', 12.5, 5],
            ['Exemple produit B', '', $d2, '', 9, ''],
        ], null, 'A1');

        return $this->streamSpreadsheetAsXlsx($spreadsheet, 'modele-import-produits.xlsx');
    }

    /**
     * Import produits depuis un fichier .xlsx ou CSV (séparateur ; ou ,), UTF-8.
     */
    public function import(Request $request): RedirectResponse
    {
        abort_unless(! $request->user()?->isInventoryReadOnly(), 403);

        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $file = $request->file('file');
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($ext, ['csv', 'txt', 'xlsx'], true)) {
            return redirect()
                ->route('products.index')
                ->withErrors(['file' => 'Utilisez un fichier .xlsx, .csv ou .txt.']);
        }

        $parsed = match ($ext) {
            'xlsx' => $this->parseProductImportXlsx($file),
            default => $this->parseProductImportCsv($file),
        };

        if ($parsed instanceof RedirectResponse) {
            return $parsed;
        }

        [$headerRow, $dataRows] = $parsed;

        return $this->processImportedProductMatrix($headerRow, $dataRows);
    }

    /**
     * Import produits : tableau de lignes (1ʳᵉ ligne = en-têtes), envoyé depuis le navigateur après lecture .xlsx/.csv en JavaScript.
     */
    public function importJson(Request $request): RedirectResponse
    {
        abort_unless(! $request->user()?->isInventoryReadOnly(), 403);

        $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:2001'],
            'rows.*' => ['array'],
        ]);

        /** @var list<array<int, mixed>> $matrix */
        $matrix = $request->input('rows');
        $headerRaw = array_shift($matrix);
        if (! is_array($headerRaw)) {
            return redirect()->route('products.index')->withErrors(['file' => 'En-têtes de colonnes manquants.']);
        }

        $headerRow = array_map(fn ($c) => $this->importCellToTrimmedString($c), $headerRaw);
        $dataRows = [];
        foreach ($matrix as $row) {
            $dataRows[] = is_array($row) ? $row : [];
        }

        return $this->processImportedProductMatrix($headerRow, $dataRows);
    }

    /**
     * @param  list<string>  $headerRow
     * @param  list<array<mixed>>  $dataRows
     */
    private function processImportedProductMatrix(array $headerRow, array $dataRows): RedirectResponse
    {
        $columnIndex = $this->mapImportHeaderRow($headerRow);
        if (! isset($columnIndex['name'], $columnIndex['department'], $columnIndex['unit_price'])) {
            return redirect()->route('products.index')->withErrors([
                'file' => 'Colonnes obligatoires : Nom, Département, Prix unitaire. (Voir le modèle téléchargeable.)',
            ]);
        }

        $departments = Department::query()->get(['id', 'name']);
        $deptByLower = [];
        foreach ($departments as $d) {
            $deptByLower[mb_strtolower(trim($d->name))] = $d->id;
        }

        $created = 0;
        $errors = [];
        $lineNo = 1;
        $seenSkus = [];
        $maxRows = 2000;

        foreach ($dataRows as $row) {
            $lineNo++;
            if ($lineNo > $maxRows + 1) {
                $errors[] = 'Import limité à '.$maxRows.' lignes de données. Le reste a été ignoré.';
                break;
            }
            if (! is_array($row)) {
                continue;
            }
            if ($this->importRowIsEmpty($row)) {
                continue;
            }

            $name = trim((string) ($row[$columnIndex['name']] ?? ''));
            if ($name === '') {
                $errors[] = 'Ligne '.$lineNo.' : nom manquant.';

                continue;
            }

            $deptName = trim((string) ($row[$columnIndex['department']] ?? ''));
            $deptKey = mb_strtolower($deptName);
            if (! isset($deptByLower[$deptKey])) {
                $errors[] = 'Ligne '.$lineNo.' : département « '.$deptName.' » introuvable.';

                continue;
            }
            $departmentId = $deptByLower[$deptKey];

            $priceParsed = $this->parseUnitPriceFromImport($row[$columnIndex['unit_price']] ?? null);
            if (! $priceParsed['ok']) {
                $errors[] = 'Ligne '.$lineNo.' : prix unitaire invalide.';

                continue;
            }
            $unitPrice = $priceParsed['value'];

            $sku = isset($columnIndex['sku']) ? trim((string) ($row[$columnIndex['sku']] ?? '')) : '';
            $sku = $sku === '' ? null : mb_substr($sku, 0, 100);
            if ($sku !== null) {
                if (isset($seenSkus[$sku])) {
                    $errors[] = 'Ligne '.$lineNo.' : code « '.$sku.' » en doublon dans le fichier.';

                    continue;
                }
                $seenSkus[$sku] = true;
                if (Product::query()->where('sku', $sku)->exists()) {
                    $errors[] = 'Ligne '.$lineNo.' : code « '.$sku.' » déjà utilisé.';

                    continue;
                }
            }

            $description = isset($columnIndex['description'])
                ? trim((string) ($row[$columnIndex['description']] ?? ''))
                : '';
            $description = $description === '' ? null : $description;

            $minStockResult = $this->parseMinimumStockFromImport(
                isset($columnIndex['minimum_stock']) ? ($row[$columnIndex['minimum_stock']] ?? null) : null,
                $lineNo
            );
            if (! $minStockResult['ok']) {
                $errors[] = $minStockResult['error'];

                continue;
            }
            $minStock = $minStockResult['value'];

            try {
                Product::query()->create([
                    'department_id' => $departmentId,
                    'name' => mb_substr($name, 0, 255),
                    'sku' => $sku,
                    'description' => $description,
                    'unit_price' => $unitPrice,
                    'minimum_stock' => $minStock,
                ]);
                $created++;
            } catch (\Throwable $e) {
                $errors[] = 'Ligne '.$lineNo.' : '.$e->getMessage();
            }
        }

        $redirect = redirect()->route('products.index');
        if ($created > 0) {
            $redirect->with('success', $created.' produit(s) importé(s).');
        }
        if ($errors !== []) {
            $redirect->with('import_errors', $errors);
        }
        if ($created === 0 && $errors === []) {
            $redirect->with('warning', 'Aucune ligne importée (fichier sans données utiles).');
        }

        return $redirect;
    }

    /**
     * @param  list<string|null>  $headerRow
     * @return array<string, int>
     */
    private function mapImportHeaderRow(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $i => $cell) {
            $key = $this->normalizeImportHeaderKey((string) $cell);
            if ($key === '') {
                continue;
            }
            $field = match ($key) {
                'nom', 'name', 'produit', 'libellé', 'libelle' => 'name',
                'code', 'sku', 'référence', 'reference', 'ref' => 'sku',
                'département', 'departement', 'department' => 'department',
                'description' => 'description',
                'prix unitaire', 'prix_unitaire', 'prix', 'unit_price', 'pu' => 'unit_price',
                'seuil min.', 'seuil min', 'seuil_min', 'minimum_stock', 'seuil' => 'minimum_stock',
                default => null,
            };
            if ($field !== null && ! isset($map[$field])) {
                $map[$field] = $i;
            }
        }

        return $map;
    }

    private function normalizeImportHeaderKey(string $header): string
    {
        $header = trim($header);
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        $header = mb_strtolower($header);
        $header = preg_replace('/\s*\([^)]*\)\s*$/u', '', $header) ?? $header;

        return trim($header);
    }

    /**
     * @param  list<string|null>|false  $row
     */
    private function importRowIsEmpty(array|false $row): bool
    {
        if ($row === false) {
            return true;
        }
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Export Excel (.xlsx).
     */
    public function exportExcel(): StreamedResponse
    {
        $products = $this->productsForExport();
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Nom', 'Code', 'Département', 'Seuil min.', 'Prix unitaire (USD)']], null, 'A1');
        $r = 2;
        foreach ($products as $p) {
            $sheet->fromArray([[
                $p->name,
                $p->sku ?? '',
                $p->department->name ?? '',
                $p->minimum_stock ?? '',
                round((float) $p->unit_price, 2),
            ]], null, 'A'.$r);
            $r++;
        }

        return $this->streamSpreadsheetAsXlsx($spreadsheet, 'produits-'.now()->format('Y-m-d').'.xlsx');
    }

    private function streamSpreadsheetAsXlsx(Spreadsheet $spreadsheet, string $downloadName): StreamedResponse
    {
        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $downloadName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array{0: list<string>, 1: list<list<mixed>>}|RedirectResponse
     */
    private function parseProductImportXlsx(UploadedFile $file): array|RedirectResponse
    {
        if (! extension_loaded('zip') || ! class_exists(\ZipArchive::class)) {
            return redirect()->route('products.index')->withErrors([
                'file' => 'L’extension PHP « zip » est requise pour lire les fichiers .xlsx. Activez extension=zip dans php.ini, puis redémarrez Apache (XAMPP).',
            ]);
        }

        // Charger depuis storage évite souvent les échecs ZipArchive sur le fichier temporaire d’upload (Windows / open_basedir).
        $relativePath = $file->storeAs('imports/tmp', Str::uuid().'.xlsx', 'local');
        if ($relativePath === false) {
            return redirect()->route('products.index')->withErrors(['file' => 'Impossible d’enregistrer le fichier importé.']);
        }

        $fullPath = Storage::disk('local')->path($relativePath);

        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($fullPath);
        } catch (\Throwable $e) {
            Log::warning('Import produits .xlsx : lecture échouée', [
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            $userMessage = 'Impossible de lire ce fichier Excel (.xlsx). Vérifiez qu’il s’agit d’un vrai classeur Excel (pas un CSV renommé).';
            if (config('app.debug')) {
                $userMessage .= ' Détail technique : '.$e->getMessage();
            }

            return redirect()->route('products.index')->withErrors(['file' => $userMessage]);
        } finally {
            Storage::disk('local')->delete($relativePath);
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
        $spreadsheet->disconnectWorksheets();

        if ($rows === []) {
            return redirect()->route('products.index')->withErrors(['file' => 'Fichier vide.']);
        }

        $headerRow = array_shift($rows);
        if (! is_array($headerRow) || $headerRow === []) {
            return redirect()->route('products.index')->withErrors(['file' => 'En-têtes de colonnes manquants.']);
        }

        $headerRow = array_map(fn ($c) => $this->importCellToTrimmedString($c), $headerRow);

        $dataRows = [];
        foreach ($rows as $row) {
            $dataRows[] = is_array($row) ? $row : [];
        }

        return [$headerRow, $dataRows];
    }

    /**
     * @return array{0: list<string>, 1: list<list<mixed>>}|RedirectResponse
     */
    private function parseProductImportCsv(UploadedFile $file): array|RedirectResponse
    {
        $path = $file->getRealPath();
        if ($path === false) {
            return redirect()->route('products.index')->withErrors(['file' => 'Fichier illisible.']);
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return redirect()->route('products.index')->withErrors(['file' => 'Impossible d’ouvrir le fichier.']);
        }

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);

            return redirect()->route('products.index')->withErrors(['file' => 'Fichier vide.']);
        }

        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
        $headerRow = str_getcsv(rtrim($firstLine, "\r\n"), $delimiter);
        if ($headerRow === [] || $headerRow === ['']) {
            fclose($handle);

            return redirect()->route('products.index')->withErrors(['file' => 'En-têtes de colonnes manquants.']);
        }

        $headerRow = array_map(fn ($c) => $this->importCellToTrimmedString($c), $headerRow);

        $dataRows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $dataRows[] = $row;
        }

        fclose($handle);

        return [$headerRow, $dataRows];
    }

    private function importCellToTrimmedString(mixed $cell): string
    {
        if ($cell === null) {
            return '';
        }
        if (is_string($cell)) {
            return trim($cell);
        }
        if (is_numeric($cell) || $cell instanceof \Stringable) {
            return trim((string) $cell);
        }

        return trim((string) $cell);
    }

    /**
     * @return array{ok: true, value: float}|array{ok: false}
     */
    private function parseUnitPriceFromImport(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return ['ok' => false];
        }
        if (is_numeric($raw)) {
            $unitPrice = (float) $raw;

            return $unitPrice < 0 ? ['ok' => false] : ['ok' => true, 'value' => $unitPrice];
        }

        $priceNormalized = str_replace([' ', "\xc2\xa0"], '', str_replace(',', '.', trim((string) $raw)));
        if ($priceNormalized === '' || ! is_numeric($priceNormalized)) {
            return ['ok' => false];
        }
        $unitPrice = (float) $priceNormalized;

        return $unitPrice < 0 ? ['ok' => false] : ['ok' => true, 'value' => $unitPrice];
    }

    /**
     * @return array{ok: true, value: int|null}|array{ok: false, error: string}
     */
    private function parseMinimumStockFromImport(mixed $raw, int $lineNo): array
    {
        if ($raw === null || $raw === '') {
            return ['ok' => true, 'value' => null];
        }
        if (is_numeric($raw)) {
            $f = (float) $raw;
            if ($f < 0 || abs($f - round($f)) > 1e-9) {
                return ['ok' => false, 'error' => 'Ligne '.$lineNo.' : seuil min. doit être un entier ≥ 0.'];
            }

            return ['ok' => true, 'value' => (int) round($f)];
        }

        $minStr = trim((string) $raw);
        if ($minStr === '') {
            return ['ok' => true, 'value' => null];
        }
        if (! ctype_digit($minStr)) {
            return ['ok' => false, 'error' => 'Ligne '.$lineNo.' : seuil min. doit être un entier.'];
        }

        return ['ok' => true, 'value' => (int) $minStr];
    }

    /**
     * @return Collection<int, Product>
     */
    protected function productsForExport(): Collection
    {
        $query = Product::query()
            ->with('department')
            ->orderBy('name');
        $this->applyProductBranchScope($query);

        return $query->get();
    }
}
