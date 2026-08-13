<?php

use App\Models\Branch;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Services\CatalogService;

beforeEach(function () {
    $this->catalog = app(CatalogService::class);
});

it('prices a service per branch, not globally', function () {
    $avenues = Branch::factory()->create();
    $borrowdale = Branch::factory()->create();
    $service = Service::factory()->create();

    $avenues->services()->attach($service, ['price_cents' => 1200, 'currency' => 'USD', 'is_active' => true]);
    $borrowdale->services()->attach($service, ['price_cents' => 1800, 'currency' => 'USD', 'is_active' => true]);

    $atAvenues = $this->catalog->priceListFor($avenues)->first()->services->first();
    $atBorrowdale = $this->catalog->priceListFor($borrowdale)->first()->services->first();

    expect($atAvenues->priceForLoadedBranch()->cents)->toBe(1200)
        ->and($atBorrowdale->priceForLoadedBranch()->cents)->toBe(1800);
});

it('leaves out services another branch sells but this one does not', function () {
    $avenues = Branch::factory()->create();
    $borrowdale = Branch::factory()->create();
    $category = ServiceCategory::factory()->create();

    $mine = Service::factory()->for($category, 'category')->create();
    $theirs = Service::factory()->for($category, 'category')->create();

    $avenues->services()->attach($mine, ['price_cents' => 1200, 'is_active' => true]);
    $borrowdale->services()->attach($theirs, ['price_cents' => 1200, 'is_active' => true]);

    $services = $this->catalog->priceListFor($avenues)->first()->services;

    expect($services)->toHaveCount(1)
        ->and($services->first()->id)->toBe($mine->id);
});

it('hides a service the branch has deactivated', function () {
    $branch = Branch::factory()->create();
    $service = Service::factory()->create();

    $branch->services()->attach($service, ['price_cents' => 1200, 'is_active' => false]);

    expect($this->catalog->priceListFor($branch))->toBeEmpty();
});

it('drops a category with nothing left to sell rather than rendering an empty heading', function () {
    $branch = Branch::factory()->create();
    $stocked = ServiceCategory::factory()->create();
    ServiceCategory::factory()->create();

    $service = Service::factory()->for($stocked, 'category')->create();
    $branch->services()->attach($service, ['price_cents' => 1200, 'is_active' => true]);

    $categories = $this->catalog->priceListFor($branch);

    expect($categories)->toHaveCount(1)
        ->and($categories->first()->id)->toBe($stocked->id);
});

it('excludes an inactive service even when the branch still prices it', function () {
    $branch = Branch::factory()->create();
    $service = Service::factory()->inactive()->create();

    $branch->services()->attach($service, ['price_cents' => 1200, 'is_active' => true]);

    expect($this->catalog->priceListFor($branch))->toBeEmpty();
});

it('falls back to the service duration when the branch sets none', function () {
    $branch = Branch::factory()->create();
    $service = Service::factory()->create(['default_duration_minutes' => 45]);

    $branch->services()->attach($service, ['price_cents' => 1200, 'duration_minutes' => null, 'is_active' => true]);

    $loaded = $this->catalog->priceListFor($branch)->first()->services->first();

    expect($loaded->durationForLoadedBranch())->toBe(45);
});

it('reports no price for a service loaded outside a branch', function () {
    $service = Service::factory()->create();

    expect($service->priceForLoadedBranch())->toBeNull();
});

it('finds the cheapest paid service for the from price, ignoring free ones', function () {
    $branch = Branch::factory()->create();

    $branch->services()->attach(Service::factory()->create(), ['price_cents' => 0, 'is_active' => true]);
    $branch->services()->attach(Service::factory()->create(), ['price_cents' => 700, 'is_active' => true]);
    $branch->services()->attach(Service::factory()->create(), ['price_cents' => 1500, 'is_active' => true]);

    expect($this->catalog->cheapestPriceCentsFor($branch))->toBe(700);
});
