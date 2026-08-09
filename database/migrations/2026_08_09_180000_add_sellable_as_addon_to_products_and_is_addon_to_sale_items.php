<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'sellable_as_addon')) {
                $table->boolean('sellable_as_addon')->default(false)->after('minimum_stock');
            }
        });

        Schema::table('sale_items', function (Blueprint $table) {
            if (! Schema::hasColumn('sale_items', 'is_addon')) {
                $table->boolean('is_addon')->default(false)->after('product_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'sellable_as_addon')) {
                $table->dropColumn('sellable_as_addon');
            }
        });

        Schema::table('sale_items', function (Blueprint $table) {
            if (Schema::hasColumn('sale_items', 'is_addon')) {
                $table->dropColumn('is_addon');
            }
        });
    }
};
