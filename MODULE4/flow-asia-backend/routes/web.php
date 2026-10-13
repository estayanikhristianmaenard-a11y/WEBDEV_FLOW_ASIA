<?php

use App\Http\Controllers\CalendarPdfController;
use App\Http\Controllers\CompanyEventController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeCalendarController;
use App\Http\Controllers\EmployeeSearchController;
use App\Http\Controllers\TeamCalendarController;
use App\Http\Middleware\StandaloneModulePreview;
use Illuminate\Support\Facades\Route;

Route::get('/m4/assets/{path}', [DashboardController::class, 'asset'])->where('path', '.*');

Route::redirect('/', '/m4');

// Session authentication and web middleware retain CSRF protection for writes.
Route::middleware([StandaloneModulePreview::class, 'auth'])->group(function () {
    Route::get('/m4', [DashboardController::class, 'show'])->name('m4.dashboard');
    Route::get('/hr/calendar/team', TeamCalendarController::class)->name('hr.calendar.team');
    Route::get('/hr/employees/search', EmployeeSearchController::class)->name('hr.employees.search');
    Route::get('/calendar/events', [CompanyEventController::class, 'feed'])->name('calendar.events');
    Route::get('/calendar/employees/{employee}/month', EmployeeCalendarController::class)
        ->middleware('can:viewCalendar,employee')->name('calendar.employee.month');
    Route::get('/calendar/employees/{employee}/pdf', CalendarPdfController::class)
        ->middleware('can:viewCalendar,employee')->name('calendar.employee.pdf');
    Route::middleware('can:manage-events')->prefix('hr')->name('hr.')->group(function () {
        Route::resource('events', CompanyEventController::class)->only(['index', 'store', 'update', 'destroy']);
    });
});
