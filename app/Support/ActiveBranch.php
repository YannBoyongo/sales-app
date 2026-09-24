<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ActiveBranch
{
    public const SESSION_KEY = 'active_branch_id';

    /**
     * Branches the user may pick after login (or switch in the header).
     *
     * @return Collection<int, Branch>
     */
    public static function selectableBranches(?User $user = null): Collection
    {
        $user = $user ?? auth()->user();
        if (! $user) {
            return collect();
        }

        if ($user->canBypassBranchScope()) {
            return Branch::query()->orderBy('name')->get(['id', 'name']);
        }

        if ($user->isStockManager()) {
            $locIds = DB::table('location_stock_manager')
                ->where('user_id', $user->id)
                ->pluck('location_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
            if ($locIds === []) {
                return collect();
            }
            $branchIds = Location::query()->whereIn('id', $locIds)->pluck('branch_id')->unique()->filter()->values()->all();
            if ($branchIds === []) {
                return collect();
            }

            return Branch::query()->whereIn('id', $branchIds)->orderBy('name')->get(['id', 'name']);
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

        if ($user->branch_id) {
            $branch = Branch::query()->whereKey((int) $user->branch_id)->first(['id', 'name']);

            return $branch ? collect([$branch]) : collect();
        }

        return collect();
    }

    /** @return list<int> */
    public static function selectableBranchIds(?User $user = null): array
    {
        return self::selectableBranches($user)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public static function id(?User $user = null): ?int
    {
        $user = $user ?? auth()->user();
        if (! $user) {
            return null;
        }

        $allowed = self::selectableBranchIds($user);
        if ($allowed === []) {
            return null;
        }

        if (count($allowed) === 1) {
            $only = $allowed[0];
            if ((int) session(self::SESSION_KEY) !== $only) {
                session([self::SESSION_KEY => $only]);
            }

            return $only;
        }

        $sessionId = session(self::SESSION_KEY);
        if ($sessionId !== null) {
            $id = (int) $sessionId;
            if (in_array($id, $allowed, true)) {
                return $id;
            }
            session()->forget(self::SESSION_KEY);
        }

        return null;
    }

    public static function requiresSelection(?User $user = null): bool
    {
        $user = $user ?? auth()->user();
        if (! $user) {
            return false;
        }

        return self::selectableBranches($user)->count() > 1 && self::id($user) === null;
    }

    public static function set(int $branchId, ?User $user = null): void
    {
        $user = $user ?? auth()->user();
        abort_unless($user, 403);
        abort_unless(in_array($branchId, self::selectableBranchIds($user), true), 403);
        session([self::SESSION_KEY => $branchId]);
    }

    public static function setIfAllowed(int $branchId, ?User $user = null): void
    {
        if (in_array($branchId, self::selectableBranchIds($user), true)) {
            session([self::SESSION_KEY => $branchId]);
        }
    }

    public static function branch(?User $user = null): ?Branch
    {
        $id = self::id($user);

        return $id !== null ? Branch::query()->find($id) : null;
    }

    /**
     * Branch ids applied to list queries and mutations (active session branch).
     *
     * @return list<int>
     */
    public static function filterIds(?User $user = null): array
    {
        $activeId = self::id($user);
        if ($activeId !== null) {
            return [$activeId];
        }

        return [];
    }
}
