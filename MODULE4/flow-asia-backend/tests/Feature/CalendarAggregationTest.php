<?php

use App\Models\AttendanceDaily;
use App\Models\CalendarReport;
use App\Models\CompanyEvent;
use App\Models\Employee;
use App\Models\User;
use App\Services\CalendarPdfData;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    $this->travelTo(now()->setDate(2026, 10, 14)->startOfDay());
});

it('combines attendance context events and employee widgets in one authorized month response', function () {
    $user = User::where('email', 'employee@test.com')->firstOrFail();
    CompanyEvent::create(['title' => 'Town hall', 'type' => 'town_hall', 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-13']);

    $response = $this->actingAs($user)->getJson('/calendar/employees/'.$user->employee_id.'/month?month=2026-10');

    $response->assertOk()->assertJsonPath('data.company_events.0.end', '2026-10-14')
        ->assertJsonPath('data.company_events.0.extendedProps.kind', 'company_event')
        ->assertJsonPath('data.days.0.shift.starts_at', '09:00:00')
        ->assertJsonPath('data.days.0.worked_minutes', 465)
        ->assertJsonPath('data.leave_balances.0.remaining_days', 8)
        ->assertJsonPath('data.next_payday', '2026-10-20')
        ->assertJsonPath('data.cutoff.start', '2026-10-01');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('prioritizes holiday then approved leave then invalid without counting rejected punches as present', function (array $extra, string $primary) {
    $user = User::where('email', 'employee@test.com')->firstOrFail();
    $row = AttendanceDaily::where('employee_id', $user->employee_id)->whereDate('date', '2026-10-01')->firstOrFail();
    $row->update(['status' => 'present', 'additional_statuses' => $extra, 'calendar_details' => ['punch_status' => 'rejected', 'correction' => ['status' => 'rejected', 'reason' => 'Unverified punch']]]);

    $response = $this->actingAs($user)->getJson('/calendar/employees/'.$user->employee_id.'/month?month=2026-10')->assertOk();

    $response->assertJsonPath('data.days.0.status', $primary)->assertJsonPath('data.days.0.worked_hours', null)
        ->assertJsonPath('data.days.0.correction.reason', 'Unverified punch');
    $day = $response->json('data.days.0');
    expect([$day['status'], ...$day['additional_statuses']])->toContain('invalid')->not->toContain('present');
    $pdf = app(CalendarPdfData::class)->build(Employee::findOrFail($user->employee_id), $user, '2026-10');
    expect($pdf['summary'])->toBe($response->json('data.summary.status_counts'));
    $this->assertDatabaseHas('attendance_daily', ['id' => $row->id, 'status' => 'present']);
})->with([
    'rejected alone' => [[], 'invalid'],
    'rejected and absent' => [['absent'], 'invalid'],
    'approved leave wins' => [['leave_approved', 'absent'], 'leave_approved'],
    'holiday wins' => [['holiday', 'leave_approved'], 'holiday'],
]);

it('shows submitted missing and not required reports and protects report contents by employee ownership', function () {
    $user = User::where('email', 'employee@test.com')->firstOrFail();
    $report = CalendarReport::updateOrCreate(['employee_id' => $user->employee_id, 'date' => '2026-10-01'],
        ['title' => 'Daily update', 'body' => '<script>private report</script>', 'submitted_at' => '2026-10-01 18:00:00']);
    AttendanceDaily::where('employee_id', $user->employee_id)->whereDate('date', '2026-10-02')
        ->update(['calendar_details' => json_encode(['shift' => ['report_required' => true]])]);
    AttendanceDaily::where('employee_id', $user->employee_id)->whereDate('date', '2026-10-03')
        ->update(['calendar_details' => json_encode(['shift' => ['report_required' => false]])]);
    CalendarReport::where('employee_id', $user->employee_id)->whereDate('date', '2026-10-02')->delete();

    $response = $this->actingAs($user)->getJson('/calendar/employees/'.$user->employee_id.'/month?month=2026-10');

    $response->assertOk()->assertJsonPath('data.days.0.report.status', 'submitted')
        ->assertJsonPath('data.days.1.report.status', 'missing')->assertJsonPath('data.days.2.report.status', 'not_required');
    $this->get($response->json('data.days.0.report.url'))->assertOk()->assertSee('&lt;script&gt;private report&lt;/script&gt;', false)
        ->assertDontSee('<script>private report</script>', false);
    $other = Employee::where('employee_no', 'EMP-009')->firstOrFail();
    $this->getJson('/calendar/employees/'.$other->id.'/reports/'.$report->id)->assertForbidden();
    $this->actingAs(User::where('email', 'hr@test.com')->firstOrFail())
        ->get('/calendar/employees/'.$other->id.'/reports/'.$report->id)->assertNotFound();
    $this->get($response->json('data.days.0.report.url'))->assertOk();
    $this->postJson($response->json('data.days.0.report.url'), [])->assertForbidden();
    $this->actingAs(User::where('email', 'supervisor@test.com')->firstOrFail())
        ->get($response->json('data.days.0.report.url'))->assertOk();
    $this->app['auth']->forgetGuards();
    $this->getJson($response->json('data.days.0.report.url'))->assertUnauthorized();
});

it('computes overnight worked hours only for a declared overnight shift and subtracts declared breaks', function () {
    $user = User::where('email', 'employee@test.com')->firstOrFail();
    $row = AttendanceDaily::where('employee_id', $user->employee_id)->whereDate('date', '2026-10-01')->firstOrFail();
    $row->update(['time_in' => '22:00:00', 'time_out' => '06:00:00', 'calendar_details' => ['shift' => ['overnight' => true, 'break_minutes' => 30]]]);

    $this->actingAs($user)->getJson('/calendar/employees/'.$user->employee_id.'/month?month=2026-10')
        ->assertOk()->assertJsonPath('data.days.0.worked_hours', 7.5);
    $row->update(['calendar_details' => []]);
    $this->getJson('/calendar/employees/'.$user->employee_id.'/month?month=2026-10')
        ->assertOk()->assertJsonPath('data.days.0.worked_hours', null);
});

it('uses source leave decisions and counts each simultaneous status once per day', function () {
    $user = User::where('email', 'employee@test.com')->firstOrFail();
    AttendanceDaily::where('employee_id', $user->employee_id)->delete();
    AttendanceDaily::create(['employee_id' => $user->employee_id, 'date' => '2026-10-01', 'status' => 'present',
        'additional_statuses' => ['holiday', 'holiday'], 'calendar_details' => ['leave' => ['type' => 'Sick', 'approval_status' => 'approved', 'days' => 0.5]], 'late_minutes' => 0, 'ot_hours' => 2]);

    $this->actingAs($user)->getJson('/calendar/employees/'.$user->employee_id.'/month?month=2026-10')
        ->assertOk()->assertJsonPath('data.days.0.status', 'holiday')
        ->assertJsonPath('data.summary.status_counts.holiday', 1)->assertJsonPath('data.summary.status_counts.leave_approved', 1)
        ->assertJsonPath('data.summary.leave_days_used', 0.5)->assertJsonPath('data.summary.cutoff_ot_hours', 2)
        ->assertJsonPath('data.summary.missing_days', 30);
});

it('does not fabricate balances payday shifts or reports when shared context is unavailable', function () {
    $user = User::where('email', 'employee@test.com')->firstOrFail();
    Employee::findOrFail($user->employee_id)->update(['calendar_profile' => null]);
    AttendanceDaily::where('employee_id', $user->employee_id)->update(['calendar_details' => null]);
    CalendarReport::where('employee_id', $user->employee_id)->delete();

    $this->actingAs($user)->getJson('/calendar/employees/'.$user->employee_id.'/month?month=2026-10')->assertOk()
        ->assertJsonCount(0, 'data.leave_balances')->assertJsonPath('data.next_payday', null)
        ->assertJsonPath('data.days.0.shift', null)->assertJsonPath('data.days.0.report.status', 'unavailable');
});

it('removes a cancelled leave status without inventing presence or absence', function () {
    $user = User::where('email', 'employee@test.com')->firstOrFail();
    AttendanceDaily::where('employee_id', $user->employee_id)->whereDate('date', '2026-10-01')->update([
        'status' => 'leave_approved', 'additional_statuses' => '[]',
        'calendar_details' => json_encode(['leave' => ['type' => 'Vacation', 'approval_status' => 'cancelled']]),
    ]);

    $this->actingAs($user)->getJson('/calendar/employees/'.$user->employee_id.'/month?month=2026-10')->assertOk()
        ->assertJsonPath('data.days.0.status', 'unrecorded')->assertJsonPath('data.days.0.additional_statuses', []);
});

it('whitelists nested calendar metadata instead of exposing private source attributes', function () {
    $user = User::where('email', 'employee@test.com')->firstOrFail();
    AttendanceDaily::where('employee_id', $user->employee_id)->whereDate('date', '2026-10-01')->update([
        'calendar_details' => json_encode(['shift' => ['starts_at' => '09:00:00', 'salary' => 'private'],
            'leave' => ['type' => 'Sick', 'approval_status' => 'pending', 'bank_account' => 'private'],
            'correction' => ['status' => 'pending', 'TIN' => 'private']]),
    ]);

    $response = $this->actingAs($user)->getJson('/calendar/employees/'.$user->employee_id.'/month?month=2026-10')->assertOk();

    expect($response->json('data.days.0.shift'))->toBe(['starts_at' => '09:00:00']);
    expect($response->json('data.days.0.leave'))->toBe(['type' => 'Sick', 'approval_status' => 'pending']);
    expect($response->json('data.days.0.correction'))->toBe(['status' => 'pending']);
});

it('includes approved overtime across a month boundary for the supplied pay cutoff', function () {
    $user = User::where('email', 'employee@test.com')->firstOrFail();
    $this->travelTo(now()->setDate(2026, 10, 2));
    Employee::findOrFail($user->employee_id)->update(['calendar_profile' => ['pay_periods' => [
        ['start' => '2026-09-25', 'end' => '2026-10-10', 'payday' => '2026-10-15'],
    ]]]);
    AttendanceDaily::where('employee_id', $user->employee_id)->delete();
    AttendanceDaily::create(['employee_id' => $user->employee_id, 'date' => '2026-09-30', 'status' => 'present', 'ot_hours' => 2]);
    AttendanceDaily::create(['employee_id' => $user->employee_id, 'date' => '2026-10-01', 'status' => 'present', 'ot_hours' => 3]);

    $this->actingAs($user)->getJson('/calendar/employees/'.$user->employee_id.'/month?month=2026-10')->assertOk()
        ->assertJsonCount(1, 'data.days')->assertJsonPath('data.summary.approved_ot_hours', 3)
        ->assertJsonPath('data.summary.cutoff_ot_hours', 5)->assertJsonPath('data.days.0.status', 'overtime');
});

it('bounds query counts for a 240 employee month without per employee attendance or report queries', function () {
    $department = Employee::firstOrFail()->department_id;
    $employees = [];
    for ($number = 41; $number <= 240; $number++) {
        $employees[] = ['employee_no' => 'STRESS-'.$number, 'first_name' => 'Load', 'last_name' => 'Employee '.$number,
            'position' => 'Team Member', 'department_id' => $department, 'hired_on' => '2025-01-01'];
    }
    Employee::insert($employees);
    $rows = [];
    foreach (Employee::where('employee_no', 'like', 'STRESS-%')->pluck('id') as $id) {
        for ($day = 1; $day <= 31; $day++) {
            $rows[] = ['employee_id' => $id, 'date' => sprintf('2026-10-%02d', $day), 'status' => 'present'];
        }
    }
    foreach (array_chunk($rows, 200) as $chunk) {
        AttendanceDaily::insert($chunk);
    }
    $this->actingAs(User::where('email', 'hr@test.com')->firstOrFail());
    DB::enableQueryLog();

    $response = $this->getJson('/hr/calendar/team?month=2026-10');

    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();
    $response->assertOk()->assertJsonCount(240, 'employees');
    expect($queries->filter(fn (array $query): bool => str_contains($query['query'], 'from "attendance_daily"')))->toHaveCount(1);
    expect($queries->filter(fn (array $query): bool => str_contains($query['query'], 'from "calendar_reports"')))->toHaveCount(1);
    expect($queries->count())->toBeLessThanOrEqual(5);
});
