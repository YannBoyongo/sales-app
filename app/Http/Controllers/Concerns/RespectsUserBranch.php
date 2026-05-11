<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Location;
use App\Models\PosShift;
use App\Models\PosTerminal;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

trait RespectsUserBranch
{
    /**
     * @return null|array<int> null = pas de filtre (admin), [] = aucune branche autorisée, [id] = une branche
     */
    protected function branchFilterIds(): ?array
    {
        $user = auth()->user();
        if (! $user || $user->canBypassBranchScope()) {
            return null;
        }

        return $user->branch_id ? [$user->branch_id] : [];
    }

    protected function branchesForUser(): Collection
    {
        $ids = $this->branchFilterIds();
        $query = Branch::query()->orderBy('name');
        if ($ids !== null) {
            if ($ids === []) {
                return collect();
            }
            $query->whereIn('id', $ids);
        }

        return $query->get();
    }

    /**
     * Branches the user may choose on the stock matrix (/stocks).
     *
     * @return Collection<int, Branch>
     */
    protected function stockBranchesForMatrix(): Collection
    {
        $user = auth()->user();
        if (! $user) {
            return collect();
        }

        if ($user->canBypassBranchScope()) {
            return Branch::query()->orderBy('name')->get(['id', 'name']);
        }

        if ($user->isPosUser()) {
            $terminals = $user->posTerminals()->get(['branch_id']);
            if ($terminals->isEmpty()) {
                return collect();
            }

            $terminalBranchIds = $terminals->pluck('branch_id')->unique()->filter()->values()->all();
            $userBranchId = $user->branch_id ? (int) $user->branch_id : null;

            if ($userBranchId !== null && $terminals->every(fn ($t) => (int) $t->branch_id === $userBranchId)) {
                $branch = Branch::query()->whereKey($userBranchId)->first(['id', 'name']);

                return $branch ? collect([$branch]) : collect();
            }

            return Branch::query()->whereIn('id', $terminalBranchIds)->orderBy('name')->get(['id', 'name']);
        }

        return $this->branchesForUser();
    }

    /**
     * @param  Collection<int, Branch>  $stockBranches
     */
    protected function resolveStockMatrixBranch(?int $branchId, Collection $stockBranches): ?Branch
    {
        if ($stockBranches->isEmpty()) {
            return null;
        }

        if ($branchId !== null) {
            $match = $stockBranches->firstWhere('id', $branchId);
            if ($match !== null) {
                return $match;
            }
        }

        $user = auth()->user();
        if ($user?->isPosUser() && $user->branch_id) {
            $preferred = $stockBranches->firstWhere('id', (int) $user->branch_id);
            if ($preferred !== null) {
                return $preferred;
            }
        }

        return $stockBranches->first();
    }

    protected function locationsForUser(): Collection
    {
        $user = auth()->user();
        if ($user?->isPosUser()) {
            $locationIds = $user->posTerminals()
                ->whereNotNull('location_id')
                ->pluck('location_id')
                ->unique()
                ->values()
                ->all();

            if ($locationIds === []) {
                return collect();
            }

            return Location::query()
                ->with('branch')
                ->whereIn('id', $locationIds)
                ->orderBy('name')
                ->get();
        }

        $ids = $this->branchFilterIds();
        $query = Location::query()->with('branch')->orderBy('name');
        if ($ids !== null) {
            if ($ids === []) {
                return collect();
            }
            $query->whereIn('branch_id', $ids);
        }

        return $query->get();
    }

    protected function applyBranchFilter(Builder $query, string $column = 'branch_id'): void
    {
        $ids = $this->branchFilterIds();
        if ($ids === null) {
            return;
        }
        if ($ids === []) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->whereIn($column, $ids);
    }

    protected function applyStockBranchFilter(Builder $query): void
    {
        $user = auth()->user();
        if ($user?->isPosUser()) {
            $locIds = $user->posTerminals()
                ->whereNotNull('location_id')
                ->pluck('location_id')
                ->unique()
                ->values()
                ->all();
            if ($locIds === []) {
                $query->whereRaw('1 = 0');

                return;
            }
            $query->whereIn($query->getModel()->getTable().'.location_id', $locIds);

            return;
        }

        $ids = $this->branchFilterIds();
        if ($ids === null) {
            return;
        }
        if ($ids === []) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->whereHas('location', fn (Builder $q) => $q->whereIn('branch_id', $ids));
    }

