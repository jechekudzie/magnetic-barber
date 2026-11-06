<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyLedger;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A client's points.
 *
 * Points are earned by the shop when a visit completes, never by the client
 * asking for them, so there is nothing here that adds any. Spending happens
 * during booking, where there is a bill to take them off.
 */
class LoyaltyController extends Controller
{
    public function show(Request $request, LoyaltyService $loyalty): JsonResponse
    {
        $client = $request->user();

        return response()->json([
            'data' => $loyalty->summaryFor($client),
            /*
             * The ledger, in the client's words. Every line says what it was
             * for, because "you have 40 points" invites the question and the
             * desk should not have to answer it.
             */
            'history' => LoyaltyLedger::query()
                ->where('client_id', $client->id)
                ->with('appointment:id,reference')
                ->latest('id')
                ->limit(50)
                ->get()
                ->map(fn (LoyaltyLedger $line): array => [
                    'id' => $line->id,
                    'points' => $line->points,
                    'balance_after' => $line->balance_after,
                    'type' => $line->type,
                    'description' => $line->description,
                    'reference' => $line->appointment?->reference,
                    'at' => $line->created_at?->toIso8601String(),
                ])
                ->all(),
        ]);
    }
}
