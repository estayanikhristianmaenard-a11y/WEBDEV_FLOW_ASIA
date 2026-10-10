<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CompanyEventController;
use App\Http\Controllers\EmployeeSearchController;
use App\Http\Controllers\DashboardController;

Route::get('/login', fn () => view('auth.login'))->middleware('guest')->name('login');
Route::post('/login', [DashboardController::class, 'login'])->middleware(['guest', 'throttle:5,1']);
Route::get('/m4/assets/{path}', [DashboardController::class, 'asset'])->where('path', '.*');

Route::get('/', function () {
    return view('welcome');
});

// Session authentication and web middleware retain CSRF protection for writes.
Route::middleware('auth')->group(function () {
    Route::get('/m4', [DashboardController::class, 'show'])->name('m4.dashboard');
    Route::post('/logout', [DashboardController::class, 'logout'])->name('logout');
    Route::get('/hr/calendar/team', \App\Http\Controllers\TeamCalendarController::class)->name('hr.calendar.team');
    Route::get('/hr/employees/search', EmployeeSearchController::class)->name('hr.employees.search');
    Route::get('/calendar/events', [CompanyEventController::class, 'feed'])->name('calendar.events');
    Route::get('/calendar/employees/{employee}/month', \App\Http\Controllers\EmployeeCalendarController::class)
        ->middleware('can:viewCalendar,employee')->name('calendar.employee.month');
    Route::get('/calendar/employees/{employee}/pdf', \App\Http\Controllers\CalendarPdfController::class)
        ->middleware('can:viewCalendar,employee')->name('calendar.employee.pdf');
    Route::middleware('can:manage-events')->prefix('hr')->name('hr.')->group(function () {
        Route::resource('events', CompanyEventController::class)->only(['index', 'store', 'update', 'destroy']);
    });
});
