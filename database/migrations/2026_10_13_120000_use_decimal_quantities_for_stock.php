<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->decimal('quantity', 14, 3)->default(0)->change();
            $table->decimal('minimum_stock', 14, 3)->nullable()->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('minimum_stock', 14, 3)->nullable()->change();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('quantity', 14, 3)->change();
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('quantity', 14, 3)->change();
        });

        if (Schema::hasTable('stock_transfer_items')) {
            Schema::table('stock_transfer_items', function (Blueprint $table) {
                $table->decimal('quantity', 14, 3)->change();
            });
        }

        if (Schema::hasTable('stock_transfer_item_batches')) {
            Schema::table('stock_transfer_item_batches', function (Blueprint $table) {
                $table->decimal('quantity', 14, 3)->change();
            });
        }

        if (Schema::hasTable('stock_batches')) {
            Schema::table('stock_batches', function (Blueprint $table) {
                $table->decimal('quantity', 14, 3)->default(0)->change();
            });
        }

        if (Schema::hasTable('purchase_order_receptions')) {
            Schema::table('purchase_order_receptions', function (Blueprint $table) {
                $table->decimal('quantity', 14, 3)->change();
            });
        }

        if (Schema::hasTable('purchase_order_items')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->decimal('quantity_ordered', 14, 3)->change();
                $table->decimal('quantity_received', 14, 3)->default(0)->change();
            });
        }

        if (Schema::hasTable('requisition_items')) {
            Schema::table('requisition_items', function (Blueprint $table) {
                $table->decimal('quantity', 14, 3)->default(1)->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(0)->change();
            $table->unsignedInteger('minimum_stock')->nullable()->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('minimum_stock')->nullable()->change();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->change();
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->change();
        });

        if (Schema::hasTable('stock_transfer_items')) {
            Schema::table('stock_transfer_items', function (Blueprint $table) {
                $table->unsignedInteger('quantity')->change();
            });
        }

        if (Schema::hasTable('stock_transfer_item_batches')) {
            Schema::table('stock_transfer_item_batches', function (Blueprint $table) {
                $table->unsignedInteger('quantity')->change();
            });
        }

        if (Schema::hasTable('stock_batches')) {
            Schema::table('stock_batches', function (Blueprint $table) {
                $table->unsignedInteger('quantity')->default(0)->change();
            });
        }

        if (Schema::hasTable('purchase_order_receptions')) {
            Schema::table('purchase_order_receptions', function (Blueprint $table) {
                $table->unsignedInteger('quantity')->change();
            });
        }

        if (Schema::hasTable('purchase_order_items')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->unsignedInteger('quantity_ordered')->change();
                $table->unsignedInteger('quantity_received')->default(0)->change();
            });
        }

        if (Schema::hasTable('requisition_items')) {
            Schema::table('requisition_items', function (Blueprint $table) {
                $table->unsignedInteger('quantity')->default(1)->change();
            });
        }
    }
};
