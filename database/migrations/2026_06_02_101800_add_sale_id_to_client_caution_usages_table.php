<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_caution_usages', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->after('client_id')->constrained('sales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('client_caution_usages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sale_id');
        });
    }
};
