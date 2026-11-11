<?php

namespace App\Http\Middleware;

use App\Services\Otp\OtpChannel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the code flow while nothing can actually send a code.
 *
 * `start` hands the code back in its own response when the bound channel does
 * not deliver, which is what makes the flow drivable on a laptop. Left open on
 * a public host that is an account takeover: type a client's number, read the
 * code out of the response, exchange it for a token.
 *
 * Keyed on delivers() rather than the environment, for the same reason the
 * response is: binding a real SMS or WhatsApp channel is what opens the flow,
 * and getting APP_ENV wrong must not be what opens it.
 */
class RequireDeliverableOtp
{
    public function __construct(private OtpChannel $channel) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->channel->delivers()) {
            return response()->json([
                'message' => 'Codes are not switched on. Sign in with your password.',
                'code' => 'otp_unavailable',
                'errors' => [],
            ], 503);
        }

        return $next($request);
    }
}
