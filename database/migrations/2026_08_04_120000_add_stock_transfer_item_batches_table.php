<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfer_item_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_transfer_item_id');
            $table->unsignedBigInteger('source_stock_batch_id')->nullable();
            $table->unsignedBigInteger('destination_stock_batch_id')->nullable();
            $table->string('batch_number', 100)->nullable();
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->index('stock_transfer_item_id');
            $table->index('source_stock_batch_id');
            $table->index('destination_stock_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_item_batches');
    }
};
