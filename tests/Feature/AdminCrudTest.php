<?php

use App\Models\Branch;
use App\Models\Plan;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\StaffProfile;
use App\Models\Style;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->branch = Branch::factory()->create(['days_open' => [0, 1, 2, 3, 4, 5, 6]]);

    $this->owner = User::factory()->create();
    $this->owner->branches()->attach($this->branch, ['is_primary' => true]);

    $this->barber = User::factory()->create();
    $this->barber->branches()->attach($this->branch, ['is_primary' => true]);

    setPermissionsTeamId($this->branch->id);
    $this->owner->assignRole('owner');
    $this->barber->assignRole('barber');
    setPermissionsTeamId(null);

    $this->category = ServiceCategory::factory()->create(['name' => 'Cuts and Beards']);
});

/* ---------------------------------------------------------------- services */

it('creates a service and it appears on the public price list', function () {
    $this->actingAs($this->owner)
        ->post('/admin/services', [
            'name' => 'Beard Sculpt',
            'service_category_id' => $this->category->id,
            'description' => 'Shaped to the jaw.',
            'default_duration_minutes' => 30,
            'buffer_minutes' => 5,
            'requires_patch_test' => false,
            'is_skin_service' => false,
            'is_house_call_eligible' => true,
            'is_featured' => false,
            'is_active' => true,
            'sort_order' => 0,
            'price' => 14.5,
        ])
        ->assertRedirect('/admin/services');

    $service = Service::where('name', 'Beard Sculpt')->firstOrFail();

    expect($service->slug)->toBe('beard-sculpt');

    // The whole point: a change in the admin is on the site immediately.
    $this->get('/services')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('categories.0.services.0.name', 'Beard Sculpt')
            ->where('categories.0.services.0.price.formatted', '$14.50'));
});

it('updates a service and the site follows', function () {
    $service = Service::factory()->for($this->category, 'category')->create(['name' => 'Old Name']);
    $this->branch->services()->attach($service, ['price_cents' => 1000, 'is_active' => true]);

    $originalSlug = $service->slug;

    $this->actingAs($this->owner)
        ->put("/admin/services/{$service->slug}", [
            'name' => 'New Name',
            'service_category_id' => $this->category->id,
            'default_duration_minutes' => 45,
            'buffer_minutes' => 0,
            'requires_patch_test' => false,
            'is_skin_service' => false,
            'is_house_call_eligible' => true,
            'is_featured' => true,
            'is_active' => true,
            'sort_order' => 0,
            'price' => 22,
        ])
        ->assertRedirect();

    $this->get('/services')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('categories.0.services.0.name', 'New Name')
            ->where('categories.0.services.0.price.formatted', '$22'));

    // The slug is deliberately not regenerated, so shared links keep working.
    expect($service->fresh()->slug)->toBe($originalSlug);
});

it('removes a service from the public list without destroying it', function () {
    $service = Service::factory()->for($this->category, 'category')->create();
    $this->branch->services()->attach($service, ['price_cents' => 1000, 'is_active' => true]);

    $this->actingAs($this->owner)
        ->delete("/admin/services/{$service->slug}")
        ->assertRedirect();

    $this->get('/services')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('categories', []));

    expect(Service::withTrashed()->find($service->id))->not->toBeNull();
});

it('requires a patch test lead time when a patch test is switched on', function () {
    $this->actingAs($this->owner)
        ->post('/admin/services', [
            'name' => 'Full Colour',
            'service_category_id' => $this->category->id,
            'default_duration_minutes' => 90,
            'buffer_minutes' => 5,
            'requires_patch_test' => true,
            'is_skin_service' => false,
            'is_house_call_eligible' => true,
            'is_featured' => false,
            'is_active' => true,
            'sort_order' => 0,
        ])
        ->assertSessionHasErrors('patch_test_lead_hours');
});

it('does not let a barber create a service', function () {
    $this->actingAs($this->barber)->get('/admin/services/create')->assertForbidden();
});

/* ------------------------------------------------------------------ styles */

it('adds a style with a photo and it shows in the public gallery', function () {
    Storage::fake('public');

    $this->actingAs($this->owner)
        ->post('/admin/styles', [
            'code' => '07',
            'name' => 'Temple Fade',
            'description' => 'Tight at the temples.',
            'hair_type_tag' => ['coily'],
            'gender_tag' => 'men',
            'is_featured' => true,
            'is_active' => true,
            'sort_order' => 0,
            'photo' => UploadedFile::fake()->image('fade.jpg', 800, 1000),
        ])
        ->assertRedirect('/admin/styles');

    $style = Style::where('code', '07')->firstOrFail();

    expect($style->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($style->image_path);

    $this->get('/styles')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('styles.0.name', 'Temple Fade')
            ->where('styles.0.image_url', fn (?string $url): bool => $url !== null));
});

