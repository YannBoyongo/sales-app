<?php

namespace App\Http\Controllers;

use App\Models\AdminActivityLog;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminActivityLogController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $filters = $this->validatedFilters($request);
        $logs = $this->filteredQuery($filters)->paginate(30)->withQueryString();

        if ($request->boolean('infinite')) {
            $nextPageUrl = null;
            if ($logs->hasMorePages()) {
                $nextPageUrl = $logs->nextPageUrl();
                $nextPageUrl .= (str_contains($nextPageUrl, '?') ? '&' : '?').'infinite=1';
            }

            return response()->json([
                'html' => view('admin_activity_logs.partials.index-rows', [
                    'logs' => $logs,
                ])->render(),
                'next_page_url' => $nextPageUrl,
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
                'total' => $logs->total(),
                'has_more' => $logs->hasMorePages(),
            ]);
        }

        $infiniteNextPageUrl = null;
        if ($logs->hasMorePages()) {
            $infiniteNextPageUrl = $logs->nextPageUrl();
            $infiniteNextPageUrl .= (str_contains($infiniteNextPageUrl, '?') ? '&' : '?').'infinite=1';
        }

        $adminUsers = User::query()
            ->whereHas('roles', fn ($q) => $q->where('slug', 'admin'))
            ->orderBy('name')
            ->get(['id', 'name']);

        $branches = Branch::query()->orderBy('name')->get(['id', 'name']);

        $actionOptions = AdminActivityLog::query()
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return view('admin_activity_logs.index', compact(
            'logs',
            'filters',
            'adminUsers',
            'branches',
            'actionOptions',
            'infiniteNextPageUrl',
        ));
    }

    public function destroyMany(Request $request): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'log_ids' => ['required', 'array', 'min:1', 'max:500'],
            'log_ids.*' => ['required', 'integer', 'distinct', 'exists:admin_activity_logs,id'],
        ]);

        $ids = collect($data['log_ids'])->map(fn ($id) => (int) $id)->values()->all();
        $deleted = AdminActivityLog::query()->whereIn('id', $ids)->delete();

        $message = $deleted.' entrée'.($deleted > 1 ? 's' : '').' supprimée'.($deleted > 1 ? 's' : '').'.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'deleted_count' => $deleted,
                'deleted_ids' => $ids,
            ]);
        }

        return redirect()
            ->route('pending-actions.index')
            ->with('success', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'action' => ['nullable', 'string', 'max:80'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters)
    {
        $query = AdminActivityLog::query()
            ->with(['user:id,name', 'branch:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($filters['date_from'] ?? null) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if ($filters['date_to'] ?? null) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
        if ($filters['user_id'] ?? null) {
            $query->where('user_id', (int) $filters['user_id']);
        }
        if ($filters['branch_id'] ?? null) {
            $query->where('branch_id', (int) $filters['branch_id']);
        }
        if ($filters['action'] ?? null) {
            $query->where('action', $filters['action']);
        }

        return $query;
    }
}
