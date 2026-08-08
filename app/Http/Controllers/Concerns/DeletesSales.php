<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AccountingTransaction;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

trait DeletesSales
{
    protected function deleteSaleWithStockRestore(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            $sale->load('items');
            $itemIds = $sale->items->pluck('id')->all();
            $clientId = $sale->client_id;
            $saleReference = (string) $sale->reference;

            foreach ($sale->items as $item) {
                Stock::modifyQuantity((int) $item->product_id, (int) $item->location_id, (int) $item->quantity);
                \App\Models\StockBatch::restore(
                    (int) $item->product_id,
                    (int) $item->location_id,
                    $item->stock_batch_id ? (int) $item->stock_batch_id : null,
                    $item->batch_number,
                    $item->unit_cost !== null ? (float) $item->unit_cost : null,
                    (int) $item->quantity,
                );
            }

            if ($clientId !== null && $saleReference !== '') {
                $payments = Payment::query()
                    ->where('client_id', $clientId)
                    ->where('note', 'Paiement à la vente '.$saleReference)
                    ->get(['id']);

                if ($payments->isNotEmpty()) {
                    $paymentIds = $payments->pluck('id')->all();

                    foreach ($paymentIds as $paymentId) {
                        AccountingTransaction::query()
                            ->where('entry_type', 'debit')
                            ->where('reference', 'like', '%(paiement #'.$paymentId.')%')
                            ->delete();
                    }

                    Payment::query()->whereIn('id', $paymentIds)->delete();
                }
            }

            if ($itemIds !== []) {
                StockMovement::query()->whereIn('sale_item_id', $itemIds)->delete();
            }

            $sale->items()->delete();
            $sale->delete();
        });
    }
}
