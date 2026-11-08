<?php

use App\Http\Controllers\Api\V1\Auth\ClientAuthController;
use App\Http\Controllers\Api\V1\Auth\StaffAuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\LoyaltyController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\Staff\DiaryController;
use App\Http\Controllers\Api\V1\Staff\EarningsController;
use App\Http\Controllers\Api\V1\Staff\PaymentDeskController;
use App\Http\Controllers\Api\V1\StyleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| Versioned from the first commit. The website and the mobile app read the
| same catalog through the same service classes, so neither can drift.
|
| The catalog is public: a price list, a gallery and opening hours are not
| private data, and the QR flow must work before anyone has an account.
|
| Everything else sits behind a Sanctum token carrying one of two abilities.
| "client" and "staff" are deliberately not interchangeable: a client token
| presented to a staff route is refused by ability, before any role or
| permission is consulted.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('branches', [BranchController::class, 'index'])->name('branches.index');
    Route::get('branches/{branch}', [BranchController::class, 'show'])->name('branches.show');
    Route::get('branches/{branch}/services', [BranchController::class, 'services'])->name('branches.services');

    Route::get('service-categories', [CatalogController::class, 'categories'])->name('service-categories.index');
    Route::get('plans', [CatalogController::class, 'plans'])->name('plans.index');
    Route::get('team', [CatalogController::class, 'team'])->name('team.index');
    Route::get('reviews', [CatalogController::class, 'reviews'])->name('reviews.index');

    Route::get('styles', [StyleController::class, 'index'])->name('styles.index');
    Route::get('styles/{slug}', [StyleController::class, 'show'])->name('styles.show');

    // Public on purpose: somebody deciding whether this shop is worth the trip
    // should see the open times before being asked to sign in.
    Route::get('availability', [BookingController::class, 'availability'])->name('availability');
    Route::get('availability/first', [BookingController::class, 'firstBookableDate'])
        ->name('availability.first');

    /*
     * Booking without an account, as the website has always worked. The
     * account is created by this first booking. Throttled to match the web
     * booking form, because nothing here proves the number belongs to whoever
     * typed it.
     */
    // "Do we know this number?" asked while the client types it.
    Route::post('bookings/lookup', [BookingController::class, 'lookup'])
        ->middleware('throttle:20,1')
        ->name('bookings.lookup');

    Route::post('bookings/guest', [BookingController::class, 'storeAsGuest'])
        ->middleware('throttle:10,1')
        ->name('bookings.guest');

    /*
     * Signing in. Clients prove they hold a phone number; staff hold a
     * password. Both throttled: a six digit code and a password are only
     * secret while guessing them is expensive.
     */
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('client/register', [ClientAuthController::class, 'register'])
            ->middleware('throttle:6,1')
            ->name('client.register');

        Route::post('client/login', [ClientAuthController::class, 'login'])
            ->middleware('throttle:10,1')
            ->name('client.login');

        // The code flow, kept for when the shop has an SMS account.
        Route::post('client/start', [ClientAuthController::class, 'start'])
            ->middleware('throttle:6,1')
            ->name('client.start');

        Route::post('client/verify', [ClientAuthController::class, 'verify'])
            ->middleware('throttle:10,1')
            ->name('client.verify');

        Route::post('staff/login', [StaffAuthController::class, 'login'])
            ->middleware('throttle:10,1')
            ->name('staff.login');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('client/logout', [ClientAuthController::class, 'logout'])->name('client.logout');
            Route::post('staff/logout', [StaffAuthController::class, 'logout'])->name('staff.logout');
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [MeController::class, 'show'])->name('me');
        Route::put('me', [MeController::class, 'update'])->name('me.update');
        Route::put('me/password', [MeController::class, 'updatePassword'])
            ->middleware('throttle:6,1')
            ->name('me.password');

        /*
         * The client surface. ability:client refuses a staff token here, so
         * the two sides of the app cannot reach into each other even though
         * both are the same user table.
         */
        Route::middleware('ability:client')->group(function () {
            Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
            Route::post('bookings', [BookingController::class, 'store'])->name('bookings.store');
            Route::get('bookings/{appointment}', [BookingController::class, 'show'])->name('bookings.show');
            Route::post('bookings/{appointment}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');

            // Paying for your own booking. EcoCash and InnBucks settle
            // elsewhere, so this records a claim and a screenshot.
            // Points, and what earned them.
            Route::get('loyalty', [LoyaltyController::class, 'show'])->name('loyalty');

            // Rating a cut that has happened.
            Route::get('bookings/{appointment}/review', [ReviewController::class, 'show'])
                ->name('bookings.review');
            Route::post('bookings/{appointment}/review', [ReviewController::class, 'store'])
                ->middleware('throttle:10,1')
                ->name('bookings.review.store');

            Route::get('bookings/{appointment}/payment', [PaymentController::class, 'show'])
                ->name('bookings.payment');
            Route::post('bookings/{appointment}/payment', [PaymentController::class, 'store'])
                ->middleware('throttle:10,1')
                ->name('bookings.payment.store');
        });

        /*
         * The staff surface. Past the ability gate every route still checks a
         * permission, because "is staff" and "may do this" are different
         * questions: a barber and the owner both hold a staff token.
         */
        Route::middleware('ability:staff')->prefix('staff')->name('staff.')->group(function () {
            Route::get('diary', [DiaryController::class, 'index'])->name('diary');

            // One tap each. The clock these start is what makes an
            // overrunning cut block the chair in the booking grid.
            Route::get('appointments/{appointment}', [DiaryController::class, 'show'])
                ->name('appointments.show');
            Route::put('appointments/{appointment}/note', [DiaryController::class, 'note'])
                ->name('appointments.note');

            Route::put('appointments/{appointment}/start', [DiaryController::class, 'start'])
                ->name('appointments.start');

            /*
             * Find whoever is standing at the desk, then take the booking.
             *
             * Both exist for one case: the receptionist is out, the client is
             * waiting, and the barber standing there is not the one the
             * booking was auto-assigned to. Without claim he cuts under
             * somebody else's name and the wrong barber is paid.
             *
             * Claiming only moves work onto yourself, so it needs none of
             * reception's power to move other people's. The controller
             * enforces that; the route only gates on being staff.
             */
            Route::get('lookup', [DiaryController::class, 'lookup'])
                ->middleware('throttle:30,1')
                ->name('lookup');
            Route::put('appointments/{appointment}/claim', [DiaryController::class, 'claim'])
                ->name('appointments.claim');
            Route::put('appointments/{appointment}/finish', [DiaryController::class, 'finish'])
                ->name('appointments.finish');
            Route::put('appointments/{appointment}/status', [DiaryController::class, 'updateStatus'])
                ->name('appointments.status');

            // The till.
            Route::post('appointments/{appointment}/payments', [PaymentDeskController::class, 'store'])
                ->name('payments.store');
            Route::get('payments/awaiting', [PaymentDeskController::class, 'awaitingCheck'])
                ->name('payments.awaiting');
            Route::put('payments/{payment}/confirm', [PaymentDeskController::class, 'confirm'])
                ->name('payments.confirm');
            Route::put('payments/{payment}/reject', [PaymentDeskController::class, 'reject'])
                ->name('payments.reject');

            Route::get('earnings/today', [EarningsController::class, 'today'])->name('earnings.today');
            Route::get('earnings/branch', [EarningsController::class, 'branch'])->name('earnings.branch');
            Route::get('earnings', [EarningsController::class, 'run'])->name('earnings.run');
        });
    });
});
