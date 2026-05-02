<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->index(['sold_at', 'id'], 'sales_sold_at_id_idx');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['created_at', 'id'], 'stock_movements_created_id_idx');
        });

        Schema::table('session_expenses', function (Blueprint $table) {
            $table->index(['sales_session_id', 'spent_at'], 'session_expenses_session_spent_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['client_id', 'paid_at'], 'payments_client_paid_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_sold_at_id_idx');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('stock_movements_created_id_idx');
        });

        Schema::table('session_expenses', function (Blueprint $table) {
            $table->dropIndex('session_expenses_session_spent_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_client_paid_idx');
        });
    }
};
