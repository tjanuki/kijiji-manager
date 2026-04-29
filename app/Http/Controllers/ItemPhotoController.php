<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ItemPhotoController extends Controller
{
    public function store(Request $request, Item $item): RedirectResponse
    {
        abort_unless($item->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'photo' => ['required', 'image', 'max:15360'],
        ]);

        $path = $validated['photo']->store("items/{$item->id}", 'public');
        $thumbnailPath = $this->createThumbnail($path);

        $position = (int) $item->photos()->max('position');
        $isFirst = $item->photos()->count() === 0;

        ItemPhoto::create([
            'item_id' => $item->id,
            'path' => $path,
            'thumbnail_path' => $thumbnailPath,
            'position' => $isFirst ? 0 : $position + 1,
            'is_primary' => $isFirst,
        ]);

        return back();
    }

    public function reorder(Request $request, Item $item): RedirectResponse
    {
        abort_unless($item->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer'],
        ]);

        $itemPhotoIds = $item->photos()->pluck('id')->all();

        foreach ($validated['order'] as $position => $photoId) {
            if (! in_array($photoId, $itemPhotoIds, true)) {
                continue;
            }
            ItemPhoto::where('id', $photoId)->update(['position' => $position]);
        }

        return back();
    }

    public function destroy(Request $request, Item $item, ItemPhoto $photo): RedirectResponse
    {
        abort_unless($item->user_id === $request->user()->id, 403);
        abort_unless($photo->item_id === $item->id, 404);

        Storage::disk('public')->delete(array_filter([$photo->path, $photo->thumbnail_path]));

        $wasPrimary = $photo->is_primary;
        $photo->delete();

        if ($wasPrimary) {
            $next = $item->photos()->orderBy('position')->first();
            $next?->update(['is_primary' => true]);
        }

        return back();
    }

    private function createThumbnail(string $originalPath): string
    {
        $manager = new ImageManager(new Driver);
        $absolute = Storage::disk('public')->path($originalPath);
        $image = $manager->decodePath($absolute);
        $image->scaleDown(width: 600);

        $thumbRelative = preg_replace('/(\.[a-z0-9]+)$/i', '_thumb$1', $originalPath);
        $image->save(Storage::disk('public')->path($thumbRelative));

        return $thumbRelative;
    }
}
