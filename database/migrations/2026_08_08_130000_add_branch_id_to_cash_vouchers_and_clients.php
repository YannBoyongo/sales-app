<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_vouchers', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        $defaultBranchId = DB::table('branches')->orderBy('id')->value('id');

        DB::table('clients')
            ->orderBy('id')
            ->each(function (object $client) use ($defaultBranchId): void {
                $branchId = DB::table('sales')
                    ->where('client_id', $client->id)
                    ->whereNotNull('branch_id')
                    ->value('branch_id');

                if ($branchId === null) {
                    $branchId = DB::table('sale_items')
                        ->where('client_id', $client->id)
                        ->whereNotNull('branch_id')
                        ->value('branch_id');
                }

                DB::table('clients')
                    ->where('id', $client->id)
                    ->update(['branch_id' => $branchId ?? $defaultBranchId]);
            });

        DB::table('cash_vouchers')
            ->orderBy('id')
            ->each(function (object $voucher) use ($defaultBranchId): void {
                $branchId = null;

                if ($voucher->pos_shift_id) {
                    $branchId = DB::table('pos_shifts')
                        ->join('pos_terminals', 'pos_terminals.id', '=', 'pos_shifts.pos_terminal_id')
                        ->where('pos_shifts.id', $voucher->pos_shift_id)
                        ->value('pos_terminals.branch_id');
                }

                if ($branchId === null && preg_match('/^CV-DETTE-(\d+)$/', (string) $voucher->voucher_no, $matches)) {
                    $paymentId = (int) $matches[1];
                    $branchId = DB::table('payments')
                        ->join('clients', 'clients.id', '=', 'payments.client_id')
                        ->where('payments.id', $paymentId)
                        ->value('clients.branch_id');
                }

                if ($branchId === null && preg_match('/^CV-CAUTION-(\d+)$/', (string) $voucher->voucher_no, $matches)) {
                    $depositId = (int) $matches[1];
                    $branchId = DB::table('client_caution_deposits')
                        ->join('clients', 'clients.id', '=', 'client_caution_deposits.client_id')
                        ->where('client_caution_deposits.id', $depositId)
                        ->value('clients.branch_id');
                }

                DB::table('cash_vouchers')
                    ->where('id', $voucher->id)
                    ->update(['branch_id' => $branchId ?? $defaultBranchId]);
            });

        Schema::table('cash_vouchers', function (Blueprint $table) {
            $table->dropUnique(['voucher_no']);
            $table->unique(['branch_id', 'voucher_no']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->unique(['branch_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('cash_vouchers', function (Blueprint $table) {
            $table->dropUnique(['branch_id', 'voucher_no']);
            $table->dropConstrainedForeignId('branch_id');
            $table->unique('voucher_no');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['branch_id', 'name']);
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
