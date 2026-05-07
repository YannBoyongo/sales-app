<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('payment_status', 30)->default('fully_paid')->after('payment_type');
            $table->decimal('amount_paid', 14, 2)->default(0)->after('total_amount');
            $table->decimal('balance_amount', 14, 2)->default(0)->after('amount_paid');
            $table->index('payment_status');
        });

        DB::table('sales')
            ->where('payment_type', 'credit')
            ->update([
                'payment_status' => 'not_paid',
                'amount_paid' => 0,
            ]);

        DB::table('sales')
            ->where('payment_type', 'cash')
            ->update([
                'payment_status' => 'fully_paid',
                'amount_paid' => DB::raw('total_amount'),
                'balance_amount' => 0,
            ]);
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);
            $table->dropColumn(['payment_status', 'amount_paid', 'balance_amount']);
        });
    }
};
