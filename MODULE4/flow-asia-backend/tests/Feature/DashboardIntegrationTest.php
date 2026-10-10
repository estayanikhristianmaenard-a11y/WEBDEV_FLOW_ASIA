<?php

use App\Models\User;
use Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->seed(DemoSeeder::class);
});

it('requires sign in and renders the original dashboard with the authenticated identity', function () {
    $this->get('/m4')->assertRedirect('/login');
    $this->post('/login', ['email' => 'hr@test.com', 'password' => 'password'])->assertRedirect('/m4');
    $this->assertAuthenticatedAs(User::where('email', 'hr@test.com')->firstOrFail());
    $page = $this->get('/m4')->assertOk()->assertSee('window.flowAsiaBackend=', false)
        ->assertSee('"role":"hr_admin"', false)->assertSee('id="staff-calendar-grid"', false);
    expect($page->headers->get('Cache-Control'))->toContain('no-store');
    $this->getJson('/hr/employees/search?q=Creative')->assertOk();
    $this->getJson('/hr/calendar/team?month=2026-10')->assertOk();
    $this->postJson('/logout')->assertNoContent();
    $this->assertGuest();
    $this->getJson('/hr/calendar/team?month=2026-10')->assertUnauthorized();
});

it('rejects incorrect credentials without signing in', function () {
    $this->post('/login', ['email' => 'hr@test.com', 'password' => 'incorrect'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
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
