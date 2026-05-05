<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('payment_type', 20);
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->timestamp('sold_at');
            $table->timestamps();

            $table->index(['branch_id', 'sold_at']);
            $table->index(['payment_type', 'sold_at']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->index(['sale_id', 'id']);
        });

        // Backfill existing lines: each historical line becomes its own sale.
        $rows = DB::table('sale_items')
            ->select('id', 'branch_id', 'user_id', 'payment_type', 'client_id', 'line_total', 'created_at')
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $reference = sprintf('LEGACY-%06d', (int) $row->id);
            $saleId = DB::table('sales')->insertGetId([
                'reference' => $reference,
                'branch_id' => $row->branch_id,
                'user_id' => $row->user_id,
                'payment_type' => $row->payment_type ?? 'cash',
                'client_id' => $row->client_id,
                'total_amount' => $row->line_total ?? 0,
                'sold_at' => $row->created_at ?? now(),
                'created_at' => $row->created_at ?? now(),
                'updated_at' => now(),
            ]);

            DB::table('sale_items')->where('id', $row->id)->update(['sale_id' => $saleId]);
        }
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropIndex(['sale_id', 'id']);
            $table->dropConstrainedForeignId('sale_id');
        });

        Schema::dropIfExists('sales');
    }
};
