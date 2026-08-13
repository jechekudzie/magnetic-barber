<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Roles are assigned per branch, so every authenticated request has to say
 * which branch it is acting in before a single gate check will resolve.
 *
 * The branch comes from the X-Branch-Id header on API calls, a ?branch= query
 * or session value in the admin, otherwise the user's primary branch.
 */
class SetPermissionsBranch
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $branch = $this->resolveBranch($request);

        if ($branch !== null) {
            setPermissionsTeamId($branch->id);
            app()->instance('currentBranch', $branch);
            $request->session()->put('admin_branch_id', $branch->id);
        }

        return $next($request);
    }

    private function resolveBranch(Request $request): ?Branch
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        $requested = $request->header('X-Branch-Id')
            ?? $request->query('branch_id')
            ?? $request->session()->get('admin_branch_id');

        if ($requested !== null) {
            $branch = Branch::query()->whereKey($requested)->first();

            // Only honour a requested branch the user actually works at,
            // otherwise a header would be enough to read another branch.
            if ($branch !== null && $user->worksAt($branch->id)) {
                return $branch;
            }
        }

        $primaryId = $user->branches()
            ->orderByDesc('branch_user.is_primary')
            ->value('branches.id');

        return $primaryId === null
            ? null
            : Branch::query()->whereKey($primaryId)->first();
    }
}