    protected function applyStockMovementBranchFilter(Builder $query): void
    {
        $user = auth()->user();
        if ($user?->isPosUser()) {
            $locIds = $user->posTerminals()
                ->whereNotNull('location_id')
                ->pluck('location_id')
                ->unique()
                ->values()
                ->all();
            if ($locIds === []) {
                $query->whereRaw('1 = 0');

                return;
            }
            $query->where(function (Builder $q) use ($locIds) {
                $q->whereIn('from_location_id', $locIds)
                    ->orWhereIn('to_location_id', $locIds);
            });

            return;
        }

        $ids = $this->branchFilterIds();
        if ($ids === null) {
            return;
        }
        if ($ids === []) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->where(function (Builder $q) use ($ids) {
            $q->whereHas('fromLocation', fn (Builder $l) => $l->whereIn('branch_id', $ids))
                ->orWhereHas('toLocation', fn (Builder $l) => $l->whereIn('branch_id', $ids));
        });
    }

    protected function ensureUserCanAccessLocation(?Location $location): void
    {
        if ($location === null) {
            return;
        }
        $user = auth()->user();
        if ($user?->isPosUser()) {
            $allowed = $user->posTerminals()
                ->where('location_id', $location->id)
                ->exists();
            abort_unless($allowed, 403, 'Accès non autorisé pour cet emplacement.');

            return;
        }

        $ids = $this->branchFilterIds();
        if ($ids === null) {
            return;
        }
        if ($ids === [] || ! in_array((int) $location->branch_id, $ids, true)) {
            abort(403, 'Accès non autorisé pour cet emplacement.');
        }
    }

    protected function ensureUserCanAccessBranchModel(Branch $branch): void
    {
        $ids = $this->branchFilterIds();
        if ($ids === null) {
            return;
        }
        if ($ids === [] || ! in_array((int) $branch->id, $ids, true)) {
            abort(403, 'Accès non autorisé pour cette branche.');
        }
    }

    protected function locationIdsForUser(): array
    {
        return $this->locationsForUser()->pluck('id')->all();
    }

    /**
     * Limit products to those with stock or sales history in the user’s branch(es). Admins: no constraint.
     *
     * @param  Builder<Product>|Relation  $query  Eloquent builder or relation (e.g. HasMany in eager-load constraints).
     */
    protected function applyProductBranchScope(Builder|Relation $query): void
    {
        $builder = $query instanceof Relation ? $query->getQuery() : $query;

        $ids = $this->branchFilterIds();
        if ($ids === null) {
            return;
        }
        if ($ids === []) {
            $builder->whereRaw('1 = 0');

            return;
        }
        $builder->where(function (Builder $q) use ($ids) {
            $q->whereHas('stocks.location', fn (Builder $l) => $l->whereIn('branch_id', $ids))
                ->orWhereHas('saleItems', fn (Builder $si) => $si->whereIn('branch_id', $ids));
        });
    }

    /**
     * Limit products to those with stock or sales history on the given branch (point-of-sale context).
     *
     * @param  Builder<Product>|Relation  $query
     */
    protected function applyProductScopeForBranch(Builder|Relation $query, Branch $branch): void
    {
        $builder = $query instanceof Relation ? $query->getQuery() : $query;
        $branchId = (int) $branch->id;
        $builder->where(function (Builder $q) use ($branchId) {
            $q->whereHas('stocks.location', fn (Builder $l) => $l->where('branch_id', $branchId))
                ->orWhereHas('saleItems', fn (Builder $si) => $si->where('branch_id', $branchId));
        });
    }

    /**
     * Limit departments to those having at least one product tied to the user’s branch(es). Admins: no constraint.
     */
    protected function applyDepartmentBranchFilter(Builder $query): void
    {
        $ids = $this->branchFilterIds();
        if ($ids === null) {
            return;
        }
        if ($ids === []) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->whereHas('products', function (Builder $pq) use ($ids) {
            $pq->where(function (Builder $q) use ($ids) {
                $q->whereHas('stocks.location', fn (Builder $l) => $l->whereIn('branch_id', $ids))
                    ->orWhereHas('saleItems', fn (Builder $si) => $si->whereIn('branch_id', $ids));
            });
        });
    }

