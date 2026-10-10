<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Contracts\CalendarDataSource::class, \App\Services\StubCalendarDataSource::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Employee::class, \App\Policies\EmployeeCalendarPolicy::class);
        \Illuminate\Support\Facades\Gate::define('manage-events', fn (\App\Models\User $user): bool =>
            in_array($user->role, config('calendar.hr_roles'), true)
        );
    }
}