it('refuses two styles sharing a number', function () {
    Style::factory()->create(['code' => '01']);

    $this->actingAs($this->owner)
        ->post('/admin/styles', [
            'code' => '01',
            'name' => 'Another Cut',
            'hair_type_tag' => [],
            'is_featured' => false,
            'is_active' => true,
            'sort_order' => 0,
        ])
        ->assertSessionHasErrors('code');
});

it('suggests the next free style number', function () {
    Style::factory()->create(['code' => '11']);

    $this->actingAs($this->owner)
        ->get('/admin/styles/create')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('nextCode', '12'));
});

/* ------------------------------------------------------------------- plans */

it('creates a plan and it appears on the public plans page', function () {
    $service = Service::factory()->for($this->category, 'category')->create();

    $this->actingAs($this->owner)
        ->post('/admin/plans', [
            'name' => 'Weekly Sharp',
            'tagline' => 'A cut a week.',
            'type' => 'session_pack',
            'session_count' => 4,
            'price' => 40,
            'validity_days' => 30,
            'included_service_ids' => [$service->id],
            'perks' => ['Priority booking', ''],
            'is_popular' => true,
            'is_active' => true,
            'sort_order' => 0,
        ])
        ->assertRedirect('/admin/plans');

    $plan = Plan::where('name', 'Weekly Sharp')->firstOrFail();

    // Blank perk rows are dropped rather than rendering as empty bullets.
    expect($plan->perks)->toBe(['Priority booking'])
        ->and($plan->price_cents)->toBe(4000);

    $this->get('/plans')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('plans.0.name', 'Weekly Sharp'));
});

it('requires a session count on a session pack', function () {
    $this->actingAs($this->owner)
        ->post('/admin/plans', [
            'name' => 'Broken Plan',
            'type' => 'session_pack',
            'price' => 40,
            'validity_days' => 30,
            'included_service_ids' => [],
            'perks' => [],
            'is_popular' => false,
            'is_active' => true,
            'sort_order' => 0,
        ])
        ->assertSessionHasErrors('session_count');
});

/* -------------------------------------------------------------- categories */

it('creates a category and refuses to delete one still holding services', function () {
    $this->actingAs($this->owner)
        ->post('/admin/categories', [
            'name' => 'Colour',
            'tagline' => 'Tints and blends.',
            'icon' => 'palette',
            'sort_order' => 3,
            'is_active' => true,
        ])
        ->assertRedirect();

    $category = ServiceCategory::where('name', 'Colour')->firstOrFail();

    Service::factory()->for($category, 'category')->create();

    $this->actingAs($this->owner)
        ->delete("/admin/categories/{$category->slug}")
        ->assertSessionHasErrors('category');

    expect(ServiceCategory::find($category->id))->not->toBeNull();
});

/* -------------------------------------------------------------------- team */

it('updates a barber profile and the public team page follows', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $user->branches()->attach($this->branch);
    $profile = StaffProfile::factory()->for($user)->create(['display_name' => 'Old Name']);

    $this->actingAs($this->owner)
        ->post("/admin/team/{$profile->slug}", [
            'display_name' => 'Blessing N',
            'title' => 'Senior Barber',
            'bio' => 'Fades and waves.',
            'specialities' => ['Waves', ''],
            'instagram_handle' => '@blessing',
            'accepts_house_calls' => true,
            'is_bookable' => true,
            'show_on_site' => true,
            'sort_order' => 1,
            'photo' => UploadedFile::fake()->image('barber.jpg'),
        ])
        ->assertRedirect('/admin/team');

    $profile->refresh();

    expect($profile->display_name)->toBe('Blessing N')
        // The leading @ is stripped so the profile link is not doubled up.
        ->and($profile->instagram_handle)->toBe('blessing')
        ->and($profile->specialities)->toBe(['Waves']);

    $this->get('/visit')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('team.0.name', 'Blessing N'));
});

it('does not let a barber edit another profile', function () {
    $profile = StaffProfile::factory()->create();

    $this->actingAs($this->barber)
        ->get("/admin/team/{$profile->slug}/edit")
        ->assertForbidden();
});
