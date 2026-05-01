<?php

use App\Models\Item;
use App\Models\ItemPhoto;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Storage::fake('public');
});

it('downloads a zip of an item\'s photos in position order', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);

    Storage::disk('public')->put('items/x/a.jpg', 'A-CONTENT');
    Storage::disk('public')->put('items/x/b.jpg', 'B-CONTENT');
    Storage::disk('public')->put('items/x/c.jpg', 'C-CONTENT');

    ItemPhoto::factory()->create(['item_id' => $item->id, 'path' => 'items/x/a.jpg', 'position' => 2]);
    ItemPhoto::factory()->create(['item_id' => $item->id, 'path' => 'items/x/b.jpg', 'position' => 0]);
    ItemPhoto::factory()->create(['item_id' => $item->id, 'path' => 'items/x/c.jpg', 'position' => 1]);

    $response = actingAs($user)->get("/items/{$item->id}/photos.zip");

    $response->assertOk();
    $response->assertHeader('content-type', 'application/zip');

    // Save the streamed body to a temp file and inspect it.
    $tmp = tempnam(sys_get_temp_dir(), 'photos');
    file_put_contents($tmp, $response->streamedContent());

    $zip = new ZipArchive;
    expect($zip->open($tmp))->toBeTrue();
    expect($zip->numFiles)->toBe(3);

    // Position 0 should come first → b.jpg, then c.jpg, then a.jpg.
    expect($zip->getNameIndex(0))->toBe('01-b.jpg');
    expect($zip->getNameIndex(1))->toBe('02-c.jpg');
    expect($zip->getNameIndex(2))->toBe('03-a.jpg');
    expect($zip->getFromIndex(0))->toBe('B-CONTENT');

    $zip->close();
    @unlink($tmp);
});

it('renumbers position prefixes contiguously when a file is missing on disk', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);

    Storage::disk('public')->put('items/x/a.jpg', 'A');
    // b.jpg deliberately not stored — file is missing on disk
    Storage::disk('public')->put('items/x/c.jpg', 'C');

    ItemPhoto::factory()->create(['item_id' => $item->id, 'path' => 'items/x/a.jpg', 'position' => 0]);
    ItemPhoto::factory()->create(['item_id' => $item->id, 'path' => 'items/x/b.jpg', 'position' => 1]);
    ItemPhoto::factory()->create(['item_id' => $item->id, 'path' => 'items/x/c.jpg', 'position' => 2]);

    $response = actingAs($user)->get("/items/{$item->id}/photos.zip");

    $response->assertOk();

    $tmp = tempnam(sys_get_temp_dir(), 'photos');
    file_put_contents($tmp, $response->streamedContent());

    $zip = new ZipArchive;
    expect($zip->open($tmp))->toBeTrue();
    expect($zip->numFiles)->toBe(2);
    expect($zip->getNameIndex(0))->toBe('01-a.jpg');
    expect($zip->getNameIndex(1))->toBe('02-c.jpg');

    $zip->close();
    @unlink($tmp);
});

it('returns 404 when every photo file is missing on disk', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);
    // No files stored on disk
    ItemPhoto::factory()->create(['item_id' => $item->id, 'path' => 'items/missing/a.jpg', 'position' => 0]);
    ItemPhoto::factory()->create(['item_id' => $item->id, 'path' => 'items/missing/b.jpg', 'position' => 1]);

    actingAs($user)
        ->get("/items/{$item->id}/photos.zip")
        ->assertNotFound();
});

it('returns 404 when the item has no photos', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->get("/items/{$item->id}/photos.zip")
        ->assertNotFound();
});

it('forbids downloading another user\'s photos', function () {
    $user = User::factory()->create();
    $other = Item::factory()->create();
    ItemPhoto::factory()->create(['item_id' => $other->id]);

    actingAs($user)
        ->get("/items/{$other->id}/photos.zip")
        ->assertForbidden();
});

it('redirects guests', function () {
    $item = Item::factory()->create();
    ItemPhoto::factory()->create(['item_id' => $item->id]);

    $this->get("/items/{$item->id}/photos.zip")->assertRedirect('/login');
});
