<?php

namespace App\Http\Controllers\Site;

use Illuminate\Http\Request;
use Inertia\Response;

class HomeController extends SiteController
{
    public function __invoke(Request $request): Response
    {
        $branch = $this->currentBranch($request);

        return inertia('site/home', [
            'site' => $this->shared($branch),
            'featuredServices' => $branch === null ? [] : $this->payload->featuredServices($branch, 6),
            'featuredStyles' => $this->payload->featuredStyles(6),
            'plans' => $this->payload->plans(),
            'team' => $this->payload->team($branch),
            'reviews' => $this->payload->reviews(6),
            'fromPrice' => $branch === null ? null : $this->payload->cheapestPrice($branch),
        ]);
    }
}
