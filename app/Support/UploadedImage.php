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
    /** Gallery and staff shots: meant to be seen by anybody on the site. */
    public static function store(UploadedFile $file, string $folder): string
    {
        return self::put($file, $folder, 'public');
    }

    /**
     * A file nobody but the shop should see.
     *
     * Payment proofs are the case: an EcoCash screenshot carries a phone
     * number, a name and an amount. On the public disk the only thing
     * protecting it is an unguessable filename, which is not protection, it
     * is obscurity. These go on the private disk and are served through a
     * route that checks who is asking.
     */
    public static function storePrivately(UploadedFile $file, string $folder): string
    {
        return self::put($file, $folder, 'local');
    }

    private static function put(UploadedFile $file, string $folder, string $disk): string
    {
        $name = Str::ulid().'.'.$file->getClientOriginalExtension();

        $path = $file->storeAs($folder, $name, $disk);

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
