<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        Setting::query()->updateOrCreate(
            ['id' => 1],
            [
                'shopname' => 'Ma Boutique',
                'phone' => '+243000000000',
                'email' => 'contact@example.com',
                'address' => 'Adresse principale',
                'rccm' => 'RCCM-0000',
                'idnat' => 'IDNAT-0000',
                'nif' => 'NIF-0000',
            ]
        );
    }
}
