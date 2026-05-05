<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Location;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SettingSeeder::class);

        $branch = Branch::query()->create(['name' => 'Goma Signers']);
        $branch_1 = Branch::query()->create(['name' => 'Bukavu']);

        User::query()->create([
            'name' => 'Administrateur',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'role' => UserRole::Admin,
            'branch_id' => null,
        ]);

        User::query()->create([
            'name' => 'Caissier',
            'email' => 'user@example.com',
            'username' => 'user',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'role' => UserRole::User,
            'branch_id' => $branch->id,
        ]);
        $location = Location::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Goma Signers',
        ]);
        $location_1 = Location::query()->create([
            'branch_id' => $branch_1->id,
            'name' => 'Bukavu',
        ]);

        $this->call(MotorcycleShopCatalogSeeder::class);

        // foreach (Product::query()->cursor() as $product) {
        //     Stock::query()->firstOrCreate(
        //         [
        //             'product_id' => $product->id,
        //             'location_id' => $location->id,
        //         ],
        //         [
        //             'quantity' => 100,
        //             'minimum_stock' => 10,
        //         ]
        //     );
        // }

        Client::factory()->count(30)->create();
    }
}
