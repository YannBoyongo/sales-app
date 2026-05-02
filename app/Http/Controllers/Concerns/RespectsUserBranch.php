<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Location;
use App\Models\Product;
use App\Models\SalesSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

trait RespectsUserBranch
{
    /**
     * @return null|array<int> null = pas de filtre (admin), [] = aucune branche autorisée, [id] = une branche
     */
    protected function branchFilterIds(): ?array
    {
        $user = auth()->user();
        if (! $user || $user->is_admin) {
            return null;
        }

        return $user->branch_id ? [$user->branch_id] : [];
    }

    protected function branchesForUser(): \Illuminate\Support\Collection
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

    protected function locationsForUser(): \Illuminate\Support\Collection
    {
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

    protected function ensureUserCanAccessSalesSession(SalesSession $session): void
    {
        $ids = $this->branchFilterIds();
        if ($ids === null) {
            return;
        }
        if ($ids === [] || ! in_array((int) $session->branch_id, $ids, true)) {
            abort(403, 'Accès non autorisé pour cette session.');
        }
    }

    protected function ensureUserCanAccessLocation(?Location $location): void
    {
        if ($location === null) {
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
     * @param  Builder<\App\Models\Product>|Relation  $query  Eloquent builder or relation (e.g. HasMany in eager-load constraints).
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
                ->orWhereHas('saleItems.salesSession', fn (Builder $ss) => $ss->whereIn('branch_id', $ids));
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
                    ->orWhereHas('saleItems.salesSession', fn (Builder $ss) => $ss->whereIn('branch_id', $ids));
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
}
