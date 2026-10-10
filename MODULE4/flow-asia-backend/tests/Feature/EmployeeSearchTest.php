<?php

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->seed(DemoSeeder::class);
});

it('searches by full name, employee number and department', function (string $term, int $count) {
    $this->actingAs(User::where('email', 'hr@test.com')->firstOrFail())
        ->getJson('/hr/employees/search?q='.urlencode($term))
        ->assertOk()->assertJsonCount($count, 'data');
})->with([
    'full name' => ['Demo Employee 02', 1],
    'ID' => ['emp-002', 1],
    'department' => ['production', 8],
    'department with spaces' => ['Human Resources', 8],
    'no match' => ['Nobody Here', 0],
    'literal wildcards' => ['%%', 0],
]);

it('limits results and exposes only calendar-safe fields', function () {
    $response = $this->actingAs(User::where('email', 'hr@test.com')->firstOrFail())
        ->getJson('/hr/employees/search?q=Demo')->assertOk()->assertJsonCount(10, 'data');
    expect(array_keys($response->json('data.0')))->toBe(['id', 'employee_no', 'name', 'position', 'department']);
});

it('permits every HR role', function (string $role) {
    $user = User::where('email', 'hr@test.com')->firstOrFail();
    $user->forceFill(['role' => $role])->save();
    $this->actingAs($user)->getJson('/hr/employees/search?q=Finance')->assertOk()->assertJsonCount(8, 'data');
})->with(['hr_admin', 'hr_staff', 'super_admin']);

it('restricts supervisors to their own employee identity and team', function () {
    $user = User::where('email', 'supervisor@test.com')->firstOrFail();
    $response = $this->actingAs($user)->getJson('/hr/employees/search?q=Demo')->assertOk()->assertJsonCount(8, 'data');
    $expected = Employee::where('id', $user->employee_id)->orWhere('supervisor_id', $user->employee_id)->pluck('id')->sort()->values()->all();
    expect(collect($response->json('data'))->pluck('id')->sort()->values()->all())->toBe($expected);
    $this->getJson('/hr/employees/search?q=Production')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/hr/employees/search?q=EMP-001')->assertJsonCount(1, 'data');
});

it('returns no records for a supervisor without an employee link', function () {
    $user = User::where('email', 'supervisor@test.com')->firstOrFail();
    $user->forceFill(['employee_id' => null])->save();
    $this->actingAs($user)->getJson('/hr/employees/search?q=Demo')->assertOk()->assertJsonCount(0, 'data');
});

it('denies employees and unknown roles', function (string $role) {
    $user = User::where('email', 'employee@test.com')->firstOrFail();
    $user->forceFill(['role' => $role])->save();
    $this->actingAs($user)->getJson('/hr/employees/search?q=Demo')->assertForbidden();
})->with(['employee', 'unknown']);

it('requires authentication', function () {
    $this->getJson('/hr/employees/search?q=Demo')->assertUnauthorized();
});

it('validates the query before searching', function (string $query) {
    $this->actingAs(User::where('email', 'hr@test.com')->firstOrFail())
        ->getJson('/hr/employees/search?'.$query)->assertUnprocessable()->assertJsonValidationErrors('q');
})->with(['', 'q=a', 'q=%20%20', 'q[]=Demo', 'q='.str_repeat('x', 101)]);

it('normalizes spaces and leaves records unchanged', function () {
    $before = Employee::orderBy('id')->get()->toArray();
    $this->actingAs(User::where('email', 'hr@test.com')->firstOrFail())
        ->getJson('/hr/employees/search?q=%20Demo%20%20Employee%2002%20')->assertOk()->assertJsonCount(1, 'data');
    expect(Employee::orderBy('id')->get()->toArray())->toBe($before);
});
