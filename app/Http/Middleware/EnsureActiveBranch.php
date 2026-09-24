<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Support\ActiveBranch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveBranch
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        if ($request->routeIs(
            'active-branch.*',
            'logout',
            'profile.*',
            'verification.*',
        )) {
            return $next($request);
        }

        if (ActiveBranch::requiresSelection()) {
            return redirect()->route('active-branch.select');
        }

        $branch = $request->route('branch');
        if ($branch instanceof Branch) {
            ActiveBranch::setIfAllowed((int) $branch->id);
        } elseif (is_numeric($branch)) {
            ActiveBranch::setIfAllowed((int) $branch);
        }

        return $next($request);
    }
}
