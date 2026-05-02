<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = [
        'reference',
        'sales_session_id',
        'sale_id',
        'location_id',
        'product_id',
        'user_id',
        'quantity',
        'unit_price',
        'line_total',
        'discount_amount',
        'payment_type',
        'client_id',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'discount_amount' => 'decimal:2',
        ];
    }

    public function salesSession(): BelongsTo
    {
        return $this->belongsTo(SalesSession::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
