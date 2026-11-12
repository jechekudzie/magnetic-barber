<?php

use App\Models\Branch;
use App\Models\LoyaltyRule;
use Inertia\Testing\AssertableInertia;

/*
 * The legal pages are the one place on the site where prose makes a promise.
 * The numbers in that prose come from the config and the loyalty rule the shop
 * actually enforces, so these tests are really about drift: a terms page
 * offering a window the booking service does not honour is worse than none.
 */

beforeEach(function () {
    Branch::factory()->create();
});

it('reaches both legal pages without an account', function (string $path) {
    // The app stores require a policy anyone can open, signed in or not.
    $this->get($path)->assertOk();
})->with(['/privacy', '/terms']);

it('quotes the cancellation window the booking service enforces', function () {
    config(['magnetic.cancellation_hours' => 6]);

    $this->get('/terms')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cancellation_hours', 6));
});

it('quotes the loyalty scheme that is actually configured', function () {
    LoyaltyRule::query()->create([
        'name' => 'Winter',
        'points_per_visit' => 8,
        'points_per_currency_unit' => 0,
        'redemption_threshold' => 80,
        'redemption_value_cents' => 1200,
        'points_expiry_months' => 9,
        'currency' => 'USD',
        'is_active' => true,
    ]);

    $this->get('/terms')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('loyalty.points_per_visit', 8)
            ->where('loyalty.threshold', 80)
            ->where('loyalty.expiry_months', 9)
            ->where('loyalty.reward.formatted', '$12'));
});

it('falls back to the default scheme when no rule is set', function () {
    // Production starts with no loyalty_rules row, so the page has to render
    // the model's default rather than a blank or a zero.
    expect(LoyaltyRule::query()->count())->toBe(0);

    $this->get('/terms')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('loyalty.points_per_visit', 5)
            ->where('loyalty.threshold', 50)
            ->where('loyalty.expiry_months', null)
            ->where('loyalty.reward.formatted', '$5'));
});

it('links both pages from the footer on every page', function () {
    // A policy nobody can find does not count, for Apple or for a client.
    $footer = file_get_contents(resource_path('js/components/site/site-footer.tsx'));

    expect($footer)->toContain('href="/privacy"')
        ->and($footer)->toContain('href="/terms"');
});
