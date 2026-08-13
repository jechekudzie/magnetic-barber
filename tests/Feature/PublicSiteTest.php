<?php

use App\Models\Branch;
use App\Models\Service;
use App\Models\Style;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
});

it('renders every public page without auth', function (string $path, string $component) {
    $this->get($path)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component($component));
})->with([
    'home' => ['/', 'site/home'],
    'services' => ['/services', 'site/services'],
    'styles' => ['/styles', 'site/styles'],
    'skin' => ['/skin', 'site/skin'],
    'plans' => ['/plans', 'site/plans'],
    'visit' => ['/visit', 'site/visit'],
    'book' => ['/book', 'site/book'],
]);

it('shares the branch list and contact links with every page', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('site.branches', 1)
            ->where('site.branch.slug', $this->branch->slug)
            ->where('site.whatsapp_link', fn (?string $link): bool => str_starts_with((string) $link, 'https://wa.me/'))
        );
});

it('remembers the chosen branch for the rest of the session', function () {
    $borrowdale = Branch::factory()->create(['sort_order' => 5]);

    $this->get("/services?branch={$borrowdale->slug}")->assertOk();

    $this->get('/services')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('site.branch.slug', $borrowdale->slug));
});

it('falls back to the default branch rather than 404ing on a stale link', function () {
    $this->get('/services?branch=closed-down-shop')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('site.branch.slug', $this->branch->slug));
});

it('shows prices for the selected branch only', function () {
    $service = Service::factory()->create();
    $this->branch->services()->attach($service, ['price_cents' => 1200, 'is_active' => true]);

    $this->get('/services')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('categories.0.services.0.price.formatted', '$12'));
});

it('keeps the style filters in the url so a grid can be shared', function () {
    Style::factory()->create(['gender_tag' => 'men', 'code' => '01']);
    Style::factory()->create(['gender_tag' => 'women', 'code' => '02']);

    $this->get('/styles?gender=women')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('styles', 1)
            ->where('styles.0.code', '02')
            ->where('activeFilters.gender', 'women'));
});

it('serves a style detail page by slug', function () {
    $style = Style::factory()->create(['code' => '01']);

    $this->get("/styles/{$style->slug}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('site/style')
            ->where('style.slug', $style->slug));
});

it('404s on an unknown style', function () {
    $this->get('/styles/no-such-cut')->assertNotFound();
});
