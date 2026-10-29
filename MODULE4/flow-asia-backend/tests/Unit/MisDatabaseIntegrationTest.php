<?php

use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use FlowAsia\Module4\Contracts\CalendarContextSource;
use FlowAsia\Module4\Contracts\CalendarDataSource;
use FlowAsia\Module4\Contracts\CalendarEmployeeDirectory;
use FlowAsia\Module4\Contracts\CalendarIdentitySource;
use FlowAsia\Module4\Contracts\CompanyEventSource;
use FlowAsia\Module4\Models\User;
use FlowAsia\Module4\Services\CalendarPdfData;
use FlowAsia\Module4\Services\MisCalendarContextSource;
use FlowAsia\Module4\Services\MisCalendarDataSource;
use FlowAsia\Module4\Services\MisCalendarEmployeeDirectory;
use FlowAsia\Module4\Services\MisCalendarIdentitySource;
use FlowAsia\Module4\Services\MisCompanyEventSource;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\SharedModuleTestCase;

uses(SharedModuleTestCase::class);

function misUser(int $id = 11): GenericUser
{
    return new GenericUser(['id' => $id]);
}

beforeEach(function () {
    $database = getenv('M4_MIS_TEST_DATABASE');
    if ($database && ! preg_match('/\Aflow_asia_m4_test_[a-z0-9_]+\z/', $database)) {
        throw new RuntimeException('MIS tests require a dedicated flow_asia_m4_test_* database.');
    }
    config([
        'module4.data_schema' => 'mis', 'module4.standalone_preview' => false,
        'module4.mis_connection' => 'mis_test',
        'module4.sources.attendance' => MisCalendarDataSource::class,
        'module4.sources.context' => MisCalendarContextSource::class,
        'module4.sources.employees' => MisCalendarEmployeeDirectory::class,
        'module4.sources.events' => MisCompanyEventSource::class,
        'module4.sources.identity' => MisCalendarIdentitySource::class,
        'database.connections.mis_test' => $database
            ? [...config('database.connections.m4_mis'), 'database' => $database]
            : ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
    ]);
    $this->mis = DB::connection('mis_test');
    if (! $database) {
        $tables = [
            'departments' => ['department_id', 'department_name', 'status'],
            'positions' => ['position_id', 'position_name', 'status'],
            'employees' => ['employee_id', 'employee_number', 'first_name', 'last_name', 'email', 'date_of_birth', 'department_id', 'position_id', 'supervisor_id', 'hire_date', 'separation_date', 'employment_status', 'employment_type'],
            'roles' => ['role_id', 'role_name'],
            'users' => ['user_id', 'employee_id', 'role_id', 'username', 'email', 'password', 'status', 'locked_until'],
            'attendance_daily' => ['attendance_daily_id', 'employee_id', 'attendance_date', 'attendance_status', 'clock_in', 'clock_out', 'worked_minutes', 'late_minutes', 'overtime_minutes', 'remarks'],
            'shifts' => ['shift_id', 'shift_name', 'start_time', 'end_time', 'break_minutes', 'status'],
            'schedules' => ['schedule_id', 'employee_id', 'schedule_date', 'shift_id', 'status'],
            'holidays' => ['holiday_id', 'holiday_date', 'holiday_name', 'holiday_type'],
            'leave_types' => ['leave_type_id', 'leave_type_name'],
            'leave_requests' => ['leave_request_id', 'employee_id', 'leave_type_id', 'start_date', 'end_date', 'total_days', 'status', 'reason'],
            'overtime_requests' => ['overtime_request_id', 'employee_id', 'overtime_date', 'requested_minutes', 'approved_minutes', 'status', 'start_time', 'end_time', 'reason'],
            'correction_requests' => ['correction_request_id', 'employee_id', 'attendance_daily_id', 'status', 'reason', 'created_at'],
            'leave_balances' => ['leave_balance_id', 'employee_id', 'leave_type_id', 'leave_year', 'remaining_days'],
            'payroll_periods' => ['payroll_period_id', 'period_name', 'start_date', 'end_date', 'pay_date', 'created_by', 'status'],
            'company_events' => ['company_event_id', 'event_title', 'description', 'event_type', 'start_datetime', 'end_datetime', 'is_all_day', 'status', 'created_by', 'location', 'created_at', 'updated_at'],
        ];
        foreach ($tables as $name => $columns) {
            $this->mis->getSchemaBuilder()->create($name, function (Blueprint $table) use ($columns): void {
                $table->increments(array_shift($columns));
                foreach ($columns as $column) {
                    $table->string($column)->nullable();
                }
            });
        }
    }
    $this->mis->beginTransaction();
    $this->mis->table('departments')->insert(['department_id' => 1, 'department_name' => 'Production', 'status' => 'ACTIVE']);
    $this->mis->table('positions')->insert(['position_id' => 1, 'position_name' => 'Crew', 'status' => 'ACTIVE']);
    foreach ([1 => 'HR Admin', 2 => 'Supervisor', 3 => 'Employee'] as $id => $name) {
        $this->mis->table('roles')->insert(['role_id' => $id, 'role_name' => $name]);
    }
    foreach ([101 => [11, 1, null], 102 => [12, 2, null], 103 => [13, 3, 102], 104 => [14, 3, null]] as $id => [$user, $role, $supervisor]) {
        $this->mis->table('employees')->insert([
            'employee_id' => $id, 'employee_number' => 'MIS-'.$id, 'first_name' => 'Fixture', 'last_name' => 'Crew '.$id,
            'email' => 'crew'.$id.'@example.test', 'date_of_birth' => '1990-01-01', 'department_id' => 1, 'position_id' => 1,
            'supervisor_id' => $supervisor, 'hire_date' => '2026-01-01', 'employment_status' => 'ACTIVE', 'employment_type' => 'REGULAR',
        ]);
        $this->mis->table('users')->insert([
            'user_id' => $user, 'employee_id' => $id, 'role_id' => $role, 'username' => 'crew'.$id,
            'email' => 'crew'.$id.'@example.test', 'password' => 'test-only-not-a-real-password', 'status' => 'ACTIVE',
        ]);
    }
});

