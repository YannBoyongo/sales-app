<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\Branch;
use App\Models\Department;
use App\Models\AccountingTransaction;
use App\Models\PosShift;
use App\Models\PosTerminal;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PosShiftController extends Controller
{
    use RespectsUserBranch;

    public function closed(): View
    {
        abort_unless(auth()->user()?->canAccessAccounting(), 403, 'Vous n’avez pas accès à cet historique.');

        $terminalIds = $this->posTerminalsForUser()
            ->pluck('id')
            ->values()
            ->all();

        $shifts = PosShift::query()
            ->with([
                'posTerminal:id,name,branch_id,location_id',
                'posTerminal.branch:id,name',
                'posTerminal.location:id,name',
                'openedByUser:id,name',
                'closedByUser:id,name',
            ])
            ->withCount('sales')
            ->withSum('sales', 'total_amount')
            ->whereNotNull('closed_at')
            ->when($terminalIds === [], fn ($q) => $q->whereRaw('1 = 0'))
            ->when($terminalIds !== [], fn ($q) => $q->whereIn('pos_terminal_id', $terminalIds))
            ->orderByDesc('closed_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('pos_terminals.closed_shifts', compact('shifts'));
    }

    public function showClosed(PosShift $shift): View
    {
        abort_unless(auth()->user()?->canAccessAccounting(), 403, 'Vous n’avez pas accès à cet historique.');
        abort_if($shift->closed_at === null, 404);

        $allowedTerminalIds = $this->posTerminalsForUser()->pluck('id')->all();
        abort_unless(in_array((int) $shift->pos_terminal_id, $allowedTerminalIds, true), 403, 'Session non autorisée.');

        $shift->load([
            'posTerminal.branch',
            'posTerminal.location',
            'openedByUser',
            'closedByUser',
            'sales' => fn ($q) => $q->orderBy('sold_at')->orderBy('id'),
            'sales.items.product.department',
        ]);

        $branch = $shift->posTerminal->branch;
        $summaries = $this->departmentSummariesForShift($shift->sales);
        $grandTotal = collect($summaries)->reduce(
            fn (string $carry, array $row) => bcadd($carry, $row['total'], 2),
            '0.00'
        );
        $accountingReference = $this->closedShiftAccountingReference($shift);
        $alreadyPushedToAccounting = AccountingTransaction::query()
            ->where('reference', $accountingReference)
            ->exists();

        return view('pos_terminals.closed_shift_show', compact(
            'shift',
            'branch',
            'summaries',
            'grandTotal',
            'alreadyPushedToAccounting',
        ));
    }

    public function pushClosedShiftToAccounting(Request $request, PosShift $shift): RedirectResponse
    {
        abort_unless($request->user()?->canAccessAccounting(), 403, 'Vous n’avez pas accès à cette action.');
        abort_if($shift->closed_at === null, 404);

        $allowedTerminalIds = $this->posTerminalsForUser()->pluck('id')->all();
        abort_unless(in_array((int) $shift->pos_terminal_id, $allowedTerminalIds, true), 403, 'Session non autorisée.');

        $accountingReference = $this->closedShiftAccountingReference($shift);
        $alreadyPushed = AccountingTransaction::query()
            ->where('reference', $accountingReference)
            ->exists();

        if ($alreadyPushed) {
            return redirect()
                ->route('pos-terminal.shifts.closed.show', $shift)
                ->with('warning', 'Cette session a déjà été transférée en comptabilité.');
        }

        $total = (string) $shift->sales()->sum('total_amount');
        if (bccomp($total, '0.00', 2) <= 0) {
            return redirect()
                ->route('pos-terminal.shifts.closed.show', $shift)
                ->with('warning', 'Aucun montant à transférer en comptabilité pour cette session.');
        }

        AccountingTransaction::create([
            'user_id' => $request->user()->id,
            'transaction_date' => optional($shift->closed_at)->toDateString() ?? now()->toDateString(),
            'reference' => $accountingReference,
            'amount' => $total,
            'entry_type' => 'debit',
        ]);

        return redirect()
            ->route('pos-terminal.shifts.closed.show', $shift)
            ->with('success', 'Session transférée en comptabilité.');
    }

    public function open(Branch $branch, PosTerminal $posTerminal): RedirectResponse
    {
        $this->ensurePosTerminalForBranch($posTerminal, $branch);
        $this->ensureUserCanAccessPosTerminal($posTerminal);

        $user = request()->user();
        $alreadyOpen = false;

        DB::transaction(function () use ($posTerminal, $user, &$alreadyOpen) {
            $existing = PosShift::query()
                ->where('pos_terminal_id', $posTerminal->id)
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $alreadyOpen = true;

                return;
            }

            PosShift::create([
                'pos_terminal_id' => $posTerminal->id,
                'opened_by' => $user->id,
                'opened_at' => now(),
            ]);
        });

        if ($alreadyOpen) {
            return redirect()
                ->route('pos-terminal.workspace', [$branch, $posTerminal])
                ->with('warning', 'Une session est déjà ouverte sur ce terminal.');
        }

        return redirect()
            ->route('pos-terminal.workspace', [$branch, $posTerminal])
            ->with('success', 'Session de caisse ouverte.');
    }

    public function confirmClose(Branch $branch, PosTerminal $posTerminal): View|RedirectResponse
    {
        $this->ensurePosTerminalForBranch($posTerminal, $branch);
        $this->ensureUserCanAccessPosTerminal($posTerminal);

        $shift = $posTerminal->openShift();
        if ($shift === null) {
            return redirect()
                ->route('pos-terminal.workspace', [$branch, $posTerminal])
                ->with('warning', 'Aucune session ouverte sur ce terminal.');
        }

        $sales = Sale::query()
            ->where('pos_shift_id', $shift->id)
            ->with('items.product.department')
            ->orderBy('sold_at')
            ->orderBy('id')
            ->get();

        $pendingDiscountCount = $sales->filter(fn (Sale $s) => $s->isPendingDiscount())->count();
        $closableSales = $sales->reject(fn (Sale $s) => $s->isPendingDiscount())->values();
        $summaries = $this->departmentSummariesForShift($closableSales);
        $grandTotal = collect($summaries)->reduce(
            fn (string $carry, array $row) => bcadd($carry, $row['total'], 2),
            '0.00'
        );
        $closableSalesCount = $closableSales->count();

        return view('pos_terminals.shift_close_review', compact(
            'branch',
            'posTerminal',
            'shift',
            'summaries',
            'grandTotal',
            'pendingDiscountCount',
            'closableSalesCount',
        ));
    }

    public function close(Request $request, Branch $branch, PosTerminal $posTerminal): RedirectResponse
    {
        $this->ensurePosTerminalForBranch($posTerminal, $branch);
        $this->ensureUserCanAccessPosTerminal($posTerminal);

        $request->validate([
            'confirm_totals' => ['accepted'],
        ], [
            'confirm_totals.accepted' => 'Cochez la case pour confirmer les totaux par département avant de fermer la session.',
        ]);

        $user = $request->user();

        $shift = $posTerminal->openShift();
        if ($shift === null) {
            return redirect()
                ->route('pos-terminal.workspace', [$branch, $posTerminal])
                ->with('warning', 'Aucune session ouverte sur ce terminal.');
        }

        $hasPendingDiscount = Sale::query()
            ->where('pos_shift_id', $shift->id)
            ->where('sale_status', Sale::STATUS_PENDING_DISCOUNT)
            ->exists();

        if ($hasPendingDiscount) {
            return redirect()
                ->route('pos-terminal.shifts.close-review', [$branch, $posTerminal])
                ->withErrors([
                    'shift_close' => 'Impossible de fermer la session : au moins une vente a une remise en attente d’approbation.',
                ]);
        }

        $shift->update([
            'closed_at' => now(),
            'closed_by' => $user->id,
        ]);

        return redirect()
            ->route('pos-terminal.workspace', [$branch, $posTerminal])
            ->with('success', 'Session de caisse fermée.');
    }

    /**
     * @param  Collection<int, Sale>  $sales
     * @return list<array{department: Department|null, label: string, sales_count: int, total: string, sales: Collection<int, Sale>}>
     */
    private function departmentSummariesForShift(Collection $sales): array
    {
        /** @var array<int, array{department: Department|null, label: string, sales_count: int, total: string, sales: Collection<int, Sale>}> $groups */
        $groups = [];

        foreach ($sales as $sale) {
            $dept = $sale->items->first()?->product?->department;
            $key = $dept?->id ?? 0;

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'department' => $dept,
                    'label' => $dept?->name ?? 'Département inconnu',
                    'sales_count' => 0,
                    'total' => '0.00',
                    'sales' => collect(),
                ];
            }

            $groups[$key]['sales_count']++;
            $groups[$key]['total'] = bcadd($groups[$key]['total'], (string) $sale->total_amount, 2);
            $groups[$key]['sales']->push($sale);
        }

        $list = array_values($groups);
        usort($list, fn (array $a, array $b) => strcmp($a['label'], $b['label']));

        return $list;
    }

    private function closedShiftAccountingReference(PosShift $shift): string
    {
        $terminalName = trim((string) optional($shift->posTerminal)->name);
        $terminalLabel = $terminalName !== '' ? $terminalName : 'terminal';

        return mb_substr(sprintf(
            'Clôture caisse %s (shift #%d)',
            $terminalLabel,
            $shift->id
        ), 0, 255);
    }
}
