<?php

namespace App\Providers;

use App\Models\ExtractJob;
use App\Policies\DigitisationJobPolicy;
use App\Services\DigitisationJobStateService;
use App\Services\ImageQualityCheckService;
use App\Services\ImageQualityCheckStateService;
use App\Services\LeafMachine2Service;
use App\Services\ResultProcessingService;
use App\Services\UploadStorageService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(UploadStorageService::class);

        // LeafMachine2 HTTP client — singleton, cheap to reuse
        $this->app->singleton(LeafMachine2Service::class);

        // State service — stateless, singleton is safe
        $this->app->singleton(DigitisationJobStateService::class);

        // IQC HTTP client and state service
        $this->app->singleton(ImageQualityCheckService::class);
        $this->app->singleton(ImageQualityCheckStateService::class);

        // ResultProcessingService depends on LeafMachine2Service
        $this->app->singleton(ResultProcessingService::class, function ($app) {
            return new ResultProcessingService($app->make(LeafMachine2Service::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerPolicies();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

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

    protected function registerPolicies(): void
    {
        Gate::policy(ExtractJob::class, DigitisationJobPolicy::class);
    }
}
