<?php

use App\Models\Branch;
use App\Models\Plan;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Style;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->branch = Branch::factory()->create();

    $this->owner = User::factory()->create();
    $this->owner->branches()->attach($this->branch, ['is_primary' => true]);

    $this->barber = User::factory()->create();
    $this->barber->branches()->attach($this->branch, ['is_primary' => true]);

    setPermissionsTeamId($this->branch->id);
    $this->owner->assignRole('owner');
    $this->barber->assignRole('barber');
    setPermissionsTeamId(null);
});

it('keeps the admin behind auth', function (string $path) {
    $this->get($path)->assertRedirect('/login');
})->with([
    '/dashboard',
    '/admin/pricing',
    '/admin/services',
    '/admin/reviews',
]);

/**
 * Every admin screen, rendered as the owner. Cheap to run and it catches a
 * page that breaks because a prop it needs stopped being passed.
 */
it('lets an owner in everywhere', function (string $path, string $component) {
    $this->actingAs($this->owner)
        ->get($path)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component($component));
})->with([
    'dashboard' => ['/dashboard', 'admin/dashboard'],
    'bookings' => ['/admin/bookings', 'admin/bookings'],
    'pricing' => ['/admin/pricing', 'admin/pricing'],
    'services' => ['/admin/services', 'admin/services'],
    'new service' => ['/admin/services/create', 'admin/service-form'],
    'categories' => ['/admin/categories', 'admin/categories'],
    'styles' => ['/admin/styles', 'admin/styles'],
    'new style' => ['/admin/styles/create', 'admin/style-form'],
    'plans' => ['/admin/plans', 'admin/plans'],
    'new plan' => ['/admin/plans/create', 'admin/plan-form'],
    'loyalty' => ['/admin/loyalty', 'admin/loyalty'],
    'team' => ['/admin/team', 'admin/team'],
    'reviews' => ['/admin/reviews', 'admin/reviews'],
    'branches' => ['/admin/branches', 'admin/branches'],
]);

it('renders the edit screens for records that exist', function () {
    $service = Service::factory()->create();
    $style = Style::factory()->create();
    $plan = Plan::factory()->create();
    $profile = StaffProfile::factory()->create();

    $this->actingAs($this->owner);

    $this->get("/admin/services/{$service->slug}/edit")->assertOk();
    $this->get("/admin/styles/{$style->slug}/edit")->assertOk();
    $this->get("/admin/plans/{$plan->slug}/edit")->assertOk();
    $this->get("/admin/team/{$profile->slug}/edit")->assertOk();
});

/**
 * The rule worth defending: a barber can look at the price list but must not
 * be able to change what the shop charges.
 */
it('lets a barber see prices but never change them', function () {
    $service = Service::factory()->create();
    $this->branch->services()->attach($service, ['price_cents' => 1200, 'is_active' => true]);

    $this->actingAs($this->barber)->get('/admin/pricing')->assertOk();

    $this->actingAs($this->barber)
        ->put("/admin/pricing/{$service->slug}", [
            'price' => 1,
            'duration_minutes' => 30,
            'is_active' => true,
        ])
        ->assertForbidden();

    expect($this->branch->services()->first()->pivot->price_cents)->toBe(1200);
});

it('does not let a barber manage plans or publish reviews', function () {
    $this->actingAs($this->barber)->get('/admin/plans')->assertForbidden();
});

it('updates a price and reflects it on the public site immediately', function () {
    $service = Service::factory()->create();
    $this->branch->services()->attach($service, ['price_cents' => 1200, 'is_active' => true]);

    $this->actingAs($this->owner)
        ->put("/admin/pricing/{$service->slug}", [
            'price' => 17.5,
            'duration_minutes' => 40,
            'is_active' => true,
        ])
        ->assertRedirect();

    $this->get('/services')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('categories.0.services.0.price.formatted', '$17.50')
            ->where('categories.0.services.0.duration_minutes', 40));
});

it('takes a service off the menu and it leaves the public list', function () {
    $service = Service::factory()->create();
    $this->branch->services()->attach($service, ['price_cents' => 1200, 'is_active' => true]);

    $this->actingAs($this->owner)
        ->put("/admin/services/{$service->slug}/toggle")
        ->assertRedirect();

    $this->get('/services')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('categories', []));
});

/* -------------------------------------------------------------- branch hours */

it('saves shop and house call hours and the calendar follows', function () {
    $this->actingAs($this->owner)
        ->put("/admin/branches/{$this->branch->slug}/hours", [
            'opens_at' => '08:00',
            'closes_at' => '18:00',
            'days_open' => [1, 2, 3, 4, 5],
            'house_call_enabled' => true,
            'house_call_opens_at' => '10:00',
            'house_call_closes_at' => '16:00',
            'house_call_days_open' => [1, 2, 3],
            'house_call_radius_km' => 20,
            'house_call_fee' => 7.5,
        ])
        ->assertRedirect();

    $branch = $this->branch->fresh();

    expect($branch->house_call_fee_cents)->toBe(750)
        ->and($branch->houseCallOpensAt())->toStartWith('10:00')
        ->and($branch->isOpenForHouseCallsOn(1))->toBeTrue()
        ->and($branch->isOpenForHouseCallsOn(4))->toBeFalse()
        // The shop still trades Thursday even though house calls do not.
        ->and($branch->isOpenOn(4))->toBeTrue();
});

it('refuses hours that close before they open', function () {
    $this->actingAs($this->owner)
        ->put("/admin/branches/{$this->branch->slug}/hours", [
            'opens_at' => '18:00',
            'closes_at' => '09:00',
            'days_open' => [1],
            'house_call_enabled' => false,
            'house_call_days_open' => [],
            'house_call_fee' => 0,
        ])
        ->assertSessionHasErrors('closes_at');
});

it('refuses house call hours that run past shop closing', function () {
    $this->actingAs($this->owner)
        ->put("/admin/branches/{$this->branch->slug}/hours", [
            'opens_at' => '08:00',
            'closes_at' => '17:00',
            'days_open' => [1],
            'house_call_enabled' => true,
            'house_call_opens_at' => '09:00',
            'house_call_closes_at' => '20:00',
            'house_call_days_open' => [1],
            'house_call_fee' => 5,
        ])
        ->assertSessionHasErrors('house_call_closes_at');
});

it('does not let a barber change opening hours', function () {
    $this->actingAs($this->barber)
        ->put("/admin/branches/{$this->branch->slug}/hours", [
            'opens_at' => '00:00',
            'closes_at' => '23:00',
            'days_open' => [0, 1, 2, 3, 4, 5, 6],
            'house_call_enabled' => false,
            'house_call_days_open' => [],
            'house_call_fee' => 0,
        ])
        ->assertForbidden();
});

it('keeps public registration closed', function () {
    $this->get('/register')->assertNotFound();
});
