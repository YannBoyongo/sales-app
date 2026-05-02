<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_transactions', function (Blueprint $table) {
            $table->index(['transaction_date', 'id'], 'acct_tx_date_id_idx');
            $table->index(['entry_type', 'transaction_date'], 'acct_tx_type_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_transactions', function (Blueprint $table) {
            $table->dropIndex('acct_tx_date_id_idx');
            $table->dropIndex('acct_tx_type_date_idx');
        });
    }
};
