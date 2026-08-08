<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Branch extends Model
{
    protected $fillable = ['name'];

    protected static function booted(): void
    {
        static::created(function (Branch $branch): void {
            DB::transaction(function () use ($branch): void {
                $location = Location::query()->create([
                    'branch_id' => $branch->id,
                    'name' => $branch->name,
                    'kind' => Location::KIND_MAIN,
                ]);

                PosTerminal::query()->create([
                    'branch_id' => $branch->id,
                    'kind' => PosTerminal::KIND_FIELD,
                    'name' => $branch->name,
                    'location_id' => $location->id,
                ]);
            });
        });
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function mainLocation(): HasOne
    {
        return $this->hasOne(Location::class)->where('kind', Location::KIND_MAIN);
    }

    public function pointOfSales(): HasMany
    {
        return $this->hasMany(Location::class)
            ->where('kind', Location::KIND_POINT_OF_SALE)
            ->orderBy('name');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function posTerminals(): HasMany
    {
        return $this->hasMany(PosTerminal::class)->orderBy('name');
    }
}
