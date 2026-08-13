<?php

namespace App\Http\Controllers\Site;

use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StyleController extends SiteController
{
    public function index(Request $request): Response
    {
        $branch = $this->currentBranch($request);

        $validated = $request->validate([
            'gender' => ['nullable', 'string', 'in:men,women,unisex,kids,all'],
            'hair_type' => ['nullable', 'string', 'max:40'],
        ]);

        $gender = $validated['gender'] ?? 'all';
        $hairType = $validated['hair_type'] ?? 'all';

        return inertia('site/styles', [
            'site' => $this->shared($branch),
            'styles' => $this->payload->styles($gender, $hairType),
            'filters' => $this->payload->styleFilters(),
            'activeFilters' => ['gender' => $gender, 'hair_type' => $hairType],
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $branch = $this->currentBranch($request);

        $style = $this->payload->style($slug, $branch);

        if ($style === null) {
            throw new NotFoundHttpException('Style not found.');
        }

        return inertia('site/style', [
            'site' => $this->shared($branch),
            'style' => $style,
            'related' => collect($this->payload->styles())
                ->where('slug', '!=', $slug)
                ->take(4)
                ->values()
                ->all(),
        ]);
    }
}
