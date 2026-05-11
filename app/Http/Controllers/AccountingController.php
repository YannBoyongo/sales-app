<?php

namespace App\Http\Controllers;

use App\Models\AccountingTransaction;
use App\Models\ChartOfAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AccountingController extends Controller
{
    public function index(): View
    {
        $filters = request()->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $base = AccountingTransaction::query()
            ->when($filters['start_date'] ?? null, fn ($q, $date) => $q->where('transaction_date', '>=', $date))
            ->when($filters['end_date'] ?? null, fn ($q, $date) => $q->where('transaction_date', '<=', $date));

        $totals = (clone $base)->selectRaw("
            COALESCE(SUM(CASE WHEN entry_type = 'debit' THEN amount ELSE 0 END), 0) as total_debit,
            COALESCE(SUM(CASE WHEN entry_type = 'credit' THEN amount ELSE 0 END), 0) as total_credit
        ")->first();

        $totalDebit = (string) ($totals->total_debit ?? '0');
        $totalCredit = (string) ($totals->total_credit ?? '0');
        $caisse = bcsub($totalDebit, $totalCredit, 2);

        $windowed = $base->clone()->selectRaw("
            id,
            transaction_date,
            account_code,
            reference,
            entry_type,
            amount,
            CASE WHEN entry_type = 'debit' THEN amount ELSE 0 END AS debit_amount,
            CASE WHEN entry_type = 'credit' THEN amount ELSE 0 END AS credit_amount,
            SUM(CASE WHEN entry_type = 'debit' THEN amount ELSE -amount END)
                OVER (ORDER BY transaction_date ASC, id ASC) AS running_balance
        ");

        $rows = DB::query()
            ->fromSub($windowed, 'tx')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->simplePaginate(50)
            ->withQueryString();

        $accounts = ChartOfAccount::query()
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get(['account_code', 'name']);

        return view('accounting.index', compact('rows', 'totalDebit', 'totalCredit', 'caisse', 'filters', 'accounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'transaction_date' => ['required', 'date'],
            'reference' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'entry_type' => ['required', 'in:debit,credit'],
            'account_code' => ['required', 'string', 'exists:chart_of_accounts,account_code'],
        ]);

        AccountingTransaction::create([
            'user_id' => $request->user()->id,
            'transaction_date' => $data['transaction_date'],
            'reference' => $data['reference'],
            'amount' => number_format((float) $data['amount'], 2, '.', ''),
            'entry_type' => $data['entry_type'],
            'account_code' => $data['account_code'],
        ]);

        return redirect()->route('accounting.index')->with('success', 'Ecriture comptable enregistrée.');
    }
}
