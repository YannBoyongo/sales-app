<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->index(['branch_id', 'id']);
            $table->index(['branch_id', 'created_at']);
            $table->index(['branch_id', 'line_total']);
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'id']);
            $table->dropIndex(['branch_id', 'created_at']);
            $table->dropIndex(['branch_id', 'line_total']);
        });
    }
};
