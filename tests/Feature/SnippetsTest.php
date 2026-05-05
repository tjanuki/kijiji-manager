<?php

use App\Models\User;
use App\Models\UserSetting;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->withoutVite();
});

it('redirects guests away from the snippets page', function () {
    get('/settings/snippets')->assertRedirect('/login');
});

it('renders the snippets page with current values', function () {
    $user = User::factory()->create();
    UserSetting::factory()->create([
        'user_id' => $user->id,
        'snippets' => ['pickup' => 'Front porch', 'payment' => 'Cash only'],
    ]);

    actingAs($user)
        ->get('/settings/snippets')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/snippets')
            ->where('snippets.pickup', 'Front porch')
            ->where('snippets.payment', 'Cash only')
        );
});

it('renders empty snippets when user has no settings row', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get('/settings/snippets')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/snippets')
            ->where('snippets.pickup', '')
            ->where('snippets.payment', '')
        );
});

it('creates a settings row on first save', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->patch('/settings/snippets', [
            'pickup' => 'Side door, Liberty Village',
            'payment' => 'E-transfer preferred',
        ])
        ->assertRedirect('/settings/snippets');

    $fresh = $user->fresh()->settings;
    expect($fresh)->not->toBeNull();
    expect($fresh->snippets['pickup'])->toBe('Side door, Liberty Village');
    expect($fresh->snippets['payment'])->toBe('E-transfer preferred');
});

it('updates an existing settings row', function () {
    $user = User::factory()->create();
    UserSetting::factory()->create([
        'user_id' => $user->id,
        'snippets' => ['pickup' => 'Old', 'payment' => 'Old'],
    ]);

    actingAs($user)
        ->patch('/settings/snippets', [
            'pickup' => 'New',
            'payment' => 'New',
        ])
        ->assertRedirect('/settings/snippets');

    expect($user->fresh()->settings->snippets['pickup'])->toBe('New');
});

it('accepts blank snippets', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->patch('/settings/snippets', ['pickup' => '', 'payment' => ''])
        ->assertRedirect('/settings/snippets');

    $fresh = $user->fresh()->settings;
    expect($fresh->snippets['pickup'])->toBe('');
    expect($fresh->snippets['payment'])->toBe('');
});

it('saves and returns reply templates', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->patch('/settings/snippets', [
            'pickup' => 'Front door',
            'payment' => 'E-transfer',
            'reply_templates' => [
                ['label' => 'Still available', 'body' => 'Yes — still available!'],
                ['label' => 'Lowest', 'body' => 'Lowest is $X.'],
            ],
        ])
        ->assertRedirect('/settings/snippets');

    actingAs($user)
        ->get('/settings/snippets')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/snippets')
            ->where('snippets.reply_templates.0.label', 'Still available')
            ->where('snippets.reply_templates.1.body', 'Lowest is $X.')
        );
});
