<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Services\AccountingJournal;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Payment;
use App\Models\AccountingTransaction;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Stock;
use App\Models\StockMovement;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class SaleController extends Controller
{
    use RespectsUserBranch;

    public function show(Branch $branch, Sale $sale): View
    {
        $this->ensureUserCanAccessBranchModel($branch);
        abort_unless((int) $sale->branch_id === (int) $branch->id, 404);

        $sale->load([
            'items.product.department',
            'items.location',
            'client',
            'user',
            'branch',
            'posShift.posTerminal',
            'discountRequestedByUser:id,name',
            'discountApprovedByUser:id,name',
        ]);

        return view('sales.show', compact('branch', 'sale'));
    }

    public function edit(Branch $branch, Sale $sale): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $this->ensureUserCanAccessBranchModel($branch);
        abort_unless((int) $sale->branch_id === (int) $branch->id, 404);

        $sale->load('client');

        return view('sales.edit', compact('branch', 'sale'));
    }

    public function update(Request $request, Branch $branch, Sale $sale): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $this->ensureUserCanAccessBranchModel($branch);
        abort_unless((int) $sale->branch_id === (int) $branch->id, 404);

        $data = $request->validate([
            'sold_at' => ['required', 'date'],
            'payment_type' => ['required', 'in:cash,credit'],
            'client_name' => [
                Rule::requiredIf(fn () => $request->input('payment_type') === 'credit'),
                'nullable', 'string', 'max:255',
            ],
            'client_phone' => ['nullable', 'string', 'max:50'],
        ]);

        $clientId = null;
        $saleClientName = null;
        $saleClientPhone = null;

        if ($data['payment_type'] === 'credit') {
            $clientName = trim((string) ($data['client_name'] ?? ''));
            if ($clientName === '') {
                return back()->withInput()->withErrors(['client_name' => 'Le nom du client est obligatoire pour une vente à crédit.']);
            }
            $client = Client::query()->firstOrCreate(['name' => $clientName]);
            $clientPhone = trim((string) ($data['client_phone'] ?? ''));
            if ($clientPhone !== '') {
                $client->update(['phone' => $clientPhone]);
            }
            $clientId = $client->id;
        } else {
            $cn = trim((string) ($data['client_name'] ?? ''));
            $cp = trim((string) ($data['client_phone'] ?? ''));
            $saleClientName = $cn !== '' ? $cn : null;
            $saleClientPhone = $cp !== '' ? $cp : null;
        }

        $soldAt = Carbon::parse($data['sold_at']);

        DB::transaction(function () use ($sale, $data, $clientId, $saleClientName, $saleClientPhone, $soldAt) {
            $paymentStatus = $data['payment_type'] === 'credit'
                ? Sale::PAYMENT_STATUS_NOT_PAID
                : Sale::PAYMENT_STATUS_FULLY_PAID;
            $sale->update([
                'sold_at' => $soldAt,
                'payment_type' => $data['payment_type'],
                'client_id' => $clientId,
                'client_name' => $saleClientName,
                'client_phone' => $saleClientPhone,
                'payment_status' => $paymentStatus,
                'amount_paid' => $paymentStatus === Sale::PAYMENT_STATUS_FULLY_PAID ? (string) $sale->total_amount : '0.00',
                'balance_amount' => $paymentStatus === Sale::PAYMENT_STATUS_FULLY_PAID ? '0.00' : (string) $sale->total_amount,
            ]);

            $sale->items()->update([
                'payment_type' => $data['payment_type'],
                'client_id' => $clientId,
            ]);
        });

        return redirect()
            ->route('sales.show', [$branch, $sale])
            ->with('success', 'Vente mise à jour.');
    }

    public function destroy(Request $request, Branch $branch, Sale $sale): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $this->ensureUserCanAccessBranchModel($branch);
        abort_unless((int) $sale->branch_id === (int) $branch->id, 404);

        try {
            DB::transaction(function () use ($sale) {
                $sale->load('items');
                $itemIds = $sale->items->pluck('id')->all();
                $clientId = $sale->client_id;
                $saleReference = (string) $sale->reference;

                foreach ($sale->items as $item) {
                    Stock::modifyQuantity((int) $item->product_id, (int) $item->location_id, (int) $item->quantity);
                }

                // If this sale created immediate dealer payments, delete them so client debt stays consistent.
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
        } catch (RuntimeException $e) {
            return redirect()
                ->route('sales.overview')
                ->withErrors(['sale' => $e->getMessage()]);
        }

        return redirect()
            ->route('sales.overview')
            ->with('success', 'Vente supprimée et stock réintégré.');
    }

    public function approveDiscount(Request $request, Branch $branch, Sale $sale): RedirectResponse
    {
        $this->ensureUserCanAccessBranchModel($branch);
        abort_unless($request->user()?->canApproveSaleDiscounts(), 403);
        abort_unless((int) $sale->branch_id === (int) $branch->id, 404);

        if (! $sale->isPendingDiscount()) {
            return redirect()
                ->route('sales.show', [$branch, $sale])
                ->withErrors(['sale' => 'Cette vente n’a pas de remise en attente d’approbation.']);
        }

        $requested = $sale->discount_requested_amount;
        if ($requested === null || bccomp((string) $requested, '0', 2) <= 0) {
            return redirect()
                ->route('sales.show', [$branch, $sale])
                ->withErrors(['sale' => 'Montant de remise demandé invalide.']);
        }

        $subtotal = (string) $sale->subtotal_amount;
        if (bccomp((string) $requested, $subtotal, 2) > 0) {
            return redirect()
                ->route('sales.show', [$branch, $sale])
                ->withErrors(['sale' => 'La remise demandée dépasse le sous-total de la vente.']);
        }

        DB::transaction(function () use ($request, $sale, $requested, $subtotal) {
            $finalTotal = bcsub($subtotal, (string) $requested, 2);
            $paidAmount = (string) ($sale->amount_paid ?? '0.00');
            if (bccomp($paidAmount, $finalTotal, 2) === 1) {
                $paidAmount = $finalTotal;
            }
            $balance = bcsub($finalTotal, $paidAmount, 2);

            if (bccomp($balance, '0.00', 2) <= 0) {
                $balance = '0.00';
                $paymentStatus = Sale::PAYMENT_STATUS_FULLY_PAID;
            } elseif (bccomp($paidAmount, '0.00', 2) === 1) {
                $paymentStatus = Sale::PAYMENT_STATUS_PARTIALLY_PAID;
            } else {
                $paymentStatus = Sale::PAYMENT_STATUS_NOT_PAID;
            }

            $sale->update([
                'sale_status' => Sale::STATUS_CONFIRMED,
                'discount_amount' => $requested,
                'total_amount' => $finalTotal,
                'amount_paid' => $paidAmount,
                'balance_amount' => $balance,
                'payment_status' => $paymentStatus,
                'payment_type' => 'cash',
                'discount_approved_by' => $request->user()->id,
                'discount_approved_at' => now(),
            ]);

            // Discount approval alone should not move this sale into client debt.
            $sale->items()->update(['payment_type' => 'cash']);
        });

        return redirect()
            ->route('sales.show', [$branch, $sale])
            ->with('success', 'Remise approuvée. Le total de la vente a été mis à jour.');
    }

    public function rejectDiscount(Request $request, Branch $branch, Sale $sale): RedirectResponse
    {
        $this->ensureUserCanAccessBranchModel($branch);
        abort_unless($request->user()?->canApproveSaleDiscounts(), 403);
        abort_unless((int) $sale->branch_id === (int) $branch->id, 404);

        if (! $sale->isPendingDiscount()) {
            return redirect()
                ->route('sales.show', [$branch, $sale])
                ->withErrors(['sale' => 'Cette vente n’a pas de remise en attente d’approbation.']);
        }

        $subtotal = (string) $sale->subtotal_amount;
        $paidAmount = (string) ($sale->amount_paid ?? '0.00');
        if (bccomp($paidAmount, $subtotal, 2) === 1) {
            $paidAmount = $subtotal;
        }
        $balance = bcsub($subtotal, $paidAmount, 2);
        if (bccomp($balance, '0.00', 2) <= 0) {
            $balance = '0.00';
            $paymentStatus = Sale::PAYMENT_STATUS_FULLY_PAID;
        } elseif (bccomp($paidAmount, '0.00', 2) === 1) {
            $paymentStatus = Sale::PAYMENT_STATUS_PARTIALLY_PAID;
        } else {
            $paymentStatus = Sale::PAYMENT_STATUS_NOT_PAID;
        }

        $sale->update([
            'sale_status' => Sale::STATUS_CONFIRMED,
            'discount_requested_amount' => null,
            'discount_requested_by' => null,
            'discount_requested_at' => null,
            'discount_amount' => null,
            'discount_approved_by' => null,
            'discount_approved_at' => null,
            'total_amount' => $subtotal,
            'amount_paid' => $paidAmount,
            'balance_amount' => $balance,
            'payment_status' => $paymentStatus,
            'payment_type' => 'cash',
        ]);

        $sale->items()->update(['payment_type' => 'cash']);

        return redirect()
            ->route('sales.show', [$branch, $sale])
            ->with('success', 'Remise refusée. La vente est confirmée au prix catalogue (sans remise).');
    }

    public function storePayment(Request $request, Branch $branch, Sale $sale): RedirectResponse
    {
        $this->ensureUserCanAccessBranchModel($branch);
        abort_unless((int) $sale->branch_id === (int) $branch->id, 404);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        if ($sale->isPendingDiscount()) {
            return redirect()
                ->route('sales.show', [$branch, $sale])
                ->withErrors(['sale_payment' => 'Paiement impossible tant que la remise est en attente d’approbation.']);
        }

        $remaining = $sale->remainingAmountValue();
        if (bccomp($remaining, '0.00', 2) <= 0) {
            return redirect()
                ->route('sales.show', [$branch, $sale])
                ->withErrors(['sale_payment' => 'Cette vente est déjà entièrement payée.']);
        }

        $amount = number_format((float) $data['amount'], 2, '.', '');
        if (bccomp($amount, $remaining, 2) === 1) {
            return back()
                ->withInput()
                ->withErrors(['amount' => 'Le montant saisi dépasse le reste à payer pour cette vente.']);
        }

        DB::transaction(function () use ($request, $sale, $amount, $data) {
            if ($sale->client_id && bccomp($amount, '0.00', 2) === 1) {
                $client = Client::query()->find($sale->client_id);
                if ($client !== null) {
                    $payment = Payment::create([
                        'client_id' => $client->id,
                        'user_id' => $request->user()->id,
                        'amount' => $amount,
                        'paid_at' => now(),
                        'note' => filled($data['note'] ?? null)
                            ? $data['note']
                            : 'Paiement sur vente '.$sale->reference,
                    ]);

                    AccountingJournal::recordClientPayment($payment, $client, $request->user());
                }
            }

            $newAmountPaid = bcadd($sale->paidAmountValue(), $amount, 2);
            $expected = $sale->expectedPayableAmount();
            $newBalance = bcsub($expected, $newAmountPaid, 2);

            if (bccomp($newBalance, '0.00', 2) <= 0) {
                $newBalance = '0.00';
                $status = Sale::PAYMENT_STATUS_FULLY_PAID;
            } elseif (bccomp($newAmountPaid, '0.00', 2) === 1) {
                $status = Sale::PAYMENT_STATUS_PARTIALLY_PAID;
            } else {
                $status = Sale::PAYMENT_STATUS_NOT_PAID;
            }

            $sale->update([
                'amount_paid' => $newAmountPaid,
                'balance_amount' => $newBalance,
                'payment_status' => $status,
            ]);
        });

        return redirect()
            ->route('sales.show', [$branch, $sale])
            ->with('success', 'Paiement enregistré sur la vente.');
    }

    public function confirmPaid(Request $request, Branch $branch, Sale $sale): RedirectResponse
    {
        $this->ensureUserCanAccessBranchModel($branch);
        abort_unless((int) $sale->branch_id === (int) $branch->id, 404);

        if ($sale->isPendingDiscount()) {
            return redirect()
                ->route('sales.show', [$branch, $sale])
                ->withErrors(['sale' => 'Impossible de confirmer le paiement tant que la remise est en attente.']);
        }

        $expected = $sale->expectedPayableAmount();

        $sale->update([
            'sale_status' => Sale::STATUS_CONFIRMED,
            'amount_paid' => $expected,
            'balance_amount' => '0.00',
            'payment_status' => Sale::PAYMENT_STATUS_FULLY_PAID,
            'payment_type' => 'cash',
        ]);

        // Ensure client debt ledger (based on sale_items.payment_type) does not keep stale credit flags.
        $sale->items()->update(['payment_type' => 'cash']);

        return redirect()
            ->route('sales.show', [$branch, $sale])
            ->with('success', 'Vente confirmée comme entièrement payée.');
    }

    public function printLarge(Branch $branch, Sale $sale): Response
    {
        $this->ensureUserCanAccessBranchModel($branch);
        abort_unless((int) $sale->branch_id === (int) $branch->id, 404);

        $sale->load(['items.product', 'items.location', 'client', 'user', 'branch']);

        [$setting, $logoDataUrl] = $this->resolvePrintBranding();

        $pdf = Pdf::loadView('sales.pdf.invoice-large', [
            'sale' => $sale,
            'setting' => $setting,
            'logoDataUrl' => $logoDataUrl,
        ]);
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('defaultFont', 'DejaVu Sans');

        $safeRef = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $sale->reference) ?: 'facture';

        return $pdf->stream('facture-'.$safeRef.'.pdf');
    }

    public function printSmall(Branch $branch, Sale $sale): View
    {
        $this->ensureUserCanAccessBranchModel($branch);
        abort_unless((int) $sale->branch_id === (int) $branch->id, 404);
        $sale->load(['items.product', 'client', 'user', 'branch']);

        [$setting, $logoDataUrl] = $this->resolvePrintBranding();

        return view('sales.print-small', compact('sale', 'setting', 'logoDataUrl'));
    }

    /**
     * @return array{0: ?Setting, 1: ?string}
     */
    private function resolvePrintBranding(): array
    {
        $setting = Setting::query()->first();
        $logoDataUrl = null;
        if ($setting?->logo && Storage::disk('public')->exists($setting->logo)) {
            $path = storage_path('app/public/'.$setting->logo);
            if (is_readable($path)) {
                $mime = @mime_content_type($path) ?: 'image/png';
                $logoDataUrl = 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
            }
        }

        return [$setting, $logoDataUrl];
    }
}
