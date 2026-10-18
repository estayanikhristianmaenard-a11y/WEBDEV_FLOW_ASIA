<?php

namespace FlowAsia\Module4\Services;

use Carbon\CarbonImmutable;
use FlowAsia\Module4\Contracts\CalendarContextSource;
use FlowAsia\Module4\Contracts\CalendarDataSource;
use FlowAsia\Module4\Contracts\CalendarEmployeeDirectory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

class TeamCalendarService
{
    public function __construct(private CalendarDataSource $source, private CalendarContextSource $context, private CalendarDayPresenter $presenter, private CalendarEmployeeDirectory $directory) {}

    public function build(Authenticatable $user, string $month, ?int $departmentId): array
    {
        $from = CarbonImmutable::createFromFormat('!Y-m', $month, config('module4.timezone'))->startOfMonth();
        $to = $from->endOfMonth();
        $today = CarbonImmutable::today(config('module4.timezone'));
        $employees = $this->directory->visibleTo($user, $departmentId);

        foreach ($employees as $employee) {
            Gate::forUser($user)->authorize('viewCalendar', $employee);
        }

        // A single batch includes the selected month and today's independent panel.
        $rows = $this->source->forEmployees($employees->pluck('id')->all(), $from->min($today), $to->max($today));
        $contexts = $this->context->forEmployees($employees->pluck('id')->all(), $from->min($today), $to->max($today));
        $result = [];
        $todayGroups = ['absent' => [], 'late' => [], 'on_leave' => []];
        $pending = ['leave' => [], 'overtime' => []];
        $leaves = [];

        foreach ($employees as $employee) {
            $identity = [
                'id' => $employee->id, 'employee_no' => $employee->employee_no,
                'name' => trim($employee->first_name.' '.$employee->last_name),
                'position' => $employee->position,
                'department' => $employee->department ? ['id' => $employee->department->id, 'name' => $employee->department->name] : null,
            ];
            $days = [];
            foreach ($rows->get($employee->id, collect()) as $row) {
                $date = $row->date->toDateString();
                $day = $this->presenter->present($row, $contexts->get($employee->id, [])['reports'][$date] ?? null);
                $statuses = array_unique([$day['status'], ...$day['additional_statuses']]);
                if ($date === $today->toDateString()) {
                    foreach (['absent' => 'absent', 'late' => 'late', 'on_leave' => 'leave_approved'] as $group => $status) {
                        if (in_array($status, $statuses, true)) {
                            $todayGroups[$group][] = $identity;
                        }
                    }
                }
                if ($date < $from->toDateString() || $date > $to->toDateString()) {
                    continue;
                }
                $days[] = $day;
                $entry = ['employee_id' => $employee->id, 'date' => $date];
                if (in_array('leave_pending', $statuses, true)) {
                    $pending['leave'][] = $entry;
                }
                if ((float) $row->pending_ot_hours > 0) {
                    $pending['overtime'][] = [...$entry, 'hours' => (float) $row->pending_ot_hours];
                }
                if (in_array('leave_approved', $statuses, true)) {
                    $key = $date.':'.$employee->department_id;
                    $leaves[$key] ??= ['date' => $date, 'department_id' => $employee->department_id, 'employee_ids' => []];
                    $leaves[$key]['employee_ids'][] = $employee->id;
                }
            }
            $result[] = [...$identity, 'days' => $days];
        }

        return [
            'month' => $month, 'read_only' => true, 'employees' => $result,
            'today' => ['date' => $today->toDateString(), ...$todayGroups],
            'leave_overlaps' => array_values(array_filter($leaves, fn ($leave) => count($leave['employee_ids']) > 1)),
            'pending' => $pending,
        ];
    }
}
