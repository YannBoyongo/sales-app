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
            $table->string('sale_status', 32)->default('confirmed')->after('total_amount');
            $table->decimal('subtotal_amount', 14, 2)->nullable()->after('sale_status');
            $table->decimal('discount_requested_amount', 14, 2)->nullable()->after('subtotal_amount');
            $table->foreignId('discount_requested_by')->nullable()->after('discount_requested_amount')->constrained('users')->nullOnDelete();
            $table->timestamp('discount_requested_at')->nullable()->after('discount_requested_by');
            $table->decimal('discount_amount', 14, 2)->nullable()->after('discount_requested_at');
            $table->foreignId('discount_approved_by')->nullable()->after('discount_amount')->constrained('users')->nullOnDelete();
            $table->timestamp('discount_approved_at')->nullable()->after('discount_approved_by');

            $table->index('sale_status');
        });

        DB::table('sales')->update([
            'sale_status' => 'confirmed',
            'subtotal_amount' => DB::raw('total_amount'),
        ]);
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['sale_status']);
            $table->dropForeign(['discount_requested_by']);
            $table->dropForeign(['discount_approved_by']);
            $table->dropColumn([
                'sale_status',
                'subtotal_amount',
                'discount_requested_amount',
                'discount_requested_by',
                'discount_requested_at',
                'discount_amount',
                'discount_approved_by',
                'discount_approved_at',
            ]);
        });
    }
};
