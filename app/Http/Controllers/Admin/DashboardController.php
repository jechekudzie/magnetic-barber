<?php

namespace App\Http\Controllers\Admin;

use App\Http\Resources\ReviewResource;
use App\Services\AdminMetrics;
use App\Services\ReviewService;
use App\Support\ResourcePayload;
use Illuminate\Http\Request;
use Inertia\Response;

class DashboardController extends AdminController
{
    public function __construct(
        private readonly AdminMetrics $metrics,
        private readonly ReviewService $reviews,
    ) {}

    public function __invoke(Request $request): Response
    {
        $branch = $this->currentBranch($request);

        return inertia('admin/dashboard', [
            'branchContext' => $this->branchContext($request),
            'metrics' => $this->metrics->forBranch($branch),
            'recentReviews' => ResourcePayload::flatten(
                ReviewResource::collection($this->reviews->published(5))
            ),
        ]);
    }
}
