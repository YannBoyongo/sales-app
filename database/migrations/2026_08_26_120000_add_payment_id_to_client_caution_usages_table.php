<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_caution_usages', function (Blueprint $table) {
            $table->foreignId('payment_id')->nullable()->after('sale_id')->constrained('payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('client_caution_usages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_id');
        });
    }
};
