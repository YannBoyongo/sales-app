<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesSession extends Model
{
    protected $fillable = [
        'branch_id',
        'opened_by',
        'closed_by',
        'status',
        'opened_at',
        'closed_at',
        'closure_total_amount',
        'closure_bank_reference',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'closure_total_amount' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(SessionExpense::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function computedTotal(): string
    {
        return (string) $this->saleItems()->sum('line_total');
    }

    public function cashSalesTotal(): string
    {
        return (string) $this->sales()->where('payment_type', 'cash')->sum('total_amount');
    }

    public function creditSalesTotal(): string
    {
        return (string) $this->sales()->where('payment_type', 'credit')->sum('total_amount');
    }

    public function expensesTotal(): string
    {
        return (string) $this->expenses()->sum('amount');
    }

    public function netCashTotal(): string
    {
        return bcsub($this->cashSalesTotal(), $this->expensesTotal(), 2);
    }

    public function pendingDiscountSalesCount(): int
    {
        return $this->sales()
            ->where('sale_status', Sale::STATUS_PENDING_DISCOUNT)
            ->count();
    }

    public function hasPendingDiscountSales(): bool
    {
        return $this->pendingDiscountSalesCount() > 0;
    }
}
