<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    public const SCOPE_INTERNAL = 'internal';

    public const SCOPE_EXTERNAL = 'external';

    protected $fillable = [
        'from_location_id',
        'to_location_id',
        'transfer_scope',
        'transferred_at',
        'user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'transferred_at' => 'date',
        ];
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isInternal(): bool
    {
        return $this->transfer_scope === self::SCOPE_INTERNAL;
    }

    public function isExternal(): bool
    {
        return $this->transfer_scope === self::SCOPE_EXTERNAL;
    }

    public static function scopeLabel(string $scope): string
    {
        return match ($scope) {
            self::SCOPE_INTERNAL => 'Interne',
            self::SCOPE_EXTERNAL => 'Externe',
            default => $scope,
        };
    }
}
