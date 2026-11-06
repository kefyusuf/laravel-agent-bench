<?php

namespace App\Providers;

use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class, function () {
            $slug = (string) request()->header('X-Tenant', '');

            return new TenantContext(
                Tenant::query()->where('slug', $slug)->firstOrFail(),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
