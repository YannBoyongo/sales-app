<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('cost_transaction_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('cost_tracking_entries', function (Blueprint $table) {
            $table->id();
            $table->date('occurred_on');
            $table->string('direction', 10);
            $table->foreignId('cost_center_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cost_transaction_type_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 16, 2);
            $table->string('description')->nullable();
            $table->decimal('balance_after', 16, 2)->default(0);
            $table->timestamps();

            $table->index(['occurred_on', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_tracking_entries');
        Schema::dropIfExists('cost_transaction_types');
        Schema::dropIfExists('cost_centers');
    }
};
