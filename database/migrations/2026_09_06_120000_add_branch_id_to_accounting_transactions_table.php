<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_transactions', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('user_id')->constrained('branches')->nullOnDelete();
        });

        if (Schema::hasTable('cash_vouchers')) {
            DB::table('accounting_transactions')
                ->join('cash_vouchers', 'cash_vouchers.accounting_transaction_id', '=', 'accounting_transactions.id')
                ->whereNull('accounting_transactions.branch_id')
                ->whereNotNull('cash_vouchers.branch_id')
                ->update(['accounting_transactions.branch_id' => DB::raw('cash_vouchers.branch_id')]);
        }
    }

    public function down(): void
    {
        Schema::table('accounting_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
