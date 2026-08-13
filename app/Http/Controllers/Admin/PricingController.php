<?php

namespace App\Http\Controllers\Admin;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * The per branch price grid. This is the screen an owner actually uses, so it
 * shows every active service including the ones this branch does not price
 * yet, rather than hiding them.
 */
class PricingController extends AdminController
{
    public function index(Request $request): Response
    {
        $branch = $this->currentBranch($request);

        $categories = ServiceCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with([
                'services' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->with(['branches' => fn ($q) => $q->whereKey($branch?->id)]),
            ])
            ->get()
            ->map(fn (ServiceCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'services' => $category->services
                    ->map(fn (Service $service): array => $this->row($service))
                    ->values()
                    ->all(),
            ])
            ->reject(fn (array $category): bool => $category['services'] === [])
            ->values()
            ->all();

        return inertia('admin/pricing', [
            'branchContext' => $this->branchContext($request),
            'categories' => $categories,
            'currency' => config('magnetic.default_currency'),
        ]);
    }

    /**
     * Prices arrive from the form in whole currency units because that is what
     * a person types. They are stored as integer cents.
     */
    public function update(Request $request, Service $service): RedirectResponse
    {
        $branch = $this->currentBranch($request);

        abort_if($branch === null, 403, 'No branch selected.');

        $validated = $request->validate([
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'is_active' => ['required', 'boolean'],
        ]);

        $money = Money::of($validated['price'], config('magnetic.default_currency'));

        $branch->services()->syncWithoutDetaching([
            $service->id => [
                'price_cents' => $money->cents,
                'currency' => $money->currency,
                'duration_minutes' => $validated['duration_minutes'],
                'is_active' => $validated['is_active'],
            ],
        ]);

        // The pivot is not a model save, so the catalog cache is bumped here.
        $service->touch();

        return back()->with('success', "{$service->name} updated.");
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Service $service): array
    {
        $price = $service->priceForLoadedBranch();
        $pivot = $service->branches->first()?->getAttribute('pivot');

        return [
            'id' => $service->id,
            'slug' => $service->slug,
            'name' => $service->name,
            'description' => $service->description,
            'is_priced' => $price !== null,
            'price' => $price?->toArray(),
            'price_amount' => $price?->amount() ?? 0,
            'duration_minutes' => $service->durationForLoadedBranch(),
            'default_duration_minutes' => $service->default_duration_minutes,
            'is_active' => (bool) ($pivot?->getAttribute('is_active') ?? false),
            'requires_patch_test' => $service->requires_patch_test,
            'is_skin_service' => $service->is_skin_service,
        ];
    }
}
