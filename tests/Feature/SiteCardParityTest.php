<?php

use App\Models\Branch;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogSeeder;

/**
 * The website and the app must show the same two cards.
 *
 * They read the same catalog through the same service classes, so the only
 * way they can disagree is if one of them stops sending the field that says
 * which card a service is on. This is the test that notices.
 */
beforeEach(function () {
    $this->seed(BranchSeeder::class);
    $this->seed(CatalogSeeder::class);
});

it('sends the price card with every service on the website', function () {
    $response = $this->get('/services');

    $response->assertOk();

    $services = collect($response->viewData('page')['props']['categories'])
        ->flatMap(fn (array $category) => $category['services'] ?? []);

    expect($services)->not->toBeEmpty()
        ->and($services->every(fn (array $service): bool => in_array(
            $service['audience'],
            ['men', 'women', 'unisex', 'kids'],
            true,
        )))->toBeTrue();
});

it('sends it on the booking page too, or the wizard cannot split them', function () {
    $services = collect($this->get('/book')->viewData('page')['props']['categories'])
        ->flatMap(fn (array $category) => $category['services'] ?? []);

    expect($services->pluck('audience')->filter()->count())->toBe($services->count());
});

it('gives the website and the app the same cards', function () {
    $fromSite = collect($this->get('/services')->viewData('page')['props']['categories'])
        ->flatMap(fn (array $category) => $category['services'] ?? [])
        ->mapWithKeys(fn (array $service): array => [
            $service['slug'] => $service['audience'],
        ])
        ->sortKeys();

    $branch = Branch::where('slug', 'harare-avenues')->sole();

    $fromApi = collect($this->getJson("/api/v1/branches/{$branch->slug}/services")->json('data'))
        ->flatMap(fn (array $category) => $category['services'] ?? [])
        ->mapWithKeys(fn (array $service): array => [
            $service['slug'] => $service['audience'],
        ])
        ->sortKeys();

    expect($fromSite->all())->toBe($fromApi->all())
        ->and($fromSite)->not->toBeEmpty();
});

it('puts seven lines on the mens card and six on the ladies, on the website', function () {
    $services = collect($this->get('/services')->viewData('page')['props']['categories'])
        ->flatMap(fn (array $category) => $category['services'] ?? []);

    $onCard = fn (string $card): int => $services
        ->filter(fn (array $service): bool => $service['audience'] === $card
            || $service['audience'] === 'unisex'
            || ($service['audience'] === 'kids' && $card === 'men'))
        ->count();

    expect($onCard('men'))->toBe(7)
        ->and($onCard('women'))->toBe(6);
});
