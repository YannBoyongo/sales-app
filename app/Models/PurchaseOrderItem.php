<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'batch_number',
        'quantity_ordered',
        'quantity_received',
        'unit_price',
        'tax',
        'other',
        'unit_cost',
        'line_cost',
        'requisition_item_id',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'tax' => 'decimal:2',
            'other' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'line_cost' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function receptions(): HasMany
    {
        return $this->hasMany(PurchaseOrderReception::class);
    }

    public function effectiveUnitCost(): ?float
    {
        if ($this->unit_cost !== null) {
            return round((float) $this->unit_cost, 2);
        }

        $qty = max(1, (int) $this->quantity_ordered);
        if ($this->line_cost !== null) {
            return round(((float) $this->line_cost) / $qty, 2);
        }

        return null;
    }
}
