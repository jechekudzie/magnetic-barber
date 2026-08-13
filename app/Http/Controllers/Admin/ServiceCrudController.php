<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\ServiceRequest;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Response;

class ServiceCrudController extends AdminController
{
    public function create(Request $request): Response
    {
        return inertia('admin/service-form', [
            'branchContext' => $this->branchContext($request),
            'categories' => $this->categoryOptions(),
            'service' => null,
        ]);
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $branch = $this->currentBranch($request);

        $service = Service::create([
            ...$this->attributes($validated),
            // Generated once and never regenerated, so a shared link or QR
            // pointing at this service keeps working after a rename.
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
        ]);

        $this->price($service, $validated, $branch?->id);

        return to_route('admin.services')->with('success', "{$service->name} added.");
    }

    public function edit(Request $request, Service $service): Response
    {
        $branch = $this->currentBranch($request);

        $service->load(['branches' => fn ($query) => $query->whereKey($branch?->id)]);

        return inertia('admin/service-form', [
            'branchContext' => $this->branchContext($request),
            'categories' => $this->categoryOptions(),
            'service' => [
                'slug' => $service->slug,
                'name' => $service->name,
                'service_category_id' => $service->service_category_id,
                'description' => $service->description,
                'default_duration_minutes' => $service->default_duration_minutes,
                'buffer_minutes' => $service->buffer_minutes,
                'requires_patch_test' => $service->requires_patch_test,
                'patch_test_lead_hours' => $service->patch_test_lead_hours,
                'is_skin_service' => $service->is_skin_service,
                'is_house_call_eligible' => $service->is_house_call_eligible,
                'is_featured' => $service->is_featured,
                'is_active' => $service->is_active,
                'sort_order' => $service->sort_order,
                'price' => $service->priceForLoadedBranch()?->amount(),
            ],
        ]);
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $validated = $request->validated();
        $branch = $this->currentBranch($request);

        $service->update($this->attributes($validated));

        $this->price($service, $validated, $branch?->id);

        return to_route('admin.services')->with('success', "{$service->name} updated.");
    }

    /**
     * Soft deleted, never destroyed: a service is referenced by every past
     * appointment line that was ever booked from it.
     */
    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        return to_route('admin.services')->with('success', "{$service->name} removed from the menu.");
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'service_category_id' => $validated['service_category_id'],
            'description' => $validated['description'] ?? null,
            'default_duration_minutes' => $validated['default_duration_minutes'],
            'buffer_minutes' => $validated['buffer_minutes'],
            'requires_patch_test' => $validated['requires_patch_test'],
            'patch_test_lead_hours' => $validated['requires_patch_test']
                ? $validated['patch_test_lead_hours']
                : null,
            'is_skin_service' => $validated['is_skin_service'],
            'is_house_call_eligible' => $validated['is_house_call_eligible'],
            'is_featured' => $validated['is_featured'],
            'is_active' => $validated['is_active'],
            'sort_order' => $validated['sort_order'],
        ];
    }

    /**
     * Price lives on the pivot, so it is attached to the branch the manager is
     * working in. Leaving it blank means "not sold here".
     *
     * @param  array<string, mixed>  $validated
     */
    private function price(Service $service, array $validated, ?int $branchId): void
    {
        if ($branchId === null) {
            return;
        }

        if (($validated['price'] ?? null) === null) {
            $service->branches()->detach($branchId);

            return;
        }

        $money = Money::of($validated['price'], config('magnetic.default_currency'));

        $service->branches()->syncWithoutDetaching([
            $branchId => [
                'price_cents' => $money->cents,
                'currency' => $money->currency,
                'duration_minutes' => $validated['default_duration_minutes'],
                'is_active' => $validated['is_active'],
            ],
        ]);

        // A pivot write is not a model save, so the catalog cache is bumped here.
        $service->touch();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function categoryOptions(): array
    {
        return ServiceCategory::query()
            ->orderBy('sort_order')
            ->get(['id', 'name'])
            ->map(fn (ServiceCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
            ])
            ->all();
    }
}
