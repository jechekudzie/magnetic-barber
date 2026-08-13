<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Support\ResourcePayload;
use Illuminate\Http\Request;

/**
 * Shared behaviour for admin screens. Every screen is scoped to one branch,
 * because prices, stock and staff are all per branch.
 */
abstract class AdminController extends Controller
{
    /**
     * The branch the admin is currently acting in. SetPermissionsBranch has
     * already validated that the user works there before it was stored.
     */
    protected function currentBranch(Request $request): ?Branch
    {
        if (app()->bound('currentBranch')) {
            return app('currentBranch');
        }

        return $request->user()?->branches()
            ->orderByDesc('branch_user.is_primary')
            ->first();
    }

    /**
     * Branch context shared with every admin page so the switcher renders.
     *
     * @return array<string, mixed>
     */
    protected function branchContext(Request $request): array
    {
        $current = $this->currentBranch($request);

        $branches = $request->user()?->branches()->orderBy('sort_order')->get()
            ?? collect();

        return [
            'current' => $current === null
                ? null
                : ResourcePayload::flatten(new BranchResource($current)),
            'available' => ResourcePayload::flatten(BranchResource::collection($branches)),
        ];
    }
}
