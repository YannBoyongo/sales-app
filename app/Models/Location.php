<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Location extends Model
{
    public const KIND_MAIN = 'main';

    public const KIND_STORAGE = 'storage';

    public const KIND_POINT_OF_SALE = 'point_of_sale';

    protected $fillable = ['branch_id', 'name', 'kind'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function posTerminal(): HasOne
    {
        return $this->hasOne(PosTerminal::class);
    }

    public function isMain(): bool
    {
        return $this->kind === self::KIND_MAIN;
    }

    public function isStorage(): bool
    {
        return $this->kind === self::KIND_STORAGE;
    }

    public function isPointOfSale(): bool
    {
        return $this->kind === self::KIND_POINT_OF_SALE;
    }

    public static function kindLabel(string $kind): string
    {
        return match ($kind) {
            self::KIND_MAIN => 'Principal (entrepôt)',
            self::KIND_STORAGE => 'Entrepôt secondaire',
            self::KIND_POINT_OF_SALE => 'Point de vente',
            default => $kind,
        };
    }

    /**
     * @param  Builder<Location>  $query
     * @return Builder<Location>
     */
    public function scopePointOfSale(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_POINT_OF_SALE);
    }

    /**
     * @param  Builder<Location>  $query
     * @return Builder<Location>
     */
    public function scopeMain(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_MAIN);
    }
}
