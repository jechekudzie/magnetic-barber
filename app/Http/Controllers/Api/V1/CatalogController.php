<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceCategoryResource;
use App\Services\CatalogPayload;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    public function __construct(
        private readonly CatalogPayload $payload,
        private readonly CatalogService $catalog,
    ) {}

    public function categories(): JsonResponse
    {
        return response()->json([
            'data' => ServiceCategoryResource::collection($this->catalog->categories()),
        ]);
    }

    public function plans(): JsonResponse
    {
        return response()->json(['data' => $this->payload->plans()]);
    }

    public function team(): JsonResponse
    {
        return response()->json(['data' => $this->payload->team()]);
    }

    public function reviews(): JsonResponse
    {
        $reviews = $this->payload->reviews(20);

        return response()->json([
            'data' => $reviews['data'],
            'meta' => [
                'average_rating' => $reviews['average_rating'],
                'total' => $reviews['total'],
            ],
        ]);
    }
}
