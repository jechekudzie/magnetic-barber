<?php

use App\Models\Branch;
use App\Models\Service;
use App\Models\Style;
use App\Services\CatalogPayload;

it('serves the same payload from cache on a second read', function () {
    $branch = Branch::factory()->create();
    $service = Service::factory()->create();
    $branch->services()->attach($service, ['price_cents' => 1200, 'is_active' => true]);

    $payload = app(CatalogPayload::class);

    expect($payload->priceList($branch))->toBe($payload->priceList($branch));
});

/**
 * The failure this guards against is subtle: a cached Eloquent model comes back
 * as __PHP_Incomplete_Class and only explodes on the second request, long after
 * the change that caused it shipped.
 */
it('survives a cache hit, which caching Eloquent models would not', function () {
    $branch = Branch::factory()->create();
    Service::factory()->create();

    $payload = app(CatalogPayload::class);

    $payload->branches();
    $payload->styles();
    $payload->team();

    expect($payload->branches())->toBeArray()
        ->and($payload->styles())->toBeArray()
        ->and($payload->team())->toBeArray();
});

it('shows a new price immediately rather than serving a stale one', function () {
    $branch = Branch::factory()->create();
    $service = Service::factory()->create();
    $branch->services()->attach($service, ['price_cents' => 1200, 'is_active' => true]);

    $payload = app(CatalogPayload::class);

    expect($payload->priceList($branch)[0]['services'][0]['price']['cents'])->toBe(1200);

    // Saving any catalog record bumps the cache version.
    $service->update(['name' => 'Renamed Cut']);
    $branch->services()->updateExistingPivot($service->id, ['price_cents' => 1500]);
    $service->touch();

    expect($payload->priceList($branch)[0]['services'][0]['price']['cents'])->toBe(1500);
});

it('returns the whole gallery when no filter is applied', function () {
    Style::factory()->count(3)->create();

    expect(app(CatalogPayload::class)->styles())->toHaveCount(3);
});
