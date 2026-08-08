<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('requisitions')) {
            return;
        }

        DB::table('requisitions')->where('status', 'open')->update(['status' => 'pending']);
        DB::table('requisitions')->whereIn('status', ['approved', 'fulfilled'])->update(['status' => 'confirmed']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('requisitions')) {
            return;
        }

        DB::table('requisitions')->where('status', 'pending')->update(['status' => 'open']);
        DB::table('requisitions')->where('status', 'confirmed')->update(['status' => 'approved']);
    }
};
