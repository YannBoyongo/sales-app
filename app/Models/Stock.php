<?php

namespace App\Models;

use App\Support\StockQuantity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class Stock extends Model
{
    protected $fillable = ['product_id', 'location_id', 'quantity', 'minimum_stock'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'minimum_stock' => 'decimal:3',
        ];
    }

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

        return StockQuantity::comp($this->quantity, $min) < 0;
    }

    /**
     * @throws RuntimeException
     */
    public static function modifyQuantity(int $productId, int $locationId, float|int|string $delta): self
    {
        $delta = StockQuantity::normalize($delta);

        $stock = static::query()
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();

        if (! $stock) {
            if (StockQuantity::comp($delta, 0) < 0) {
                throw new RuntimeException('Stock insuffisant pour ce produit à cet emplacement.');
            }

            if (StockQuantity::isZero($delta)) {
                throw new RuntimeException('La quantité doit être positive pour créer un stock.');
            }

            return static::create([
                'product_id' => $productId,
                'location_id' => $locationId,
                'quantity' => $delta,
                'minimum_stock' => null,
            ]);
        }

        $newQty = StockQuantity::add($stock->quantity, $delta);
        if (StockQuantity::comp($newQty, 0) < 0) {
            throw new RuntimeException('Stock insuffisant pour ce produit à cet emplacement.');
        }

        $stock->update(['quantity' => $newQty]);

        return $stock->fresh();
    }
}
