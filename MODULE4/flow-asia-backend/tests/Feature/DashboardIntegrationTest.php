<?php

use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use FlowAsia\Module4\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->seed(DemoSeeder::class);
});

it('uses the main system identity when preview mode is disabled', function () {
    $this->get('/m4')->assertRedirect('/login');
    $this->actingAs(User::where('email', 'hr@test.com')->firstOrFail());
    $page = $this->get('/m4')->assertOk()->assertSee('window.flowAsiaBackend=', false)
        ->assertSee('"role":"hr_admin"', false)->assertSee('id="staff-calendar-grid"', false);
    expect($page->headers->get('Cache-Control'))->toContain('no-store');
    $this->getJson('/hr/employees/search?q=Creative')->assertOk();
    $this->getJson('/hr/calendar/team?month=2026-10')->assertOk();
});

it('opens the seeded module preview without a login flow', function () {
    config(['module4.standalone_preview' => true]);
    $this->get('/m4')->assertOk()->assertSee('"role":"hr_admin"', false);
    $this->getJson('/hr/calendar/team?month=2026-10')->assertOk();
    $this->get('/login')->assertNotFound();
    $this->postJson('/logout')->assertNotFound();
});

it('never enables preview identity in production', function () {
    config(['module4.standalone_preview' => true]);
    app()->instance('env', 'production');
    $this->getJson('/hr/calendar/team?month=2026-10')->assertUnauthorized();
});

it('preserves an existing user when preview is enabled', function () {
    config(['module4.standalone_preview' => true]);
    $this->actingAs(User::where('email', 'employee@test.com')->firstOrFail());
    $this->get('/m4')->assertOk()->assertSee('"role":"employee"', false);
    $this->getJson('/hr/employees/search?q=Demo')->assertForbidden();
});

it('explains that demo data must be seeded when the preview identity is missing', function () {
    config(['module4.standalone_preview' => true, 'module4.preview_user_email' => 'missing@test.com']);
    $this->getJson('/m4')->assertStatus(503);
});

it('serves only dashboard code assets with correct content types', function () {
    $script = $this->get('/m4/assets/js/modules/m4-backend.js')->assertOk();
    expect($script->headers->get('Content-Type'))->toStartWith('text/javascript');
    $style = $this->get('/m4/assets/css/modules/m4-backend.css')->assertOk();
    expect($style->headers->get('Content-Type'))->toStartWith('text/css');
    $this->get('/m4/assets/index.html')->assertNotFound();
    $this->get('/m4/assets/js/../../flow-asia-backend/routes/web.php')->assertNotFound();
});

it('uses server identity rather than a client role preview to enforce access', function () {
    $this->actingAs(User::where('email', 'employee@test.com')->firstOrFail());
    $this->get('/m4')->assertOk()->assertSee('"role":"employee"', false);
    $this->getJson('/hr/employees/search?q=Demo&role=hr_admin')->assertForbidden();
    $this->postJson('/hr/events', ['role' => 'hr_admin'])->assertForbidden();
});

it('supplies the application URL and configured event choices to the connected frontend', function () {
    config(['module4_calendar.event_types' => ['town_hall' => 'Town Hall', 'other' => 'Other']]);
    $this->actingAs(User::where('email', 'hr@test.com')->firstOrFail());

    $this->withServerVariables([
        'SCRIPT_NAME' => '/flow-asia/public/index.php',
        'SCRIPT_FILENAME' => public_path('index.php'),
    ])->get('http://localhost/flow-asia/public/m4')->assertOk()
        ->assertSee('"baseUrl":"http:\/\/localhost\/flow-asia\/public"', false)
        ->assertSee('"eventTypes":{"town_hall":"Town Hall","other":"Other"}', false)
        ->assertDontSee('cdn.jsdelivr.net/npm/chart.js', false);
});

it('keeps team and company events available for HR accounts without an employee profile', function () {
    $user = User::where('email', 'hr@test.com')->firstOrFail();
    $user->forceFill(['employee_id' => null])->save();

    $this->actingAs($user)->get('/m4')->assertOk()->assertSee('"employee_id":null', false);
    $this->getJson('/calendar/events?start=2026-10-01&end=2026-11-01')->assertOk();
    $this->getJson('/hr/calendar/team?month=2026-10')->assertOk()->assertJsonCount(40, 'employees');
});

it('supplies the server calendar date and connected shell without prototype authentication', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 00:30:00', 'Asia/Manila'));
    $this->actingAs(User::where('email', 'employee@test.com')->firstOrFail());

    $this->get('/m4')->assertOk()
        ->assertSee('"today":"2026-10-15"', false)
        ->assertSee('"todayLabel":"Thursday, October 15, 2026"', false)
        ->assertSee('"month":"2026-10"', false)
        ->assertSee('"logoutUrl":null', false)
        ->assertDontSee('>Maria Santos<', false)
        ->assertDontSee('id="kpi-present">18', false)
        ->assertSee('<body data-connected="true">', false);
});

it('includes only existing host navigation routes configured for the authenticated role', function () {
    Route::get('/host/leave', fn (): string => 'Host leave module')->name('test.host.leave');
    Route::get('/host/payroll', fn (): string => 'Host payroll module')->name('test.host.payroll');
    Route::post('/host/request', fn (): string => 'Host write handler')->name('test.host.write');
    Route::getRoutes()->refreshNameLookups();
    config(['module4.navigation' => [
        'employee' => ['leave' => 'test.host.leave', 'profile' => 'test.missing.route', 'punch' => 'test.host.write'],
        'hr_admin' => ['payroll' => 'test.host.payroll'],
    ]]);
    $this->actingAs(User::where('email', 'employee@test.com')->firstOrFail());

    $this->get('/m4')->assertOk()
        ->assertSee('"navigation":{"leave":'.json_encode(route('test.host.leave')).'}', false)
        ->assertDontSee('host\/payroll', false)
        ->assertDontSee('test.missing.route', false);
});

it('uses the existing host POST logout route and refuses a GET logout action', function () {
    Route::post('/host/sign-out', fn (): string => 'Host session handler')->name('test.host.logout');
    Route::get('/host/unsafe-sign-out', fn (): string => 'Read only')->name('test.host.get-logout');
    Route::getRoutes()->refreshNameLookups();
    $this->actingAs(User::where('email', 'employee@test.com')->firstOrFail());
    config(['module4.logout_route' => 'test.host.logout']);
    $this->get('/m4')->assertOk()->assertSee('"logoutUrl":'.json_encode(route('test.host.logout')), false);

    config(['module4.logout_route' => 'test.host.get-logout']);
    $this->get('/m4')->assertOk()->assertSee('"logoutUrl":null', false);
});