afterEach(function () {
    if (isset($this->mis) && $this->mis->transactionLevel() > 0) {
        $this->mis->rollBack();
    }
    DB::purge('mis_test');
});

it('maps canonical users and scopes safe employee searches without reading the demo database', function () {
    $identity = app(CalendarIdentitySource::class)->forUser(misUser(12));
    expect($identity)->toMatchArray(['id' => 12, 'employee_id' => 102, 'role' => 'supervisor']);
    $directory = app(CalendarEmployeeDirectory::class);
    expect($directory->visibleTo(misUser(12))->pluck('id')->all())->toBe([102, 103]);
    expect($directory->search(misUser(13), 'Crew')->pluck('id')->all())->toBe([103]);
    expect($directory->search(misUser(), 'Production'))->toHaveCount(4);
    expect($directory->search(misUser(), '%'))->toHaveCount(0);
    $this->actingAs(misUser(12));
    $this->getJson('/modules/m4/calendar/employees/104/month?month=2026-10')->assertForbidden();
    $this->getJson('/modules/m4/hr/employees/search?q=MIS-103')->assertOk()->assertJsonPath('data.0.employee_no', 'MIS-103');
    $this->mis->table('users')->where('user_id', 12)->update(['status' => 'INACTIVE']);
    expect(app(CalendarIdentitySource::class)->forUser(misUser(12))['role'])->toBe('');
    $this->getJson('/modules/m4/calendar/employees/103/month?month=2026-10')->assertForbidden();
    $this->mis->table('users')->where('user_id', 12)->update(['status' => 'ACTIVE', 'locked_until' => now()->addDay()->format('Y-m-d H:i:s')]);
    expect(app(CalendarIdentitySource::class)->forUser(misUser(12))['role'])->toBe('');
});

