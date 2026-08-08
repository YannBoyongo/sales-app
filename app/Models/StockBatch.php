<?php

namespace App\Models;

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
            'quantity' => 'integer',
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
        int $quantity,
        ?int $purchaseOrderId = null,
        ?int $purchaseOrderItemId = null,
        ?int $purchaseOrderReceptionId = null,
    ): self {
        if ($quantity <= 0) {
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
                'quantity' => (int) $batch->quantity + $quantity,
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
    public static function reverseReceive(int $stockBatchId, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $batch = static::query()->whereKey($stockBatchId)->lockForUpdate()->first();
        if (! $batch) {
            return;
        }

        $newQty = (int) $batch->quantity - $quantity;
        if ($newQty < 0) {
            throw new RuntimeException('Stock lot insuffisant pour annuler cette réception.');
        }

        if ($newQty === 0) {
            $batch->delete();

            return;
        }

        $batch->update(['quantity' => $newQty]);
    }

    /**
     * FIFO consume tracked batches, then untracked (legacy) qty.
     * Aggregate stocks.quantity must already cover the request (caller decrements stocks).
     *
     * @return Collection<int, array{stock_batch_id: ?int, batch_number: ?string, unit_cost: ?float, quantity: int}>
     */
    public static function consumeFifo(int $productId, int $locationId, int $quantity): Collection
    {
        if ($quantity <= 0) {
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
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, (int) $batch->quantity);
            $batchId = (int) $batch->id;
            $batchNumber = $batch->batch_number;
            $unitCost = round((float) $batch->unit_cost, 2);
            $newQty = (int) $batch->quantity - $take;

            if ($newQty === 0) {
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

            $remaining -= $take;
        }

        if ($remaining > 0) {
            // Legacy / unbatched stock — sellable, cost unknown.
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
     * FIFO move from source location batches onto destination location layers.
     *
     * @return Collection<int, array{
     *     source_stock_batch_id: ?int,
     *     destination_stock_batch_id: ?int,
     *     batch_number: ?string,
     *     unit_cost: ?float,
     *     quantity: int
     * }>
     */
    public static function transferFifo(
        int $productId,
        int $fromLocationId,
        int $toLocationId,
        int $quantity,
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
        int $quantity,
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

    /**
     * Restore qty onto a batch after sale deletion (or create layer again).
     */
    public static function restore(
        int $productId,
        int $locationId,
        ?int $stockBatchId,
        ?string $batchNumber,
        ?float $unitCost,
        int $quantity,
    ): void {
        if ($quantity <= 0) {
            return;
        }

        if ($stockBatchId) {
            $batch = static::query()->whereKey($stockBatchId)->lockForUpdate()->first();
            if ($batch) {
                $batch->update(['quantity' => (int) $batch->quantity + $quantity]);

                return;
            }
        }

        if ($batchNumber === null || $unitCost === null) {
            // Legacy sale — only aggregate stock is restored by caller.
            return;
        }

        self::receive($productId, $locationId, $batchNumber, $unitCost, $quantity);
    }

    public static function batchedQuantity(int $productId, int $locationId): int
    {
        return (int) static::query()
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->sum('quantity');
    }
}
