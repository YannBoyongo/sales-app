<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisition_items', function (Blueprint $table) {
            $table->dropUnique('requisition_items_unique');
        });

        Schema::table('requisition_items', function (Blueprint $table) {
            $table->unsignedBigInteger('location_id')->nullable()->change();
        });

        // Keep one row per product on each requisition.
        $duplicates = DB::table('requisition_items')
            ->select('requisition_id', 'product_id', DB::raw('MIN(id) as keep_id'), DB::raw('SUM(quantity) as total_qty'))
            ->groupBy('requisition_id', 'product_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            DB::table('requisition_items')
                ->where('requisition_id', $dup->requisition_id)
                ->where('product_id', $dup->product_id)
                ->where('id', '!=', $dup->keep_id)
                ->delete();

            DB::table('requisition_items')
                ->where('id', $dup->keep_id)
                ->update([
                    'quantity' => (int) $dup->total_qty,
                    'location_id' => null,
                ]);
        }

        DB::table('requisition_items')->update(['location_id' => null]);

        Schema::table('requisition_items', function (Blueprint $table) {
            $table->unique(['requisition_id', 'product_id'], 'requisition_items_unique');
        });
    }

    public function down(): void
    {
        Schema::table('requisition_items', function (Blueprint $table) {
            $table->dropUnique('requisition_items_unique');
        });

        Schema::table('requisition_items', function (Blueprint $table) {
            $table->unsignedBigInteger('location_id')->nullable(false)->change();
            $table->unique(['requisition_id', 'product_id', 'location_id'], 'requisition_items_unique');
        });
    }
};
