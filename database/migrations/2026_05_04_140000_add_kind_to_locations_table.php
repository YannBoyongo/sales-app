<?php

use App\Models\Branch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('kind', 32)->default('storage')->after('name');
        });

        DB::table('locations')->update(['kind' => 'storage']);

        foreach (Branch::query()->pluck('id') as $branchId) {
            $firstId = DB::table('locations')
                ->where('branch_id', $branchId)
                ->orderBy('id')
                ->value('id');
            if ($firstId) {
                DB::table('locations')->where('id', $firstId)->update(['kind' => 'main']);
            }
        }

        $saleLocationIds = DB::table('sale_items')->distinct()->pluck('location_id');
        foreach ($saleLocationIds as $locationId) {
            if (! $locationId) {
                continue;
            }
            $kind = DB::table('locations')->where('id', $locationId)->value('kind');
            if ($kind === null || $kind === 'main') {
                continue;
            }
            DB::table('locations')->where('id', $locationId)->update(['kind' => 'point_of_sale']);
        }
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
