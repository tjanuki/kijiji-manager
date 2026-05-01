<?php

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\UniqueConstraintViolationException;

it('creates a user setting via factory linked to a user', function () {
    $setting = UserSetting::factory()->create();

    expect($setting->user)->toBeInstanceOf(User::class);
    expect($setting->snippets)->toBeArray();
});

it('casts snippets to array', function () {
    $setting = UserSetting::factory()->create([
        'snippets' => ['pickup' => 'Front porch', 'payment' => 'Cash or e-transfer'],
    ]);

    expect($setting->fresh()->snippets)->toBe([
        'pickup' => 'Front porch',
        'payment' => 'Cash or e-transfer',
    ]);
});

it('exposes settings via user hasOne relation', function () {
    $user = User::factory()->create();
    $setting = UserSetting::factory()->create(['user_id' => $user->id]);

    expect($user->settings->id)->toBe($setting->id);
});

it('enforces one setting per user', function () {
    $user = User::factory()->create();
    UserSetting::factory()->create(['user_id' => $user->id]);

    expect(fn () => UserSetting::factory()->create(['user_id' => $user->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});
