<?php

use App\Models\User;

test('guests see the welcome page', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});

test('authenticated users are redirected to the dashboard', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('home'));

    $response->assertRedirect(route('dashboard'));
});
