<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\AccountingTransaction;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\PosTerminal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountingController extends Controller
{
    use RespectsUserBranch;

    public function index(): View
    {
        $filters = request()->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'pos_terminal_id' => ['nullable', 'integer', 'exists:pos_terminals,id'],
        ]);

        $branchesForFilter = $this->branchesForUser();
        $showsBranchFilter = $branchesForFilter->count() > 1;

        if (($filters['branch_id'] ?? null) !== null) {
            abort_unless($branchesForFilter->contains('id', (int) $filters['branch_id']), 403);
        }

        $allPosTerminals = $this->posTerminalsForAccountingFilter(null);
        $showsTerminalFilter = $allPosTerminals->isNotEmpty();
        $showsMultipleTerminalBranches = $allPosTerminals->pluck('branch_id')->unique()->count() > 1;
        $allPosTerminalsForFilter = $allPosTerminals
            ->map(fn (PosTerminal $terminal) => [
                'id' => $terminal->id,
                'branch_id' => $terminal->branch_id,
                'name' => $terminal->name,
                'branch_name' => $terminal->branch?->name ?? '',
            ])
            ->values()
            ->all();

        if (($filters['pos_terminal_id'] ?? null) !== null) {
            abort_unless($allPosTerminals->contains('id', (int) $filters['pos_terminal_id']), 403);
        }

        $base = AccountingTransaction::query()
            ->leftJoin('branches', 'branches.id', '=', 'accounting_transactions.branch_id')
            ->leftJoin('pos_terminals', 'pos_terminals.id', '=', 'accounting_transactions.pos_terminal_id')
            ->when($filters['start_date'] ?? null, fn ($q, $date) => $q->where('accounting_transactions.transaction_date', '>=', $date))
            ->when($filters['end_date'] ?? null, fn ($q, $date) => $q->where('accounting_transactions.transaction_date', '<=', $date))
            ->when($filters['branch_id'] ?? null, fn ($q, $value) => $q->where('accounting_transactions.branch_id', (int) $value))
            ->when($filters['pos_terminal_id'] ?? null, fn ($q, $value) => $q->where('accounting_transactions.pos_terminal_id', (int) $value));

        $this->applyBranchFilter($base, 'accounting_transactions.branch_id');

        $totals = (clone $base)->selectRaw("
            COALESCE(SUM(CASE WHEN accounting_transactions.entry_type = 'debit' THEN accounting_transactions.amount ELSE 0 END), 0) as total_debit,
            COALESCE(SUM(CASE WHEN accounting_transactions.entry_type = 'credit' THEN accounting_transactions.amount ELSE 0 END), 0) as total_credit
        ")->first();

        $totalDebit = (string) ($totals->total_debit ?? '0');
        $totalCredit = (string) ($totals->total_credit ?? '0');
        $caisse = bcsub($totalDebit, $totalCredit, 2);

        $windowed = $base->clone()->selectRaw("
            accounting_transactions.id,
            accounting_transactions.transaction_date,
            accounting_transactions.branch_id,
            branches.name as branch_name,
            accounting_transactions.pos_terminal_id,
            pos_terminals.name as pos_terminal_name,
            accounting_transactions.account_code,
            accounting_transactions.reference,
            accounting_transactions.entry_type,
            accounting_transactions.amount,
            CASE WHEN accounting_transactions.entry_type = 'debit' THEN accounting_transactions.amount ELSE 0 END AS debit_amount,
            CASE WHEN accounting_transactions.entry_type = 'credit' THEN accounting_transactions.amount ELSE 0 END AS credit_amount,
            SUM(CASE WHEN accounting_transactions.entry_type = 'debit' THEN accounting_transactions.amount ELSE -accounting_transactions.amount END)
                OVER (ORDER BY accounting_transactions.transaction_date ASC, accounting_transactions.id ASC) AS running_balance
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

        return view('accounting.index', compact(
            'rows',
            'totalDebit',
            'totalCredit',
            'caisse',
            'filters',
            'accounts',
            'branchesForFilter',
            'showsBranchFilter',
            'allPosTerminals',
            'showsTerminalFilter',
            'showsMultipleTerminalBranches',
            'allPosTerminalsForFilter',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $branchesForFilter = $this->branchesForUser();
        $showsBranchFilter = $branchesForFilter->count() > 1;
        $posTerminals = $this->posTerminalsForAccountingFilter(null);
        $showsTerminalFilter = $posTerminals->isNotEmpty();

        $data = $request->validate([
            'transaction_date' => ['required', 'date'],
            'reference' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'entry_type' => ['required', 'in:debit,credit'],
            'account_code' => ['required', 'string', 'exists:chart_of_accounts,account_code'],
            'branch_id' => [
                Rule::requiredIf($showsBranchFilter),
                'nullable',
                'integer',
                'exists:branches,id',
            ],
            'pos_terminal_id' => ['nullable', 'integer', 'exists:pos_terminals,id'],
        ]);

        if (($data['pos_terminal_id'] ?? null) !== null) {
            abort_unless($posTerminals->contains('id', (int) $data['pos_terminal_id']), 403);
        }

        $branchId = $this->resolveBranchIdForMutation(
            isset($data['branch_id']) ? (int) $data['branch_id'] : null
        );

        $terminalId = isset($data['pos_terminal_id']) ? (int) $data['pos_terminal_id'] : null;
        if ($terminalId !== null) {
            $terminal = $posTerminals->firstWhere('id', $terminalId);
            if ($terminal !== null && (int) $terminal->branch_id !== $branchId) {
                return back()
                    ->withInput()
                    ->withErrors(['pos_terminal_id' => 'Le terminal sélectionné n’appartient pas à la branche choisie.']);
            }
        }

        AccountingTransaction::create([
            'user_id' => $request->user()->id,
            'branch_id' => $branchId,
            'pos_terminal_id' => $terminalId,
            'transaction_date' => $data['transaction_date'],
            'reference' => $data['reference'],
            'amount' => number_format((float) $data['amount'], 2, '.', ''),
            'entry_type' => $data['entry_type'],
            'account_code' => $data['account_code'],
        ]);

        return redirect()->route('accounting.index')->with('success', 'Ecriture comptable enregistrée.');
    }

    /** @return Collection<int, PosTerminal> */
    private function posTerminalsForAccountingFilter(?int $branchId = null): Collection
    {
        $branch = $branchId !== null ? Branch::query()->find($branchId) : null;

        return $this->posTerminalsForUser($branch, true);
    }
}
