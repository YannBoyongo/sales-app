<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\CashVoucher;
use App\Models\Client;
use App\Models\ClientCautionDeposit;
use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientController extends Controller
{
    use RespectsUserBranch;

    public function index(): View
    {
        $clients = Client::query()
            ->withSum(['creditSales as total_credit_amount' => fn ($q) => $q], 'line_total')
            ->withSum('payments', 'amount')
            ->orderBy('name')
            ->paginate(20);

        return view('clients.index', compact('clients'));
    }

    public function create(): View
    {
        abort_unless(auth()->user()?->canEditClientProfile(), 403);

        return view('clients.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canEditClientProfile(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:clients,name'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $client = Client::create($data);

        return redirect()->route('clients.show', $client)->with('success', 'Client créé.');
    }

    public function edit(Client $client): View
    {
        abort_unless(auth()->user()?->canEditClientProfile(), 403);

        return view('clients.edit', compact('client'));
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        abort_unless($request->user()?->canEditClientProfile(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('clients', 'name')->ignore($client->id)],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $client->update($data);

        return redirect()->route('clients.show', $client)->with('success', 'Client mis à jour.');
    }

    public function show(Client $client): View
    {
        $ids = $this->branchFilterIds();
        if ($ids !== null) {
            $accessible = $client->creditSales()
                ->whereIn('branch_id', $ids)
                ->exists();
            abort_unless($accessible, 403, 'Accès non autorisé pour ce client.');
        }

        $showFinanceDetail = auth()->user()?->canViewClientsLedger() ?? false;

        $totalCredit = '0';
        $totalPaid = '0';
        $balance = '0';
        $cautionTotal = '0';
        $cautionUsed = '0';
        $cautionBalance = '0';

        if ($showFinanceDetail) {
            $client->load([
                'creditSales' => fn ($q) => $q->latest()->with(['branch', 'product', 'sale']),
                'payments' => fn ($q) => $q->latest()->with('user'),
                'cautionDeposits' => fn ($q) => $q->latest('deposited_at')->with('user'),
                'cautionUsages' => fn ($q) => $q->latest('used_at')->with(['user', 'sale.branch']),
            ]);

            $totalCredit = $client->totalCreditAmount();
            $totalPaid = $client->totalPaidAmount();
            $balance = $client->debtBalance();
            $cautionTotal = $client->cautionTotal();
            $cautionUsed = $client->cautionUsedAmount();
            $cautionBalance = $client->cautionBalance();
        }

        return view('clients.show', compact(
            'client',
            'totalCredit',
            'totalPaid',
            'balance',
            'cautionTotal',
            'cautionUsed',
            'cautionBalance',
            'showFinanceDetail',
        ));
    }

    public function storePayment(Request $request, Client $client): RedirectResponse
    {
        $ids = $this->branchFilterIds();
        if ($ids !== null) {
            $accessible = $client->creditSales()
                ->whereIn('branch_id', $ids)
                ->exists();
            abort_unless($accessible, 403, 'Accès non autorisé pour ce client.');
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $currentDebt = (float) $client->debtBalance();
        $amount = (float) $data['amount'];

        if ($amount > $currentDebt) {
            return back()->withInput()->withErrors([
                'amount' => 'Le montant dépasse la dette actuelle du client.',
            ]);
        }

        DB::transaction(function () use ($request, $client, $amount, $data) {
            $payment = Payment::create([
                'client_id' => $client->id,
                'user_id' => $request->user()->id,
                'amount' => number_format($amount, 2, '.', ''),
                'paid_at' => now(),
                'note' => $data['note'] ?? null,
            ]);

            $amountStr = number_format($amount, 2, '.', '');
            $description = sprintf(
                'Entrée caisse issue du paiement dette — %s',
                $client->name
            );
            if (filled($data['note'] ?? null)) {
                $description .= ' — '.mb_substr((string) $data['note'], 0, 500);
            }

            CashVoucher::query()->create([
                'voucher_no' => 'CV-DETTE-'.$payment->id,
                'date' => optional($payment->paid_at)->toDateString() ?? now()->toDateString(),
                'description' => mb_substr($description, 0, 2000),
                'type' => 'entry',
                'amount' => $amountStr,
            ]);
        });

        return redirect()
            ->route('clients.show', $client)
            ->with('success', 'Paiement enregistré. Un bon de caisse (entrée) a été créé — validez-le puis enregistrez-le en comptabilité depuis Bons de caisse.');
    }

    public function storeCautionDeposit(Request $request, Client $client): RedirectResponse
    {
        $ids = $this->branchFilterIds();
        if ($ids !== null) {
            $accessible = $client->creditSales()
                ->whereIn('branch_id', $ids)
                ->exists();
            abort_unless($accessible, 403, 'Accès non autorisé pour ce client.');
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $amount = (float) $data['amount'];

        DB::transaction(function () use ($request, $client, $amount, $data) {
            $deposit = ClientCautionDeposit::create([
                'client_id' => $client->id,
                'user_id' => $request->user()->id,
                'amount' => number_format($amount, 2, '.', ''),
                'deposited_at' => now(),
                'note' => $data['note'] ?? null,
            ]);

            $amountStr = number_format($amount, 2, '.', '');
            $description = sprintf(
                'Entrée caisse — dépôt caution — %s',
                $client->name
            );
            if (filled($data['note'] ?? null)) {
                $description .= ' — '.mb_substr((string) $data['note'], 0, 500);
            }

            CashVoucher::query()->create([
                'voucher_no' => 'CV-CAUTION-'.$deposit->id,
                'date' => optional($deposit->deposited_at)->toDateString() ?? now()->toDateString(),
                'description' => mb_substr($description, 0, 2000),
                'type' => 'entry',
                'amount' => $amountStr,
            ]);
        });

        return redirect()
            ->route('clients.show', $client)
            ->with('success', 'Dépôt de caution enregistré. Un bon de caisse (entrée) a été créé — validez-le puis enregistrez-le en comptabilité depuis Bons de caisse.');
    }

    public function destroyCautionDeposit(Client $client, ClientCautionDeposit $deposit): RedirectResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        abort_unless((int) $deposit->client_id === (int) $client->id, 404);

        $voucher = CashVoucher::query()
            ->where('voucher_no', 'CV-CAUTION-'.$deposit->id)
            ->first();

        if ($voucher?->accounting_transaction_id) {
            return back()->with('danger', 'Impossible de supprimer ce dépôt : le bon de caisse associé a déjà été comptabilisé.');
        }

        DB::transaction(function () use ($deposit, $voucher) {
            $voucher?->delete();
            $deposit->delete();
        });

        return back()->with('success', 'Dépôt de caution supprimé.');
    }

    public function destroyPayment(Client $client, Payment $payment): RedirectResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        abort_unless((int) $payment->client_id === (int) $client->id, 404);

        $voucher = CashVoucher::query()
            ->where('voucher_no', 'CV-DETTE-'.$payment->id)
            ->first();

        if ($voucher?->accounting_transaction_id) {
            return back()->with('danger', 'Impossible de supprimer ce paiement : le bon de caisse associé a déjà été comptabilisé.');
        }

        DB::transaction(function () use ($client, $payment, $voucher) {
            $this->reverseSalePaymentIfApplicable($payment, $client, $voucher);
            $voucher?->delete();
            $payment->delete();
        });

        return back()->with('success', 'Paiement supprimé.');
    }

    private function reverseSalePaymentIfApplicable(Payment $payment, Client $client, ?CashVoucher $voucher): void
    {
        $reference = $this->saleReferenceFromPayment($payment, $voucher);
        if ($reference === null) {
            return;
        }

        $sale = Sale::query()
            ->where('client_id', $client->id)
            ->where('reference', $reference)
            ->lockForUpdate()
            ->first();

        if ($sale === null) {
            return;
        }

        $amount = number_format((float) $payment->amount, 2, '.', '');
        $newAmountPaid = bcsub($sale->paidAmountValue(), $amount, 2);
        if (bccomp($newAmountPaid, '0', 2) === -1) {
            $newAmountPaid = '0.00';
        }

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

        $sale->syncLinePaymentTypesForBalance();
    }

    private function saleReferenceFromPayment(Payment $payment, ?CashVoucher $voucher): ?string
    {
        $note = (string) ($payment->note ?? '');
        if (preg_match('/Paiement (?:sur|à la) vente (\S+)/u', $note, $matches)) {
            return $matches[1];
        }

        $description = (string) ($voucher?->description ?? '');
        if (preg_match('/\(vente (\S+)\)/u', $description, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
