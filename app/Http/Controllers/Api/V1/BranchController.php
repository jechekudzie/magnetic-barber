<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\CatalogPayload;
use Illuminate\Http\JsonResponse;

class BranchController extends Controller
{
    public function __construct(private readonly CatalogPayload $payload) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->payload->branches()]);
    }

    public function show(Branch $branch): JsonResponse
    {
        return response()->json([
            'data' => $this->payload->branch($branch),
            'meta' => ['team' => $this->payload->team($branch)],
        ]);
    }

    /**
     * The price list for one branch. Price and duration come from the pivot,
     * so this is the only endpoint that can answer "what does a fade cost".
     */
    public function services(Branch $branch): JsonResponse
    {
        return response()->json([
            'data' => $this->payload->priceList($branch),
            'meta' => [
                'branch' => $this->payload->branch($branch),
                'currency' => config('magnetic.default_currency'),
            ],
        ]);
    }
}
