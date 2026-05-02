<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class Stock extends Model
{
    protected $fillable = ['product_id', 'location_id', 'quantity', 'minimum_stock'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function isBelowMinimum(): bool
    {
        $min = $this->minimum_stock ?? $this->product?->minimum_stock;
        if ($min === null) {
            return false;
        }

        return $this->quantity < $min;
    }

    /**
     * @throws RuntimeException
     */
    public static function modifyQuantity(int $productId, int $locationId, int $delta): self
    {
        $stock = static::query()
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();

        if (! $stock) {
            if ($delta < 0) {
                throw new RuntimeException('Stock insuffisant pour ce produit à cet emplacement.');
            }

            return static::create([
                'product_id' => $productId,
                'location_id' => $locationId,
                'quantity' => $delta,
                'minimum_stock' => null,
            ]);
        }

        $newQty = $stock->quantity + $delta;
        if ($newQty < 0) {
            throw new RuntimeException('Stock insuffisant pour ce produit à cet emplacement.');
        }

        $stock->update(['quantity' => $newQty]);

        return $stock->fresh();
    }
}
