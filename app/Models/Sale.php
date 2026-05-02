<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PENDING_DISCOUNT = 'pending_discount';

    protected $fillable = [
        'reference',
        'sales_session_id',
        'user_id',
        'payment_type',
        'client_id',
        'total_amount',
        'sale_status',
        'subtotal_amount',
        'discount_requested_amount',
        'discount_requested_by',
        'discount_requested_at',
        'discount_amount',
        'discount_approved_by',
        'discount_approved_at',
        'sold_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'subtotal_amount' => 'decimal:2',
            'discount_requested_amount' => 'decimal:2',
            'discount_requested_at' => 'datetime',
            'discount_amount' => 'decimal:2',
            'discount_approved_at' => 'datetime',
            'sold_at' => 'datetime',
        ];
    }

    public function isPendingDiscount(): bool
    {
        return $this->sale_status === self::STATUS_PENDING_DISCOUNT;
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SalesSession::class, 'sales_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function discountRequestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discount_requested_by');
    }

    public function discountApprovedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discount_approved_by');
    }
}
