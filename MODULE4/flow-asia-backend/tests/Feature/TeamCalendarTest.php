<?php

use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use FlowAsia\Module4\Models\AttendanceDaily;
use FlowAsia\Module4\Models\Employee;
use FlowAsia\Module4\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-10 12:00:00', 'Asia/Manila'));
    $this->seed(DemoSeeder::class);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('returns the roster with one batched attendance query and preserves missing days', function () {
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        if (str_contains(strtolower($query->sql), 'select') && str_contains($query->sql, 'attendance_daily')) {
            $queries[] = $query->sql;
        }
    });
    $response = $this->actingAs(User::where('email', 'hr@test.com')->firstOrFail())
        ->getJson('/hr/calendar/team?month=2026-10')->assertOk()
        ->assertJsonCount(40, 'employees')->assertJsonPath('read_only', true);
    expect($queries)->toHaveCount(1);
    $empty = collect($response->json('employees'))->firstWhere('employee_no', 'EMP-040');
    expect($empty['days'])->toBe([]);
    expect($response->json('pending.overtime'))->not->toBeEmpty();
});

it('restricts a supervisor to self and direct reports even with a department filter', function () {
    $user = User::where('email', 'supervisor@test.com')->firstOrFail();
    $this->actingAs($user)->getJson('/hr/calendar/team?month=2026-10')->assertOk()->assertJsonCount(8, 'employees');
    $other = Employee::where('employee_no', 'EMP-009')->firstOrFail();
    $this->getJson('/hr/calendar/team?month=2026-10&department_id='.$other->department_id)
        ->assertOk()->assertJsonCount(0, 'employees')->assertJsonCount(0, 'today.absent');
});

it('groups today statuses, approved overlaps and pending requests without changing records', function () {
    $employees = Employee::whereIn('employee_no', ['EMP-002', 'EMP-003', 'EMP-004'])->orderBy('employee_no')->get();
    foreach ($employees as $index => $employee) {
        AttendanceDaily::where('employee_id', $employee->id)->whereDate('date', '2026-10-10')
            ->update(['status' => ['absent', 'late', 'leave_approved'][$index], 'additional_statuses' => '[]']);
        AttendanceDaily::where('employee_id', $employee->id)->whereDate('date', '2026-10-14')
            ->update(['status' => $index < 2 ? 'leave_approved' : 'leave_pending', 'additional_statuses' => '[]']);
    }
    $response = $this->actingAs(User::where('email', 'hr@test.com')->firstOrFail())
        ->getJson('/hr/calendar/team?month=2026-10&department_id='.$employees[0]->department_id)->assertOk();
    expect(collect($response->json('today.absent'))->pluck('id')->all())->toContain($employees[0]->id);
    expect(collect($response->json('today.late'))->pluck('id')->all())->toContain($employees[1]->id);
    expect(collect($response->json('today.on_leave'))->pluck('id')->all())->toContain($employees[2]->id);
    $overlap = collect($response->json('leave_overlaps'))->firstWhere('date', '2026-10-14');
    expect($overlap['employee_ids'])->toContain($employees[0]->id, $employees[1]->id);
    expect(collect($response->json('pending.leave'))->where('date', '2026-10-14')->pluck('employee_id')->all())
        ->toContain($employees[2]->id);
    $this->postJson('/hr/calendar/team', ['month' => '2026-10'])->assertForbidden();
});

it('keeps the today panel independent of the selected month', function () {
    $employee = Employee::where('employee_no', 'EMP-002')->firstOrFail();
    AttendanceDaily::where('employee_id', $employee->id)->whereDate('date', '2026-10-10')->update(['status' => 'absent']);
    $response = $this->actingAs(User::where('email', 'supervisor@test.com')->firstOrFail())
        ->getJson('/hr/calendar/team?month=2026-09')->assertOk()->assertJsonPath('today.date', '2026-10-10');
    expect(collect($response->json('today.absent'))->pluck('id')->all())->toContain($employee->id);
    expect(collect($response->json('employees'))->pluck('days')->flatten(1)->all())->toBe([]);
});

it('rejects employee access and invalid filters', function () {
    $this->getJson('/hr/calendar/team?month=2026-10')->assertUnauthorized();
    $this->actingAs(User::where('email', 'employee@test.com')->firstOrFail())
        ->getJson('/hr/calendar/team?month=2026-10')->assertForbidden();
    $this->actingAs(User::where('email', 'hr@test.com')->firstOrFail())
        ->getJson('/hr/calendar/team?month=2026-13')->assertUnprocessable()->assertJsonValidationErrors('month');
    $this->getJson('/hr/calendar/team?month=2026-10&department_id=999999')
        ->assertUnprocessable()->assertJsonValidationErrors('department_id');
});
