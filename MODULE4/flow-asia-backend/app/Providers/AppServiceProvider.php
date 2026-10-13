<?php

namespace App\Providers;

use App\Contracts\CalendarContextSource;
use App\Contracts\CalendarDataSource;
use App\Models\Employee;
use App\Models\User;
use App\Policies\EmployeeCalendarPolicy;
use App\Services\StubCalendarContextSource;
use App\Services\StubCalendarDataSource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CalendarDataSource::class, StubCalendarDataSource::class);
        $this->app->bind(CalendarContextSource::class, StubCalendarContextSource::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Employee::class, EmployeeCalendarPolicy::class);
        Gate::define('manage-events', fn (User $user): bool => in_array($user->role, config('calendar.hr_roles'), true)
        );
    }
}
