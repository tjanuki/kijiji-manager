<?php

use App\Models\Item;
use App\Models\ItemPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Storage::fake('public');
});

it('stores a photo and generates a thumbnail', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);

    $file = UploadedFile::fake()->image('couch.jpg', 1200, 900);

    actingAs($user)
        ->post("/items/{$item->id}/photos", ['photo' => $file])
        ->assertRedirect();

    $photo = $item->photos()->first();
    expect($photo)->not->toBeNull();
    expect($photo->is_primary)->toBeTrue();
    expect($photo->thumbnail_path)->not->toBeNull();
    Storage::disk('public')->assertExists($photo->path);
    Storage::disk('public')->assertExists($photo->thumbnail_path);

    actingAs($user)->get("/items/{$item->id}/edit")
        ->assertInertia(fn ($page) => $page
            ->hasFlash('toast.type', 'success')
            ->hasFlash('toast.message', 'Photo uploaded.')
        );
});

it('does not mark subsequent photos as primary', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);
    ItemPhoto::factory()->primary()->create(['item_id' => $item->id, 'position' => 0]);

    $file = UploadedFile::fake()->image('extra.jpg', 800, 600);

    actingAs($user)->post("/items/{$item->id}/photos", ['photo' => $file]);

    $second = $item->photos()->reorder('position', 'desc')->first();
    expect($second->is_primary)->toBeFalse();
    expect($second->position)->toBe(1);
});

it('forbids uploading to another user\'s item', function () {
    $user = User::factory()->create();
    $other = Item::factory()->create();

    $file = UploadedFile::fake()->image('couch.jpg');

    actingAs($user)
        ->post("/items/{$other->id}/photos", ['photo' => $file])
        ->assertForbidden();
});

it('deletes a photo and reassigns primary', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);

    Storage::disk('public')->put('items/x/a.jpg', 'a');
    Storage::disk('public')->put('items/x/b.jpg', 'b');
    $a = ItemPhoto::factory()->primary()->create(['item_id' => $item->id, 'path' => 'items/x/a.jpg', 'position' => 0]);
    $b = ItemPhoto::factory()->create(['item_id' => $item->id, 'path' => 'items/x/b.jpg', 'position' => 1]);

    actingAs($user)
        ->delete("/items/{$item->id}/photos/{$a->id}")
        ->assertRedirect();

    expect(ItemPhoto::find($a->id))->toBeNull();
    Storage::disk('public')->assertMissing('items/x/a.jpg');
    expect($b->fresh()->is_primary)->toBeTrue();

    actingAs($user)->get("/items/{$item->id}/edit")
        ->assertInertia(fn ($page) => $page
            ->hasFlash('toast.type', 'success')
            ->hasFlash('toast.message', 'Photo removed.')
        );
});

it('forbids deleting another user\'s photo', function () {
    $user = User::factory()->create();
    $other = ItemPhoto::factory()->create();

    actingAs($user)
        ->delete("/items/{$other->item_id}/photos/{$other->id}")
        ->assertForbidden();
});

it('reorders photos by id list', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);

    $a = ItemPhoto::factory()->create(['item_id' => $item->id, 'position' => 0]);
    $b = ItemPhoto::factory()->create(['item_id' => $item->id, 'position' => 1]);
    $c = ItemPhoto::factory()->create(['item_id' => $item->id, 'position' => 2]);

    actingAs($user)
        ->patch("/items/{$item->id}/photos/reorder", [
            'order' => [$c->id, $a->id, $b->id],
        ])
        ->assertRedirect();

    expect($a->fresh()->position)->toBe(1);
    expect($b->fresh()->position)->toBe(2);
    expect($c->fresh()->position)->toBe(0);
});
