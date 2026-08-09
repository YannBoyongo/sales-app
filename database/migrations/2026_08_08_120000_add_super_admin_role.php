<?php

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        (new RoleSeeder)->run();

        $hasSuperAdmin = User::query()
            ->whereHas('roles', fn ($q) => $q->where('slug', UserRole::SuperAdmin->value))
            ->exists();

        if ($hasSuperAdmin) {
            return;
        }

        $superAdmin = User::query()->firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Administrateur',
                'username' => 'superadmin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => UserRole::SuperAdmin->value,
                'branch_id' => null,
            ]
        );

        $roleId = Role::query()->where('slug', UserRole::SuperAdmin->value)->value('id');

        if ($roleId) {
            $superAdmin->roles()->syncWithoutDetaching([(int) $roleId]);
            $superAdmin->update(['role' => UserRole::SuperAdmin->value]);
        }
    }

    public function down(): void
    {
        User::query()
            ->where('email', 'superadmin@example.com')
            ->whereHas('roles', fn ($q) => $q->where('slug', UserRole::SuperAdmin->value))
            ->each(function (User $user): void {
                $user->roles()->detach(
                    Role::query()->where('slug', UserRole::SuperAdmin->value)->pluck('id')
                );
                $user->delete();
            });

        Role::query()->where('slug', UserRole::SuperAdmin->value)->delete();
    }
};
