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
            $table->foreignId('pos_terminal_id')
                ->nullable()
                ->after('pos_shift_id')
                ->constrained('pos_terminals')
                ->nullOnDelete();
        });

        DB::table('cash_vouchers')
            ->whereNotNull('pos_shift_id')
            ->update([
                'pos_terminal_id' => DB::raw('(SELECT pos_terminal_id FROM pos_shifts WHERE pos_shifts.id = cash_vouchers.pos_shift_id)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('cash_vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pos_terminal_id');
        });
    }
};
