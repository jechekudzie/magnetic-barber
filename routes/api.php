<?php

use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\StyleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| Versioned from the first commit. The website and the mobile app read the
| same catalog through the same service classes, so neither can drift.
|
| Everything below is public: a price list, a gallery and opening hours are
| not private data, and the QR flow must work before anyone has an account.
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

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', fn (Request $request) => $request->user())->name('me');
    });
});
