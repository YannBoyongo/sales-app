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
            if (! Schema::hasColumn('purchase_orders', 'requisition_id')) {
                $table->unsignedBigInteger('requisition_id')->nullable()->after('id');
                $table->index('requisition_id');
            }
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_order_items', 'batch_number')) {
                $table->string('batch_number', 100)->nullable()->after('product_id');
            }
            if (! Schema::hasColumn('purchase_order_items', 'unit_price')) {
                $table->decimal('unit_price', 12, 2)->nullable()->after('quantity_received');
            }
            if (! Schema::hasColumn('purchase_order_items', 'tax')) {
                $table->decimal('tax', 12, 2)->nullable()->after('unit_price');
            }
            if (! Schema::hasColumn('purchase_order_items', 'other')) {
                $table->decimal('other', 12, 2)->nullable()->after('tax');
            }
            if (! Schema::hasColumn('purchase_order_items', 'unit_cost')) {
                $table->decimal('unit_cost', 12, 2)->nullable()->after('other');
            }
            if (! Schema::hasColumn('purchase_order_items', 'line_cost')) {
                $table->decimal('line_cost', 12, 2)->nullable()->after('unit_cost');
            }
            if (! Schema::hasColumn('purchase_order_items', 'requisition_item_id')) {
                $table->unsignedBigInteger('requisition_item_id')->nullable()->after('line_cost');
            }
        });

        if (! Schema::hasTable('stock_batches')) {
            Schema::create('stock_batches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('location_id');
                $table->string('batch_number', 100);
                $table->decimal('unit_cost', 12, 2)->default(0);
                $table->unsignedInteger('quantity')->default(0);
                $table->unsignedBigInteger('purchase_order_id')->nullable();
                $table->unsignedBigInteger('purchase_order_item_id')->nullable();
                $table->unsignedBigInteger('purchase_order_reception_id')->nullable();
                $table->timestamps();

                $table->unique(['product_id', 'location_id', 'batch_number', 'unit_cost'], 'stock_batches_unique_layer');
                $table->index(['product_id', 'location_id']);
                $table->index('batch_number');
            });
        }

        Schema::table('stock_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_movements', 'stock_batch_id')) {
                $table->unsignedBigInteger('stock_batch_id')->nullable()->after('product_id');
                $table->index('stock_batch_id');
            }
            if (! Schema::hasColumn('stock_movements', 'purchase_order_reception_id')) {
                $table->unsignedBigInteger('purchase_order_reception_id')->nullable()->after('stock_batch_id');
            }
        });

        Schema::table('sale_items', function (Blueprint $table) {
            if (! Schema::hasColumn('sale_items', 'stock_batch_id')) {
                $table->unsignedBigInteger('stock_batch_id')->nullable()->after('product_id');
            }
            if (! Schema::hasColumn('sale_items', 'batch_number')) {
                $table->string('batch_number', 100)->nullable()->after('stock_batch_id');
            }
            if (! Schema::hasColumn('sale_items', 'unit_cost')) {
                $table->decimal('unit_cost', 12, 2)->nullable()->after('unit_price');
            }
            if (! Schema::hasColumn('sale_items', 'cost_total')) {
                $table->decimal('cost_total', 12, 2)->nullable()->after('unit_cost');
            }
            if (! Schema::hasColumn('sale_items', 'benefit')) {
                $table->decimal('benefit', 12, 2)->nullable()->after('cost_total');
            }
        });

        if (Schema::hasTable('requisition_items')) {
            DB::table('requisition_items')->whereNull('batch_number')->update(['batch_number' => '']);

            $indexes = collect(DB::select('SHOW INDEX FROM requisition_items'))->pluck('Key_name');
            if ($indexes->contains('requisition_items_unique')) {
                Schema::table('requisition_items', function (Blueprint $table) {
                    $table->dropUnique('requisition_items_unique');
                });
            }

            Schema::table('requisition_items', function (Blueprint $table) {
                $table->unique(['requisition_id', 'product_id', 'batch_number'], 'requisition_items_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('requisition_items')) {
            $indexes = collect(DB::select('SHOW INDEX FROM requisition_items'))->pluck('Key_name');
            if ($indexes->contains('requisition_items_unique')) {
                Schema::table('requisition_items', function (Blueprint $table) {
                    $table->dropUnique('requisition_items_unique');
                });
            }

            Schema::table('requisition_items', function (Blueprint $table) {
                $table->unique(['requisition_id', 'product_id'], 'requisition_items_unique');
            });
        }

        Schema::table('sale_items', function (Blueprint $table) {
            foreach (['stock_batch_id', 'batch_number', 'unit_cost', 'cost_total', 'benefit'] as $column) {
                if (Schema::hasColumn('sale_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            foreach (['stock_batch_id', 'purchase_order_reception_id'] as $column) {
                if (Schema::hasColumn('stock_movements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('stock_batches');

        Schema::table('purchase_order_items', function (Blueprint $table) {
            foreach (['batch_number', 'unit_price', 'tax', 'other', 'unit_cost', 'line_cost', 'requisition_item_id'] as $column) {
                if (Schema::hasColumn('purchase_order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_orders', 'requisition_id')) {
                $table->dropColumn('requisition_id');
            }
        });
    }
};
