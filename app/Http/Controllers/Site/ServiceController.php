<?php

namespace App\Http\Controllers\Site;

use Illuminate\Http\Request;
use Inertia\Response;

class ServiceController extends SiteController
{
    public function __invoke(Request $request): Response
    {
        $branch = $this->currentBranch($request);

        return inertia('site/services', [
            'site' => $this->shared($branch),
            'categories' => $branch === null ? [] : $this->payload->priceList($branch),
        ]);
    }
}
