<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_vouchers', function (Blueprint $table) {
            $table->foreignId('accounting_transaction_id')
                ->nullable()
                ->after('approved_by')
                ->constrained('accounting_transactions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cash_vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accounting_transaction_id');
        });
    }
};
