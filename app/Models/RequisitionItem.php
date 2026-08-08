<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisitionItem extends Model
{
    protected $fillable = [
        'requisition_id',
        'product_id',
        'location_id',
        'quantity',
        'batch_number',
        'unit_price',
        'tax',
        'other',
        'cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'tax' => 'decimal:2',
            'other' => 'decimal:2',
            'cost' => 'decimal:2',
        ];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function merchandiseTotal(): float
    {
        return round((int) $this->quantity * (float) $this->unit_price, 2);
    }
}
