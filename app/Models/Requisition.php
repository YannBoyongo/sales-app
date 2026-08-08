<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Requisition extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_ORDERED = 'ordered';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'reference',
        'date',
        'status',
        'expenses',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'expenses' => 'decimal:2',
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

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, 'open'], true);
    }

    public function canConvertToPurchaseOrder(): bool
    {
        return $this->status === self::STATUS_CONFIRMED
            && $this->items()->exists()
            && ! $this->purchaseOrders()->exists();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_CONFIRMED, 'approved', 'fulfilled' => 'Confirmée',
            self::STATUS_ORDERED => 'Commandée',
            self::STATUS_REJECTED => 'Refusée',
            self::STATUS_PENDING, 'open' => 'En attente',
            default => ucfirst((string) $this->status),
        };
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'REQ-'.Str::upper(Str::random(4)).random_int(1000, 9999);
        } while (self::query()->where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * Allocate requisition expenses onto lines by share of merchandise (qty × unit_price).
     * Sets each item's other, cost, and returns the enriched rows (does not persist).
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    public static function allocateExpensesToItems(Collection $items, float|string $expenses): Collection
    {
        $expensesCents = (int) round(((float) $expenses) * 100);

        $rows = $items->values()->map(function (array $item) {
            $quantity = max(0, (int) ($item['quantity'] ?? 0));
            $unitPrice = round((float) ($item['unit_price'] ?? 0), 2);
            $tax = round((float) ($item['tax'] ?? 0), 2);
            $merchandise = round($quantity * $unitPrice, 2);

            return array_merge($item, [
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax' => $tax,
                'merchandise' => $merchandise,
                'merchandise_cents' => (int) round($merchandise * 100),
            ]);
        });

        $totalMerchandiseCents = (int) $rows->sum('merchandise_cents');
        $allocatedCents = 0;

        return $rows->values()->map(function (array $item, int $index) use ($rows, $expensesCents, $totalMerchandiseCents, &$allocatedCents) {
            $isLast = $index === ($rows->count() - 1);

            if ($expensesCents <= 0 || $rows->count() === 0) {
                $otherCents = 0;
            } elseif ($totalMerchandiseCents <= 0) {
                // Equal split when all merchandise is zero.
                $base = intdiv($expensesCents, $rows->count());
                $remainder = $expensesCents - ($base * $rows->count());
                $otherCents = $base + ($index < $remainder ? 1 : 0);
            } elseif ($isLast) {
                $otherCents = max(0, $expensesCents - $allocatedCents);
            } else {
                $otherCents = (int) round($expensesCents * ($item['merchandise_cents'] / $totalMerchandiseCents));
                $allocatedCents += $otherCents;
            }

            $other = round($otherCents / 100, 2);
            $costTotal = round($item['merchandise'] + $item['tax'] + $other, 2);
            $quantity = max(1, (int) $item['quantity']);
            $unitCost = round($costTotal / $quantity, 2);
            $sharePercent = $totalMerchandiseCents > 0
                ? round(($item['merchandise_cents'] / $totalMerchandiseCents) * 100, 2)
                : ($rows->count() > 0 ? round(100 / $rows->count(), 2) : 0);

            unset($item['merchandise_cents']);

            return array_merge($item, [
                'other' => $other,
                // Stored `cost` remains the line total (coût total).
                'cost' => $costTotal,
                'unit_cost' => $unitCost,
                'cost_total' => $costTotal,
                'share_percent' => $sharePercent,
            ]);
        });
    }
}
