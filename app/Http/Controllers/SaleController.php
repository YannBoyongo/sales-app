<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
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

        $finalTotal = bcsub($subtotal, (string) $requested, 2);

        $sale->update([
            'sale_status' => Sale::STATUS_CONFIRMED,
            'discount_amount' => $requested,
            'total_amount' => $finalTotal,
            'discount_approved_by' => $request->user()->id,
            'discount_approved_at' => now(),
        ]);

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

        $sale->update([
            'sale_status' => Sale::STATUS_CONFIRMED,
            'discount_requested_amount' => null,
            'discount_requested_by' => null,
            'discount_requested_at' => null,
            'discount_amount' => null,
            'discount_approved_by' => null,
            'discount_approved_at' => null,
            'total_amount' => $subtotal,
        ]);

        return redirect()
            ->route('sales.show', [$branch, $sale])
            ->with('success', 'Remise refusée. La vente est confirmée au prix catalogue (sans remise).');
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
