<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            if (! Schema::hasColumn('requisitions', 'expenses')) {
                $table->decimal('expenses', 12, 2)->default(0)->after('status');
            }
        });

        Schema::table('requisition_items', function (Blueprint $table) {
            if (! Schema::hasColumn('requisition_items', 'batch_number')) {
                $table->string('batch_number', 100)->nullable()->after('quantity');
            }
            if (! Schema::hasColumn('requisition_items', 'unit_price')) {
                $table->decimal('unit_price', 12, 2)->default(0)->after('batch_number');
            }
            if (! Schema::hasColumn('requisition_items', 'tax')) {
                $table->decimal('tax', 12, 2)->default(0)->after('unit_price');
            }
            if (! Schema::hasColumn('requisition_items', 'other')) {
                $table->decimal('other', 12, 2)->default(0)->after('tax');
            }
            if (! Schema::hasColumn('requisition_items', 'cost')) {
                $table->decimal('cost', 12, 2)->default(0)->after('other');
            }
        });
    }

    public function down(): void
    {
        Schema::table('requisition_items', function (Blueprint $table) {
            foreach (['batch_number', 'unit_price', 'tax', 'other', 'cost'] as $column) {
                if (Schema::hasColumn('requisition_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('requisitions', function (Blueprint $table) {
            if (Schema::hasColumn('requisitions', 'expenses')) {
                $table->dropColumn('expenses');
            }
        });
    }
};
