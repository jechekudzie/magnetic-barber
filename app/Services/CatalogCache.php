<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * The catalog is read on every page load and changes a few times a month, so it
 * is cached. Cache tags are not available on every store, so keys carry a
 * version number that any catalog write bumps.
 */
final class CatalogCache
{
    private const VERSION_KEY = 'catalog:version';

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public function remember(string $key, Closure $callback): mixed
    {
        $seconds = (int) config('magnetic.catalog_cache_seconds', 300);

        if ($seconds < 1) {
            return $callback();
        }

        /** @var TValue */
        return Cache::remember($this->versionedKey($key), $seconds, $callback);
    }

    /**
     * Bumping the version orphans every previously cached catalog key, which
     * then expires on its own. Nothing needs to be enumerated or deleted.
     */
    public function flush(): void
    {
        Cache::increment(self::VERSION_KEY);
    }

    public function version(): int
    {
        $version = Cache::get(self::VERSION_KEY);

        if ($version === null) {
            Cache::forever(self::VERSION_KEY, 1);

            return 1;
        }

        return (int) $version;
    }

    private function versionedKey(string $key): string
    {
        return "catalog:v{$this->version()}:{$key}";
    }
}
