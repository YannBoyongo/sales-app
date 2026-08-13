<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\AccountingTransaction;
use App\Models\CashVoucher;
use App\Models\ChartOfAccount;
use App\Models\PosTerminal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CashVoucherController extends Controller
{
    use RespectsUserBranch;

    public function index(Request $request): View|JsonResponse
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'type' => ['nullable', 'in:entry,exit'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'pos_terminal_id' => ['nullable', 'integer', 'exists:pos_terminals,id'],
        ]);

        $branchesForFilter = $this->branchesForUser();
        $showsBranchFilter = $branchesForFilter->count() > 1;

        if (($filters['branch_id'] ?? null) !== null) {
            abort_unless($branchesForFilter->contains('id', (int) $filters['branch_id']), 403);
        }

        $posTerminals = $this->posTerminalsForCashVoucherFilter($filters['branch_id'] ?? null);
        $allPosTerminals = $this->posTerminalsForCashVoucherFilter(null);
        $showsMultipleTerminalBranches = $posTerminals->pluck('branch_id')->unique()->count() > 1;
        $showsMultipleTerminalBranchesAll = $allPosTerminals->pluck('branch_id')->unique()->count() > 1;
        $allPosTerminalsForFilter = $allPosTerminals
            ->map(fn (PosTerminal $terminal) => [
                'id' => $terminal->id,
                'branch_id' => $terminal->branch_id,
                'name' => $terminal->name,
                'branch_name' => $terminal->branch?->name ?? '',
            ])
            ->values()
            ->all();

        $baseQuery = CashVoucher::query()
            ->with('branch:id,name')
            ->when($filters['date_from'] ?? null, fn ($q, $value) => $q->whereDate('date', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($q, $value) => $q->whereDate('date', '<=', $value))
            ->when($filters['type'] ?? null, fn ($q, $value) => $q->where('type', $value))
            ->when($filters['branch_id'] ?? null, fn ($q, $value) => $q->where('branch_id', (int) $value))
            ->when($filters['pos_terminal_id'] ?? null, function ($q, $value) use ($posTerminals) {
                abort_unless($posTerminals->contains('id', (int) $value), 403);
                $q->whereHas('posShift', fn ($shift) => $shift->where('pos_terminal_id', (int) $value));
            });

        $this->applyBranchFilter($baseQuery);

        $totals = (clone $baseQuery)
            ->whereNotNull('approved_at')
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'entry' THEN amount ELSE 0 END), 0) as total_entries")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'exit' THEN amount ELSE 0 END), 0) as total_exits")
            ->first();

        $pendingVouchers = (clone $baseQuery)
            ->whereNull('approved_at')
            ->orderByDesc('id')
            ->get();

        $approvedVouchers = (clone $baseQuery)
            ->whereNotNull('approved_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        if ($request->boolean('infinite')) {
            $nextPageUrl = null;
            if ($approvedVouchers->hasMorePages()) {
                $nextPageUrl = $approvedVouchers->nextPageUrl();
                $nextPageUrl .= (str_contains($nextPageUrl, '?') ? '&' : '?').'infinite=1';
            }

            return response()->json([
                'html' => view('cash_vouchers.partials.approved-rows', [
                    'approvedVouchers' => $approvedVouchers,
                ])->render(),
                'next_page_url' => $nextPageUrl,
                'from' => $approvedVouchers->firstItem(),
                'to' => $approvedVouchers->lastItem(),
                'total' => $approvedVouchers->total(),
                'has_more' => $approvedVouchers->hasMorePages(),
            ]);
        }

        $infiniteNextPageUrl = null;
        if ($approvedVouchers->hasMorePages()) {
            $infiniteNextPageUrl = $approvedVouchers->nextPageUrl();
            $infiniteNextPageUrl .= (str_contains($infiniteNextPageUrl, '?') ? '&' : '?').'infinite=1';
        }

        return view('cash_vouchers.index', [
            'pendingVouchers' => $pendingVouchers,
            'approvedVouchers' => $approvedVouchers,
            'filters' => $filters,
            'branchesForFilter' => $branchesForFilter,
            'showsBranchFilter' => $showsBranchFilter,
            'posTerminals' => $posTerminals,
            'showsMultipleTerminalBranches' => $showsMultipleTerminalBranches,
            'allPosTerminalsForFilter' => $allPosTerminalsForFilter,
            'showsMultipleTerminalBranchesAll' => $showsMultipleTerminalBranchesAll,
            'infiniteNextPageUrl' => $infiniteNextPageUrl,
            'totalEntries' => (float) ($totals?->total_entries ?? 0),
            'totalExits' => (float) ($totals?->total_exits ?? 0),
            'balance' => (float) (($totals?->total_entries ?? 0) - ($totals?->total_exits ?? 0)),
        ]);
    }

    /** @return Collection<int, PosTerminal> */
    private function posTerminalsForCashVoucherFilter(?int $branchId = null): Collection
    {
        $user = auth()->user();
        if ($user?->isPosUser() || ($user?->isCashier() && $user->posTerminals()->exists())) {
            $assigned = $this->posTerminalsForUser(null, true);
            if ($assigned->isNotEmpty()) {
                $terminals = $assigned->loadMissing(['branch:id,name', 'location:id,name'])
                    ->sortBy(fn (PosTerminal $t) => ($t->branch->name ?? '').' '.$t->name)
                    ->values();

                if ($branchId !== null) {
                    $terminals = $terminals->where('branch_id', $branchId)->values();
                }

                return $terminals;
            }
        }

        $query = PosTerminal::query()
            ->with(['branch:id,name', 'location:id,name'])
            ->orderBy('branch_id')
            ->orderBy('name');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $this->applyBranchFilter($query, 'branch_id');

        return $query->get();
    }

    public function approve(Request $request, CashVoucher $cashVoucher): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()?->hasApplicationAdminAccess(), 403, 'Action non autorisée.');
        $this->ensureUserCanAccessCashVoucher($cashVoucher);

        if ($cashVoucher->approved_at !== null) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Ce bon de caisse est déjà approuvé.',
                ], 422);
            }

            return redirect()
                ->route('cash-vouchers.index')
                ->with('warning', 'Ce bon de caisse est déjà approuvé.');
        }

        $cashVoucher->update([
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        $cashVoucher->refresh()->load('branch:id,name');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Bon de caisse approuvé.',
                'voucher' => [
                    'id' => $cashVoucher->id,
                    'type' => $cashVoucher->type,
                    'amount' => (float) $cashVoucher->amount,
                ],
                'row_html' => view('cash_vouchers.partials.row', [
                    'voucher' => $cashVoucher,
                ])->render(),
            ]);
        }

        return redirect()
            ->route('cash-vouchers.index')
            ->with('success', 'Bon de caisse approuvé.');
    }

    public function update(Request $request, CashVoucher $cashVoucher): RedirectResponse
    {
        abort_unless($request->user()?->hasApplicationAdminAccess(), 403, 'Action non autorisée.');
        $this->ensureUserCanAccessCashVoucher($cashVoucher);

        if ($cashVoucher->approved_at !== null) {
            return redirect()
                ->route('cash-vouchers.index')
                ->with('warning', 'Seuls les bons en attente peuvent être modifiés.');
        }

        if ($cashVoucher->accounting_transaction_id !== null) {
            return redirect()
                ->route('cash-vouchers.index')
                ->with('warning', 'Impossible de modifier un bon comptabilisé.');
        }

        $data = $request->validate([
            'voucher_no' => [
                'required',
                'string',
                'max:100',
                Rule::unique('cash_vouchers', 'voucher_no')
                    ->where(fn ($q) => $q->where('branch_id', $cashVoucher->branch_id))
                    ->ignore($cashVoucher->id),
            ],
            'edit_voucher_id' => ['nullable', 'integer'],
        ]);

        $cashVoucher->update([
            'voucher_no' => $data['voucher_no'],
        ]);

        return redirect()
            ->route('cash-vouchers.index')
            ->with('success', 'Numéro du bon mis à jour.');
    }

    public function store(Request $request): RedirectResponse
    {
        $branchesForFilter = $this->branchesForUser();
        $showsBranchFilter = $branchesForFilter->count() > 1;

        $data = $request->validate([
            'branch_id' => [
                Rule::requiredIf($showsBranchFilter),
                'nullable',
                'integer',
                'exists:branches,id',
            ],
            'voucher_no' => ['required', 'string', 'max:100'],
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:2000'],
            'type' => ['required', 'in:entry,exit'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $branchId = $this->resolveBranchIdForMutation($data['branch_id'] ?? null);

        $request->validate([
            'voucher_no' => [
                Rule::unique('cash_vouchers', 'voucher_no')->where(fn ($q) => $q->where('branch_id', $branchId)),
            ],
        ]);

        CashVoucher::query()->create([
            ...$data,
            'branch_id' => $branchId,
        ]);

        return redirect()
            ->route('cash-vouchers.index')
            ->with('success', 'Bon de caisse créé avec succès.');
    }

    public function createAccountingEntry(Request $request, CashVoucher $cashVoucher): View|RedirectResponse
    {
        abort_unless($request->user()?->canAccessAccounting(), 403, 'Action non autorisée.');
        $this->ensureUserCanAccessCashVoucher($cashVoucher);

        if ($cashVoucher->approved_at === null) {
            return redirect()
                ->route('cash-vouchers.index')
                ->with('warning', 'Ce bon de caisse doit être approuvé avant enregistrement comptable.');
        }

        if ($cashVoucher->accounting_transaction_id !== null) {
            return redirect()
                ->route('cash-vouchers.index')
                ->with('warning', 'Ce bon de caisse est déjà enregistré en comptabilité.');
        }

        $accounts = ChartOfAccount::query()
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get(['account_code', 'name']);

        return view('cash_vouchers.register_accounting', compact('cashVoucher', 'accounts'));
    }

    public function storeAccountingEntry(Request $request, CashVoucher $cashVoucher): RedirectResponse
    {
        abort_unless($request->user()?->canAccessAccounting(), 403, 'Action non autorisée.');
        $this->ensureUserCanAccessCashVoucher($cashVoucher);

        if ($cashVoucher->approved_at === null) {
            return redirect()
                ->route('cash-vouchers.index')
                ->with('warning', 'Ce bon de caisse doit être approuvé avant enregistrement comptable.');
        }

        if ($cashVoucher->accounting_transaction_id !== null) {
            return redirect()
                ->route('cash-vouchers.index')
                ->with('warning', 'Ce bon de caisse est déjà enregistré en comptabilité.');
        }

        $data = $request->validate([
            'account_code' => ['required', 'string', 'max:30'],
            'new_account_name' => ['nullable', 'string', 'max:150'],
            'new_account_type' => ['nullable', 'in:asset,liability,equity,revenue,expense'],
        ]);

        $accountCode = trim((string) $data['account_code']);
        $account = ChartOfAccount::query()->where('account_code', $accountCode)->first();

        if ($account === null) {
            $extra = $request->validate([
                'new_account_name' => ['required', 'string', 'max:150'],
                'new_account_type' => ['required', 'in:asset,liability,equity,revenue,expense'],
            ]);

            $account = ChartOfAccount::query()->create([
                'account_code' => $accountCode,
                'name' => $extra['new_account_name'],
                'account_type' => $extra['new_account_type'],
                'is_active' => true,
            ]);
        }

        DB::transaction(function () use ($request, $cashVoucher, $accountCode): void {
            $entryType = $cashVoucher->type === 'entry' ? 'debit' : 'credit';

            $transaction = AccountingTransaction::query()->create([
                'user_id' => $request->user()->id,
                'transaction_date' => optional($cashVoucher->date)->toDateString() ?? now()->toDateString(),
                'reference' => sprintf('Bon de caisse %s - %s', $cashVoucher->voucher_no, $cashVoucher->description),
                'amount' => number_format((float) $cashVoucher->amount, 2, '.', ''),
                'entry_type' => $entryType,
                'account_code' => $accountCode,
            ]);

            $cashVoucher->update([
                'accounting_transaction_id' => $transaction->id,
            ]);
        });

        return redirect()
            ->route('cash-vouchers.index')
            ->with('success', 'Bon de caisse enregistré en comptabilité.');
    }

    public function unaccount(Request $request, CashVoucher $cashVoucher): RedirectResponse
    {
        abort_unless($request->user()?->canAccessAccounting(), 403, 'Action non autorisée.');
        $this->ensureUserCanAccessCashVoucher($cashVoucher);

        if ($cashVoucher->accounting_transaction_id === null) {
            return redirect()
                ->route('cash-vouchers.index')
                ->with('warning', 'Ce bon de caisse n’est pas comptabilisé.');
        }

        DB::transaction(function () use ($cashVoucher): void {
            $transactionId = $cashVoucher->accounting_transaction_id;

            $cashVoucher->update([
                'accounting_transaction_id' => null,
                'approved_at' => null,
                'approved_by' => null,
            ]);

            AccountingTransaction::query()->whereKey($transactionId)->delete();
        });

        return redirect()
            ->route('cash-vouchers.index')
            ->with('success', 'Comptabilisation annulée. Le bon est de nouveau en attente.');
    }

    public function destroy(Request $request, CashVoucher $cashVoucher): RedirectResponse
    {
        abort_unless($request->user()?->hasApplicationAdminAccess(), 403, 'Action non autorisée.');
        $this->ensureUserCanAccessCashVoucher($cashVoucher);

        if ($cashVoucher->accounting_transaction_id !== null) {
            return redirect()
                ->route('cash-vouchers.index')
                ->with('warning', 'Impossible de supprimer un bon déjà comptabilisé.');
        }

        $cashVoucher->delete();

        return redirect()
            ->route('cash-vouchers.index')
            ->with('success', 'Bon de caisse supprimé.');
    }

    protected function ensureUserCanAccessCashVoucher(CashVoucher $cashVoucher): void
    {
        $cashVoucher->loadMissing('branch');
        if ($cashVoucher->branch !== null) {
            $this->ensureUserCanAccessBranchModel($cashVoucher->branch);
        }
    }
}
