<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_reception_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('submitted_at');
            $table->string('status', 20)->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['purchase_order_id', 'status']);
        });

        Schema::table('purchase_order_receptions', function (Blueprint $table) {
            $table->unsignedBigInteger('reception_batch_id')->nullable()->after('purchase_order_id');
            $table->foreign('reception_batch_id', 'po_receptions_batch_id_fk')
                ->references('id')
                ->on('purchase_order_reception_batches')
                ->cascadeOnDelete();
        });

        $groups = DB::table('purchase_order_receptions')
            ->select('purchase_order_id', 'received_by', 'received_at')
            ->groupBy('purchase_order_id', 'received_by', 'received_at')
            ->get();

        foreach ($groups as $group) {
            $batchId = DB::table('purchase_order_reception_batches')->insertGetId([
                'purchase_order_id' => $group->purchase_order_id,
                'submitted_by' => $group->received_by,
                'submitted_at' => $group->received_at,
                'status' => 'approved',
                'approved_by' => $group->received_by,
                'approved_at' => $group->received_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('purchase_order_receptions')
                ->where('purchase_order_id', $group->purchase_order_id)
                ->where('received_by', $group->received_by)
                ->where('received_at', $group->received_at)
                ->update(['reception_batch_id' => $batchId]);
        }
    }

    public function down(): void
    {
        Schema::table('purchase_order_receptions', function (Blueprint $table) {
            $table->dropForeign('po_receptions_batch_id_fk');
            $table->dropColumn('reception_batch_id');
        });

        Schema::dropIfExists('purchase_order_reception_batches');
    }
};
