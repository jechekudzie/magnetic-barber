<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\BranchHoursRequest;
use App\Http\Resources\BranchResource;
use App\Http\Resources\PlanResource;
use App\Http\Resources\StaffResource;
use App\Http\Resources\StyleResource;
use App\Models\Branch;
use App\Models\Plan;
use App\Models\Review;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Style;
use App\Support\Money;
use App\Support\ResourcePayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * List and toggle screens for everything in the catalog that is not priced.
 *
 * These are deliberately read plus a small number of safe writes: publish a
 * review, feature a style, take a service off the menu. Full record editing
 * arrives with the forms slice.
 */
class CatalogAdminController extends AdminController
{
    public function services(Request $request): Response
    {
        $branch = $this->currentBranch($request);

        $services = Service::query()
            ->with(['category', 'branches' => fn ($q) => $q->whereKey($branch?->id)])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Service $service): array => [
                'id' => $service->ulid,
                'slug' => $service->slug,
                'name' => $service->name,
                'description' => $service->description,
                'category' => $service->category?->name,
                'duration_minutes' => $service->default_duration_minutes,
                'price' => $service->priceForLoadedBranch()?->format(),
                'is_active' => $service->is_active,
                'is_featured' => $service->is_featured,
                'is_skin_service' => $service->is_skin_service,
                'requires_patch_test' => $service->requires_patch_test,
            ])
            ->all();

        return inertia('admin/services', [
            'branchContext' => $this->branchContext($request),
            'services' => $services,
        ]);
    }

    public function toggleService(Service $service): RedirectResponse
    {
        $service->update(['is_active' => ! $service->is_active]);

        return back()->with(
            'success',
            $service->name.($service->is_active ? ' is back on the menu.' : ' is off the menu.')
        );
    }

    public function styles(Request $request): Response
    {
        return inertia('admin/styles', [
            'branchContext' => $this->branchContext($request),
            'styles' => ResourcePayload::flatten(
                StyleResource::collection(
                    Style::query()->orderBy('sort_order')->with('service')->get()
                )
            ),
        ]);
    }

    public function toggleStyleFeatured(Style $style): RedirectResponse
    {
        $style->update(['is_featured' => ! $style->is_featured]);

        return back()->with(
            'success',
            $style->is_featured
                ? "{$style->name} now shows on the homepage."
                : "{$style->name} removed from the homepage."
        );
    }

    public function plans(Request $request): Response
    {
        return inertia('admin/plans', [
            'branchContext' => $this->branchContext($request),
            'plans' => ResourcePayload::flatten(
                PlanResource::collection(Plan::query()->orderBy('sort_order')->get())
            ),
        ]);
    }

    public function team(Request $request): Response
    {
        $branch = $this->currentBranch($request);

        $profiles = StaffProfile::query()
            ->orderBy('sort_order')
            ->with('user')
            ->when($branch !== null, fn ($query) => $query->whereHas(
                'user',
                fn ($q) => $q->whereHas('branches', fn ($b) => $b->whereKey($branch->id))
            ))
            ->get();

        return inertia('admin/team', [
            'branchContext' => $this->branchContext($request),
            'team' => $profiles
                ->map(fn (StaffProfile $profile): array => [
                    ...ResourcePayload::flatten(new StaffResource($profile)),
                    'show_on_site' => $profile->show_on_site,
                    // Staff contact details are admin-only, never public.
                    'roles' => $profile->user?->getRoleNames()->all() ?? [],
                ])
                ->all(),
        ]);
    }

    public function reviews(Request $request): Response
    {
        $reviews = Review::query()
            ->latest('created_at')
            ->with('branch:id,name,slug')
            ->limit(100)
            ->get()
            ->map(fn (Review $review): array => [
                'id' => $review->id,
                'author_name' => $review->author_name,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'branch' => $review->branch?->name,
                'is_public' => $review->is_public,
                'is_flagged' => $review->flagged_at !== null,
                'published_at' => $review->published_at?->toDateString(),
                'created_at' => $review->created_at?->toDateString(),
            ])
            ->all();

        return inertia('admin/reviews', [
            'branchContext' => $this->branchContext($request),
            'reviews' => $reviews,
        ]);
    }

    public function toggleReview(Review $review): RedirectResponse
    {
        $publishing = ! $review->is_public;

        $review->update([
            'is_public' => $publishing,
            'published_at' => $publishing ? now() : null,
        ]);

        return back()->with(
            'success',
            $publishing ? 'Review published to the site.' : 'Review hidden from the site.'
        );
    }

    public function branches(Request $request): Response
    {
        return inertia('admin/branches', [
            'branchContext' => $this->branchContext($request),
            'branches' => Branch::query()
                ->ordered()
                ->get()
                ->map(fn (Branch $branch): array => [
                    ...ResourcePayload::flatten(new BranchResource($branch)),
                    // The editable settings, in the shape the form binds to.
                    'settings' => [
                        'opens_at' => substr((string) $branch->opens_at, 0, 5),
                        'closes_at' => substr((string) $branch->closes_at, 0, 5),
                        'days_open' => $branch->days_open ?? [],
                        'house_call_enabled' => $branch->house_call_enabled,
                        'house_call_opens_at' => $branch->house_call_opens_at
                            ? substr($branch->house_call_opens_at, 0, 5)
                            : '',
                        'house_call_closes_at' => $branch->house_call_closes_at
                            ? substr($branch->house_call_closes_at, 0, 5)
                            : '',
                        'house_call_days_open' => $branch->house_call_days_open ?? [],
                        'house_call_radius_km' => $branch->house_call_radius_km,
                        'house_call_fee' => $branch->house_call_fee_cents / 100,
                    ],
                ])
                ->all(),
        ]);
    }

    /**
     * Opening hours drive the whole booking calendar, so this is the screen
     * that decides what a client can even see as available.
     */
    public function updateBranchHours(BranchHoursRequest $request, Branch $branch): RedirectResponse
    {
        $validated = $request->validated();

        $branch->update([
            'opens_at' => $validated['opens_at'],
            'closes_at' => $validated['closes_at'],
            'days_open' => array_values($validated['days_open']),
            'house_call_enabled' => $validated['house_call_enabled'],
            // Blank means "same as the shop", so it is stored as null.
            'house_call_opens_at' => $validated['house_call_opens_at'] ?: null,
            'house_call_closes_at' => $validated['house_call_closes_at'] ?: null,
            'house_call_days_open' => array_values($validated['house_call_days_open']),
            'house_call_radius_km' => $validated['house_call_radius_km'],
            'house_call_fee_cents' => Money::of($validated['house_call_fee'])->cents,
        ]);

        return back()->with('success', "{$branch->name} hours updated.");
    }
}
