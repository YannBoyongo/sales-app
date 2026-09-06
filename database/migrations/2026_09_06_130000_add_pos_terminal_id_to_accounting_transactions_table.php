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
            $table->foreignId('pos_terminal_id')->nullable()->after('branch_id')->constrained('pos_terminals')->nullOnDelete();
        });

        if (Schema::hasTable('cash_vouchers')) {
            DB::table('accounting_transactions')
                ->join('cash_vouchers', 'cash_vouchers.accounting_transaction_id', '=', 'accounting_transactions.id')
                ->leftJoin('pos_shifts', 'pos_shifts.id', '=', 'cash_vouchers.pos_shift_id')
                ->whereNull('accounting_transactions.pos_terminal_id')
                ->update([
                    'accounting_transactions.pos_terminal_id' => DB::raw('COALESCE(cash_vouchers.pos_terminal_id, pos_shifts.pos_terminal_id)'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('accounting_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pos_terminal_id');
        });
    }
};
