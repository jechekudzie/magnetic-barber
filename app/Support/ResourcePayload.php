<?php

namespace App\Support;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Flattens an API Resource all the way down to plain arrays.
 *
 * A resource's own resolve() only shapes the top level: a nested resource
 * collection stays an object, which survives a JSON response but not a cache
 * round trip and not an Inertia prop. Serialising through JSON forces the
 * whole tree down to arrays, which is what both clients actually want.
 */
final class ResourcePayload
{
    /**
     * @return array<array-key, mixed>
     */
    public static function flatten(JsonResource $resource): array
    {
        $json = json_encode($resource);

        if ($json === false) {
            return [];
        }

        return json_decode($json, true) ?? [];
    }
}
