<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps clients out of the back of house.
 *
 * Every admin route already checks its own permission, so a client reached
 * them and got 403 — except /dashboard, which checks nothing and so handed
 * them the whole staff shell. Rather than bolt a permission onto the
 * dashboard, this sends anyone without a staff profile to their own page,
 * which is where they were trying to go.
 *
 * A redirect rather than a refusal because for a client this is not a denied
 * request, it is the wrong door.
 */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->isStaff()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'That is a staff area.'], 403)
                : redirect()->route('account');
        }

        return $next($request);
    }
}
