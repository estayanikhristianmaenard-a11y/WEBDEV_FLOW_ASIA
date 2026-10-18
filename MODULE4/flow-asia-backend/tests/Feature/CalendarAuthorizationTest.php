<?php

use Database\Seeders\DemoSeeder;
use FlowAsia\Module4\Models\Employee;
use FlowAsia\Module4\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    // A test-only endpoint verifies policy wiring without building #09's API.
    Route::middleware(['web', 'auth', 'can:viewCalendar,employee'])
        ->get('/calendar/employees/{employee}', fn (Employee $employee) => response()->json(['id' => $employee->id]));
});

it('allows all HR roles to view any employee calendar', function (string $role) {
    $user = User::where('email', 'hr@test.com')->firstOrFail();
    $user->forceFill(['role' => $role])->save();
    $employee = Employee::where('employee_no', 'EMP-040')->firstOrFail();
    $this->actingAs($user)->getJson('/calendar/employees/'.$employee->id)->assertOk();
})->with(['hr_admin', 'hr_staff', 'super_admin']);

it('allows employees to view only their own calendar', function () {
    $user = User::where('email', 'employee@test.com')->firstOrFail();
    $other = Employee::where('employee_no', 'EMP-001')->firstOrFail();
    $this->actingAs($user)->getJson('/calendar/employees/'.$user->employee_id)->assertOk();
    $this->getJson('/calendar/employees/'.$other->id)->assertForbidden();
    $this->getJson('/hr/employees/search?q=Demo')->assertForbidden();
});

it('allows supervisors to view themselves and direct reports, but not another team', function () {
    $user = User::where('email', 'supervisor@test.com')->firstOrFail();
    $member = Employee::where('employee_no', 'EMP-002')->firstOrFail();
    $other = Employee::where('employee_no', 'EMP-009')->firstOrFail();
    $this->actingAs($user)->getJson('/calendar/employees/'.$user->employee_id)->assertOk();
    $this->getJson('/calendar/employees/'.$member->id)->assertOk();
    $this->getJson('/calendar/employees/'.$other->id)->assertForbidden();
});

it('denies calendar access for missing identity links and unknown roles', function (string $role, bool $missingLink) {
    $user = User::where('email', 'employee@test.com')->firstOrFail();
    $employeeId = $user->employee_id;
    $user->forceFill(['role' => $role, 'employee_id' => $missingLink ? null : $employeeId])->save();
    $this->actingAs($user)->getJson('/calendar/employees/'.$employeeId)->assertForbidden();
    expect(Employee::visibleTo($user)->count())->toBe(0);
})->with([['employee', true], ['supervisor', true], ['unknown', false], ['payroll_officer', false]]);

it('requires authentication to view a calendar', function () {
    $this->getJson('/calendar/employees/1')->assertUnauthorized();
});

it('denies attendance, leave and overtime mutation abilities for every role', function (string $role) {
    $user = User::where('email', 'employee@test.com')->firstOrFail();
    $user->forceFill(['role' => $role])->save();
    $employee = Employee::findOrFail($user->employee_id);
    foreach (['updateAttendance', 'updateLeave', 'updateOvertime'] as $ability) {
        expect(Gate::forUser($user)->allows($ability, $employee))->toBeFalse();
    }
})->with(['employee', 'supervisor', 'hr_admin', 'hr_staff', 'super_admin']);

it('blocks calendar writes before a handler can modify data', function (string $method) {
    $employee = Employee::where('employee_no', 'EMP-002')->firstOrFail();
    Route::middleware('web')->match([$method], '/hr/employees/{employee}/calendar/attendance', function (Employee $employee) {
        $employee->update(['first_name' => 'Changed']);

        return response()->noContent();
    });
    $this->actingAs(User::where('email', 'hr@test.com')->firstOrFail())
        ->json($method, '/hr/employees/'.$employee->id.'/calendar/attendance')->assertForbidden();
    expect($employee->fresh()->first_name)->toBe('Demo');
})->with(['POST', 'PUT', 'PATCH', 'DELETE']);

it('keeps the shared event feed read-only', function () {
    $this->actingAs(User::where('email', 'hr@test.com')->firstOrFail())
        ->postJson('/calendar/events', [])->assertForbidden();
});

it('preserves HR company event editing while calendar writes are blocked', function () {
    $this->actingAs(User::where('email', 'hr@test.com')->firstOrFail())->postJson('/hr/events', [
        'title' => 'Team Town Hall', 'type' => 'town_hall', 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-12',
    ])->assertCreated();
});
