<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('requisitions')) {
            Schema::create('requisitions', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 50)->unique();
                $table->date('date');
                $table->string('status', 20)->default('open');
                $table->unsignedBigInteger('created_by');
                $table->timestamps();

                $table->index('created_by');
                $table->index(['status', 'date']);
            });

            return;
        }

        $hasUnique = collect(DB::select('SHOW INDEX FROM requisitions WHERE Column_name = ?', ['reference']))
            ->contains(fn ($index) => (int) $index->Non_unique === 0);

        if (! $hasUnique) {
            Schema::table('requisitions', function (Blueprint $table) {
                $table->unique('reference');
            });
        }

        $indexes = collect(DB::select('SHOW INDEX FROM requisitions'))->pluck('Key_name');
        if (! $indexes->contains('requisitions_created_by_index')) {
            Schema::table('requisitions', function (Blueprint $table) {
                $table->index('created_by');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('requisitions');
    }
};
