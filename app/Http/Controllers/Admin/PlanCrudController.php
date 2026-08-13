<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlanType;
use App\Http\Requests\Admin\PlanRequest;
use App\Models\Plan;
use App\Models\Service;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Response;

class PlanCrudController extends AdminController
{
    public function create(Request $request): Response
    {
        return inertia('admin/plan-form', [
            'branchContext' => $this->branchContext($request),
            'services' => $this->serviceOptions(),
            'plan' => null,
        ]);
    }

    public function store(PlanRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $plan = Plan::create([
            ...$this->attributes($validated),
            'slug' => Str::slug($validated['name']),
            'branch_scope' => 'all',
        ]);

        return to_route('admin.plans')->with('success', "{$plan->name} added.");
    }

    public function edit(Request $request, Plan $plan): Response
    {
        return inertia('admin/plan-form', [
            'branchContext' => $this->branchContext($request),
            'services' => $this->serviceOptions(),
            'plan' => [
                'slug' => $plan->slug,
                'name' => $plan->name,
                'tagline' => $plan->tagline,
                'description' => $plan->description,
                'type' => $plan->type->value,
                'session_count' => $plan->session_count,
                'price' => $plan->price()->amount(),
                'validity_days' => $plan->validity_days,
                'included_service_ids' => $plan->included_service_ids ?? [],
                'perks' => $plan->perks ?? [],
                'is_popular' => $plan->is_popular,
                'is_active' => $plan->is_active,
                'sort_order' => $plan->sort_order,
            ],
        ]);
    }

    public function update(PlanRequest $request, Plan $plan): RedirectResponse
    {
        $plan->update($this->attributes($request->validated()));

        return to_route('admin.plans')->with('success', "{$plan->name} updated.");
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        // Soft deleted: subscriptions bought against it still reference it.
        $plan->delete();

        return to_route('admin.plans')->with('success', "{$plan->name} removed.");
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        $type = PlanType::from($validated['type']);

        return [
            'name' => $validated['name'],
            'tagline' => $validated['tagline'] ?? null,
            'description' => $validated['description'] ?? null,
            'type' => $type,
            'session_count' => $type === PlanType::Unlimited
                ? null
                : $validated['session_count'],
            'price_cents' => Money::of($validated['price'])->cents,
            'currency' => config('magnetic.default_currency'),
            'validity_days' => $validated['validity_days'],
            'included_service_ids' => array_values($validated['included_service_ids']),
            'perks' => array_values(array_filter(
                $validated['perks'],
                fn (?string $perk): bool => $perk !== null && trim($perk) !== '',
            )),
            'is_popular' => $validated['is_popular'],
            'is_active' => $validated['is_active'],
            'sort_order' => $validated['sort_order'],
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function serviceOptions(): array
    {
        return Service::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name'])
            ->map(fn (Service $service): array => [
                'id' => $service->id,
                'name' => $service->name,
            ])
            ->all();
    }
}
