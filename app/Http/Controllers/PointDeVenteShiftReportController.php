<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\DeletesSales;
use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\CashVoucher;
use App\Models\PosShift;
use App\Models\PosTerminal;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class PointDeVenteShiftReportController extends Controller
{
    use DeletesSales;
    use RespectsUserBranch;

    public function index(Request $request): View|RedirectResponse
    {
        abort_unless(auth()->user()?->canAccessPosSales(), 403, 'Vous n’avez pas accès au point de vente.');

        $terminals = $this->posTerminalsForUser(null, true, PosTerminal::KIND_FIELD);

        if ($terminals->count() === 1) {
            $terminal = $terminals->first()->load('branch');
            $shift = $this->resolveReportShift($request, $terminal);

            if ($shift === null) {
                return view('point_de_vente.shift_reports.show', [
                    'shift' => null,
                    'terminal' => $terminal,
                    'summaries' => [],
                    'grandTotalSold' => '0.00',
                    'grandTotalCollected' => '0.00',
                    'grandSalesCount' => 0,
                    'directReport' => true,
                    'recentShifts' => collect(),
                ]);
            }

            return $this->renderShow($shift, directReport: true, recentShifts: $this->recentShiftsForTerminal($terminal));
        }

        $terminalIds = $terminals->pluck('id')->all();

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'pos_terminal_id' => ['nullable', 'integer'],
        ]);

        $shifts = PosShift::query()
            ->with(['posTerminal.branch', 'openedByUser:id,name', 'closedByUser:id,name'])
            ->withCount('sales')
            ->whereIn('pos_terminal_id', $terminalIds !== [] ? $terminalIds : [0])
            ->when($filters['date_from'] ?? null, function ($q, $dateFrom) {
                $q->whereRaw('COALESCE(session_date, DATE(opened_at)) >= ?', [$dateFrom]);
            })
            ->when($filters['date_to'] ?? null, function ($q, $dateTo) {
                $q->whereRaw('COALESCE(session_date, DATE(opened_at)) <= ?', [$dateTo]);
            })
            ->when($filters['pos_terminal_id'] ?? null, function ($q, $terminalId) use ($terminalIds) {
                if (in_array((int) $terminalId, $terminalIds, true)) {
                    $q->where('pos_terminal_id', (int) $terminalId);
                }
            })
            ->orderByDesc(DB::raw('COALESCE(session_date, DATE(opened_at))'))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $pageShiftIds = $shifts->pluck('id')->all();
        $totalsByShiftId = $this->shiftSalesTotalsByShiftId($pageShiftIds);

        $shifts->setCollection(
            $shifts->getCollection()->map(function (PosShift $shift) use ($totalsByShiftId) {
                $totals = $totalsByShiftId[(int) $shift->id] ?? ['total_sold' => '0.00', 'total_collected' => '0.00'];
                $shift->setAttribute('total_sold', $totals['total_sold']);
                $shift->setAttribute('total_collected', $totals['total_collected']);

                return $shift;
            })
        );

        return view('point_de_vente.shift_reports.index', [
            'shifts' => $shifts,
            'filters' => $filters,
            'terminals' => $terminals,
            'canDeleteShifts' => auth()->user()?->isAdmin() ?? false,
        ]);
    }

    public function destroy(Request $request, PosShift $shift): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $shift->load('posTerminal');
        abort_unless($shift->posTerminal?->isFieldPointOfSale(), 404);

        $allowedTerminalIds = $this->posTerminalsForUser(null, true, PosTerminal::KIND_FIELD)->pluck('id')->all();
        abort_unless(in_array((int) $shift->pos_terminal_id, $allowedTerminalIds, true), 403, 'Session non autorisée.');

        if ($this->shiftHasFinanceLinks($shift)) {
            return $this->redirectAfterShiftDelete($request)
                ->with('warning', 'Impossible de supprimer : des bons de caisse ou écritures comptables sont liés à cette session.');
        }

        $salesCount = $shift->sales()->count();

        try {
            DB::transaction(function () use ($shift) {
                $sales = Sale::query()->where('pos_shift_id', $shift->id)->get();
                foreach ($sales as $sale) {
                    $this->deleteSaleWithStockRestore($sale);
                }
                $shift->delete();
            });
        } catch (RuntimeException $e) {
            return $this->redirectAfterShiftDelete($request)
                ->withErrors(['shift' => $e->getMessage()]);
        }

        $message = $salesCount > 0
            ? 'Session supprimée avec ses ventes. Le stock a été réintégré.'
            : 'Session supprimée.';

        return $this->redirectAfterShiftDelete($request)
            ->with('success', $message);
    }

    private function redirectAfterShiftDelete(Request $request): RedirectResponse
    {
        $terminals = $this->posTerminalsForUser(null, true, PosTerminal::KIND_FIELD);
        if ($terminals->count() === 1) {
            return redirect()->route('point-de-vente.shifts.report');
        }

        return redirect()->route('point-de-vente.shifts.report', $request->only([
            'date_from',
            'date_to',
            'pos_terminal_id',
        ]));
    }

    private function shiftHasFinanceLinks(PosShift $shift): bool
    {
        return CashVoucher::query()
            ->where(function (Builder $q) use ($shift): void {
                $q->where('voucher_no', 'like', 'CV-SHIFT-'.$shift->id.'-%')
                    ->orWhere('pos_shift_id', $shift->id);
            })
            ->exists();
    }

    public function show(Request $request, PosShift $shift): View|RedirectResponse
    {
        abort_unless(auth()->user()?->canAccessPosSales(), 403, 'Vous n’avez pas accès au point de vente.');

        $terminals = $this->posTerminalsForUser(null, true, PosTerminal::KIND_FIELD);

        if ($terminals->count() === 1) {
            return redirect()->route('point-de-vente.shifts.report', ['shift' => $shift->id]);
        }

        abort_unless($shift->posTerminal?->isFieldPointOfSale(), 404);

        $allowedTerminalIds = $terminals->pluck('id')->all();
        abort_unless(in_array((int) $shift->pos_terminal_id, $allowedTerminalIds, true), 403, 'Session non autorisée.');

        return $this->renderShow($shift, directReport: false, recentShifts: collect());
    }

    private function renderShow(PosShift $shift, bool $directReport, Collection $recentShifts): View
    {
        $shift->load([
            'posTerminal.branch',
            'openedByUser:id,name',
            'closedByUser:id,name',
        ]);

        abort_unless($shift->posTerminal?->isFieldPointOfSale(), 404);

        $allowedTerminalIds = $this->posTerminalsForUser(null, true, PosTerminal::KIND_FIELD)->pluck('id')->all();
        abort_unless(in_array((int) $shift->pos_terminal_id, $allowedTerminalIds, true), 403, 'Session non autorisée.');

        $summaries = $this->branchLocationSummariesForShift($shift);
        $grandTotalSold = collect($summaries)->reduce(
            fn (string $carry, array $row) => bcadd($carry, $row['total_sold'], 2),
            '0.00'
        );
        $grandTotalCollected = collect($summaries)->reduce(
            fn (string $carry, array $row) => bcadd($carry, $row['total_collected'], 2),
            '0.00'
        );
        $grandSalesCount = collect($summaries)->sum('sales_count');
        $canDeleteShifts = (bool) auth()->user()?->isAdmin();

        return view('point_de_vente.shift_reports.show', compact(
            'shift',
            'summaries',
            'grandTotalSold',
            'grandTotalCollected',
            'grandSalesCount',
            'directReport',
            'recentShifts',
            'canDeleteShifts',
        ));
    }

    private function resolveReportShift(Request $request, PosTerminal $terminal): ?PosShift
    {
        $shiftId = $request->integer('shift');
        if ($shiftId > 0) {
            return PosShift::query()
                ->where('pos_terminal_id', $terminal->id)
                ->whereKey($shiftId)
                ->first();
        }

        $openShift = $terminal->openShift();
        if ($openShift !== null) {
            return $openShift;
        }

        return PosShift::query()
            ->where('pos_terminal_id', $terminal->id)
            ->orderByDesc(DB::raw('COALESCE(session_date, DATE(opened_at))'))
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return Collection<int, PosShift>
     */
    private function recentShiftsForTerminal(PosTerminal $terminal): Collection
    {
        return PosShift::query()
            ->where('pos_terminal_id', $terminal->id)
            ->orderByDesc(DB::raw('COALESCE(session_date, DATE(opened_at))'))
            ->orderByDesc('id')
            ->limit(30)
            ->get(['id', 'pos_terminal_id', 'session_date', 'opened_at', 'closed_at']);
    }

    /**
     * @param  array<int, int>  $shiftIds
     * @return array<int, array{total_sold: string, total_collected: string}>
     */
    private function shiftSalesTotalsByShiftId(array $shiftIds): array
    {
        if ($shiftIds === []) {
            return [];
        }

        $totals = [];
        $sales = Sale::query()
            ->whereIn('pos_shift_id', $shiftIds)
            ->get([
                'id',
                'pos_shift_id',
                'total_amount',
                'amount_paid',
                'payment_status',
                'payment_type',
                'subtotal_amount',
                'discount_amount',
                'sale_status',
                'cash_at_shift_close',
            ]);

        foreach ($sales as $sale) {
            $shiftId = (int) $sale->pos_shift_id;
            if (! isset($totals[$shiftId])) {
                $totals[$shiftId] = ['total_sold' => '0.00', 'total_collected' => '0.00'];
            }
            $totals[$shiftId]['total_sold'] = bcadd(
                $totals[$shiftId]['total_sold'],
                (string) ($sale->total_amount ?? '0'),
                2
            );
            $totals[$shiftId]['total_collected'] = bcadd(
                $totals[$shiftId]['total_collected'],
                $sale->cashForShiftTotals(),
                2
            );
        }

        return $totals;
    }

    /**
     * @return list<array{branch: \App\Models\Branch|null, location: \App\Models\Location|null, sales_count: int, total_sold: string, total_collected: string, sales: Collection<int, Sale>}>
     */
    private function branchLocationSummariesForShift(PosShift $shift): array
    {
        $sales = Sale::query()
            ->where('pos_shift_id', $shift->id)
            ->with(['branch:id,name', 'saleLocation:id,name'])
            ->orderBy('sold_at')
            ->orderBy('id')
            ->get();

        /** @var array<string, array{branch: \App\Models\Branch|null, location: \App\Models\Location|null, sales_count: int, total_sold: string, total_collected: string, sales: Collection<int, Sale>}> $groups */
        $groups = [];

        foreach ($sales as $sale) {
            $key = (int) $sale->branch_id.'-'.(int) ($sale->sale_location_id ?? 0);

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'branch' => $sale->branch,
                    'location' => $sale->saleLocation,
                    'sales_count' => 0,
                    'total_sold' => '0.00',
                    'total_collected' => '0.00',
                    'sales' => collect(),
                ];
            }

            $groups[$key]['sales_count']++;
            $groups[$key]['total_sold'] = bcadd(
                $groups[$key]['total_sold'],
                (string) ($sale->total_amount ?? '0'),
                2
            );
            $groups[$key]['total_collected'] = bcadd(
                $groups[$key]['total_collected'],
                $sale->cashForShiftTotals(),
                2
            );
            $groups[$key]['sales']->push($sale);
        }

        $list = array_values($groups);
        usort($list, function (array $a, array $b): int {
            $branchCompare = strcmp($a['branch']?->name ?? '', $b['branch']?->name ?? '');
            if ($branchCompare !== 0) {
                return $branchCompare;
            }

            return strcmp($a['location']?->name ?? '', $b['location']?->name ?? '');
        });

        return $list;
    }
}
