<?php

namespace App\Observers;

use App\Services\CatalogCache;
use Illuminate\Database\Eloquent\Model;

/**
 * Any catalog write invalidates the cached price list, gallery and team so an
 * owner changing a price sees it on the site immediately.
 */
class CatalogObserver
{
    public function __construct(private readonly CatalogCache $cache) {}

    public function saved(Model $model): void
    {
        $this->cache->flush();
    }

    public function deleted(Model $model): void
    {
        $this->cache->flush();
    }

    public function restored(Model $model): void
    {
        $this->cache->flush();
    }
}
