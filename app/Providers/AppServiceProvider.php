<?php

namespace App\Providers;

use App\Services\Updates\NativeUpdateCommandRunner;
use App\Services\Updates\UpdateCommandRunner;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UpdateCommandRunner::class, NativeUpdateCommandRunner::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
