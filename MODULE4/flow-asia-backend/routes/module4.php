<?php

use FlowAsia\Module4\Http\Controllers\CalendarPdfController;
use FlowAsia\Module4\Http\Controllers\CalendarReportController;
use FlowAsia\Module4\Http\Controllers\CompanyEventController;
use FlowAsia\Module4\Http\Controllers\DashboardController;
use FlowAsia\Module4\Http\Controllers\EmployeeCalendarController;
use FlowAsia\Module4\Http\Controllers\EmployeeSearchController;
use FlowAsia\Module4\Http\Controllers\TeamCalendarController;
use FlowAsia\Module4\Http\Middleware\StandaloneModulePreview;
use Illuminate\Support\Facades\Route;

Route::prefix(config('module4.route_prefix'))->middleware('web')->group(function (): void {
    $shared = config('module4.mode') === 'shared';
    Route::get($shared ? '/assets/{path}' : '/m4/assets/{path}', [DashboardController::class, 'asset'])
        ->where('path', '.*')->name('m4.assets');
    Route::middleware([StandaloneModulePreview::class, config('module4.auth_middleware')])->group(function () use ($shared): void {
        Route::get($shared ? '/' : '/m4', [DashboardController::class, 'show'])->name('m4.dashboard');
        Route::name(config('module4.route_name_prefix'))->group(function (): void {
            Route::get('/hr/calendar/team', TeamCalendarController::class)->name('hr.calendar.team');
            Route::get('/hr/employees/search', EmployeeSearchController::class)->name('hr.employees.search');
            Route::get('/calendar/events', [CompanyEventController::class, 'feed'])->name('calendar.events');
            Route::get('/calendar/employees/{calendarEmployee}/month', EmployeeCalendarController::class)
                ->middleware('can:viewCalendar,calendarEmployee')->name('calendar.employee.month');
            Route::get('/calendar/employees/{calendarEmployee}/pdf', CalendarPdfController::class)
                ->middleware('can:viewCalendar,calendarEmployee')->name('calendar.employee.pdf');
            Route::get('/calendar/employees/{calendarEmployee}/reports/{report}', CalendarReportController::class)
                ->whereNumber('report')->middleware('can:viewCalendar,calendarEmployee')->name('calendar.employee.report');
            Route::middleware('can:m4.manage-events')->group(function (): void {
                Route::get('/hr/events', [CompanyEventController::class, 'index'])->name('hr.events.index');
                Route::post('/hr/events', [CompanyEventController::class, 'store'])->name('hr.events.store');
                Route::match(['PUT', 'PATCH'], '/hr/events/{event}', [CompanyEventController::class, 'update'])
                    ->whereNumber('event')->name('hr.events.update');
                Route::delete('/hr/events/{event}', [CompanyEventController::class, 'destroy'])
                    ->whereNumber('event')->name('hr.events.destroy');
            });
        });
    });
});
