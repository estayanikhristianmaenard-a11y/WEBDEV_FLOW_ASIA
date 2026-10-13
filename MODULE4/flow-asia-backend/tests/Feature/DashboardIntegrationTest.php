<?php

use App\Models\User;
use Database\Seeders\DemoSeeder;

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
