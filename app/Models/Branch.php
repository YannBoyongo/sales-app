<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Branch extends Model
{
    protected $fillable = ['name'];

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
