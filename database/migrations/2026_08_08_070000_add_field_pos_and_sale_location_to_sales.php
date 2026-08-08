<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_terminals', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
        });

        Schema::table('pos_terminals', function (Blueprint $table) {
            $table->dropUnique(['location_id']);
            $table->string('kind', 20)->default('pos')->after('branch_id');
            $table->index(['branch_id', 'kind']);
            $table->foreign('location_id')->references('id')->on('locations')->cascadeOnDelete();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('sale_location_id')
                ->nullable()
                ->after('pos_shift_id')
                ->constrained('locations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['sale_location_id']);
            $table->dropColumn('sale_location_id');
        });

        Schema::table('pos_terminals', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropIndex(['branch_id', 'kind']);
            $table->dropColumn('kind');
            $table->unique('location_id');
            $table->foreign('location_id')->references('id')->on('locations')->cascadeOnDelete();
        });
    }
};
