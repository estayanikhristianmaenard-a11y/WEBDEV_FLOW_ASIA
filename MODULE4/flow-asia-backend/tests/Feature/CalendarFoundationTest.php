<?php

use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use FlowAsia\Module4\Contracts\CalendarDataSource;
use FlowAsia\Module4\Models\Department;
use FlowAsia\Module4\Models\Employee;
use FlowAsia\Module4\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(DemoSeeder::class);
});

it('creates a repeatable roster and linked demo accounts', function () {
    $this->seed(DemoSeeder::class);
    expect(Department::count())->toBe(5)
        ->and(Employee::count())->toBe(40)
        ->and(User::where('email', 'supervisor@test.com')->first()->role)->toBe('supervisor');

    foreach (Department::all() as $department) {
        expect($department->employees()->whereNull('supervisor_id')->count())->toBe(1);
    }
});

it('preserves empty calendars, mid-month hires and simultaneous statuses', function () {
    $source = app(CalendarDataSource::class);
    $start = CarbonImmutable::parse('2026-10-01');
    $end = $start->endOfMonth();
    $empty = Employee::where('employee_no', 'EMP-040')->firstOrFail();
    $newHire = Employee::where('employee_no', 'EMP-039')->firstOrFail();
    $overlap = Employee::where('employee_no', 'EMP-002')->firstOrFail();
    $grouped = $source->forEmployees([$empty->id, $newHire->id, $overlap->id], $start, $end);

    expect($grouped->get($empty->id))->toHaveCount(0)
        ->and($grouped->get($newHire->id))->toHaveCount(17)
        ->and($grouped->get($newHire->id)->first()->date->toDateString())->toBe('2026-10-15');

    $day = $grouped->get($overlap->id)->first(fn ($row) => $row->date->toDateString() === '2026-10-12');
    expect($day->status)->toBe('holiday')
        ->and($day->additional_statuses)->toBe(['leave_approved']);
});

it('loads multiple employee calendars with one attendance query', function () {
    $ids = Employee::pluck('id')->all();
    DB::enableQueryLog();
    app(CalendarDataSource::class)->forEmployees($ids, CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'));
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect($queries)->toHaveCount(1);
});

it('rejects reversed date ranges', function () {
    app(CalendarDataSource::class)->forEmployees([], CarbonImmutable::parse('2026-10-31'), CarbonImmutable::parse('2026-10-01'));
})->throws(InvalidArgumentException::class);

it('covers every configured attendance status in the demo month', function () {
    $rows = app(CalendarDataSource::class)->forEmployee(
        Employee::where('employee_no', 'EMP-001')->firstOrFail()->id,
        CarbonImmutable::parse('2026-10-01'),
        CarbonImmutable::parse('2026-10-31'),
    );
    expect($rows->pluck('status')->unique()->sort()->values()->all())
        ->toBe(collect(array_keys(config('module4_calendar.statuses')))->sort()->values()->all());
});
