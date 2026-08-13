<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CatalogPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StyleController extends Controller
{
    public function __construct(private readonly CatalogPayload $payload) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gender' => ['nullable', 'string', 'in:men,women,unisex,kids,all'],
            'hair_type' => ['nullable', 'string', 'max:40'],
        ]);

        $styles = $this->payload->styles(
            $validated['gender'] ?? null,
            $validated['hair_type'] ?? null,
        );

        return response()->json([
            'data' => $styles,
            'meta' => [
                'total' => count($styles),
                'filters' => $this->payload->styleFilters(),
            ],
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $style = collect($this->payload->styles())->firstWhere('slug', $slug);

        if ($style === null) {
            throw new NotFoundHttpException('Style not found.');
        }

        return response()->json(['data' => $style]);
    }
}
