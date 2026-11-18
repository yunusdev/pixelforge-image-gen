<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

test('make-admin marks the user with that email as an admin', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $this->artisan('users:make-admin', ['email' => 'ada@example.com'])
        ->expectsOutput('ada@example.com is now an admin.')
        ->assertSuccessful();

    expect($user->fresh()->is_admin)->toBeTrue();
});

test('make-admin fails for an unknown email', function () {
    $this->artisan('users:make-admin', ['email' => 'nobody@example.com'])
        ->expectsOutput('No user with the email nobody@example.com.')
        ->assertFailed();
});

test('guests are sent to log in', function () {
    $this->get(route('admin.report'))->assertRedirect(route('login'));
});

test('non-admins get the Admins only page from the server', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.report'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('AdminsOnly'));
});

test('admins can open the Admin report', function () {
    Http::fake(['pixelforge.test/usage*' => Http::response(pixelforgeUsage([]))]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.report'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/Report'));
});

test('admin can\'t be set by updating the profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Mallory', 'email' => $user->email, 'is_admin' => true,
    ]);

    expect($user->fresh()->is_admin)->toBeFalse();
});

test('pages tell the top bar whether to show the Admin report link', function (bool $admin) {
    $this->actingAs(User::factory()->state(['is_admin' => $admin])->create())
        ->get(route('create'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.is_admin', $admin));
})->with([true, false]);
