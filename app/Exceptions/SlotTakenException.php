<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Two clients tapping the same 10:00 slot is not a rare edge case, it is
 * Saturday morning. The apps switch on the `code`, never the message text.
 */
class SlotTakenException extends RuntimeException
{
    public function __construct(string $message = 'That time has just been taken. Please pick another.')
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse|bool
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->getMessage(),
                'code' => 'slot_taken',
                'errors' => [],
            ], 409);
        }

        return false;
    }
}
