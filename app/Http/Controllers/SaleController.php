<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\Sale;
use App\Models\SalesSession;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class SaleController extends Controller
{
    use RespectsUserBranch;

    public function show(SalesSession $salesSession, Sale $sale): View
    {
        $this->ensureUserCanAccessSalesSession($salesSession);
        abort_unless((int) $sale->sales_session_id === (int) $salesSession->id, 404);

        $sale->load([
            'items.product.department',
            'items.location',
            'client',
            'user',
            'session.branch',
            'discountRequestedByUser:id,name',
            'discountApprovedByUser:id,name',
        ]);

        return view('sales.show', compact('salesSession', 'sale'));
    }

    public function approveDiscount(Request $request, SalesSession $salesSession, Sale $sale): RedirectResponse
    {
        $this->ensureUserCanAccessSalesSession($salesSession);
        abort_unless($request->user()?->is_admin, 403);
        abort_unless((int) $sale->sales_session_id === (int) $salesSession->id, 404);

        if (! $sale->isPendingDiscount()) {
            return redirect()
                ->route('sales.show', [$salesSession, $sale])
                ->withErrors(['sale' => 'Cette vente n’a pas de remise en attente d’approbation.']);
        }

        $requested = $sale->discount_requested_amount;
        if ($requested === null || bccomp((string) $requested, '0', 2) <= 0) {
            return redirect()
                ->route('sales.show', [$salesSession, $sale])
                ->withErrors(['sale' => 'Montant de remise demandé invalide.']);
        }

        $subtotal = (string) $sale->subtotal_amount;
        if (bccomp((string) $requested, $subtotal, 2) > 0) {
            return redirect()
                ->route('sales.show', [$salesSession, $sale])
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
            ->route('sales.show', [$salesSession, $sale])
            ->with('success', 'Remise approuvée. Le total de la vente a été mis à jour.');
    }

    public function rejectDiscount(Request $request, SalesSession $salesSession, Sale $sale): RedirectResponse
    {
        $this->ensureUserCanAccessSalesSession($salesSession);
        abort_unless($request->user()?->is_admin, 403);
        abort_unless((int) $sale->sales_session_id === (int) $salesSession->id, 404);

        if (! $sale->isPendingDiscount()) {
            return redirect()
                ->route('sales.show', [$salesSession, $sale])
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
            ->route('sales.show', [$salesSession, $sale])
            ->with('success', 'Remise refusée. La vente est confirmée au prix catalogue (sans remise).');
    }

    public function printLarge(SalesSession $salesSession, Sale $sale): Response
    {
        $this->ensureUserCanAccessSalesSession($salesSession);
        abort_unless((int) $sale->sales_session_id === (int) $salesSession->id, 404);

        $sale->load(['items.product', 'items.location', 'client', 'user', 'session.branch']);

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

    public function printSmall(SalesSession $salesSession, Sale $sale): View
    {
        $this->ensureUserCanAccessSalesSession($salesSession);
        abort_unless((int) $sale->sales_session_id === (int) $salesSession->id, 404);
        $sale->load(['items.product', 'client', 'user', 'session.branch']);

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
