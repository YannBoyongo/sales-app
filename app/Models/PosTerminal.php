<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosTerminal extends Model
{
    public const KIND_POS = 'pos';

    public const KIND_FIELD = 'field';

    protected $fillable = [
        'branch_id',
        'kind',
        'location_id',
        'name',
    ];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'location_id' => 'integer',
        ];
    }

    public function isFieldPointOfSale(): bool
    {
        return $this->kind === self::KIND_FIELD;
    }

    public function isClassicPos(): bool
    {
        return $this->kind === self::KIND_POS;
    }

    public function hasStockLocation(): bool
    {
        return $this->location_id !== null;
    }

    public static function kindLabel(string $kind): string
    {
        return match ($kind) {
            self::KIND_FIELD => 'Point de vente',
            default => 'Terminal POS',
        };
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(PosShift::class);
    }

    public function posUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pos_terminal_user')->withTimestamps();
    }

    public function openShift(): ?PosShift
    {
        return $this->shifts()->whereNull('closed_at')->first();
    }
}
