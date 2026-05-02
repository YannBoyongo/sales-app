<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Catalog for a dealer selling motorcycles, engine oil, spare parts and cooking gas.
 */
class MotorcycleShopCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $motos = Department::query()->firstOrCreate(['name' => 'Motos']);
        $huiles = Department::query()->firstOrCreate(['name' => 'Huiles moteur']);
        $pieces = Department::query()->firstOrCreate(['name' => 'Pièces détachées']);
        $gazCuisine = Department::query()->firstOrCreate(['name' => 'Gaz de cuisine']);

        $catalog = [
            $motos->id => [
                ['name' => 'Moto routière 125 cm³', 'sku' => 'MOTO-125-R', 'description' => 'Moto neuve ou occasion, catégorie route, cylindrée 125 cm³.', 'unit_price' => 2899.00, 'minimum_stock' => 1],
                ['name' => 'Scooter urbain 50 cm³', 'sku' => 'MOTO-50-SC', 'description' => 'Scooter deux places, idéal centre-ville.', 'unit_price' => 1599.00, 'minimum_stock' => 1],
                ['name' => 'Moto trail 300 cm³', 'sku' => 'MOTO-300-TR', 'description' => 'Trail polyvalent route et chemins.', 'unit_price' => 4299.00, 'minimum_stock' => 1],
                ['name' => 'Moto électrique urbaine', 'sku' => 'MOTO-EL-01', 'description' => 'Deux-roues électrique, autonomie urbaine.', 'unit_price' => 3499.00, 'minimum_stock' => 1],
            ],
            $huiles->id => [
                ['name' => 'Huile moteur 4T 10W-40', 'sku' => 'OIL-4T-1040', 'description' => 'Lubrifiant minéral / semi-synthétique 4 temps, 1 L.', 'unit_price' => 12.50, 'minimum_stock' => 16],
                ['name' => 'Huile moteur 2T mélange', 'sku' => 'OIL-2T-01', 'description' => 'Huile deux temps pour mélange carburant, 1 L.', 'unit_price' => 14.90, 'minimum_stock' => 16],
                ['name' => 'Huile synthétique 15W-50', 'sku' => 'OIL-SYN-1550', 'description' => 'Formule synthétique haute performance, 1 L.', 'unit_price' => 18.75, 'minimum_stock' => 14],
                ['name' => 'Liquide de fourche 10W', 'sku' => 'OIL-FORK-10', 'description' => 'Huile de suspension fourche, 1 L.', 'unit_price' => 22.00, 'minimum_stock' => 12],
            ],
            $pieces->id => [
                ['name' => 'Kit plaquettes de frein avant', 'sku' => 'PART-BRK-F', 'description' => 'Jeu de plaquettes pour étrier avant (générique).', 'unit_price' => 45.00, 'minimum_stock' => 8],
                ['name' => 'Filtre à air rond', 'sku' => 'PART-AIR-01', 'description' => 'Filtre à air lavable / papier selon modèle.', 'unit_price' => 24.50, 'minimum_stock' => 12],
                ['name' => 'Kit chaîne + couronnes', 'sku' => 'PART-CHN-KIT', 'description' => 'Chaîne, couronne et pignon (kit standard).', 'unit_price' => 189.00, 'minimum_stock' => 5],
                ['name' => 'Batterie 12 V 9 Ah', 'sku' => 'PART-BAT-12', 'description' => 'Batterie AGM moto / scooter, bornes selon modèle.', 'unit_price' => 79.90, 'minimum_stock' => 6],
            ],
            $gazCuisine->id => [
                ['name' => 'Gaz 6 kg', 'sku' => 'GAZ-6KG', 'description' => 'Bouteille de gaz de cuisine 6 kg (recharge ou consigne selon point de vente).', 'unit_price' => 22.00, 'minimum_stock' => 18],
                ['name' => 'Gaz 12 kg', 'sku' => 'GAZ-12KG', 'description' => 'Bouteille de gaz de cuisine 12 kg (recharge ou consigne selon point de vente).', 'unit_price' => 38.00, 'minimum_stock' => 15],
                ['name' => 'Réchaud 2 plaques', 'sku' => 'GAZ-RECH-2P', 'description' => 'Réchaud à gaz portable, deux feux, avec détendeur compatible bouteilles standard.', 'unit_price' => 65.00, 'minimum_stock' => 6],
            ],
        ];

        foreach ($catalog as $departmentId => $products) {
            foreach ($products as $row) {
                Product::query()->updateOrCreate(
                    ['sku' => $row['sku']],
                    [
                        'department_id' => $departmentId,
                        'name' => $row['name'],
                        'description' => $row['description'],
                        'unit_price' => $row['unit_price'],
                        'minimum_stock' => $row['minimum_stock'],
                    ]
                );
            }
        }
    }
}
