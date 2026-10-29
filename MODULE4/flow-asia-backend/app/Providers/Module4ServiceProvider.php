<?php

namespace FlowAsia\Module4\Providers;

use FlowAsia\Module4\Console\Commands\CheckModule4Integration;
use FlowAsia\Module4\Contracts\CalendarContextSource;
use FlowAsia\Module4\Contracts\CalendarDataSource;
use FlowAsia\Module4\Contracts\CalendarEmployeeDirectory;
use FlowAsia\Module4\Contracts\CalendarIdentitySource;
use FlowAsia\Module4\Contracts\CompanyEventSource;
use FlowAsia\Module4\Http\Middleware\ReadOnlyCalendar;
use FlowAsia\Module4\Http\Middleware\StandaloneModulePreview;
use FlowAsia\Module4\Models\Employee;
use FlowAsia\Module4\Policies\EmployeeCalendarPolicy;
use FlowAsia\Module4\Services\CalendarEmployee;
use FlowAsia\Module4\Services\DatabaseCompanyEventSource;
use FlowAsia\Module4\Services\StubCalendarContextSource;
use FlowAsia\Module4\Services\StubCalendarDataSource;
use FlowAsia\Module4\Services\StubCalendarEmployeeDirectory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use LogicException;

class Module4ServiceProvider extends ServiceProvider
{
    public const SOURCES = [
        'attendance' => CalendarDataSource::class,
        'context' => CalendarContextSource::class,
        'employees' => CalendarEmployeeDirectory::class,
        'events' => CompanyEventSource::class,
        'identity' => CalendarIdentitySource::class,
    ];

    public const LOCAL_SOURCES = [StubCalendarDataSource::class, StubCalendarContextSource::class, StubCalendarEmployeeDirectory::class, DatabaseCompanyEventSource::class];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $root = dirname(__DIR__, 2);
        $this->mergeConfigFrom($root.'/config/module4.php', 'module4');
        $this->mergeConfigFrom($root.'/config/calendar.php', 'module4_calendar');
        foreach (self::SOURCES as $key => $contract) {
            if ($this->app->bound($contract)) {
                continue;
            }
            $this->app->bind($contract, function ($app) use ($key, $contract) {
                $class = config('module4.sources.'.$key);
                if (! is_string($class) || ! is_a($class, $contract, true)
                    || (config('module4.mode') === 'shared' && in_array($class, self::LOCAL_SOURCES, true))) {
                    throw new LogicException('Configure a shared M4 '.$key.' adapter implementing '.$contract.'. Run php artisan m4:check.');
                }

                return $app->make($class);
            });
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Employee::class, EmployeeCalendarPolicy::class);
        Gate::policy(CalendarEmployee::class, EmployeeCalendarPolicy::class);
        Gate::define('m4.manage-events', fn (Authenticatable $user): bool => in_array(
            app(CalendarIdentitySource::class)->forUser($user)['role'], config('module4_calendar.hr_roles'), true,
        ));
        $root = dirname(__DIR__, 2);
        $this->loadViewsFrom($root.'/resources/views', 'm4');
        if (config('module4.mode') === 'standalone' && config('module4.data_schema') === 'demo') {
            $this->loadMigrationsFrom($root.'/database/migrations/standalone');
        }
        Route::bind('calendarEmployee', function (string $id): CalendarEmployee {
            abort_unless(ctype_digit($id) && (int) $id > 0, 404);
            $employee = app(CalendarEmployeeDirectory::class)->find((int) $id);
            abort_unless($employee, 404);

            return $employee;
        });
        $this->callAfterResolving(Kernel::class, function ($kernel): void {
            $kernel->prependMiddleware(ReadOnlyCalendar::class);
            $kernel->addToMiddlewarePriorityBefore(AuthenticatesRequests::class, StandaloneModulePreview::class);
        });
        $this->loadRoutesFrom($root.'/routes/module4.php');
        if ($this->app->runningInConsole()) {
            $this->commands([CheckModule4Integration::class]);
        }
    }
}
