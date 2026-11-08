<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A move the desk or the barber asked for that the visit will not take:
 * finishing a cut nobody started, moving a chair on a visit already paid and
 * closed, handing a booking to a barber who is cutting somebody else.
 *
 * Distinct from SlotTakenException, which the apps switch on to mean "pick
 * another time". This one has no retry: the message is the whole answer, so
 * it is written for the person holding the phone.
 */
class VisitActionException extends RuntimeException
{
    public function render(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->getMessage(),
                'code' => 'visit_action_refused',
                'errors' => [],
            ], 422);
        }

        // 422 on the web too, not a redirect: Inertia surfaces the body, and
        // the desk needs to be told the move was refused rather than quietly
        // handed back the page it was already looking at.
        return response($this->getMessage(), 422);
    }
}
