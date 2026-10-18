<?php

use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use FlowAsia\Module4\Contracts\CalendarContextSource;
use FlowAsia\Module4\Contracts\CalendarDataSource;
use FlowAsia\Module4\Contracts\CalendarEmployeeDirectory;
use FlowAsia\Module4\Contracts\CompanyEventSource;
use FlowAsia\Module4\Providers\Module4ServiceProvider;
use FlowAsia\Module4\Services\CalendarEmployee;
use FlowAsia\Module4\Services\CalendarPdfData;
use FlowAsia\Module4\Services\StubCalendarDataSource;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\SharedModuleTestCase;
use Tests\SharedTestEvents;

uses(SharedModuleTestCase::class);

function sharedModuleUser(string $role = 'TeamLead'): GenericUser
{
    return new GenericUser(['id' => 77, 'display_name' => 'Shared Team Lead', 'access_role' => $role, 'staff_id' => '101']);
}

function installSharedModuleSources(): SharedTestEvents
{
    $people = collect([
        101 => new CalendarEmployee(101, 'CREW-101', 'Team', 'Lead', 'Supervisor', 9, (object) ['id' => 9, 'name' => 'Production']),
        102 => new CalendarEmployee(102, 'CREW-102', 'Shared', 'Crew', 'Artist', 9, (object) ['id' => 9, 'name' => 'Production'], 101),
        103 => new CalendarEmployee(103, 'CREW-103', 'Other', 'Team', 'Artist', 9, (object) ['id' => 9, 'name' => 'Production'], 999),
    ]);
    $visible = fn ($user): Collection => $people->filter(fn (CalendarEmployee $employee): bool => Gate::forUser($user)->allows('viewCalendar', $employee))->values();
    $directory = Mockery::mock(CalendarEmployeeDirectory::class);
    $directory->shouldReceive('find')->andReturnUsing(fn (int $id): ?CalendarEmployee => $people->get($id));
    $directory->shouldReceive('visibleTo')->andReturnUsing(fn ($user, $department): Collection => $department === null || $department === 9 ? $visible($user) : collect());
    $directory->shouldReceive('search')->andReturnUsing(fn ($user, $term): Collection => $visible($user));
    $directory->shouldReceive('departmentExists')->andReturnUsing(fn (int $id): bool => $id === 9);
    app()->instance(CalendarEmployeeDirectory::class, $directory);

    $rows = $people->map(fn (CalendarEmployee $person): object => (object) [
        'employee_id' => $person->id, 'date' => CarbonImmutable::parse('2026-10-01'), 'status' => 'present',
        'additional_statuses' => [], 'late_minutes' => 0, 'ot_hours' => 1, 'pending_ot_hours' => 0,
        'time_in' => '09:00:00', 'time_out' => '18:00:00', 'remarks' => 'Upstream record',
        'calendar_details' => ['worked_minutes' => 480, 'report_required' => true],
    ]);
    $source = Mockery::mock(CalendarDataSource::class);
    $source->shouldReceive('forEmployee')->andReturnUsing(fn ($id, $from, $to): Collection => collect([$rows->get($id)])->filter(fn ($row): bool => $row && $row->date->betweenIncluded($from, $to))->values());
    $source->shouldReceive('forEmployees')->andReturnUsing(fn ($ids, $from, $to): Collection => collect($ids)->mapWithKeys(fn ($id): array => [$id => collect([$rows->get($id)])->filter(fn ($row): bool => $row && $row->date->betweenIncluded($from, $to))->values()]));
    app()->instance(CalendarDataSource::class, $source);
    $context = Mockery::mock(CalendarContextSource::class);
    $context->shouldReceive('forEmployees')->andReturnUsing(fn ($ids, $from, $to): Collection => collect($ids)->mapWithKeys(fn ($id): array => [$id => [
        'profile' => ['leave_balances' => [['type' => 'Vacation', 'remaining_days' => 5]], 'pay_periods' => []],
        'reports' => $id === 102 ? ['2026-10-01' => ['id' => 501, 'submitted_at' => '2026-10-01T18:00:00+08:00']] : [],
    ]]));
    $context->shouldReceive('reportForEmployee')->andReturnUsing(fn ($employee, $report): ?array => $employee === 102 && $report === 501
        ? ['title' => 'Shared progress', 'body' => 'Upstream report body', 'date' => '2026-10-01'] : null);
    app()->instance(CalendarContextSource::class, $context);
    app()->instance(CompanyEventSource::class, $events = new SharedTestEvents);

    return $events;
}

