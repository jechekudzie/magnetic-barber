<?php

use App\Models\Branch;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

/*
 * This used to assert that any signed-in user could see the dashboard, which
 * was true and was the bug: clients and staff share a users table, so a
 * client who signed in on the website landed in the staff shell.
 */
test('staff can visit the dashboard', function () {
    $branch = Branch::factory()->create();

    $staff = User::factory()->create();
    $staff->branches()->attach($branch, ['is_primary' => true]);

    $this->actingAs($staff)->get(route('dashboard'))->assertOk();
});

test('a client is sent to their own page instead', function () {
    // No branch and no staff profile: somebody the shop cuts, not employs.
    $client = User::factory()->create();

    $this->actingAs($client)
        ->get(route('dashboard'))
        ->assertRedirect(route('account'));
});
