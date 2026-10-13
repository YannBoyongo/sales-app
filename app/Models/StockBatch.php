<?php

namespace App\Models;

use App\Support\StockQuantity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use RuntimeException;

class StockBatch extends Model
{
    public const LEGACY_BATCH = 'LEGACY';

    protected $fillable = [
        'product_id',
        'location_id',
        'batch_number',
        'unit_cost',
        'quantity',
        'purchase_order_id',
        'purchase_order_item_id',
        'purchase_order_reception_id',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'quantity' => 'decimal:3',
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

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public static function normalizeBatchNumber(?string $batchNumber, ?string $fallback = null): string
    {
        $value = trim((string) $batchNumber);

        return $value !== '' ? $value : ($fallback ?: self::LEGACY_BATCH);
    }

    /**
     * Increase a batch layer (creates row if needed). Does not touch aggregate stocks.
     */
    public static function receive(
        int $productId,
        int $locationId,
        string $batchNumber,
        float $unitCost,
        float|int|string $quantity,
        ?int $purchaseOrderId = null,
        ?int $purchaseOrderItemId = null,
        ?int $purchaseOrderReceptionId = null,
    ): self {
        $quantity = StockQuantity::normalize($quantity);
        if (! StockQuantity::isPositive($quantity)) {
            throw new RuntimeException('La quantité du lot doit être positive.');
        }

        $batchNumber = self::normalizeBatchNumber($batchNumber);
        $unitCost = round($unitCost, 2);

        $batch = static::query()
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->where('batch_number', $batchNumber)
            ->where('unit_cost', number_format($unitCost, 2, '.', ''))
            ->lockForUpdate()
            ->first();

        if ($batch) {
            $batch->update([
                'quantity' => StockQuantity::add($batch->quantity, $quantity),
                'purchase_order_id' => $purchaseOrderId ?? $batch->purchase_order_id,
                'purchase_order_item_id' => $purchaseOrderItemId ?? $batch->purchase_order_item_id,
                'purchase_order_reception_id' => $purchaseOrderReceptionId ?? $batch->purchase_order_reception_id,
            ]);

            return $batch->fresh();
        }

        return static::query()->create([
            'product_id' => $productId,
            'location_id' => $locationId,
            'batch_number' => $batchNumber,
            'unit_cost' => $unitCost,
            'quantity' => $quantity,
            'purchase_order_id' => $purchaseOrderId,
            'purchase_order_item_id' => $purchaseOrderItemId,
            'purchase_order_reception_id' => $purchaseOrderReceptionId,
        ]);
    }

    /**
     * Remove qty from a specific batch layer.
     */
    public static function reverseReceive(int $stockBatchId, float|int|string $quantity): void
    {
        $quantity = StockQuantity::normalize($quantity);
        if (! StockQuantity::isPositive($quantity)) {
            return;
        }

        $batch = static::query()->whereKey($stockBatchId)->lockForUpdate()->first();
        if (! $batch) {
            return;
        }

        $newQty = StockQuantity::sub($batch->quantity, $quantity);
        if (StockQuantity::comp($newQty, 0) < 0) {
            throw new RuntimeException('Stock lot insuffisant pour annuler cette réception.');
        }

        if (StockQuantity::isZero($newQty)) {
            $batch->delete();

            return;
        }

        $batch->update(['quantity' => $newQty]);
    }

    /**
     * FIFO consume tracked batches, then untracked (legacy) qty.
     *
     * @return Collection<int, array{stock_batch_id: ?int, batch_number: ?string, unit_cost: ?float, quantity: string}>
     */
    public static function consumeFifo(int $productId, int $locationId, float|int|string $quantity): Collection
    {
        $quantity = StockQuantity::normalize($quantity);
        if (! StockQuantity::isPositive($quantity)) {
            return collect();
        }

        $remaining = $quantity;
        $chunks = collect();

        $batches = static::query()
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->where('quantity', '>', 0)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if (StockQuantity::isZero($remaining)) {
                break;
            }

            $take = StockQuantity::min($remaining, $batch->quantity);
            $batchId = (int) $batch->id;
            $batchNumber = $batch->batch_number;
            $unitCost = round((float) $batch->unit_cost, 2);
            $newQty = StockQuantity::sub($batch->quantity, $take);

            if (StockQuantity::isZero($newQty)) {
                $batch->delete();
            } else {
                $batch->update(['quantity' => $newQty]);
            }

            $chunks->push([
                'stock_batch_id' => $batchId,
                'batch_number' => $batchNumber,
                'unit_cost' => $unitCost,
                'quantity' => $take,
            ]);

            $remaining = StockQuantity::sub($remaining, $take);
        }

        if (StockQuantity::isPositive($remaining)) {
            $chunks->push([
                'stock_batch_id' => null,
                'batch_number' => null,
                'unit_cost' => null,
                'quantity' => $remaining,
            ]);
        }

        return $chunks;
    }

    /**
     * @return Collection<int, array{
     *     source_stock_batch_id: ?int,
     *     destination_stock_batch_id: ?int,
     *     batch_number: ?string,
     *     unit_cost: ?float,
     *     quantity: string
     * }>
     */
    public static function transferFifo(
        int $productId,
        int $fromLocationId,
        int $toLocationId,
        float|int|string $quantity,
    ): Collection {
        return self::consumeFifo($productId, $fromLocationId, $quantity)
            ->map(function (array $chunk) use ($productId, $toLocationId) {
                $destinationBatchId = null;

                if ($chunk['batch_number'] !== null && $chunk['unit_cost'] !== null) {
                    $destinationBatchId = (int) self::receive(
                        $productId,
                        $toLocationId,
                        $chunk['batch_number'],
                        $chunk['unit_cost'],
                        $chunk['quantity'],
                    )->id;
                }

                return [
                    'source_stock_batch_id' => $chunk['stock_batch_id'],
                    'destination_stock_batch_id' => $destinationBatchId,
                    'batch_number' => $chunk['batch_number'],
                    'unit_cost' => $chunk['unit_cost'],
                    'quantity' => $chunk['quantity'],
                ];
            });
    }

    public static function reverseTransferChunk(
        int $productId,
        int $fromLocationId,
        ?int $sourceStockBatchId,
        ?int $destinationStockBatchId,
        ?string $batchNumber,
        ?float $unitCost,
        float|int|string $quantity,
    ): void {
        if ($destinationStockBatchId) {
            self::reverseReceive($destinationStockBatchId, $quantity);
        }

        self::restore(
            $productId,
            $fromLocationId,
            $sourceStockBatchId,
            $batchNumber,
            $unitCost,
            $quantity,
        );
    }

    public static function restore(
        int $productId,
        int $locationId,
        ?int $stockBatchId,
        ?string $batchNumber,
        ?float $unitCost,
        float|int|string $quantity,
    ): void {
        $quantity = StockQuantity::normalize($quantity);
        if (! StockQuantity::isPositive($quantity)) {
            return;
        }

        if ($stockBatchId) {
            $batch = static::query()->whereKey($stockBatchId)->lockForUpdate()->first();
            if ($batch) {
                $batch->update(['quantity' => StockQuantity::add($batch->quantity, $quantity)]);

                return;
            }
        }

        if ($batchNumber === null || $unitCost === null) {
            return;
        }

        self::receive($productId, $locationId, $batchNumber, $unitCost, $quantity);
    }

    public static function batchedQuantity(int $productId, int $locationId): string
    {
        $sum = static::query()
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->sum('quantity');

        return StockQuantity::normalize($sum);
    }
}
