<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);

        /*
         * Sign in with an email or a phone number.
         *
         * Fortify's username is the email column, and in this shop that
         * excludes most of the people who need to sign in: barbers are
         * created with a number and no address at all, and a client the
         * website registered during a booking has only ever given a number.
         *
         * Same resolution as the API, through the same trait, so a barber
         * typing 0782000002 reaches the same account on either surface.
         */
        Fortify::authenticateUsing(function (Request $request): ?User {
            $identifier = (string) $request->input(Fortify::username(), '');
            $password = (string) $request->input('password', '');

            $user = $this->findByIdentifier($identifier);

            /*
             * Compare against a real hash whether or not the account exists,
             * so the response takes the same time either way. Answering
             * faster for an unknown identifier is itself an answer.
             */
            $knownHash = $user?->password;
            $matches = Hash::check($password, $knownHash ?? self::TIMING_DECOY_HASH);

            if ($user === null || $knownHash === null || ! $matches || ! $user->is_active) {
                return null;
            }

            return $user;
        });
    }

    /**
     * A valid bcrypt hash of nothing anybody knows, so an unknown identifier
     * still costs a full hash comparison.
     */
    private const TIMING_DECOY_HASH = '$2y$12$ZZZZZZZZZZZZZZZZZZZZZeZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZ';

    /**
     * An email or a phone number. The number is normalised first, so
     * 0781879820 and +263781879820 find the same person.
     */
    private function findByIdentifier(string $identifier): ?User
    {
        if (Str::contains($identifier, '@')) {
            return User::query()->where('email', $identifier)->first();
        }

        $phone = Phone::normalise($identifier);

        return $phone === null
            ? null
            : User::query()->where('phone', $phone)->first();
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
