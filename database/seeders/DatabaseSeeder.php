<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Location;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SettingSeeder::class);
        $this->call(RoleSeeder::class);

        $branch = Branch::query()->create(['name' => 'Goma']);
        // $branch_1 = Branch::query()->create(['name' => 'Bukavu']);

        $admin = User::query()->create([
            'name' => 'Administrateur',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'role' => 'admin',
            'branch_id' => null,
        ]);
        $admin->roles()->sync(Role::query()->where('slug', 'admin')->pluck('id')->all());

        $cashier = User::query()->create([
            'name' => 'Caissier',
            'email' => 'user@example.com',
            'username' => 'user',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'role' => 'cashier',
            'branch_id' => $branch->id,
        ]);
        $cashier->roles()->sync(Role::query()->whereIn('slug', ['cashier', 'pos_user'])->pluck('id')->all());
        $location = Location::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Goma',
        ]);
        // $location_1 = Location::query()->create([
        //     'branch_id' => $branch_1->id,
        //     'name' => 'Bukavu',
        // ]);

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
