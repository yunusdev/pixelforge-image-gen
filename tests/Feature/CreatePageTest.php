<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a freshly registered user lands on the Create page without verifying their email', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Sam Okafor',
        'email' => 'sam@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect(route('create', absolute: false));

    $this->get(route('create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Create'));
});

test('guests are sent to log in when opening the Create page', function () {
    $this->get(route('create'))->assertRedirect(route('login'));
});

test('an unverified user can open the Create and Credits pages', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('create'))->assertOk();
    $this->actingAs($user)->get(route('credits'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Credits'));
});

test('visitors opening the app are sent to sign up', function () {
    $this->get('/')->assertRedirect(route('register'));
});

test('signed-in users opening the app are sent to the Create page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertRedirect(route('create'));
});

test('the account menu receives the signed-in user name and email', function () {
    $user = User::factory()->create(['name' => 'Sam Okafor', 'email' => 'sam@example.com']);

    $this->actingAs($user)->get(route('create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.name', 'Sam Okafor')
            ->where('auth.user.email', 'sam@example.com'));
});
