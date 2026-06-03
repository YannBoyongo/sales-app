<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_caution_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamp('deposited_at');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'deposited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_caution_deposits');
    }
};
