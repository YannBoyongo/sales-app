<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItemBatch extends Model
{
    protected $fillable = [
        'stock_transfer_item_id',
        'source_stock_batch_id',
        'destination_stock_batch_id',
        'batch_number',
        'unit_cost',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    public function stockTransferItem(): BelongsTo
    {
        return $this->belongsTo(StockTransferItem::class);
    }

    public function isLegacy(): bool
    {
        return $this->batch_number === null || $this->unit_cost === null;
    }
}
