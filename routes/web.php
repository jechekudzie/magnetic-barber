<?php

use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\CatalogAdminController;
use App\Http\Controllers\Admin\CategoryCrudController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LoyaltyController;
use App\Http\Controllers\Admin\PlanCrudController;
use App\Http\Controllers\Admin\PricingController;
use App\Http\Controllers\Admin\ServiceCrudController;
use App\Http\Controllers\Admin\StyleCrudController;
use App\Http\Controllers\Admin\TeamCrudController;
use App\Http\Controllers\Site\BookController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\PlanController;
use App\Http\Controllers\Site\ServiceController;
use App\Http\Controllers\Site\SkinController;
use App\Http\Controllers\Site\StyleController;
use App\Http\Controllers\Site\VisitController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public site
|--------------------------------------------------------------------------
|
| Anyone. Marketing, prices, the gallery and the ways to book.
|
*/

Route::get('/', HomeController::class)->name('home');
Route::get('services', ServiceController::class)->name('services');
Route::get('styles', [StyleController::class, 'index'])->name('styles');
Route::get('styles/{slug}', [StyleController::class, 'show'])->name('styles.show');
Route::get('skin', SkinController::class)->name('skin');
Route::get('plans', PlanController::class)->name('plans');
Route::get('visit', VisitController::class)->name('visit');

/*
 * The booking wizard. Lookup is rate limited because it answers "is this
 * number known to you", which is exactly what someone enumerating numbers
 * would want to ask repeatedly.
 */
Route::get('book', [BookController::class, 'show'])->name('book');
Route::post('book/lookup', [BookController::class, 'lookup'])
    ->middleware('throttle:10,1')->name('booking.lookup');
Route::get('book/availability', [BookController::class, 'availability'])
    ->middleware('throttle:60,1')->name('booking.availability');
Route::post('book', [BookController::class, 'store'])
    ->middleware('throttle:10,1')->name('booking.store');
Route::get('booked/{appointment}', [BookController::class, 'confirmed'])->name('booking.confirmed');

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
|
| Staff, behind auth.
|
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('pricing', [PricingController::class, 'index'])
            ->middleware('can:price.view')->name('pricing');
        Route::put('pricing/{service}', [PricingController::class, 'update'])
            ->middleware('can:price.update')->name('pricing.update');

        Route::get('bookings', [BookingController::class, 'index'])
            ->middleware('can:appointment.view.branch')->name('bookings');
        Route::put('bookings/{appointment}/status', [BookingController::class, 'updateStatus'])
            ->middleware('can:appointment.update')->name('bookings.status');

        Route::get('services', [CatalogAdminController::class, 'services'])
            ->middleware('can:service.view')->name('services');
        Route::put('services/{service}/toggle', [CatalogAdminController::class, 'toggleService'])
            ->middleware('can:service.update')->name('services.toggle');
        Route::get('services/create', [ServiceCrudController::class, 'create'])
            ->middleware('can:service.create')->name('services.create');
        Route::post('services', [ServiceCrudController::class, 'store'])
            ->middleware('can:service.create')->name('services.store');
        Route::get('services/{service}/edit', [ServiceCrudController::class, 'edit'])
            ->middleware('can:service.update')->name('services.edit');
        Route::put('services/{service}', [ServiceCrudController::class, 'update'])
            ->middleware('can:service.update')->name('services.update');
        Route::delete('services/{service}', [ServiceCrudController::class, 'destroy'])
            ->middleware('can:service.update')->name('services.destroy');

        Route::get('categories', [CategoryCrudController::class, 'index'])
            ->middleware('can:service.view')->name('categories');
        Route::post('categories', [CategoryCrudController::class, 'store'])
            ->middleware('can:service.create')->name('categories.store');
        Route::put('categories/{category}', [CategoryCrudController::class, 'update'])
            ->middleware('can:service.update')->name('categories.update');
        Route::delete('categories/{category}', [CategoryCrudController::class, 'destroy'])
            ->middleware('can:service.update')->name('categories.destroy');

        Route::get('styles', [CatalogAdminController::class, 'styles'])
            ->middleware('can:service.view')->name('styles');
        Route::put('styles/{style}/feature', [CatalogAdminController::class, 'toggleStyleFeatured'])
            ->middleware('can:service.update')->name('styles.feature');
        Route::get('styles/create', [StyleCrudController::class, 'create'])
            ->middleware('can:service.create')->name('styles.create');
        Route::post('styles', [StyleCrudController::class, 'store'])
            ->middleware('can:service.create')->name('styles.store');
        Route::get('styles/{style}/edit', [StyleCrudController::class, 'edit'])
            ->middleware('can:service.update')->name('styles.edit');
        // POST not PUT: a photo upload has to be multipart, which PHP does not
        // parse on a PUT body. _method spoofing is handled by Laravel.
        Route::post('styles/{style}', [StyleCrudController::class, 'update'])
            ->middleware('can:service.update')->name('styles.update');
        Route::delete('styles/{style}', [StyleCrudController::class, 'destroy'])
            ->middleware('can:service.update')->name('styles.destroy');

        Route::get('plans', [CatalogAdminController::class, 'plans'])
            ->middleware('can:plan.manage')->name('plans');
        Route::get('plans/create', [PlanCrudController::class, 'create'])
            ->middleware('can:plan.manage')->name('plans.create');
        Route::post('plans', [PlanCrudController::class, 'store'])
            ->middleware('can:plan.manage')->name('plans.store');
        Route::get('plans/{plan}/edit', [PlanCrudController::class, 'edit'])
            ->middleware('can:plan.manage')->name('plans.edit');
        Route::put('plans/{plan}', [PlanCrudController::class, 'update'])
            ->middleware('can:plan.manage')->name('plans.update');
        Route::delete('plans/{plan}', [PlanCrudController::class, 'destroy'])
            ->middleware('can:plan.manage')->name('plans.destroy');

        Route::get('loyalty', [LoyaltyController::class, 'index'])
            ->middleware('can:loyalty.view')->name('loyalty');
        Route::put('loyalty', [LoyaltyController::class, 'update'])
            ->middleware('can:loyalty.adjust')->name('loyalty.update');
        Route::post('loyalty/adjust', [LoyaltyController::class, 'adjust'])
            ->middleware('can:loyalty.adjust')->name('loyalty.adjust');

        Route::get('team', [CatalogAdminController::class, 'team'])
            ->middleware('can:staff.view')->name('team');
        Route::get('team/{staffProfile}/edit', [TeamCrudController::class, 'edit'])
            ->middleware('can:staff.update')->name('team.edit');
        Route::post('team/{staffProfile}', [TeamCrudController::class, 'update'])
            ->middleware('can:staff.update')->name('team.update');

        Route::get('reviews', [CatalogAdminController::class, 'reviews'])
            ->middleware('can:review.view')->name('reviews');
        Route::put('reviews/{review}/toggle', [CatalogAdminController::class, 'toggleReview'])
            ->middleware('can:review.publish')->name('reviews.toggle');

        Route::get('branches', [CatalogAdminController::class, 'branches'])
            ->middleware('can:branch.view')->name('branches');
        Route::put('branches/{branch}/hours', [CatalogAdminController::class, 'updateBranchHours'])
            ->middleware('can:branch.update')->name('branches.hours');
    });
});

require __DIR__.'/settings.php';
