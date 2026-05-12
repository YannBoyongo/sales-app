<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ProductsCatalogExport implements FromCollection, WithHeadings, WithTitle
{
    /**
     * @param  Collection<int, Product>  $products
     */
    public function __construct(
        private Collection $products,
    ) {}

    public function collection(): Collection
    {
        return $this->products->map(fn ($p) => [
            $p->name,
            $p->sku ?? '',
            $p->department->name ?? '',
            $p->minimum_stock ?? '',
            round((float) $p->unit_price, 2),
        ]);
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['Nom', 'Code', 'Catégorie', 'Seuil min.', 'Prix unitaire (USD)'];
    }

    public function title(): string
    {
        return 'Produits';
    }
}
