<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\AccountingTransaction;
use App\Models\Branch;
use App\Models\CashVoucher;
use App\Models\Department;
use App\Models\PosShift;
use App\Models\PosTerminal;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
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
        abort_unless(auth()->user()?->canAccessCashDeskFinanceFeatures(), 403, 'Vous n’avez pas accès à cet historique.');

        $filters = request()->validate([
            'registration' => ['nullable', 'in:registered,unregistered,all'],
        ]);
        $user = auth()->user();
        $defaultRegistration = ($user?->isCashier() && ! $user->canAccessAccounting())
            ? 'all'
            : 'unregistered';
        $registrationFilter = (string) ($filters['registration'] ?? $defaultRegistration);

        $terminalIds = $this->posTerminalsForUser(null, true)
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
            ->whereNotNull('closed_at')
            ->when($terminalIds === [], fn ($q) => $q->whereRaw('1 = 0'))
            ->when($terminalIds !== [], fn ($q) => $q->whereIn('pos_terminal_id', $terminalIds))
            ->when($registrationFilter === 'registered', function (Builder $q) {
                $q->where(function (Builder $outer) {
                    $outer->whereExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('accounting_transactions')
                            ->whereRaw("accounting_transactions.reference LIKE CONCAT('%(shift #', pos_shifts.id, ')%')");
                    })->orWhereExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('cash_vouchers')
                            ->whereRaw("cash_vouchers.voucher_no LIKE CONCAT('CV-SHIFT-', pos_shifts.id, '-%')")
                            ->whereNotNull('cash_vouchers.accounting_transaction_id');
                    });
                });
            })
            ->when($registrationFilter === 'unregistered', function (Builder $q) {
                $q->where(function (Builder $outer) {
                    $outer->whereNotExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('accounting_transactions')
                            ->whereRaw("accounting_transactions.reference LIKE CONCAT('%(shift #', pos_shifts.id, ')%')");
                    })->whereNotExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('cash_vouchers')
                            ->whereRaw("cash_vouchers.voucher_no LIKE CONCAT('CV-SHIFT-', pos_shifts.id, '-%')")
                            ->whereNotNull('cash_vouchers.accounting_transaction_id');
                    });
                });
            })
            ->orderByDesc('closed_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $pageShiftIds = $shifts->pluck('id')->all();
        $cashByShiftId = [];
        if ($pageShiftIds !== []) {
            $pageSales = Sale::query()
                ->whereIn('pos_shift_id', $pageShiftIds)
                ->get([
                    'id',
                    'pos_shift_id',
                    'amount_paid',
                    'payment_status',
                    'payment_type',
                    'total_amount',
                    'subtotal_amount',
                    'discount_amount',
                    'sale_status',
                    'cash_at_shift_close',
                ]);
            foreach ($pageSales as $sale) {
                $shiftId = (int) $sale->pos_shift_id;
                $cashByShiftId[$shiftId] = bcadd(
                    $cashByShiftId[$shiftId] ?? '0.00',
                    $sale->cashForShiftTotals(),
                    2
                );
            }
        }

        $shifts->setCollection(
            $shifts->getCollection()->map(function (PosShift $shift) use ($cashByShiftId) {
                $legacyReference = $this->closedShiftAccountingReference($shift);
                $legacyCount = AccountingTransaction::query()
                    ->where(function ($q) use ($shift, $legacyReference) {
                        $q->where('reference', $legacyReference)
                            ->orWhere('reference', 'like', '%(shift #'.$shift->id.') | %');
                    })
                    ->count();
                $accountedShiftBons = CashVoucher::query()
                    ->where('voucher_no', 'like', 'CV-SHIFT-'.$shift->id.'-%')
                    ->whereNotNull('accounting_transaction_id')
                    ->count();

                $shift->setAttribute('accounting_registered_count', $legacyCount + $accountedShiftBons);
                $shift->setAttribute(
                    'shift_cash_collected_sum',
                    $cashByShiftId[(int) $shift->id] ?? '0.00'
                );

                return $shift;
            })
        );

        $closedShiftsDescription = ($user?->isCashier() && ! $user->canAccessAccounting())
            ? 'Toutes les sessions fermées de votre branche (bons de caisse, suivi d’encaissement). Filtrez par enregistrement comptable si besoin.'
            : 'Liste des sessions de caisse déjà clôturées pour les terminaux auxquels vous avez accès.';

        return view('pos_terminals.closed_shifts', compact('shifts', 'registrationFilter', 'closedShiftsDescription'));
    }

    public function showClosed(PosShift $shift): View
    {
        abort_unless(auth()->user()?->canAccessCashDeskFinanceFeatures(), 403, 'Vous n’avez pas accès à cet historique.');
        abort_if($shift->closed_at === null, 404);

        $allowedTerminalIds = $this->posTerminalsForUser(null, true)->pluck('id')->all();
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
        $pushedShiftDepartmentVoucherNos = CashVoucher::query()
            ->where('voucher_no', 'like', 'CV-SHIFT-'.$shift->id.'-%')
            ->pluck('voucher_no')
            ->map(fn ($n) => (string) $n)
            ->all();

        return view('pos_terminals.closed_shift_show', compact(
            'shift',
            'branch',
            'summaries',
            'grandTotal',
            'pushedShiftDepartmentVoucherNos',
        ));
    }

    public function pushClosedShiftToAccounting(Request $request, PosShift $shift): RedirectResponse
    {
        abort_unless($request->user()?->canPushClosedShiftCashEntry(), 403, 'Vous n’avez pas accès à cette action.');
        abort_if($shift->closed_at === null, 404);

        $allowedTerminalIds = $this->posTerminalsForUser(null, true)->pluck('id')->all();
        abort_unless(in_array((int) $shift->pos_terminal_id, $allowedTerminalIds, true), 403, 'Session non autorisée.');

        $data = $request->validate([
            'department_id' => ['nullable', 'integer'],
        ]);
        $departmentId = isset($data['department_id']) && $data['department_id'] !== ''
            ? (int) $data['department_id']
            : null;

        $shift->loadMissing([
            'posTerminal',
            'sales.items.product.department',
        ]);

        $summaries = $this->departmentSummariesForShift($shift->sales);
        $selected = collect($summaries)->first(function (array $row) use ($departmentId): bool {
            $rowDepartmentId = (int) ($row['department']?->id ?? 0);
            $selectedId = (int) ($departmentId ?? 0);

            return $rowDepartmentId === $selectedId;
        });

        if ($selected === null) {
            return redirect()
                ->route('pos-terminal.shifts.closed.show', $shift)
                ->with('warning', 'Département invalide pour cette session.');
        }

        $departmentLabel = (string) ($selected['label'] ?? 'Département inconnu');
        $voucherNo = sprintf('CV-SHIFT-%d-%s', $shift->id, (string) ($selected['department']?->id ?? 'ND'));

        $alreadyPushed = CashVoucher::query()->where('voucher_no', $voucherNo)->exists();

        if ($alreadyPushed) {
            return redirect()
                ->route('pos-terminal.shifts.closed.show', $shift)
                ->with('warning', 'Un bon de caisse existe déjà pour ce département et cette session.');
        }

        $total = (string) ($selected['total'] ?? '0.00');
        if (bccomp($total, '0.00', 2) <= 0) {
            return redirect()
                ->route('pos-terminal.shifts.closed.show', $shift)
                ->with('warning', 'Aucun montant à enregistrer pour ce département.');
        }

        CashVoucher::query()->firstOrCreate(
            ['voucher_no' => $voucherNo],
            [
                'date' => optional($shift->closed_at)->toDateString() ?? now()->toDateString(),
                'description' => sprintf(
                    'Entrée caisse issue de la clôture session #%d - %s (%s)',
                    $shift->id,
                    $departmentLabel,
                    optional($shift->posTerminal)->name ?? 'Terminal'
                ),
                'type' => 'entry',
                'amount' => $total,
            ]
        );

        return redirect()
            ->route('pos-terminal.shifts.closed.show', $shift)
            ->with('success', 'Bon de caisse créé. Approuvez-le puis enregistrez-le en comptabilité depuis Bons de caisse.');
    }

    public function destroyClosed(Request $request, PosShift $shift): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_if($shift->closed_at === null, 404);

        $allowedTerminalIds = $this->posTerminalsForUser(null, true)->pluck('id')->all();
        abort_unless(in_array((int) $shift->pos_terminal_id, $allowedTerminalIds, true), 403, 'Session non autorisée.');

        if ($shift->sales()->exists()) {
            return redirect()
                ->route('pos-terminal.shifts.closed', $request->only('registration'))
                ->with('warning', 'Impossible de supprimer : ce shift a des ventes liées.');
        }

        $shift->loadMissing('posTerminal');
        if ($this->closedShiftHasFinanceLinks($shift)) {
            return redirect()
                ->route('pos-terminal.shifts.closed', $request->only('registration'))
                ->with('warning', 'Impossible de supprimer : des écritures ou bons de caisse sont liés à ce shift.');
        }

        $shift->delete();

        return redirect()
            ->route('pos-terminal.shifts.closed', $request->only('registration'))
            ->with('success', 'Session sans vente supprimée.');
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

        DB::transaction(function () use ($shift, $user): void {
            $salesToClose = Sale::query()
                ->where('pos_shift_id', $shift->id)
                ->where('sale_status', '!=', Sale::STATUS_PENDING_DISCOUNT)
                ->lockForUpdate()
                ->get();

            foreach ($salesToClose as $sale) {
                $sale->update([
                    'cash_at_shift_close' => $sale->cashCollectedForShift(),
                ]);
            }

            $shift->update([
                'closed_at' => now(),
                'closed_by' => $user->id,
            ]);
        });

        return redirect()
            ->route('pos-terminal.workspace', [$branch, $posTerminal])
            ->with('success', 'Session de caisse fermée.');
    }

    /**
     * @param  Collection<int, Sale>  $sales
     * @return list<array{department: Department|null, label: string, sales_count: int, total: string, sales: Collection<int, Sale>}>
     *         {@see Sale::cashForShiftTotals()} pour total (encaissements figés après clôture).
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
            $groups[$key]['total'] = bcadd($groups[$key]['total'], $sale->cashForShiftTotals(), 2);
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

    private function closedShiftHasFinanceLinks(PosShift $shift): bool
    {
        $legacyReference = $this->closedShiftAccountingReference($shift);
        if (AccountingTransaction::query()
            ->where(function ($q) use ($shift, $legacyReference) {
                $q->where('reference', $legacyReference)
                    ->orWhere('reference', 'like', '%(shift #'.$shift->id.') | %');
            })
            ->exists()) {
            return true;
        }

        return CashVoucher::query()
            ->where('voucher_no', 'like', 'CV-SHIFT-'.$shift->id.'-%')
            ->exists();
    }
}
