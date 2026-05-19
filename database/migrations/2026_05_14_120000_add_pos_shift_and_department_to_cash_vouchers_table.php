<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_vouchers', function (Blueprint $table) {
            $table->foreignId('pos_shift_id')->nullable()->after('accounting_transaction_id')->constrained('pos_shifts')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('pos_shift_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cash_vouchers', function (Blueprint $table) {
            $table->dropForeign(['pos_shift_id']);
            $table->dropForeign(['department_id']);
            $table->dropColumn(['pos_shift_id', 'department_id']);
        });
    }
};
