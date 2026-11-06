<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Concerns\AuthenticatesWithPassword;
use App\Enums\ClientSource;
use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Models\Branch;
use App\Models\User;
use App\Services\ClientAccountService;
use App\Services\Otp\OtpService;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Client accounts.
 *
 * A client signs in with an email or a phone number and a password. The
 * number is still the identity key everything else in the shop is built on —
 * the desk, the diary and the loyalty ledger all find people by phone — so a
 * number is required even when an email is given.
 *
 * Booking never comes through here. A client who has only ever booked has an
 * account with no password, created by that booking; registering with the
 * same number attaches a password to it rather than starting a second file,
 * so their history and their points follow them in.
 *
 * The one time code flow below still works and is kept deliberately. It is
 * the stronger credential and wants no password at all; it is waiting on the
 * shop having an SMS or WhatsApp account to deliver with.
 */
class ClientAuthController extends Controller
{
    use AuthenticatesWithPassword;

    /**
     * Create an account, or put a password on one that a booking created.
     */
    public function register(Request $request, ClientAccountService $accounts): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'phone' => ['required', 'string', 'phone:ZW,mobile'],
            'email' => ['nullable', 'email', 'max:120'],
            /*
             * Eight characters, not the house policy. That policy guards staff
             * accounts, which read other people's records; this guards a
             * client's own bookings, and a rule that forces symbols onto
             * somebody booking a haircut on a phone buys very little and
             * costs the shop support calls.
             */
            'password' => ['required', 'string', Password::min(8)],
            'branch' => ['nullable', 'string', 'exists:branches,slug'],
        ], [
            'phone.phone' => 'That does not look like a Zimbabwean mobile number.',
        ]);

        $phone = Phone::normalise($validated['phone']);
        $existing = User::query()->where('phone', $phone)->first();

        if ($existing?->password !== null) {
            throw ValidationException::withMessages([
                'phone' => 'There is already an account on that number. Sign in instead.',
            ]);
        }

        if (isset($validated['email'])) {
            $emailTaken = User::query()
                ->where('email', $validated['email'])
                ->when($existing !== null, fn ($query) => $query->whereKeyNot($existing->id))
                ->exists();

            if ($emailTaken) {
                throw ValidationException::withMessages([
                    'email' => 'That email is already on another account.',
                ]);
            }
        }

        $user = DB::transaction(function () use ($validated, $accounts, $existing): User {
            // register() finds before it creates, so a number the shop has
            // seen lands on its own record with its visits and points intact.
            // Keep the name the shop already has on file: a booking form is
            // not the place to rename an existing client.
            $knownName = $existing?->name;

            $client = $accounts->register(
                $knownName ?? $validated['name'],
                $validated['phone'],
                $this->branchFrom($validated['branch'] ?? null),
                ClientSource::App,
            );

            $client->forceFill(array_filter([
                'password' => $validated['password'],
                'email' => $validated['email'] ?? null,
            ]))->save();

            return $client;
        });

        return response()->json([
            'token' => $user->createToken('magnetic-app', ['client'])->plainTextToken,
            'user' => new MeResource($user->fresh()->load('clientProfile')),
        ], 201);
    }

    /**
     * Sign in with an email or a phone number, and a password.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string'],
        ]);

        $user = $this->attemptPasswordLogin(
            $request,
            $validated['identifier'],
            $validated['password'],
            'client',
        );

        if ($user === null) {
            $this->refuseLogin();
        }

        abort_if(! $user->is_active, 403, 'This account is closed. Please call the shop.');

        // Staff have their own door, and their own token ability. Letting a
        // staff password through here would hand out a client token for an
        // account that can see other people's records.
        if ($user->isStaff()) {
            $this->refuseLogin();
        }

        return response()->json([
            'token' => $user->createToken('magnetic-app', ['client'])->plainTextToken,
            'user' => new MeResource($user->load('clientProfile')),
        ]);
    }

    /**
     * Step one of the code flow. Send a code to the number.
     *
     * This answers the same way whether or not the number is known. "No
     * account with that number" is a free tool for working out which of a list
     * of numbers belongs to a client of this shop.
     */
    public function start(Request $request, OtpService $otp): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'phone:ZW,mobile'],
        ], [
            'phone.phone' => 'That does not look like a Zimbabwean mobile number.',
        ]);

        $issued = $otp->issue($validated['phone'], $request->ip());

        return response()->json([
            'message' => 'We have sent you a code.',
            'expires_in_minutes' => $issued['expires_in_minutes'],
            // Present only while no channel actually delivers, so the flow can
            // be driven on a laptop. A real SMS channel removes it.
            'code' => $issued['code'],
            'is_registered' => $this->existingClient($validated['phone']) !== null,
        ]);
    }

    /**
     * Step two of the code flow. Exchange a good code for a token.
     */
    public function verify(Request $request, OtpService $otp, ClientAccountService $accounts): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'phone:ZW,mobile'],
            'code' => ['required', 'string', 'size:6'],
            'name' => ['nullable', 'string', 'min:2', 'max:80'],
            'branch' => ['nullable', 'string', 'exists:branches,slug'],
        ]);

        if (! $otp->verify($validated['phone'], $validated['code'])) {
            // One message for every failure. Which one it was is not the
            // client's business and is useful to nobody but an attacker.
            throw ValidationException::withMessages([
                'code' => 'That code is not right, or it has expired. Ask for a new one.',
            ]);
        }

        $user = $this->existingClient($validated['phone']);

        if ($user === null) {
            if (blank($validated['name'] ?? null)) {
                throw ValidationException::withMessages([
                    'name' => 'We have not met this number before. What should we call you?',
                ]);
            }

            $user = $accounts->register(
                $validated['name'],
                $validated['phone'],
                $this->branchFrom($validated['branch'] ?? null),
                ClientSource::App,
            );
        }

        abort_if(! $user->is_active, 403, 'This account is closed. Please call the shop.');

        $user->forceFill(['phone_verified_at' => now()])->save();

        return response()->json([
            'token' => $user->createToken('magnetic-app', ['client'])->plainTextToken,
            'user' => new MeResource($user->load(['clientProfile'])),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    /**
     * A known client, as opposed to a staff member who happens to share the
     * number. Staff sign in at their own door.
     */
    private function existingClient(string $phone): ?User
    {
        return User::query()
            ->where('phone', Phone::normalise($phone))
            ->whereHas('clientProfile')
            ->first();
    }

    private function branchFrom(?string $slug): Branch
    {
        if ($slug !== null) {
            $branch = Branch::query()->where('slug', $slug)->first();

            if ($branch !== null) {
                return $branch;
            }
        }

        return Branch::query()->active()->ordered()->firstOrFail();
    }
}
