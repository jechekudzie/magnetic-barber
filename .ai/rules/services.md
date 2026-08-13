---
paths:
  - 'app/Services/**'
---

# Services

## Never cache Eloquent models or collections
Laravel 13 cache stores unserialize with an `allowed_classes` allowlist, so a cached Eloquent model or Collection comes back as `__PHP_Incomplete_Class` and blows up on first method call. It fails silently on a cache miss and only breaks on the second read, which makes it easy to ship.

Cache plain arrays instead. In this app that means caching the resolved API Resource payload (see App\Services\CatalogPayload), never the query result. Service classes return live Eloquent; CatalogPayload caches the arrays those get shaped into.
