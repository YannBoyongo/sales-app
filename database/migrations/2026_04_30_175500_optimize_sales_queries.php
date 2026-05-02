<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_sessions', function (Blueprint $table) {
            $table->index(['branch_id', 'status']);
            $table->index(['branch_id', 'opened_at']);
            $table->index(['status', 'opened_at']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->index(['sales_session_id', 'id']);
            $table->index(['sales_session_id', 'created_at']);
            $table->index(['sales_session_id', 'line_total']);
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropIndex(['sales_session_id', 'id']);
            $table->dropIndex(['sales_session_id', 'created_at']);
            $table->dropIndex(['sales_session_id', 'line_total']);
        });

        Schema::table('sales_sessions', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'status']);
            $table->dropIndex(['branch_id', 'opened_at']);
            $table->dropIndex(['status', 'opened_at']);
        });
    }
};