beforeEach(function () {
    config([
        'module4.standalone_preview' => false,
        'module4.identity.fields' => ['name' => 'display_name', 'role' => 'access_role', 'employee_id' => 'staff_id'],
        'module4.identity.roles' => ['TeamLead' => 'supervisor', 'HR' => 'hr_admin'],
    ]);
    Route::get('/', fn () => response('Host dashboard'));
    Route::post('/calendar/host-action', fn () => response('Host action'));
});

it('registers prefixed routes and no standalone migrations while preserving host routes', function () {
    expect(config('module4.mode'))->toBe('shared');
    expect(app('migrator')->getMigrationFiles([database_path('migrations'), ...app('migrator')->paths()]))->toBe([]);
    expect(Route::has('m4.calendar.employee.month'))->toBeTrue();
    expect(Route::has('calendar.employee.month'))->toBeFalse();
    $this->get('/')->assertOk()->assertSee('Host dashboard');
    $this->post('/calendar/host-action')->assertOk();
    $this->postJson('/modules/m4/calendar/anything')->assertForbidden();
    config(['module4.standalone_preview' => true]);
    $this->getJson('/modules/m4')->assertUnauthorized();
});

it('serves the host identity employee calendar team report and PDF with no demo tables or queries', function () {
    installSharedModuleSources();
    expect(Schema::hasTable('employees'))->toBeFalse();
    DB::enableQueryLog();
    DB::flushQueryLog();
    $this->actingAs(sharedModuleUser());
    $this->get('/modules/m4')->assertOk()->assertSee('"role":"supervisor"', false)->assertSee('Shared Team Lead')
        ->assertSee('modules\/m4', false);
    $this->get('/modules/m4/assets/js/core/api.js')->assertOk();
    $this->getJson('/modules/m4/hr/employees/search?q=Crew')->assertOk()->assertJsonCount(2, 'data');
    $response = $this->getJson('/modules/m4/calendar/employees/102/month?month=2026-10')->assertOk()
        ->assertJsonPath('data.employee_no', 'CREW-102')->assertJsonPath('data.leave_balances.0.remaining_days', 5)
        ->assertJsonPath('data.days.0.report.status', 'submitted');
    $this->get($response->json('data.days.0.report.url'))->assertOk()->assertSee('Upstream report body');
    $this->getJson('/modules/m4/hr/calendar/team?month=2026-10&department_id=9')->assertOk()->assertJsonCount(2, 'employees');
    $this->get('/modules/m4/calendar/employees/102/pdf?month=2026-10')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(DB::getQueryLog())->toBe([]);
});

it('enforces role ownership and missing-record validation with an external user model', function () {
    $events = installSharedModuleSources();
    $this->actingAs(sharedModuleUser());
    $this->getJson('/modules/m4/calendar/employees/103/month?month=2026-10')->assertForbidden();
    $this->getJson('/modules/m4/calendar/employees/999/month?month=2026-10')->assertNotFound();
    $this->getJson('/modules/m4/calendar/employees/101/reports/501')->assertNotFound();
    $this->getJson('/modules/m4/hr/calendar/team?month=2026-10&department_id=999')->assertUnprocessable();
    $this->postJson('/modules/m4/hr/events', [])->assertForbidden();
    expect($events->records)->toBe([]);
    $this->actingAs(sharedModuleUser('unknown'));
    $this->getJson('/modules/m4/hr/employees/search?q=Crew')->assertForbidden();
    $this->getJson('/modules/m4/calendar/employees/101/month?month=2026-10')->assertForbidden();
});

