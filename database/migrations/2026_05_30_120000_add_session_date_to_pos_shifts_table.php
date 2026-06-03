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
            $table->date('session_date')->nullable()->after('opened_by');
        });

        DB::table('pos_shifts')->update([
            'session_date' => DB::raw('DATE(opened_at)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('pos_shifts', function (Blueprint $table) {
            $table->dropColumn('session_date');
        });
    }
};
