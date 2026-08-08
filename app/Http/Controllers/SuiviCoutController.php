<?php

namespace App\Http\Controllers;

use App\Models\CostCenter;
use App\Models\CostTrackingEntry;
use App\Models\CostTransactionType;
use App\Services\CostTrackingBalanceService;
use Database\Seeders\CostTrackingDemoSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class SuiviCoutController extends Controller
{
    public function __construct(
        private readonly CostTrackingBalanceService $balanceService,
    ) {}

    public function index(Request $request): View
    {
        (new CostTrackingDemoSeeder)->run();

        $filters = $this->listFiltersFromRequest($request);

        $entries = $this->filteredEntriesQuery($filters)
            ->with(['costCenter', 'transactionType'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get();

        return view('suivi-cout.index', [
            'entries' => $entries,
            'costCenters' => CostCenter::query()->orderBy('name')->get(['id', 'name']),
            'transactionTypes' => CostTransactionType::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
                'direction' => $filters['direction'] ?? '',
            ],
        ]);
    }

    public function costCentersReport(Request $request): View
    {
        $costCenters = CostCenter::query()->orderBy('name')->get(['id', 'name']);
        $transactionTypes = CostTransactionType::query()->orderBy('name')->get(['id', 'name']);

        $filters = $request->validate([
            'cost_center_id' => ['nullable', 'exists:cost_centers,id'],
            'cost_transaction_type_id' => ['nullable', 'exists:cost_transaction_types,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'loaded' => ['nullable', 'boolean'],
        ]);

        $loaded = $request->boolean('loaded');

        if (! $request->hasAny(['cost_center_id', 'date_from', 'date_to', 'cost_transaction_type_id'])) {
            $filters['date_from'] = now()->startOfMonth()->toDateString();
            $filters['date_to'] = now()->toDateString();
            $loaded = false;
        }

        $selectedCenter = null;
        $entries = collect();
        $totals = [
            'entries' => 0,
            'entry' => '0.00',
            'exit' => '0.00',
            'net' => '0.00',
        ];

        if ($loaded && ! empty($filters['cost_center_id'])) {
            $query = CostTrackingEntry::query()
                ->where('cost_center_id', $filters['cost_center_id']);

            if (! empty($filters['cost_transaction_type_id'])) {
                $query->where('cost_transaction_type_id', $filters['cost_transaction_type_id']);
            }

            if (! empty($filters['date_from'])) {
                $query->whereDate('occurred_on', '>=', $filters['date_from']);
            }

            if (! empty($filters['date_to'])) {
                $query->whereDate('occurred_on', '<=', $filters['date_to']);
            }

            $entries = $query
                ->orderByDesc('occurred_on')
                ->orderByDesc('id')
                ->get();

            $entryTotal = '0.00';
            $exitTotal = '0.00';

            foreach ($entries as $entry) {
                if ($entry->isEntry()) {
                    $entryTotal = bcadd($entryTotal, (string) $entry->amount, 2);
                } else {
                    $exitTotal = bcadd($exitTotal, (string) $entry->amount, 2);
                }
            }

            $totals = [
                'entries' => $entries->count(),
                'entry' => $entryTotal,
                'exit' => $exitTotal,
                'net' => bcsub($entryTotal, $exitTotal, 2),
            ];

            $selectedCenter = $costCenters->firstWhere('id', (int) $filters['cost_center_id']);
        }

        return view('suivi-cout.centres-report', [
            'costCenters' => $costCenters,
            'transactionTypes' => $transactionTypes,
            'selectedCenter' => $selectedCenter,
            'entries' => $entries,
            'totals' => $totals,
            'loaded' => $loaded,
            'filters' => [
                'cost_center_id' => $filters['cost_center_id'] ?? '',
                'cost_transaction_type_id' => $filters['cost_transaction_type_id'] ?? '',
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
            ],
            'periodLabel' => $this->reportPeriodLabel(
                $filters['date_from'] ?? null,
                $filters['date_to'] ?? null,
            ),
        ]);
    }

    public function indexCostCenters(): View
    {
        $costCenters = CostCenter::query()
            ->withCount('entries')
            ->orderBy('name')
            ->get();

        return view('suivi-cout.centres.index', compact('costCenters'));
    }

    public function editCostCenter(CostCenter $costCenter): View
    {
        return view('suivi-cout.centres.edit', compact('costCenter'));
    }

    public function updateCostCenter(Request $request, CostCenter $costCenter): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:cost_centers,name,'.$costCenter->id],
        ]);

        $costCenter->update([
            'name' => trim($data['name']),
        ]);

        return redirect()
            ->route('suivi-cout.centres.index')
            ->with('success', 'Centre de coût mis à jour.');
    }

    public function destroyCostCenter(CostCenter $costCenter): RedirectResponse
    {
        if ($costCenter->entries()->exists()) {
            return redirect()
                ->route('suivi-cout.centres.index')
                ->withErrors([
                    'cost_center' => 'Impossible de supprimer : des écritures utilisent ce centre de coût.',
                ]);
        }

        $costCenter->delete();

        return redirect()
            ->route('suivi-cout.centres.index')
            ->with('success', 'Centre de coût supprimé.');
    }

    public function indexTransactionTypes(): View
    {
        $transactionTypes = CostTransactionType::query()
            ->withCount('entries')
            ->orderBy('name')
            ->get();

        return view('suivi-cout.types.index', compact('transactionTypes'));
    }

    public function editTransactionType(CostTransactionType $costTransactionType): View
    {
        return view('suivi-cout.types.edit', compact('costTransactionType'));
    }

    public function updateTransactionType(Request $request, CostTransactionType $costTransactionType): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:cost_transaction_types,name,'.$costTransactionType->id],
        ]);

        $costTransactionType->update([
            'name' => trim($data['name']),
        ]);

        return redirect()
            ->route('suivi-cout.types.index')
            ->with('success', 'Type de transaction mis à jour.');
    }

    public function destroyTransactionType(CostTransactionType $costTransactionType): RedirectResponse
    {
        if ($costTransactionType->entries()->exists()) {
            return redirect()
                ->route('suivi-cout.types.index')
                ->withErrors([
                    'transaction_type' => 'Impossible de supprimer : des écritures utilisent ce type de transaction.',
                ]);
        }

        $costTransactionType->delete();

        return redirect()
            ->route('suivi-cout.types.index')
            ->with('success', 'Type de transaction supprimé.');
    }

    public function storeEntry(Request $request): JsonResponse
    {
        $data = $this->entryDataFromRequest($request);

        CostTrackingEntry::query()->create([
            'occurred_on' => $data['occurred_on'],
            'direction' => $data['direction'],
            'cost_center_id' => $data['cost_center_id'],
            'cost_transaction_type_id' => $data['cost_transaction_type_id'],
            'amount' => number_format((float) $data['amount'], 2, '.', ''),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'balance_after' => '0.00',
        ]);

        $this->balanceService->recalculateAll();

        return $this->jsonListResponse(
            $request,
            'Écriture enregistrée avec succès',
        );
    }

    public function updateEntry(Request $request, CostTrackingEntry $entry): JsonResponse
    {
        $data = $this->entryDataFromRequest($request);

        $entry->update([
            'occurred_on' => $data['occurred_on'],
            'direction' => $data['direction'],
            'cost_center_id' => $data['cost_center_id'],
            'cost_transaction_type_id' => $data['cost_transaction_type_id'],
            'amount' => number_format((float) $data['amount'], 2, '.', ''),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
        ]);

        $this->balanceService->recalculateAll();

        return $this->jsonListResponse(
            $request,
            'Écriture modifiée avec succès',
        );
    }

    public function destroyEntry(Request $request, CostTrackingEntry $entry): JsonResponse
    {
        $entry->delete();

        $this->balanceService->recalculateAll();

        return $this->jsonListResponse(
            $request,
            'Écriture supprimée avec succès',
        );
    }

    public function storeCostCenter(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:cost_centers,name'],
        ]);

        $center = CostCenter::query()->create([
            'name' => trim($data['name']),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'id' => $center->id,
                'name' => $center->name,
            ]);
        }

        return redirect()
            ->route('suivi-cout.centres.index')
            ->with('success', 'Centre de coût créé.');
    }

    public function storeTransactionType(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:cost_transaction_types,name'],
        ]);

        $type = CostTransactionType::query()->create([
            'name' => trim($data['name']),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'id' => $type->id,
                'name' => $type->name,
            ]);
        }

        return redirect()
            ->route('suivi-cout.types.index')
            ->with('success', 'Type de transaction créé.');
    }

    private function reportPeriodLabel(?string $dateFrom, ?string $dateTo): string
    {
        $from = $dateFrom
            ? Carbon::parse($dateFrom)->translatedFormat('d/m/Y')
            : '—';
        $to = $dateTo
            ? Carbon::parse($dateTo)->translatedFormat('d/m/Y')
            : '—';

        return "{$from} -> {$to}";
    }

    private function jsonListResponse(Request $request, string $message): JsonResponse
    {
        $filters = $this->listFiltersFromRequest($request);

        $entries = $this->filteredEntriesQuery($filters)
            ->with(['costCenter', 'transactionType'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'message' => $message,
            'balance' => $this->balanceService->currentBalance(),
            'rows_html' => view('suivi-cout.partials.entries-body', compact('entries'))->render(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function entryDataFromRequest(Request $request): array
    {
        return $request->validate([
            'occurred_on' => ['required', 'date'],
            'direction' => ['required', 'in:entry,exit'],
            'cost_center_id' => ['required', 'exists:cost_centers,id'],
            'cost_transaction_type_id' => ['required', 'exists:cost_transaction_types,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function listFiltersFromRequest(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'direction' => ['nullable', 'in:entry,exit'],
            'filter_q' => ['nullable', 'string', 'max:255'],
            'filter_date_from' => ['nullable', 'date'],
            'filter_date_to' => ['nullable', 'date', 'after_or_equal:filter_date_from'],
            'filter_direction' => ['nullable', 'in:entry,exit'],
        ]);

        return [
            'q' => $validated['filter_q'] ?? $validated['q'] ?? null,
            'date_from' => $validated['filter_date_from'] ?? $validated['date_from'] ?? null,
            'date_to' => $validated['filter_date_to'] ?? $validated['date_to'] ?? null,
            'direction' => $validated['filter_direction'] ?? $validated['direction'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredEntriesQuery(array $filters): Builder
    {
        $query = CostTrackingEntry::query();

        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('description', 'like', $term)
                    ->orWhereHas('costCenter', fn (Builder $c) => $c->where('name', 'like', $term))
                    ->orWhereHas('transactionType', fn (Builder $t) => $t->where('name', 'like', $term));
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('occurred_on', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('occurred_on', '<=', $filters['date_to']);
        }

        if (! empty($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }

        return $query;
    }
}
