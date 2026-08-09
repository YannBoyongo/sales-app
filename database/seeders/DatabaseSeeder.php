<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SettingSeeder::class);
        $this->call(RoleSeeder::class);

        $this->seedUser(
            lookup: ['email' => 'superadmin@example.com'],
            attributes: [
                'name' => 'Super Administrateur',
                'username' => 'superadmin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'super_admin',
                'branch_id' => null,
            ],
            roleSlugs: ['super_admin'],
        );

        $branch = Branch::query()->firstOrCreate(['name' => 'Goma']);

        $this->seedUser(
            lookup: ['email' => 'admin@example.com'],
            attributes: [
                'name' => 'Administrateur',
                'username' => 'admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'admin',
                'branch_id' => null,
            ],
            roleSlugs: ['admin'],
        );

        $this->seedUser(
            lookup: ['email' => 'user@example.com'],
            attributes: [
                'name' => 'Caissier',
                'username' => 'user',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'cashier',
                'branch_id' => $branch->id,
            ],
            roleSlugs: ['cashier', 'pos_user'],
        );

        Location::query()->firstOrCreate(
            [
                'branch_id' => $branch->id,
                'name' => 'Goma',
            ],
        );

        $this->call(MotorcycleShopCatalogSeeder::class);

        if (Client::query()->where('branch_id', $branch->id)->doesntExist()) {
            Client::factory()->count(30)->create(['branch_id' => $branch->id]);
        }
    }

    /**
     * @param  array<string, mixed>  $lookup
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $roleSlugs
     */
    private function seedUser(array $lookup, array $attributes, array $roleSlugs): User
    {
        $user = User::query()->firstOrCreate($lookup, $attributes);
        $user->roles()->sync(
            Role::query()->whereIn('slug', $roleSlugs)->pluck('id')->all()
        );

        return $user;
    }
}
