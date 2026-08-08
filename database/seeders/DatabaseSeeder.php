<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
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

        $mainLocation = $branch->mainLocation;
        if ($mainLocation) {
            Setting::current()->update([
                'field_pos_stock_branch_id' => $branch->id,
                'field_pos_stock_location_id' => $mainLocation->id,
            ]);
        }

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
