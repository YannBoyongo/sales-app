<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Admin', 'slug' => UserRole::Admin->value],
            ['name' => 'Manager', 'slug' => UserRole::Manager->value],
            ['name' => 'POS user', 'slug' => UserRole::PosUser->value],
            ['name' => 'Cashier', 'slug' => UserRole::Cashier->value],
            ['name' => 'Accountant', 'slug' => UserRole::Accountant->value],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(['slug' => $role['slug']], ['name' => $role['name']]);
        }
    }
}
