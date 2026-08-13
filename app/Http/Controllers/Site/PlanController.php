<?php

namespace App\Http\Controllers\Site;

use Illuminate\Http\Request;
use Inertia\Response;

class PlanController extends SiteController
{
    public function __invoke(Request $request): Response
    {
        $branch = $this->currentBranch($request);

        return inertia('site/plans', [
            'site' => $this->shared($branch),
            'plans' => $this->payload->plans(),
        ]);
    }
}
