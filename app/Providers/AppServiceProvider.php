<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\Plan;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\StaffProfile;
use App\Models\Style;
use App\Observers\CatalogObserver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
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
        $this->configureDefaults();
        $this->observeCatalog();
    }

    /**
     * Anything that appears on the public price list, gallery or team page
     * busts the catalog cache when it changes.
     */
    protected function observeCatalog(): void
    {
        foreach ([Branch::class, ServiceCategory::class, Service::class, Style::class, Plan::class, StaffProfile::class, Review::class] as $model) {
            $model::observe(CatalogObserver::class);
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // An update() that quietly drops a non fillable attribute is a bug
        // that only shows up as a number that never changes. Outside
        // production it throws instead.
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
        Model::preventLazyLoading(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
