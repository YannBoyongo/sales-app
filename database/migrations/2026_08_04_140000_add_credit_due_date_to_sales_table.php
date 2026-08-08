<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('sales', 'credit_due_date')) {
            return;
        }

        // MySQL may reject ALTER when sold_at has a legacy invalid default.
        DB::statement('ALTER TABLE `sales` MODIFY `sold_at` TIMESTAMP NULL DEFAULT NULL');
        DB::statement('ALTER TABLE `sales` ADD `credit_due_date` DATE NULL DEFAULT NULL');
    }

    public function down(): void
    {
        if (! Schema::hasColumn('sales', 'credit_due_date')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('credit_due_date');
        });
    }
};
