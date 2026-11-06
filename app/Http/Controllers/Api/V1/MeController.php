<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Who am I, and what may I do here?
 *
 * The app calls this on launch to decide which navigation to build. The
 * branch it reports against comes from the X-Branch-Id header, handled by
 * SetPermissionsBranch, so a staff member switching branches gets the roles
 * and permissions they hold at that branch rather than everywhere.
 */
class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->load($user->isStaff()
            ? ['staffProfile', 'branches']
            : ['clientProfile']);

        return response()->json(['data' => new MeResource($user)]);
    }

    /**
     * Change your own name, email or number.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:80'],
            'email' => ['sometimes', 'nullable', 'email', 'max:120'],
            'phone' => ['sometimes', 'string', 'phone:ZW,mobile'],
        ], [
            'phone.phone' => 'That does not look like a Zimbabwean mobile number.',
        ]);

        if (array_key_exists('email', $validated) && filled($validated['email'])) {
            $taken = User::query()
                ->where('email', $validated['email'])
                ->whereKeyNot($user->id)
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    'email' => 'That email is already on another account.',
                ]);
            }
        }

        if (array_key_exists('phone', $validated)) {
            $phone = Phone::normalise($validated['phone']);

            $taken = User::query()
                ->where('phone', $phone)
                ->whereKeyNot($user->id)
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    'phone' => 'That number is already on another account.',
                ]);
            }

            // The number is the identity key the whole shop finds people by,
            // so changing it unverifies it until it is proven again.
            if ($phone !== $user->phone) {
                $validated['phone_verified_at'] = null;
            }
        }

        $user->forceFill($validated)->save();

        $user->load($user->isStaff() ? ['staffProfile', 'branches'] : ['clientProfile']);

        return response()->json(['data' => new MeResource($user)]);
    }

    /**
     * Change your own password.
     *
     * The current one is required even though the caller already holds a
     * token: a handset left unlocked on the counter should not be enough to
     * lock its owner out of their own account.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        if ($user->password === null || ! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'That is not your current password.',
            ]);
        }

        $user->forceFill(['password' => $validated['password']])->save();

        /*
         * Every other device is signed out. Changing a password is what
         * somebody does when they think another person has it, and leaving
         * those sessions alive makes the whole exercise pointless.
         */
        $current = $request->user()->currentAccessToken();

        $user->tokens()->whereKeyNot($current->getKey())->delete();

        return response()->json([
            'message' => 'Password changed. Any other device has been signed out.',
        ]);
    }
}
