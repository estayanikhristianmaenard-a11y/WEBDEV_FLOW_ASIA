<?php

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->seed(DemoSeeder::class);
});

it('loads only the requested employee and month without inventing records', function () {
    $user = User::where('email', 'employee@test.com')->firstOrFail();
    $this->actingAs($user)->getJson('/calendar/employees/'.$user->employee_id.'/month?month=2026-10')
        ->assertOk()->assertJsonPath('data.id', $user->employee_id)->assertJsonPath('read_only', true)
        ->assertJsonCount(31, 'data.days')
        ->assertJsonPath('data.days.0.time_in', '09:15:00')
        ->assertJsonPath('data.days.0.time_out', '18:00:00')
        ->assertJsonPath('data.days.0.remarks', 'Demo data only.');
    $this->getJson('/calendar/employees/'.$user->employee_id.'/month?month=2026-09')
        ->assertOk()->assertJsonCount(0, 'data.days');
});

it('enforces employee and supervisor scope before reading records', function () {
    $other = Employee::where('employee_no', 'EMP-009')->firstOrFail();
    foreach (['employee@test.com', 'supervisor@test.com'] as $email) {
        $this->actingAs(User::where('email', $email)->firstOrFail())
            ->getJson('/calendar/employees/'.$other->id.'/month?month=2026-10')->assertForbidden();
    }
    $report = Employee::where('employee_no', 'EMP-002')->firstOrFail();
    $this->getJson('/calendar/employees/'.$report->id.'/month?month=2026-10')->assertOk();
});

it('allows HR to view empty calendars and rejects bad months and writes', function () {
    $employee = Employee::where('employee_no', 'EMP-040')->firstOrFail();
    $url = '/calendar/employees/'.$employee->id.'/month';
    $this->getJson($url.'?month=2026-10')->assertUnauthorized();
    $this->actingAs(User::where('email', 'hr@test.com')->firstOrFail())
        ->getJson($url.'?month=2026-10')->assertOk()->assertJsonCount(0, 'data.days');
    $this->getJson($url.'?month=2026-13')->assertUnprocessable()->assertJsonValidationErrors('month');
    $this->postJson($url, ['month' => '2026-10'])->assertForbidden();
    $this->getJson('/calendar/employees/999999/month?month=2026-10')->assertNotFound();
});
