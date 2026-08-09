<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\Branch;
use App\Models\PosTerminal;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SalesOverviewController extends Controller
{
    use RespectsUserBranch;

    public function __invoke(Request $request): View|JsonResponse
    {
        $user = $request->user();
        $canApproveDiscounts = (bool) ($user?->canApproveSaleDiscounts());

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'pos_terminal_id' => ['nullable', 'integer', 'exists:pos_terminals,id'],
            'payment_type' => ['nullable', 'in:cash,credit,caution'],
        ]);

        $branchesForFilter = $this->branchesForUser();
        $showsMultipleBranches = $branchesForFilter->count() > 1;

        if (($filters['branch_id'] ?? null) !== null) {
            abort_unless($branchesForFilter->contains('id', (int) $filters['branch_id']), 403);
        }

        $posTerminals = $this->posTerminalsForSalesFilter($filters['branch_id'] ?? null);
        $showsMultipleTerminalBranches = $posTerminals->pluck('branch_id')->unique()->count() > 1;

        $salesQuery = $this->salesOverviewQuery($request, $filters, $user, $posTerminals);
        $summaryTotals = $this->summarizeSalesOverview($salesQuery);
        $branchTotals = $this->summarizeSalesByBranch($salesQuery, $branchesForFilter, $filters);

        $sales = (clone $salesQuery)
            ->with([
                'branch:id,name',
                'user:id,name',
                'client:id,name',
                'items.product:id,name',
                'posShift:id,pos_terminal_id,session_date,opened_at',
                'posShift.posTerminal:id,name,branch_id',
                'posShift.posTerminal.branch:id,name',
            ]);

        if ($canApproveDiscounts) {
            $sales->orderByRaw('CASE WHEN sale_status = ? THEN 0 ELSE 1 END', [Sale::STATUS_PENDING_DISCOUNT]);
        }

        $sales = $sales
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        if ($request->boolean('infinite')) {
            $nextPageUrl = null;
            if ($sales->hasMorePages()) {
                $nextPageUrl = $sales->nextPageUrl();
                $nextPageUrl .= (str_contains($nextPageUrl, '?') ? '&' : '?').'infinite=1';
            }

            return response()->json([
                'html' => view('sales.partials.overview-rows', [
                    'sales' => $sales,
                    'filters' => $filters,
                    'showsMultipleBranches' => $showsMultipleTerminalBranches,
                ])->render(),
                'next_page_url' => $nextPageUrl,
                'from' => $sales->firstItem(),
                'to' => $sales->lastItem(),
                'total' => $sales->total(),
                'has_more' => $sales->hasMorePages(),
            ]);
        }

        $pendingDiscountQuery = Sale::query()->where('sale_status', Sale::STATUS_PENDING_DISCOUNT);
        $this->applyBranchFilter($pendingDiscountQuery, 'branch_id');
        $pendingDiscountCount = $pendingDiscountQuery->count();

        $infiniteNextPageUrl = null;
        if ($sales->hasMorePages()) {
            $infiniteNextPageUrl = $sales->nextPageUrl();
            $infiniteNextPageUrl .= (str_contains($infiniteNextPageUrl, '?') ? '&' : '?').'infinite=1';
        }

        return view('sales.overview', compact(
            'sales',
            'filters',
            'posTerminals',
            'branchesForFilter',
            'showsMultipleBranches',
            'showsMultipleTerminalBranches',
            'canApproveDiscounts',
            'pendingDiscountCount',
            'infiniteNextPageUrl',
            'summaryTotals',
            'branchTotals',
        ));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  Collection<int, PosTerminal>  $posTerminals
     */
    private function salesOverviewQuery(
        Request $request,
        array $filters,
        ?\App\Models\User $user,
        Collection $posTerminals,
    ): Builder {
        $query = Sale::query();

        $this->applyBranchFilter($query, 'branch_id');

        if (! $user?->hasApplicationAdminAccess()) {
            $query->where('user_id', $user->id);
        }

        if ($request->boolean('remise')) {
            $query->where('sale_status', Sale::STATUS_PENDING_DISCOUNT);
        }

        return $query
            ->when($filters['date_from'] ?? null, fn ($q, $value) => $q->whereDate('sold_at', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($q, $value) => $q->whereDate('sold_at', '<=', $value))
            ->when($filters['branch_id'] ?? null, fn ($q, $value) => $q->where('branch_id', (int) $value))
            ->when($filters['pos_terminal_id'] ?? null, function ($q, $value) use ($posTerminals) {
                abort_unless($posTerminals->contains('id', (int) $value), 403);
                $q->whereHas('posShift', fn ($shift) => $shift->where('pos_terminal_id', (int) $value));
            })
            ->when($filters['payment_type'] ?? null, fn ($q, $value) => $q->where('payment_type', $value));
    }

    /**
     * @return array{expected: string, paid: string, remaining: string, count: int}
     */
    private function summarizeSalesOverview(Builder $query): array
    {
        $expected = '0.00';
        $paid = '0.00';
        $remaining = '0.00';
        $count = 0;

        (clone $query)
            ->select([
                'id',
                'subtotal_amount',
                'total_amount',
                'discount_amount',
                'amount_paid',
                'payment_type',
                'payment_status',
            ])
            ->orderBy('id')
            ->chunkById(200, function ($sales) use (&$expected, &$paid, &$remaining, &$count) {
                foreach ($sales as $sale) {
                    $count++;
                    $expected = bcadd($expected, $sale->expectedPayableAmount(), 2);
                    $paid = bcadd($paid, $sale->paidAmountValue(), 2);
                    $remaining = bcadd($remaining, $sale->remainingAmountValue(), 2);
                }
            });

        return [
            'expected' => $expected,
            'paid' => $paid,
            'remaining' => $remaining,
            'count' => $count,
        ];
    }

    /**
     * @param  Collection<int, Branch>  $branchesForFilter
     * @param  array<string, mixed>  $filters
     * @return list<array{branch_id: int, branch_name: string, expected: string, paid: string, remaining: string, count: int}>
     */
    private function summarizeSalesByBranch(Builder $query, Collection $branchesForFilter, array $filters): array
    {
        if ($branchesForFilter->count() <= 1 || ($filters['branch_id'] ?? null) !== null) {
            return [];
        }

        $branchIds = (clone $query)
            ->select('branch_id')
            ->distinct()
            ->pluck('branch_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($branchIds === []) {
            return [];
        }

        $branchNames = Branch::query()
            ->whereIn('id', $branchIds)
            ->pluck('name', 'id');

        $rows = [];

        foreach ($branchIds as $branchId) {
            $branchQuery = (clone $query)->where('branch_id', $branchId);
            $summary = $this->summarizeSalesOverview($branchQuery);

            if ($summary['count'] === 0) {
                continue;
            }

            $rows[] = [
                'branch_id' => $branchId,
                'branch_name' => (string) ($branchNames[$branchId] ?? 'Branche #'.$branchId),
                ...$summary,
            ];
        }

        usort($rows, fn (array $a, array $b) => strcmp($a['branch_name'], $b['branch_name']));

        return $rows;
    }

    /** @return Collection<int, PosTerminal> */
    private function posTerminalsForSalesFilter(?int $branchId = null): Collection
    {
        $user = auth()->user();
        if ($user?->isPosUser() || ($user?->isCashier() && $user->posTerminals()->exists())) {
            $assigned = $this->posTerminalsForUser();
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
}
