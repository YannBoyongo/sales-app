<?php

namespace App\Services;

use App\Models\Product;

class ProductImportService
{
    /**
     * @param  list<string>  $headerRow
     * @param  iterable<int, array<int, mixed>>  $dataRows
     * @return array{created: int, errors: list<string>}
     */
    public function process(array $headerRow, iterable $dataRows, array $deptByLower): array
    {
        $columnIndex = $this->mapImportHeaderRow($headerRow);
        if (! isset($columnIndex['name'], $columnIndex['department'], $columnIndex['unit_price'])) {
            return [
                'created' => 0,
                'errors' => ['Colonnes obligatoires : Nom, Catégorie, Prix unitaire. (Voir le modèle téléchargeable.)'],
            ];
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
                $errors[] = 'Ligne '.$lineNo.' : catégorie « '.$deptName.' » introuvable.';

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

        return ['created' => $created, 'errors' => $errors];
    }

    /**
     * @param  list<string|null>  $headerRow
     * @return array<string, int>
     */
    public function mapImportHeaderRow(array $headerRow): array
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
                'département', 'departement', 'department', 'category', 'catégorie', 'categorie' => 'department',
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

    public function normalizeImportHeaderKey(string $header): string
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
    public function importRowIsEmpty(array|false $row): bool
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
     * @return array{ok: true, value: float}|array{ok: false}
     */
    public function parseUnitPriceFromImport(mixed $raw): array
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
    public function parseMinimumStockFromImport(mixed $raw, int $lineNo): array
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
     * @param  list<mixed>  $cells
     * @return list<string>
     */
    public function headerCellsToStrings(array $cells): array
    {
        return array_map(fn ($c) => $this->importCellToTrimmedString($c), $cells);
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
        if ($cell instanceof \DateTimeInterface) {
            return trim($cell->format('Y-m-d H:i:s'));
        }

        return trim((string) $cell);
    }
}
