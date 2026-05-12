<?php

namespace App\Imports;

use App\Services\ProductImportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class ProductsImport implements ToCollection
{
    public int $createdCount = 0;

    /** @var list<string> */
    public array $errors = [];

    /** @var list<string> */
    public array $fatalErrors = [];

    private ProductImportService $importService;

    public function __construct(
        private array $deptByLower,
        ?ProductImportService $importService = null,
    ) {
        $this->importService = $importService ?? new ProductImportService;
    }

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            $this->fatalErrors[] = 'Fichier vide.';

            return;
        }

        $first = $rows->first();
        $headerCells = $first instanceof Collection
            ? $first->values()->all()
            : array_values((array) $first);
        $headerRow = $this->importService->headerCellsToStrings($headerCells);

        $dataRows = [];
        foreach ($rows->slice(1) as $row) {
            $dataRows[] = $row instanceof Collection
                ? $row->values()->all()
                : array_values((array) $row);
        }

        $result = $this->importService->process($headerRow, $dataRows, $this->deptByLower);
        $this->createdCount = $result['created'];
        $this->errors = $result['errors'];
    }
}
