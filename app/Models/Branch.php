<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Branch extends Model
{
    protected $fillable = [
        'name',
        'can_sell_on_credit',
        'can_apply_discount',
    ];

    protected function casts(): array
    {
        return [
            'can_sell_on_credit' => 'boolean',
            'can_apply_discount' => 'boolean',
        ];
    }

    public function allowsDealerCreditSales(): bool
    {
        return (bool) $this->can_sell_on_credit;
    }

    public function allowsLineDiscount(): bool
    {
        return (bool) $this->can_apply_discount;
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

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function cashVouchers(): HasMany
    {
        return $this->hasMany(CashVoucher::class);
    }
}
