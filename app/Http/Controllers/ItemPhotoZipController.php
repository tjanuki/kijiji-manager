<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ItemPhotoZipController extends Controller
{
    public function __invoke(Request $request, Item $item): StreamedResponse
    {
        abort_unless($item->user_id === $request->user()->id, 403);

        $photos = $item->photos()->orderBy('position')->get();
        abort_if($photos->isEmpty(), 404);

        $tmp = tempnam(sys_get_temp_dir(), 'photos_');
        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Could not create zip archive.');
        }

        $added = 0;
        foreach ($photos as $photo) {
            $absolute = Storage::disk('public')->path($photo->path);
            if (! is_file($absolute)) {
                continue;
            }
            $added++;
            $entry = sprintf('%02d-%s', $added, basename($photo->path));
            $zip->addFile($absolute, $entry);
        }

        $zip->close();

        if ($added === 0) {
            @unlink($tmp);
            abort(404);
        }

        $filename = "item-{$item->id}-photos.zip";

        return response()->stream(function () use ($tmp) {
            try {
                readfile($tmp);
            } finally {
                @unlink($tmp);
            }
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