it('uses a replaceable event store for HR writes feeds calendars and PDF data', function () {
    $events = installSharedModuleSources();
    $this->actingAs(sharedModuleUser('HR'));
    Gate::define('manage-events', fn (): bool => false);
    expect(Gate::allows('manage-events'))->toBeFalse();
    $payload = ['title' => 'Shared town hall', 'type' => 'town_hall', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-02'];
    $this->postJson('/modules/m4/hr/events', $payload)->assertCreated()->assertJsonPath('data.created_by', 77);
    $this->getJson('/modules/m4/calendar/events?start=2026-10-01&end=2026-11-01')->assertOk()->assertJsonPath('0.end', '2026-10-03');
    $this->getJson('/modules/m4/calendar/employees/102/month?month=2026-10')->assertOk()->assertJsonPath('data.company_events.0.title', 'Shared town hall');
    $this->putJson('/modules/m4/hr/events/1', [...$payload, 'title' => 'Updated shared hall'])->assertOk()->assertJsonPath('data.title', 'Updated shared hall');
    $this->getJson('/modules/m4/hr/events')->assertOk()->assertJsonPath('data.0.title', 'Updated shared hall');
    $this->deleteJson('/modules/m4/hr/events/1')->assertNoContent();
    $this->deleteJson('/modules/m4/hr/events/1')->assertNotFound();
    expect($events->records)->toBe([]);
});

it('reports missing adapters instead of falling back to demo storage in shared mode', function () {
    $this->artisan('m4:check')->assertFailed();
    config(['module4.sources.attendance' => StubCalendarDataSource::class]);
    expect(fn () => app(CalendarDataSource::class))->toThrow(LogicException::class);
});

it('passes the assembly check with explicit host adapter bindings', function () {
    installSharedModuleSources();
    $this->artisan('m4:check')->assertSuccessful();
});

it('keeps the module calendar date independent of the host timezone', function () {
    installSharedModuleSources();
    config(['app.timezone' => 'UTC']);
    $this->travelTo(CarbonImmutable::parse('2026-10-31T18:00:00Z'));
    $this->actingAs(sharedModuleUser())->get('/modules/m4')->assertOk()->assertSee('"month":"2026-11"', false);
    $this->getJson('/modules/m4/hr/calendar/team?month=2026-10')->assertOk()->assertJsonPath('today.date', '2026-11-01');
});

it('rejects demo seeding and reports routes left out of sync with configuration', function () {
    expect(fn () => (new DemoSeeder)->run())->toThrow(RuntimeException::class);
    installSharedModuleSources();
    config(['module4.route_prefix' => 'other-prefix']);
    $this->artisan('m4:check')->expectsOutputToContain('M4 routes do not match configuration')->assertFailed();
});

it('authorizes every team result before asking upstream modules for attendance', function () {
    installSharedModuleSources();
    $directory = Mockery::mock(CalendarEmployeeDirectory::class);
    $directory->shouldReceive('visibleTo')->andReturn(collect([new CalendarEmployee(103, 'CREW-103', 'Other', 'Team', supervisor_id: 999)]));
    app()->instance(CalendarEmployeeDirectory::class, $directory);
    $source = Mockery::mock(CalendarDataSource::class);
    $source->shouldNotReceive('forEmployees');
    app()->instance(CalendarDataSource::class, $source);
    $this->actingAs(sharedModuleUser())->getJson('/modules/m4/hr/calendar/team?month=2026-10')->assertForbidden();
});

it('installs safeguards when the host HTTP kernel was already resolved before provider boot', function () {
    $kernel = app(Kernel::class);
    $kernel->setGlobalMiddleware([]);
    (new Module4ServiceProvider(app()))->boot();

    $this->postJson('/modules/m4/calendar/unregistered-write')->assertForbidden();
});

it('rereads upstream changes consistently across employee team context and PDF without copying records', function () {
    installSharedModuleSources();
    $this->travelTo(CarbonImmutable::parse('2026-10-01T12:00:00+08:00'));
    $record = (object) [
        'employee_id' => 102, 'date' => CarbonImmutable::parse('2026-10-01'),
        'status' => 'present', 'additional_statuses' => [],
        'late_minutes' => 0, 'ot_hours' => 0, 'pending_ot_hours' => 0,
        'time_in' => '09:00:00', 'time_out' => '18:00:00', 'remarks' => null,
        'calendar_details' => ['worked_minutes' => 480],
    ];
    $profile = (object) ['remaining' => 5, 'reports' => []];
    $source = Mockery::mock(CalendarDataSource::class);
    $read = fn ($id, $from, $to): Collection => collect([$record])->filter(
        fn ($row): bool => $row->employee_id === $id && $row->date->betweenIncluded($from, $to),
    )->values();
    $source->shouldReceive('forEmployee')->andReturnUsing($read);
    $source->shouldReceive('forEmployees')->andReturnUsing(fn ($ids, $from, $to): Collection => collect($ids)
        ->mapWithKeys(fn ($id): array => [$id => $read($id, $from, $to)]));
    app()->instance(CalendarDataSource::class, $source);
    $context = Mockery::mock(CalendarContextSource::class);
    $context->shouldReceive('forEmployees')->andReturnUsing(fn ($ids): Collection => collect($ids)->mapWithKeys(fn ($id): array => [$id => [
        'profile' => ['leave_balances' => [['type' => 'Vacation', 'remaining_days' => $profile->remaining]],
            'pay_periods' => [['start' => '2026-10-01', 'end' => '2026-10-15', 'payday' => '2026-10-15']]],
        'reports' => $profile->reports,
    ]]));
    app()->instance(CalendarContextSource::class, $context);
    DB::enableQueryLog();
    DB::flushQueryLog();
    $user = sharedModuleUser();
    $this->actingAs($user);
    $url = '/modules/m4/calendar/employees/102/month?month=2026-10';
    $this->getJson($url)->assertOk()->assertJsonPath('data.summary.status_counts.present', 1)
        ->assertJsonPath('data.leave_balances.0.remaining_days', 5);

    $record->status = 'leave_approved';
    $record->time_in = $record->time_out = null;
    $record->calendar_details = ['leave' => ['type' => 'Vacation', 'approval_status' => 'approved', 'days' => 1]];
    $profile->remaining = 4;
    $this->getJson($url)->assertOk()->assertJsonPath('data.summary.status_counts.present', 0)
        ->assertJsonPath('data.summary.leave_days_used', 1)->assertJsonPath('data.leave_balances.0.remaining_days', 4);
    $this->getJson('/modules/m4/hr/calendar/team?month=2026-10')->assertOk()
        ->assertJsonPath('today.on_leave.0.id', 102)->assertJsonPath('employees.1.days.0.status', 'leave_approved');

    $record->status = 'present';
    $record->ot_hours = 2;
    $record->time_in = '09:00:00';
    $record->time_out = '20:00:00';
    $record->calendar_details = ['worked_minutes' => 600];
    $profile->reports = ['2026-10-01' => ['id' => 501, 'submitted_at' => '2026-10-01T20:00:00+08:00']];
    $response = $this->getJson($url)->assertOk()->assertJsonPath('data.summary.leave_days_used', 0)
        ->assertJsonPath('data.summary.cutoff_ot_hours', 2)->assertJsonPath('data.days.0.report.status', 'submitted')
        ->assertJsonPath('data.next_payday', '2026-10-15');
    $this->getJson('/modules/m4/hr/calendar/team?month=2026-10')->assertOk()
        ->assertJsonPath('employees.1.days.0.status', 'overtime')->assertJsonCount(0, 'today.on_leave');
    $employee = app(CalendarEmployeeDirectory::class)->find(102);
    $pdf = app(CalendarPdfData::class)->build($employee, $user, '2026-10');
    expect($pdf['summary'])->toBe($response->json('data.summary.status_counts'));
    expect($pdf['totals']['cutoff_ot_hours'])->toBe(2.0);
    expect(DB::getQueryLog())->toBe([]);
});
