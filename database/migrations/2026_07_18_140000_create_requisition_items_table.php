<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisition_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('requisition_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('location_id');
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['requisition_id', 'product_id', 'location_id'], 'requisition_items_unique');
            $table->index('requisition_id');
            $table->index('product_id');
            $table->index('location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisition_items');
    }
};
