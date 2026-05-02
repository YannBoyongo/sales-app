<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->decimal('amount', 14, 2);
            $table->timestamp('spent_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_expenses');
    }
};
