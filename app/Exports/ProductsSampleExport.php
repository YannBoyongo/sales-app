<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class ProductsSampleExport implements FromArray, WithTitle
{
    public function __construct(
        private string $departmentExample1,
        private string $departmentExample2,
    ) {}

    /**
     * Même colonnes que l’import attendu (voir modèle sur /products).
     *
     * @return list<list<string|float|int>>
     */
    public function array(): array
    {
        return [
            ['Nom', 'Code', 'Catégorie', 'Description', 'Prix unitaire', 'Seuil min.'],
            ['Exemple produit A', 'EX-A-001', $this->departmentExample1, 'Produit de démonstration', 12.5, 5],
            ['Exemple produit B', '', $this->departmentExample2, '', 9, ''],
        ];
    }

    public function title(): string
    {
        return 'Produits';
    }
}
