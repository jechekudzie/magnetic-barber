<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Rating a cut.
 *
 * Only a visit that actually happened, and only your own. A review is tied to
 * the appointment rather than left floating, which is what makes it possible
 * to say later which barber a score was about.
 *
 * Nothing is published automatically. A one star review with somebody's name
 * on it going straight onto the website is a decision the shop should make,
 * not a side effect of a tap.
 */
class ReviewController extends Controller
{
    public function store(Request $request, Appointment $appointment): JsonResponse
    {
        abort_if($appointment->client_id !== $request->user()->id, 404);

        abort_unless(
            $appointment->status === AppointmentStatus::Completed,
            422,
            'You can rate a cut once it has happened.',
        );

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        $review = Review::updateOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'client_id' => $appointment->client_id,
                'staff_id' => $appointment->staff_id,
                'branch_id' => $appointment->branch_id,
                'author_name' => $request->user()->name,
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                // The shop decides what goes on its own website.
                'is_public' => false,
                'published_at' => null,
            ],
        );

        return response()->json([
            'data' => [
                'rating' => $review->rating,
                'comment' => $review->comment,
                'at' => $review->created_at?->toIso8601String(),
            ],
            'message' => 'Thank you. The shop reads every one of these.',
        ], 201);
    }

    /**
     * What this client already said about a visit, so the app can show the
     * stars it has rather than asking twice.
     */
    public function show(Request $request, Appointment $appointment): JsonResponse
    {
        abort_if($appointment->client_id !== $request->user()->id, 404);

        $review = Review::query()->where('appointment_id', $appointment->id)->first();

        return response()->json([
            'data' => $review === null ? null : [
                'rating' => $review->rating,
                'comment' => $review->comment,
                'at' => $review->created_at?->toIso8601String(),
            ],
        ]);
    }
}
