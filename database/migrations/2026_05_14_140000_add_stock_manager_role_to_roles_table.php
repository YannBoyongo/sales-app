<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $exists = DB::table('roles')->where('slug', UserRole::StockManager->value)->exists();
        if ($exists) {
            DB::table('roles')->where('slug', UserRole::StockManager->value)->update([
                'name' => 'Magasinier',
                'updated_at' => $now,
            ]);

            return;
        }

        DB::table('roles')->insert([
            'name' => 'Magasinier',
            'slug' => UserRole::StockManager->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('roles')->where('slug', UserRole::StockManager->value)->delete();
    }
};