    protected function ensureProductAccessibleForBranchUser(Product $product): void
    {
        $ids = $this->branchFilterIds();
        if ($ids === null) {
            return;
        }
        $query = Product::query()->whereKey($product->getKey());
        $this->applyProductBranchScope($query);
        abort_unless($query->exists(), 403, 'Accès non autorisé pour ce produit.');
    }

    protected function ensureDepartmentAccessibleForBranchUser(Department $department): void
    {
        $ids = $this->branchFilterIds();
        if ($ids === null) {
            return;
        }
        $query = Department::query()->whereKey($department->getKey());
        $this->applyDepartmentBranchFilter($query);
        abort_unless($query->exists(), 403, 'Accès non autorisé pour ce département.');
    }

    /**
     * @param  Collection<int, PosTerminal>  $terminals
     * @return array<int, int> Terminal ids that currently have an open shift (closed_at null).
     */
    protected function openPosTerminalIds(Collection $terminals): array
    {
        if ($terminals->isEmpty()) {
            return [];
        }

        return PosShift::query()
            ->whereNull('closed_at')
            ->whereIn('pos_terminal_id', $terminals->pluck('id')->all())
            ->pluck('pos_terminal_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  bool  $forClosedShiftsListing  Caissier rattaché à une branche (sans terminal pivot) : inclure tous les terminaux de la branche pour l’historique des shifts fermés / bons de caisse.
     * @return Collection<int, PosTerminal>
     */
    protected function posTerminalsForUser(?Branch $branch = null, bool $forClosedShiftsListing = false): Collection
    {
        $user = auth()->user();
        if (! $user) {
            return collect();
        }
        $cashierMayListBranchClosedShifts = $forClosedShiftsListing
            && $user->isCashier()
            && $user->branch_id;
        $accountingMayListClosedShifts = $forClosedShiftsListing && $user->canAccessAccounting();
        if (! $user->canAccessPosSales() && ! $cashierMayListBranchClosedShifts && ! $accountingMayListClosedShifts) {
            return collect();
        }

        if ($user->canBypassBranchScope()) {
            $q = PosTerminal::query()->with('location', 'branch');
            if ($branch !== null) {
                $q->where('branch_id', $branch->id);
            }

            return $q->orderBy('branch_id')->orderBy('name')->get();
        }

        if ($user->isCashier()) {
            $branchIds = $user->branch_id
                ? [(int) $user->branch_id]
                : $user->posTerminals()
                    ->pluck('branch_id')
                    ->unique()
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();
            if ($branchIds === []) {
                return collect();
            }
            $q = PosTerminal::query()
                ->with('location', 'branch')
                ->whereIn('branch_id', $branchIds);
            if ($branch !== null) {
                $q->where('branch_id', $branch->id);
            }

            return $q->orderBy('branch_id')->orderBy('name')->get();
        }

        if ($user->isPosUser()) {
            $q = $user->posTerminals()->with('location', 'branch');
            if ($branch !== null) {
                $q->where('pos_terminals.branch_id', $branch->id);
            }

            return $q->orderBy('name')->get();
        }

        if ($user->branch_id) {
            $q = PosTerminal::query()
                ->with('location', 'branch')
                ->where('branch_id', $user->branch_id);
            if ($branch !== null) {
                $q->where('branch_id', $branch->id);
            }

            return $q->orderBy('name')->get();
        }

        return collect();
    }

    protected function ensureUserCanAccessPosTerminal(PosTerminal $terminal): void
    {
        $user = auth()->user();
        abort_unless($user && $user->canAccessPosSales(), 403, 'Accès caisse non autorisé.');

        if ($user->canBypassBranchScope()) {
            return;
        }

        if ($user->isPosUser() || $user->isCashier()) {
            abort_unless(
                $user->posTerminals()->whereKey($terminal->getKey())->exists(),
                403,
                'Vous n’êtes pas affecté à ce terminal.'
            );

            return;
        }

        if ($user->isManager() && (int) $user->branch_id === (int) $terminal->branch_id) {
            return;
        }

        abort(403, 'Accès non autorisé pour ce terminal.');
    }

    protected function ensurePosTerminalForBranch(PosTerminal $terminal, Branch $branch): void
    {
        abort_unless((int) $terminal->branch_id === (int) $branch->id, 404);
    }
}
