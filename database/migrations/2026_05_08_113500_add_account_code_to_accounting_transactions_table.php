<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_transactions', function (Blueprint $table) {
            $table->string('account_code', 30)->nullable()->after('entry_type');
            $table->index(['account_code'], 'acct_tx_account_code_idx');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_transactions', function (Blueprint $table) {
            $table->dropIndex('acct_tx_account_code_idx');
            $table->dropColumn('account_code');
        });
    }
};
