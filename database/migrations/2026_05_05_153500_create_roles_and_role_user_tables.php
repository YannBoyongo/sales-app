<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 50)->unique();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['role_id', 'user_id']);
        });

        $now = now();
        $roles = [
            ['name' => 'Admin', 'slug' => 'admin', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Manager', 'slug' => 'manager', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'POS user', 'slug' => 'pos_user', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Cashier', 'slug' => 'cashier', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Accountant', 'slug' => 'accountant', 'created_at' => $now, 'updated_at' => $now],
        ];
        DB::table('roles')->insert($roles);

        $roleIds = DB::table('roles')->pluck('id', 'slug');
        $users = DB::table('users')->select('id', 'role')->get();

        foreach ($users as $user) {
            $legacy = (string) ($user->role ?? '');
            $mapped = match ($legacy) {
                'admin' => 'admin',
                'accountant' => 'accountant',
                'pos_user' => 'pos_user',
                'cashier' => 'cashier',
                default => 'manager',
            };

            $roleId = $roleIds[$mapped] ?? null;
            if ($roleId === null) {
                continue;
            }

            DB::table('role_user')->insert([
                'role_id' => $roleId,
                'user_id' => $user->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Keep legacy users.role aligned to the mapped primary role during transition.
            DB::table('users')->where('id', $user->id)->update(['role' => $mapped]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
    }
};
