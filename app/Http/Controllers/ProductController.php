<?php

namespace App\Http\Controllers;

use App\Exports\ProductsCatalogExport;
use App\Exports\ProductsSampleExport;
use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Imports\ProductsImport;
use App\Models\Department;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductController extends Controller
{
    use RespectsUserBranch;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $query = Product::query()
            ->with('department')
            ->orderBy('name');
        $this->applyProductBranchScope($query);

        $search = isset($filters['q']) ? trim($filters['q']) : '';
        if ($search !== '') {
            $escaped = addcslashes($search, '%_\\');
            $term = '%'.$escaped.'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('products.name', 'like', $term)
                    ->orWhere('products.sku', 'like', $term)
                    ->orWhere('products.description', 'like', $term);
            });
        }

        if (! empty($filters['department_id'])) {
            $query->where('products.department_id', (int) $filters['department_id']);
        }

        $products = $query->paginate(20)->withQueryString();

        $departmentIdQuery = Product::query()->select('products.department_id')->distinct();
        $this->applyProductBranchScope($departmentIdQuery);
        $departments = Department::query()
            ->whereIn('id', $departmentIdQuery)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('products.index', compact('products', 'departments', 'filters'));
    }

    public function create(): View
    {
        abort_unless(auth()->user()?->canCreateOrImportProducts(), 403);

        $departments = Department::orderBy('name')->get();

        return view('products.create', compact('departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canCreateOrImportProducts(), 403);

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
        abort_unless(auth()->user()?->canMutateProductCatalog(), 403);

        $this->ensureProductAccessibleForBranchUser($product);

        $departments = Department::orderBy('name')->get();

        return view('products.edit', compact('product', 'departments'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        abort_unless($request->user()?->canMutateProductCatalog(), 403);

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
        abort_unless(auth()->user()?->canMutateProductCatalog(), 403);

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
     * Modèle Excel : mêmes colonnes que l’import (Nom, Code, Département, …).
     */
    public function importSample(): BinaryFileResponse|RedirectResponse
    {
        abort_unless(auth()->user()?->canCreateOrImportProducts(), 403);

        if (! $this->zipExtensionAvailable()) {
            return redirect()->route('products.index')->withErrors([
                'file' => $this->zipExtensionMissingMessage(),
            ]);
        }

        $depts = Department::query()->orderBy('name')->limit(2)->pluck('name')->all();
        $d1 = $depts[0] ?? 'Département exemple';
        $d2 = $depts[1] ?? $d1;

        return Excel::download(
            new ProductsSampleExport($d1, $d2),
            'modele-import-produits.xlsx',
            ExcelFormat::XLSX
        );
    }

    /**
     * Import produits (.xlsx, .csv, .txt) via Maatwebsite/Excel — aligné sur le modèle téléchargeable.
     */
    public function import(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canCreateOrImportProducts(), 403);

        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $file = $request->file('file');
        if ($file === null) {
            return redirect()->route('products.index')->withErrors(['file' => 'Aucun fichier reçu.']);
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'csv', 'txt'], true)) {
            return redirect()
                ->route('products.index')
                ->withErrors(['file' => 'Utilisez un fichier .xlsx, .csv ou .txt (comme le modèle de la page).']);
        }

        if ($ext === 'xlsx' && ! $this->zipExtensionAvailable()) {
            return redirect()->route('products.index')->withErrors([
                'file' => $this->zipExtensionMissingMessage(),
            ]);
        }

        $departments = Department::query()->get(['id', 'name']);
        $deptByLower = [];
        foreach ($departments as $d) {
            $deptByLower[mb_strtolower(trim($d->name))] = $d->id;
        }

        $import = new ProductsImport($deptByLower);

        try {
            Excel::import($import, $file);
        } catch (\Throwable $e) {
            Log::warning('Import produits : échec lecture fichier', [
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            if (str_contains($e->getMessage(), 'ZipArchive')) {
                return redirect()->route('products.index')->withErrors([
                    'file' => $this->zipExtensionMissingMessage(),
                ]);
            }

            $msg = 'Impossible de lire ce fichier. Utilisez le modèle .xlsx de la page ou un CSV UTF-8 valide.';
            if (config('app.debug')) {
                $msg .= ' '.$e->getMessage();
            }

            return redirect()->route('products.index')->withErrors(['file' => $msg]);
        }

        $errors = array_merge($import->fatalErrors, $import->errors);
        $created = $import->createdCount;

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

    public function exportExcel(): BinaryFileResponse|RedirectResponse
    {
        if (! $this->zipExtensionAvailable()) {
            return redirect()->route('products.index')->withErrors([
                'file' => $this->zipExtensionMissingMessage(),
            ]);
        }

        $products = $this->productsForExport();

        return Excel::download(
            new ProductsCatalogExport($products),
            'produits-'.now()->format('Y-m-d').'.xlsx',
            ExcelFormat::XLSX
        );
    }

    private function zipExtensionAvailable(): bool
    {
        return extension_loaded('zip') && class_exists(\ZipArchive::class);
    }

    private function zipExtensionMissingMessage(): string
    {
        return 'Les fichiers Excel (.xlsx) nécessitent l’extension PHP « zip » (classe ZipArchive). '
            .'Sous XAMPP : ouvrez le php.ini utilisé par Apache (panneau XAMPP → Apache → Config → PHP (php.ini), ou regardez « Loaded Configuration File » dans un phpinfo() affiché par le serveur web), '
            .'assurez-vous d’avoir la ligne extension=zip (sans point-virgule devant), enregistrez puis redémarrez Apache. '
            .'Le PHP en ligne de commande peut être différent du PHP d’Apache. '
            .'En attendant, vous pouvez importer un fichier .csv UTF-8 avec les mêmes colonnes que le modèle.';
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
