<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales_sessions', 'department_id')) {
            return;
        }

        Schema::table('sales_sessions', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
        });

        Schema::table('sales_sessions', function (Blueprint $table) {
            $table->dropColumn('department_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales_sessions', function (Blueprint $table) {
            $table->foreignId('department_id')->after('branch_id')->constrained()->cascadeOnDelete();
        });
    }
};
