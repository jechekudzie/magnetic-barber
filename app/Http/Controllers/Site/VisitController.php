<?php

namespace App\Http\Controllers\Site;

use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Where we are, when we are open, and the three ways to get in the chair.
 */
class VisitController extends SiteController
{
    public function __invoke(Request $request): Response
    {
        $branch = $this->currentBranch($request);

        return inertia('site/visit', [
            'site' => $this->shared($branch),
            'team' => $this->payload->team($branch),
            'fromPrice' => $branch === null ? null : $this->payload->cheapestPrice($branch),
        ]);
    }
}
