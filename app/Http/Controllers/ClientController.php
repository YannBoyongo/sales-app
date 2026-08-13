<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\CashVoucher;
use App\Models\Client;
use App\Models\ClientCautionDeposit;
use App\Models\ClientCautionUsage;
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

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $branchesForFilter = $this->branchesForUser();
        $showsMultipleBranches = $branchesForFilter->count() > 1;

        if (($filters['branch_id'] ?? null) !== null) {
            abort_unless($branchesForFilter->contains('id', (int) $filters['branch_id']), 403);
        }

        $query = Client::query()
            ->with('branch:id,name')
            ->withSum(['creditSales as total_credit_amount' => fn ($q) => $q], 'line_total')
            ->withSum('payments', 'amount')
            ->when($filters['branch_id'] ?? null, fn ($q, $value) => $q->where('branch_id', (int) $value));

        $this->applyBranchFilter($query);

        $clients = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('clients.index', compact(
            'clients',
            'filters',
            'branchesForFilter',
            'showsMultipleBranches',
        ));
    }

    public function create(): View
    {
        abort_unless(auth()->user()?->canEditClientProfile(), 403);

        $branchesForFilter = $this->branchesForUser();
        $showsMultipleBranches = $branchesForFilter->count() > 1;
        $defaultBranch = $branchesForFilter->count() === 1 ? $branchesForFilter->first() : null;

        return view('clients.create', compact('branchesForFilter', 'showsMultipleBranches', 'defaultBranch'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canEditClientProfile(), 403);

        $branchesForFilter = $this->branchesForUser();
        $showsMultipleBranches = $branchesForFilter->count() > 1;

        $data = $request->validate([
            'branch_id' => [
                Rule::requiredIf($showsMultipleBranches),
                'nullable',
                'integer',
                'exists:branches,id',
            ],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $branchId = $this->resolveBranchIdForMutation($data['branch_id'] ?? null);

        $request->validate([
            'name' => [
                Rule::unique('clients', 'name')->where(fn ($q) => $q->where('branch_id', $branchId)),
            ],
        ]);

        $client = Client::create([
            'branch_id' => $branchId,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
        ]);

        return redirect()->route('clients.show', $client)->with('success', 'Client créé.');
    }

    public function edit(Client $client): View
    {
        abort_unless(auth()->user()?->canEditClientProfile(), 403);
        $this->ensureUserCanAccessClient($client);

        $client->loadMissing('branch:id,name');
        $branchesForFilter = $this->branchesForUser();
        $showsMultipleBranches = $branchesForFilter->count() > 1;

        return view('clients.edit', compact('client', 'branchesForFilter', 'showsMultipleBranches'));
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        abort_unless($request->user()?->canEditClientProfile(), 403);
        $this->ensureUserCanAccessClient($client);

        $branchesForFilter = $this->branchesForUser();
        $showsMultipleBranches = $branchesForFilter->count() > 1;
        $canChangeBranch = $showsMultipleBranches && auth()->user()?->canBypassBranchScope();

        $data = $request->validate([
            'branch_id' => [
                Rule::requiredIf($canChangeBranch),
                'nullable',
                'integer',
                'exists:branches,id',
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('clients', 'name')
                    ->where(fn ($q) => $q->where('branch_id', $canChangeBranch ? (int) ($request->input('branch_id') ?? $client->branch_id) : $client->branch_id))
                    ->ignore($client->id),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        if ($canChangeBranch) {
            abort_unless($branchesForFilter->contains('id', (int) $data['branch_id']), 403);
        }

        $client->update([
            'branch_id' => $canChangeBranch ? (int) $data['branch_id'] : $client->branch_id,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
        ]);

        return redirect()->route('clients.show', $client)->with('success', 'Client mis à jour.');
    }

    public function show(Client $client): View
    {
        $this->ensureUserCanAccessClient($client);
        $client->loadMissing('branch:id,name');

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
        $this->ensureUserCanAccessClient($client);

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
                'branch_id' => $client->branch_id,
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
        $this->ensureUserCanAccessClient($client);

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
                'branch_id' => $client->branch_id,
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
        abort_unless(auth()->user()?->hasApplicationAdminAccess(), 403);
        $this->ensureUserCanAccessClient($client);

        abort_unless((int) $deposit->client_id === (int) $client->id, 404);

        $voucher = CashVoucher::query()
            ->where('branch_id', $client->branch_id)
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

    public function destroyCautionUsage(Client $client, ClientCautionUsage $usage): RedirectResponse
    {
        abort_unless(auth()->user()?->hasApplicationAdminAccess(), 403);
        $this->ensureUserCanAccessClient($client);

        abort_unless((int) $usage->client_id === (int) $client->id, 404);

        if ($usage->sale_id !== null && Sale::query()->whereKey($usage->sale_id)->exists()) {
            return back()->with(
                'danger',
                'Impossible de supprimer cette utilisation : la vente associée existe encore. Supprimez la vente pour restaurer la caution automatiquement.'
            );
        }

        $usage->delete();

        return back()->with('success', 'Utilisation de caution supprimée. Le solde caution du client a été restauré.');
    }

    public function destroyPayment(Client $client, Payment $payment): RedirectResponse
    {
        abort_unless(auth()->user()?->hasApplicationAdminAccess(), 403);
        $this->ensureUserCanAccessClient($client);

        abort_unless((int) $payment->client_id === (int) $client->id, 404);

        $voucher = CashVoucher::query()
            ->where('branch_id', $client->branch_id)
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

    protected function ensureUserCanAccessClient(Client $client): void
    {
        $client->loadMissing('branch');
        if ($client->branch !== null) {
            $this->ensureUserCanAccessBranchModel($client->branch);
        }
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
