<?php

use App\Models\Buyer;
use App\Models\Item;
use App\Models\ItemPhoto;
use App\Models\Pickup;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('walks the full sale lifecycle without JavaScript errors', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $buyer = Buyer::factory()->create([
        'user_id' => $user->id,
        'display_name' => 'Alex Smoke',
    ]);

    $this->actingAs($user);

    $page = visit('/dashboard');
    $page->assertSee('Dashboard')
        ->assertNoJavaScriptErrors();

    $page->navigate('/items/create')
        ->assertSee('New item')
        ->fill('title', 'Smoke Couch')
        ->fill('asking_price_cents', '12500')
        ->click('Create');

    $item = Item::where('user_id', $user->id)->where('title', 'Smoke Couch')->firstOrFail();
    expect($item->status->value)->toBe('draft');

    $page->assertPathIs("/items/{$item->id}/edit")
        ->assertSee('Add photo')
        ->assertNoJavaScriptErrors();

    // The Pest browser plugin can't forward multipart uploads through to Laravel
    // (LaravelHttpServer.php:257 — files aren't wired up), so we attach a photo
    // directly via the factory to satisfy the "≥1 photo" condition for ready.
    ItemPhoto::factory()->primary()->create([
        'item_id' => $item->id,
        'path' => 'items/'.$item->id.'/smoke.jpg',
    ]);

    $page->navigate("/items/{$item->id}")
        ->assertSee('Smoke Couch')
        ->assertNoJavaScriptErrors()
        ->click('Mark as ready')
        ->assertSee('Publish to Kijiji');

    expect($item->fresh()->status->value)->toBe('ready');

    $page->fill('item-'.$item->id.'-kijiji-url', 'https://www.kijiji.ca/v-couch/123')
        ->click('Mark as published')
        ->assertSee('Schedule pickup');

    expect($item->fresh()->status->value)->toBe('listed');

    $page->select('buyer_id', (string) $buyer->id)
        ->fill('notes', 'Saturday around 2pm, front porch')
        ->click('Schedule pickup')
        ->assertSee('Complete & mark sold')
        ->assertNoJavaScriptErrors();

    expect($item->fresh()->status->value)->toBe('reserved');
    $pickup = Pickup::where('buyer_id', $buyer->id)->latest('id')->firstOrFail();

    $page->navigate('/pickups')
        ->assertSee('Alex Smoke')
        ->assertSee('Saturday around 2pm')
        ->assertNoJavaScriptErrors();

    $page->navigate("/pickups/{$pickup->id}")
        ->assertNoJavaScriptErrors()
        ->click('Complete & mark sold')
        ->assertDontSee('Complete & mark sold');

    expect($pickup->fresh()->status->value)->toBe('completed');
    expect($pickup->fresh()->payment_status->value)->toBe('received');
    expect($item->fresh()->status->value)->toBe('sold');
});

it('shows a success toast after saving an item edit', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create([
        'user_id' => $user->id,
        'title' => 'Original Title',
    ]);

    $this->actingAs($user);

    $page = visit("/items/{$item->id}/edit");
    $page->assertSee('Edit item')
        ->fill('title', 'New Title')
        ->click('Save')
        ->assertSee('Item updated.')
        ->assertNoJavaScriptErrors();

    expect($item->fresh()->title)->toBe('New Title');
});
