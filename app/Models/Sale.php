<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    public const PAYMENT_STATUS_NOT_PAID = 'not_paid';

    public const PAYMENT_STATUS_PARTIALLY_PAID = 'partially_paid';

    public const PAYMENT_STATUS_FULLY_PAID = 'fully_paid';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PENDING_DISCOUNT = 'pending_discount';

    protected $fillable = [
        'reference',
        'branch_id',
        'pos_shift_id',
        'user_id',
        'payment_type',
        'client_id',
        'client_name',
        'client_phone',
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
        'payment_status',
        'amount_paid',
        'balance_amount',
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
            'amount_paid' => 'decimal:2',
            'balance_amount' => 'decimal:2',
        ];
    }

    public function isPendingDiscount(): bool
    {
        return $this->sale_status === self::STATUS_PENDING_DISCOUNT;
    }

    public function paymentStatusLabel(): string
    {
        return match ($this->effectivePaymentStatus()) {
            self::PAYMENT_STATUS_NOT_PAID => 'Non payé',
            self::PAYMENT_STATUS_PARTIALLY_PAID => 'Partiellement payé',
            default => 'Entièrement payé',
        };
    }

    public function expectedPayableAmount(): string
    {
        $subtotal = (string) ($this->subtotal_amount ?? $this->total_amount ?? '0');
        $discount = (string) ($this->discount_amount ?? '0');

        if (bccomp($discount, '0', 2) === 1) {
            return bcsub($subtotal, $discount, 2);
        }

        return $subtotal;
    }

    public function paidAmountValue(): string
    {
        $storedPaid = (string) ($this->amount_paid ?? '0');
        if (bccomp($storedPaid, '0', 2) === 1) {
            return $storedPaid;
        }

        $expected = $this->expectedPayableAmount();
        $total = (string) ($this->total_amount ?? '0');

        // Legacy fallback: for partial dealer sales total_amount may store collected cash.
        if ($this->payment_type === 'credit' && bccomp($total, $expected, 2) === -1) {
            return $total;
        }

        if ($this->payment_type === 'cash') {
            return $expected;
        }

        return '0.00';
    }

    public function remainingAmountValue(): string
    {
        $expected = $this->expectedPayableAmount();
        $paid = $this->paidAmountValue();

        if (bccomp($paid, $expected, 2) >= 0) {
            return '0.00';
        }

        return bcsub($expected, $paid, 2);
    }

    public function effectivePaymentStatus(): string
    {
        $expected = $this->expectedPayableAmount();
        $paid = $this->paidAmountValue();
        $remaining = $this->remainingAmountValue();

        if (bccomp($remaining, '0', 2) === 1 && bccomp($paid, '0', 2) === 1) {
            return self::PAYMENT_STATUS_PARTIALLY_PAID;
        }

        if (bccomp($remaining, '0', 2) === 1 || bccomp($expected, '0', 2) === 1) {
            if (bccomp($paid, '0', 2) === 0) {
                return self::PAYMENT_STATUS_NOT_PAID;
            }
        }

        return self::PAYMENT_STATUS_FULLY_PAID;
    }

    public function displayClientName(): ?string
    {
        if ($this->client_id) {
            return $this->client?->name;
        }

        return filled($this->client_name) ? (string) $this->client_name : null;
    }

    public function displayClientPhone(): ?string
    {
        if ($this->client_id) {
            return $this->client?->phone;
        }

        return filled($this->client_phone) ? (string) $this->client_phone : null;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function posShift(): BelongsTo
    {
        return $this->belongsTo(PosShift::class);
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
