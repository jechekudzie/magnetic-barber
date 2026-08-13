<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores an uploaded photo and returns its path.
 *
 * Gallery and staff shots arrive as multi-megabyte phone pictures. They are
 * stored as-is for now; resizing belongs with the media pipeline, but the size
 * limit in the form request keeps a 12MB upload from ever landing here.
 */
final class UploadedImage
{
    public static function store(UploadedFile $file, string $folder): string
    {
        $name = Str::ulid().'.'.$file->getClientOriginalExtension();

        $path = $file->storeAs($folder, $name, 'public');

        if ($path === false) {
            throw new RuntimeException('Could not save that photo. Check the storage disk.');
        }

        return $path;
    }

    /**
     * Replaces an existing photo, deleting the old file so the disk does not
     * fill with orphans every time someone reuploads.
     */
    public static function replace(?string $existing, UploadedFile $file, string $folder): string
    {
        self::forget($existing);

        return self::store($file, $folder);
    }

    public static function forget(?string $path): void
    {
        if ($path !== null && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
