<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Concerns\AuthenticatesWithPassword;
use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Signing a staff member in.
 *
 * Staff hold passwords, so this is a single step. The identifier is an email
 * or a phone number, because not every barber has a work email: the team
 * seeder creates several with a phone and no address at all.
 */
class StaffAuthController extends Controller
{
    use AuthenticatesWithPassword;

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:60'],
        ]);

        $user = $this->attemptPasswordLogin(
            $request,
            $validated['identifier'],
            $validated['password'],
            'staff',
        );

        if ($user === null) {
            $this->refuseLogin();
        }

        abort_if(! $user->is_active, 403, 'This account has been deactivated.');

        // A client has no business on the staff surface, even with a password.
        if ($user->branches()->doesntExist()) {
            $this->refuseLogin();
        }

        $user->forceFill(['last_seen_at' => now()])->save();

        /*
         * Roles are assigned per branch, so they only resolve once a branch is
         * in context. SetPermissionsBranch cannot do it for this request: it
         * runs before the token exists, so there is nobody to resolve a branch
         * for. Without this the response reports a barber with no roles at
         * all, and the app builds an empty navigation.
         */
        setPermissionsTeamId(
            $user->branches()
                ->orderByDesc('branch_user.is_primary')
                ->value('branches.id')
        );

        return response()->json([
            // Scoped to the staff surface, so a staff token and a client token
            // are not interchangeable.
            'token' => $user->createToken(
                $validated['device_name'] ?? 'magnetic-staff',
                ['staff'],
            )->plainTextToken,
            'user' => new MeResource($user->load(['staffProfile', 'branches'])),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Signed out.']);
    }
}
