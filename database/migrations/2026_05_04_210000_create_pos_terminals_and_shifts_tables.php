<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_terminals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique('location_id');
            $table->index(['branch_id', 'name']);
        });

        Schema::create('pos_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_terminal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opened_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['pos_terminal_id', 'closed_at']);
            $table->index(['opened_at', 'closed_at']);
        });

        Schema::create('pos_terminal_user', function (Blueprint $table) {
            $table->foreignId('pos_terminal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['pos_terminal_id', 'user_id']);
            $table->timestamps();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('pos_shift_id')->nullable()->after('branch_id')->constrained('pos_shifts')->nullOnDelete();
            $table->index(['pos_shift_id', 'sold_at']);
        });

        $posLocations = DB::table('locations')
            ->where('kind', 'point_of_sale')
            ->orderBy('id')
            ->get(['id', 'branch_id', 'name']);

        foreach ($posLocations as $loc) {
            DB::table('pos_terminals')->insert([
                'branch_id' => $loc->branch_id,
                'location_id' => $loc->id,
                'name' => $loc->name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['pos_shift_id']);
            $table->dropIndex(['pos_shift_id', 'sold_at']);
            $table->dropColumn('pos_shift_id');
        });

        Schema::dropIfExists('pos_terminal_user');
        Schema::dropIfExists('pos_shifts');
        Schema::dropIfExists('pos_terminals');
    }
};
