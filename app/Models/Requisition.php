<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Requisition extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_FULFILLED = 'fulfilled';

    protected $fillable = [
        'reference',
        'date',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RequisitionItem::class);
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'Approuvée',
            self::STATUS_REJECTED => 'Refusée',
            self::STATUS_FULFILLED => 'Traitée',
            default => 'Ouverte',
        };
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'REQ-'.Str::upper(Str::random(4)).random_int(1000, 9999);
        } while (self::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
