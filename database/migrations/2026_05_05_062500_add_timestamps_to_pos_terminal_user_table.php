<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_terminal_user')) {
            return;
        }

        Schema::table('pos_terminal_user', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_terminal_user', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pos_terminal_user')) {
            return;
        }

        Schema::table('pos_terminal_user', function (Blueprint $table) {
            if (Schema::hasColumn('pos_terminal_user', 'created_at')) {
                $table->dropTimestamps();
            }
        });
    }
};
