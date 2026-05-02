<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->boolean('reception_started')->default(false)->after('status');
            $table->index(['status', 'created_at']);
            $table->index('location_id');
            $table->index('created_by');
            $table->index('reception_started');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->index('purchase_order_id');
            $table->index(['purchase_order_id', 'quantity_received']);
            $table->index('product_id');
        });

        Schema::table('purchase_order_receptions', function (Blueprint $table) {
            $table->index(['purchase_order_id', 'received_at']);
            $table->index('received_by');
        });

        DB::table('purchase_orders')
            ->whereIn('id', function ($q) {
                $q->select('purchase_order_id')
                    ->from('purchase_order_receptions');
            })
            ->orWhereIn('id', function ($q) {
                $q->select('purchase_order_id')
                    ->from('purchase_order_items')
                    ->where('quantity_received', '>', 0);
            })
            ->update(['reception_started' => true]);
    }

    public function down(): void
    {
        Schema::table('purchase_order_receptions', function (Blueprint $table) {
            $table->dropIndex(['purchase_order_id', 'received_at']);
            $table->dropIndex(['received_by']);
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropIndex(['purchase_order_id']);
            $table->dropIndex(['purchase_order_id', 'quantity_received']);
            $table->dropIndex(['product_id']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['location_id']);
            $table->dropIndex(['created_by']);
            $table->dropIndex(['reception_started']);
            $table->dropColumn('reception_started');
        });
    }
};
