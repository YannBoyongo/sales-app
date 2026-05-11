<?php

namespace App\Http\Controllers;

use App\Models\AccountingTransaction;
use App\Models\CashVoucher;
use App\Models\ChartOfAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CashVoucherController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'type' => ['nullable', 'in:entry,exit'],
        ]);

        $baseQuery = CashVoucher::query()
            ->when($filters['date_from'] ?? null, fn ($q, $value) => $q->whereDate('date', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($q, $value) => $q->whereDate('date', '<=', $value))
            ->when($filters['type'] ?? null, fn ($q, $value) => $q->where('type', $value));

        $totals = (clone $baseQuery)
            ->whereNotNull('approved_at')
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'entry' THEN amount ELSE 0 END), 0) as total_entries")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'exit' THEN amount ELSE 0 END), 0) as total_exits")
            ->first();

        $cashVouchers = $baseQuery
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('cash_vouchers.index', [
            'cashVouchers' => $cashVouchers,
            'filters' => $filters,
            'totalEntries' => (float) ($totals?->total_entries ?? 0),
            'totalExits' => (float) ($totals?->total_exits ?? 0),
            'balance' => (float) (($totals?->total_entries ?? 0) - ($totals?->total_exits ?? 0)),
        ]);
    }

    public function approve(Request $request, CashVoucher $cashVoucher): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Action non autorisée.');

        if ($cashVoucher->approved_at !== null) {
            return redirect()
                ->route('cash-vouchers.index')
                ->with('warning', 'Ce bon de caisse est déjà approuvé.');
        }

        $cashVoucher->update([
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('cash-vouchers.index')
            ->with('success', 'Bon de caisse approuvé.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'voucher_no' => ['required', 'string', 'max:100', 'unique:cash_vouchers,voucher_no'],
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:2000'],
            'type' => ['required', 'in:entry,exit'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        CashVoucher::query()->create($data);

        return redirect()
            ->route('cash-vouchers.index')
            ->with('success', 'Bon de caisse créé avec succès.');
    }

    public function createAccountingEntry(Request $request, CashVoucher $cashVoucher): View|RedirectResponse
    {
        abort_unless($request->user()?->canAccessAccounting(), 403, 'Action non autorisée.');

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
}
