<?php

use App\Models\Branch;
use App\Models\Review;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Style;
use App\Models\User;

it('lists active branches without exposing internal ids', function () {
    $branch = Branch::factory()->create();
    Branch::factory()->inactive()->create();

    $response = $this->getJson('/api/v1/branches')->assertOk();

    expect($response->json('data'))->toHaveCount(1);

    $response->assertJsonPath('data.0.id', $branch->ulid)
        ->assertJsonPath('data.0.slug', $branch->slug)
        ->assertJsonMissingPath('data.0.chair_count.id');
});

it('serves a price list carrying the money shape both clients expect', function () {
    $branch = Branch::factory()->create();
    $service = Service::factory()->create();
    $branch->services()->attach($service, ['price_cents' => 1200, 'currency' => 'USD', 'is_active' => true]);

    $this->getJson("/api/v1/branches/{$branch->slug}/services")
        ->assertOk()
        ->assertJsonPath('data.0.services.0.price.cents', 1200)
        ->assertJsonPath('data.0.services.0.price.currency', 'USD')
        ->assertJsonPath('data.0.services.0.price.formatted', '$12')
        ->assertJsonPath('meta.branch.slug', $branch->slug);
});

it('filters the gallery by gender and keeps unisex in every list', function () {
    Style::factory()->create(['gender_tag' => 'men', 'code' => '01']);
    Style::factory()->create(['gender_tag' => 'women', 'code' => '02']);
    Style::factory()->create(['gender_tag' => 'unisex', 'code' => '03']);

    $codes = collect($this->getJson('/api/v1/styles?gender=men')->assertOk()->json('data'))
        ->pluck('code');

    expect($codes)->toHaveCount(2)
        ->and($codes)->toContain('01', '03');
});

it('rejects a gender that is not a real tag', function () {
    $this->getJson('/api/v1/styles?gender=nonsense')->assertStatus(422);
});

it('returns 404 for a style that does not exist', function () {
    $this->getJson('/api/v1/styles/no-such-cut')->assertNotFound();
});

it('hides styles that are not active', function () {
    Style::factory()->create(['code' => '01']);
    Style::factory()->inactive()->create(['code' => '02']);

    expect($this->getJson('/api/v1/styles')->assertOk()->json('data'))->toHaveCount(1);
});

it('publishes only approved reviews and never flagged ones', function () {
    Review::factory()->published()->create();
    Review::factory()->create();
    Review::factory()->published()->flagged()->create();

    $this->getJson('/api/v1/reviews')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 1);
});

it('never exposes a staff phone number or email on the public team endpoint', function () {
    $user = User::factory()->create(['phone' => '+263781879820', 'email' => 'barber@example.com']);
    StaffProfile::factory()->for($user)->create();

    $response = $this->getJson('/api/v1/team')->assertOk();

    expect(json_encode($response->json()))
        ->not->toContain('+263781879820')
        ->not->toContain('barber@example.com');
});

it('leaves hidden staff off the team endpoint', function () {
    StaffProfile::factory()->create();
    StaffProfile::factory()->hidden()->create();

    $this->getJson('/api/v1/team')->assertOk()->assertJsonCount(1, 'data');
});

it('requires a token for the me endpoint', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});
