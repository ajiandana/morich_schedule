<?php

namespace App\Providers;

use App\Services\ScheduleService;
use App\Services\WorkdayCalendar;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WorkdayCalendar::class);
        $this->app->singleton(ScheduleService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
