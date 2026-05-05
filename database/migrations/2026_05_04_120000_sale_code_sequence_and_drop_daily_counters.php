<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_code_sequences', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        $maxSl = 0;
        foreach (DB::table('sales')->where('reference', 'like', 'SL%')->pluck('reference') as $ref) {
            if (preg_match('/^SL(\d+)$/', (string) $ref, $m)) {
                $maxSl = max($maxSl, (int) $m[1]);
            }
        }

        DB::table('sale_code_sequences')->insert([
            'id' => 1,
            'last_number' => $maxSl,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::dropIfExists('sale_reference_counters');
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_code_sequences');

        Schema::create('sale_reference_counters', function (Blueprint $table) {
            $table->date('sale_date')->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });
    }
};
