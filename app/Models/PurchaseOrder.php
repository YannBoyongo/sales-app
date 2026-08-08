<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'reference',
        'requisition_id',
        'location_id',
        'created_by',
        'supplier',
        'status',
        'reception_started',
    ];

    protected function casts(): array
    {
        return [
            'reception_started' => 'boolean',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receptions(): HasMany
    {
        return $this->hasMany(PurchaseOrderReception::class);
    }

    public function receptionBatches(): HasMany
    {
        return $this->hasMany(PurchaseOrderReceptionBatch::class);
    }

    public static function generateReferenceFromRequisition(Requisition $requisition): string
    {
        $base = 'PO-'.$requisition->reference;
        $reference = $base;
        $suffix = 1;

        while (self::query()->where('reference', $reference)->exists()) {
            $reference = $base.'-'.$suffix;
            $suffix++;
        }

        return $reference;
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'PO-'.Str::upper(Str::random(4)).random_int(1000, 9999);
        } while (self::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