it('combines authoritative minutes requests holidays shifts and correction metadata without writing sources', function () {
    $this->mis->table('attendance_daily')->insert([
        'attendance_daily_id' => 1, 'employee_id' => 103, 'attendance_date' => '2026-10-12', 'attendance_status' => 'LATE',
        'clock_in' => '2026-10-12 09:15:00', 'clock_out' => '2026-10-12 20:00:00', 'worked_minutes' => 585, 'late_minutes' => 15, 'overtime_minutes' => 120,
    ]);
    $this->mis->table('shifts')->insert(['shift_id' => 1, 'shift_name' => 'Regular', 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'break_minutes' => 60, 'status' => 'ACTIVE']);
    $this->mis->table('schedules')->insert(['employee_id' => 103, 'schedule_date' => '2026-10-12', 'shift_id' => 1, 'status' => 'SCHEDULED']);
    $this->mis->table('leave_types')->insert(['leave_type_id' => 1, 'leave_type_name' => 'Vacation']);
    $this->mis->table('leave_requests')->insert([
        'employee_id' => 103, 'leave_type_id' => 1, 'start_date' => '2026-10-12', 'end_date' => '2026-10-12', 'total_days' => .5, 'status' => 'APPROVED', 'reason' => 'Fixture',
    ]);
    $this->mis->table('holidays')->insert(['holiday_date' => '2026-10-12', 'holiday_name' => 'Fixture holiday', 'holiday_type' => 'SPECIAL']);
    $this->mis->table('correction_requests')->insert(['employee_id' => 103, 'attendance_daily_id' => 1, 'status' => 'REJECTED', 'reason' => 'Fixture correction', 'created_at' => '2026-10-12 21:00:00']);
    foreach (['APPROVED' => 120, 'PENDING' => 90, 'REJECTED' => 60] as $status => $minutes) {
        $this->mis->table('overtime_requests')->insert([
            'employee_id' => 103, 'overtime_date' => '2026-10-12', 'requested_minutes' => $minutes, 'approved_minutes' => $status === 'APPROVED' ? $minutes : null,
            'status' => $status, 'start_time' => '18:00:00', 'end_time' => '20:00:00', 'reason' => 'Fixture',
        ]);
    }
    $this->actingAs(misUser(12));
    $this->getJson('/modules/m4/calendar/employees/103/month?month=2026-10')->assertOk()
        ->assertJsonPath('data.days.0.status', 'holiday')->assertJsonPath('data.days.0.ot_hours', 2)
        ->assertJsonPath('data.days.0.pending_ot_hours', 1.5)->assertJsonPath('data.days.0.worked_minutes', 585)
        ->assertJsonPath('data.days.0.correction.status', 'rejected')->assertJsonPath('data.days.0.report.status', 'unavailable')
        ->assertJsonPath('data.summary.leave_days_used', .5)->assertJsonPath('data.summary.status_counts.invalid', 0);
    expect($this->mis->table('attendance_daily')->value('attendance_status'))->toBe('LATE');
    $this->mis->table('attendance_daily')->where('attendance_daily_id', 1)->update(['overtime_minutes' => 180]);
    $this->getJson('/modules/m4/calendar/employees/103/month?month=2026-10')->assertOk()->assertJsonPath('data.days.0.ot_hours', 3);
    $this->get('/modules/m4/calendar/employees/103/pdf?month=2026-10')->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

it('keeps missing attendance unrecorded and fails unknown statuses instead of inventing presence', function () {
    $source = app(CalendarDataSource::class);
    $from = CarbonImmutable::parse('2026-10-01');
    $to = $from->endOfMonth();
    expect($source->forEmployees([103, 104], $from, $to)->get(104))->toHaveCount(0);
    $this->mis->table('attendance_daily')->insert([
        'employee_id' => 103, 'attendance_date' => '2026-10-12', 'attendance_status' => 'UNRECOGNIZED', 'worked_minutes' => 0, 'late_minutes' => 0, 'overtime_minutes' => 0,
    ]);
    expect(fn () => $source->forEmployee(103, $from, $to))->toThrow(UnexpectedValueException::class);
    $this->mis->table('attendance_daily')->update(['attendance_status' => 'INVALID']);
    expect($source->forEmployee(103, $from, $to)->first()->status)->toBe('invalid');
    expect(fn () => $source->forEmployee(103, $to, $from))->toThrow(InvalidArgumentException::class);
});

it('reads supplied leave balances and pay periods and leaves nonexistent reports unavailable', function () {
    $this->mis->table('leave_types')->insert(['leave_type_id' => 1, 'leave_type_name' => 'Vacation']);
    $this->mis->table('leave_balances')->insert(['employee_id' => 103, 'leave_type_id' => 1, 'leave_year' => 2026, 'remaining_days' => 7.5]);
    $this->mis->table('payroll_periods')->insert([
        'period_name' => 'Fixture cutoff', 'start_date' => '2026-09-26', 'end_date' => '2026-10-10', 'pay_date' => '2026-10-15', 'created_by' => 11, 'status' => 'OPEN',
    ]);
    $context = app(CalendarContextSource::class)->forEmployees([103], CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'))->get(103);
    expect($context['profile']['leave_balances'][0])->toBe(['type' => 'Vacation', 'remaining_days' => 7.5]);
    expect($context['profile']['pay_periods'][0]['start'])->toBe('2026-09-26');
    expect($context['reports'])->toBe([]);
    expect(app(CalendarContextSource::class)->reportForEmployee(103, 1))->toBeNull();
});

it('preserves canonical event creators locations and timed events while using soft cancellation', function () {
    $this->actingAs(misUser());
    $payload = ['title' => 'Shared town hall', 'type' => 'town_hall', 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-13'];
    $response = $this->postJson('/modules/m4/hr/events', $payload)->assertCreated()->assertJsonPath('data.created_by', 11);
    $id = $response->json('data.id');
    $this->mis->table('company_events')->where('company_event_id', $id)->update(['location' => 'Existing location']);
    $this->putJson('/modules/m4/hr/events/'.$id, [...$payload, 'title' => 'Updated'])->assertOk();
    expect($this->mis->table('company_events')->where('company_event_id', $id)->value('location'))->toBe('Existing location');
    $this->getJson('/modules/m4/calendar/events?start=2026-10-01&end=2026-11-01')->assertOk()->assertJsonPath('0.end', '2026-10-14');
    $this->deleteJson('/modules/m4/hr/events/'.$id)->assertNoContent();
    expect($this->mis->table('company_events')->where('company_event_id', $id)->value('status'))->toBe('CANCELLED');
    $this->deleteJson('/modules/m4/hr/events/'.$id)->assertNotFound();
    $timedId = $this->mis->table('company_events')->insertGetId([
        'event_title' => 'Timed shoot', 'event_type' => 'shoot', 'start_datetime' => '2026-10-12 10:00:00',
        'end_datetime' => '2026-10-12 11:00:00', 'is_all_day' => false, 'status' => 'ACTIVE', 'created_by' => 11,
    ], 'company_event_id');
    $this->getJson('/modules/m4/calendar/events?start=2026-10-01&end=2026-11-01')->assertOk()->assertJsonPath('0.allDay', true)
        ->assertJsonPath('0.start', '2026-10-12')->assertJsonPath('0.end', '2026-10-13')
        ->assertJsonPath('0.extendedProps.sourceAllDay', false);
    $pdf = app(CalendarPdfData::class)->build(app(CalendarEmployeeDirectory::class)->find(103), misUser(), '2026-10');
    $day = collect($pdf['weeks'])->flatten(1)->filter()->firstWhere('day', 12);
    expect($day['events'])->toHaveCount(1);
    expect(fn () => app(CompanyEventSource::class)->update($timedId, $payload))->toThrow(ValidationException::class);
    expect($this->mis->table('company_events')->where('company_event_id', $timedId)->value('start_datetime'))->toBe('2026-10-12 10:00:00');
    $this->mis->table('company_events')->where('company_event_id', $timedId)->update(['end_datetime' => '2026-10-13 00:00:00']);
    $this->getJson('/modules/m4/calendar/events?start=2026-10-01&end=2026-11-01')->assertOk()->assertJsonPath('0.end', '2026-10-13');
});

it('never maps local demo numeric IDs onto unrelated MySQL accounts', function () {
    config(['module4.mode' => 'standalone', 'module4.standalone_preview' => true]);
    $demo = new User;
    $demo->forceFill(['id' => 11, 'name' => 'Local HR', 'email' => 'different@example.test', 'role' => 'hr_admin', 'employee_id' => 103]);
    $identity = app(CalendarIdentitySource::class)->forUser($demo);
    expect($identity)->toMatchArray(['id' => 0, 'employee_id' => null, 'role' => 'hr_admin']);
    $this->actingAs($demo);
    $this->postJson('/modules/m4/hr/events', ['title' => 'Denied', 'type' => 'other', 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-12'])->assertForbidden();
    expect($this->mis->table('company_events')->count())->toBe(0);
    expect(fn () => app(DemoSeeder::class)->run())->toThrow(RuntimeException::class);
    $linked = new User;
    $linked->forceFill(['id' => 999, 'name' => 'Local HR', 'email' => 'crew101@example.test', 'role' => 'hr_admin', 'employee_id' => 999]);
    $this->actingAs($linked);
    $this->postJson('/modules/m4/hr/events', ['title' => 'Linked creator', 'type' => 'other', 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-12'])
        ->assertCreated()->assertJsonPath('data.created_by', 11);
    expect((int) $this->mis->table('company_events')->value('created_by'))->toBe(11);
});

it('batches group reads and keeps scheduled days without punches unrecorded', function () {
    $this->mis->table('shifts')->insert(['shift_id' => 1, 'shift_name' => 'Night', 'start_time' => '22:00:00', 'end_time' => '06:00:00', 'break_minutes' => 60, 'status' => 'ACTIVE']);
    $this->mis->table('schedules')->insert(['employee_id' => 103, 'schedule_date' => '2026-10-12', 'shift_id' => 1, 'status' => 'SCHEDULED']);
    $source = app(CalendarDataSource::class);
    $from = CarbonImmutable::parse('2026-10-01');
    $to = $from->endOfMonth();
    $this->mis->enableQueryLog();
    $this->mis->flushQueryLog();
    $source->forEmployees([103], $from, $to);
    $singleQueries = count($this->mis->getQueryLog());
    $this->mis->flushQueryLog();
    $days = $source->forEmployees([101, 102, 103, 104], $from, $to);
    expect(count($this->mis->getQueryLog()))->toBe($singleQueries);
    expect($days->get(104))->toHaveCount(0);
    $row = $days->get(103)->first();
    expect($row->status)->toBe('unrecorded');
    expect($row->calendar_details['shift']['overnight'])->toBeTrue();
    expect($row->time_in)->toBeNull();
    $this->mis->disableQueryLog();
});
