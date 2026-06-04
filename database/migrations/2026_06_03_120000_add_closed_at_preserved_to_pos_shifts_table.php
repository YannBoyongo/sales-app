<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_shifts', function (Blueprint $table) {
            $table->timestamp('closed_at_preserved')->nullable()->after('closed_at');
        });

        DB::table('pos_shifts')
            ->whereNotNull('closed_at')
            ->update(['closed_at_preserved' => DB::raw('closed_at')]);
    }

    public function down(): void
    {
        Schema::table('pos_shifts', function (Blueprint $table) {
            $table->dropColumn('closed_at_preserved');
        });
    }
};
