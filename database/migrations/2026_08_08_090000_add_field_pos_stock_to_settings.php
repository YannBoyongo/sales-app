<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->foreignId('field_pos_stock_branch_id')
                ->nullable()
                ->after('logo')
                ->constrained('branches')
                ->nullOnDelete();
            $table->foreignId('field_pos_stock_location_id')
                ->nullable()
                ->after('field_pos_stock_branch_id')
                ->constrained('locations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropForeign(['field_pos_stock_location_id']);
            $table->dropForeign(['field_pos_stock_branch_id']);
            $table->dropColumn(['field_pos_stock_location_id', 'field_pos_stock_branch_id']);
        });
    }
};
