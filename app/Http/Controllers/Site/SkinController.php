<?php

namespace App\Http\Controllers\Site;

use Illuminate\Http\Request;
use Inertia\Response;

class SkinController extends SiteController
{
    public function __invoke(Request $request): Response
    {
        $branch = $this->currentBranch($request);

        return inertia('site/skin', [
            'site' => $this->shared($branch),
            'services' => $branch === null
                ? []
                : $this->payload->servicesInCategory($branch, 'skin-and-facials'),
            'plans' => array_values(array_filter(
                $this->payload->plans(),
                fn (array $plan): bool => str_contains($plan['slug'], 'skin'),
            )),
            'team' => $this->payload->team($branch),
        ]);
    }
}
