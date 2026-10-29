<?php

namespace FlowAsia\Module4\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use FlowAsia\Module4\Contracts\CalendarDataSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use UnexpectedValueException;

class MisCalendarDataSource implements CalendarDataSource
{
    public function forEmployee(int $employeeId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->forEmployees([$employeeId], $from, $to)->get($employeeId, collect());
    }

    public function forEmployees(array $employeeIds, CarbonInterface $from, CarbonInterface $to): Collection
    {
        if ($from->gt($to)) {
            throw new InvalidArgumentException('The end date must be on or after the start date.');
        }
        $employeeIds = array_values(array_unique($employeeIds));
        $result = collect($employeeIds)->mapWithKeys(fn ($id): array => [$id => collect()]);
        if ($employeeIds === []) {
            return $result;
        }
        $db = DB::connection(config('module4.mis_connection'));
        $bounds = [$from->toDateString(), $to->toDateString()];
        $rows = [];
        $daily = $db->table('attendance_daily')->whereIn('employee_id', $employeeIds)
            ->whereBetween('attendance_date', $bounds)->orderBy('attendance_date')
            ->get(['attendance_daily_id', 'employee_id', 'attendance_date', 'attendance_status', 'clock_in', 'clock_out',
                'worked_minutes', 'late_minutes', 'overtime_minutes', 'remarks']);
        foreach ($daily as $record) {
            $row = $this->day($rows, (int) $record->employee_id, $record->attendance_date);
            $row->status = $this->status($record->attendance_status);
            $row->time_in = $record->clock_in ? substr($record->clock_in, 11, 8) : null;
            $row->time_out = $record->clock_out ? substr($record->clock_out, 11, 8) : null;
            $row->late_minutes = (int) $record->late_minutes;
            $row->ot_hours = (float) $record->overtime_minutes / 60;
            $row->remarks = $record->remarks;
            $row->calendar_details['worked_minutes'] = (int) $record->worked_minutes;
        }
        $schedules = $db->table('schedules as s')->join('shifts as shift', 'shift.shift_id', '=', 's.shift_id')
            ->whereIn('s.employee_id', $employeeIds)->whereBetween('s.schedule_date', $bounds)
            ->whereRaw('UPPER(s.status) NOT IN (?, ?)', ['CANCELLED', 'CANCELED'])
            ->get(['s.employee_id', 's.schedule_date', 'shift.start_time', 'shift.end_time', 'shift.break_minutes']);
        foreach ($schedules as $schedule) {
            $row = $this->day($rows, (int) $schedule->employee_id, $schedule->schedule_date);
            $row->calendar_details['shift'] = [
                'starts_at' => $schedule->start_time, 'ends_at' => $schedule->end_time,
                'break_minutes' => (int) $schedule->break_minutes,
                'overnight' => $schedule->end_time < $schedule->start_time,
            ];
        }
        $holidays = $db->table('holidays')->whereBetween('holiday_date', $bounds)->get(['holiday_date', 'holiday_name']);
        if ($holidays->isNotEmpty()) {
            $employees = $db->table('employees')->whereIn('employee_id', $employeeIds)->get(['employee_id', 'hire_date', 'separation_date']);
            foreach ($employees as $employee) {
                foreach ($holidays as $holiday) {
                    if ($holiday->holiday_date < $employee->hire_date || ($employee->separation_date && $holiday->holiday_date > $employee->separation_date)) {
                        continue;
                    }
                    $row = $this->day($rows, (int) $employee->employee_id, $holiday->holiday_date);
                    $row->calendar_details['holiday'] = ['name' => $holiday->holiday_name];
                    $row->additional_statuses[] = 'holiday';
                }
            }
        }
        $leaves = $db->table('leave_requests as l')->join('leave_types as t', 't.leave_type_id', '=', 'l.leave_type_id')
            ->whereIn('l.employee_id', $employeeIds)->where('l.start_date', '<=', $bounds[1])->where('l.end_date', '>=', $bounds[0])
            ->whereIn(DB::raw('UPPER(l.status)'), ['APPROVED', 'HR_APPROVED', 'APPROVED_HR', 'PENDING', 'PENDING_SUPERVISOR', 'PENDING_HR'])
            ->orderBy('l.leave_request_id')->get(['l.employee_id', 'l.start_date', 'l.end_date', 'l.total_days', 'l.status', 't.leave_type_name']);
        foreach ($leaves as $leave) {
            $start = CarbonImmutable::parse($leave->start_date, config('module4.timezone'));
            $end = CarbonImmutable::parse($leave->end_date, config('module4.timezone'));
            if ($end->lt($start)) {
                throw new UnexpectedValueException('A MIS leave request has an invalid date range.');
            }
            $length = (int) $start->diffInDays($end) + 1;
            $approved = in_array(strtoupper($leave->status), ['APPROVED', 'HR_APPROVED', 'APPROVED_HR'], true);
            $perDay = floor((float) $leave->total_days * 100 / $length) / 100;
            for ($date = $start->max($from); $date->lte($end->min($to)); $date = $date->addDay()) {
                $row = $this->day($rows, (int) $leave->employee_id, $date->toDateString());
                if (($row->calendar_details['leave']['approval_status'] ?? null) === 'approved') {
                    continue;
                }
                $row->calendar_details['leave'] = [
                    'type' => $leave->leave_type_name, 'approval_status' => $approved ? 'approved' : 'pending',
                    'days' => $date->isSameDay($end) ? round((float) $leave->total_days - $perDay * ($length - 1), 2) : $perDay,
                ];
                $row->additional_statuses[] = $approved ? 'leave_approved' : 'leave_pending';
            }
        }
        $overtime = $db->table('overtime_requests')->whereIn('employee_id', $employeeIds)->whereBetween('overtime_date', $bounds)
            ->whereIn(DB::raw('UPPER(status)'), ['APPROVED', 'PENDING', 'PENDING_SUPERVISOR', 'PENDING_HR'])
            ->get(['employee_id', 'overtime_date', 'status', 'approved_minutes', 'requested_minutes']);
        $recorded = $daily->mapWithKeys(fn (object $record): array => [$record->employee_id.':'.$record->attendance_date => true]);
        foreach ($overtime as $request) {
            $row = $this->day($rows, (int) $request->employee_id, $request->overtime_date);
            if (strtoupper($request->status) === 'APPROVED') {
                if (! $recorded->has($request->employee_id.':'.$request->overtime_date)) {
                    $row->ot_hours += (float) ($request->approved_minutes ?? 0) / 60;
                }
            } else {
                $row->pending_ot_hours += (float) $request->requested_minutes / 60;
            }
        }
        $corrections = $db->table('correction_requests as c')
            ->join('attendance_daily as a', 'a.attendance_daily_id', '=', 'c.attendance_daily_id')
            ->whereIn('c.employee_id', $employeeIds)->whereColumn('c.employee_id', 'a.employee_id')
            ->whereBetween('a.attendance_date', $bounds)->orderBy('c.created_at')->orderBy('c.correction_request_id')
            ->get(['c.employee_id', 'a.attendance_date', 'c.status', 'c.reason', 'c.created_at']);
        foreach ($corrections as $correction) {
            $row = $this->day($rows, (int) $correction->employee_id, $correction->attendance_date);
            $row->calendar_details['correction'] = [
                'status' => strtolower($correction->status), 'reason' => $correction->reason,
                'requested_at' => $correction->created_at,
            ];
        }

        foreach ($employeeIds as $id) {
            $result[$id] = collect($rows[$id] ?? [])->sortKeys()->values();
        }

        return $result;
    }

    private function day(array &$rows, int $employeeId, string $date): object
    {
        return $rows[$employeeId][$date] ??= (object) [
            'employee_id' => $employeeId, 'date' => CarbonImmutable::parse($date, config('module4.timezone')),
            'status' => 'unrecorded', 'additional_statuses' => [], 'time_in' => null, 'time_out' => null,
            'late_minutes' => 0, 'ot_hours' => 0.0, 'pending_ot_hours' => 0.0, 'remarks' => null, 'calendar_details' => [],
        ];
    }

    private function status(string $status): string
    {
        $normalized = strtolower(str_replace([' ', '-'], '_', trim($status)));
        $normalized = match ($normalized) {
            'on_leave', 'leave', 'approved_leave' => 'leave_approved',
            'pending_leave' => 'leave_pending',
            'invalid_punch' => 'invalid',
            'ot' => 'overtime',
            'rest' => 'rest_day',
            default => $normalized,
        };
        if (! in_array($normalized, [...array_keys(config('module4_calendar.statuses')), 'invalid', 'unrecorded'], true)) {
            throw new UnexpectedValueException('Unsupported MIS attendance status. Configure its calendar mapping before use.');
        }

        return $normalized;
    }
}
