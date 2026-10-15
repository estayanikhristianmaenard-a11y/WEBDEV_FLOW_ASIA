<?php

namespace FlowAsia\Module4\Services;

use Illuminate\Support\Arr;

class CalendarDayPresenter
{
    public function present(object $row, ?array $report = null): array
    {
        $details = $row->calendar_details ?? [];
        $statuses = array_values(array_unique([$row->status, ...($row->additional_statuses ?? [])]));
        $rejected = ($details['punch_status'] ?? null) === 'rejected' || in_array('invalid', $statuses, true);
        if ($rejected) {
            $statuses = array_values(array_diff($statuses, ['present', 'late', 'half_day', 'overtime']));
            $statuses[] = 'invalid';
        }
        if (! empty($details['holiday']['name'])) {
            $statuses[] = 'holiday';
        }
        if (! $rejected && (float) $row->ot_hours > 0) {
            $statuses[] = 'overtime';
        }
        $leaveStatus = $details['leave']['approval_status'] ?? null;
        if (in_array($leaveStatus, ['approved', 'pending', 'rejected', 'cancelled'], true)) {
            $statuses = array_values(array_diff($statuses, ['leave_approved', 'leave_pending']));
            if (in_array($leaveStatus, ['approved', 'pending'], true)) {
                $statuses[] = $leaveStatus === 'approved' ? 'leave_approved' : 'leave_pending';
            }
        }
        $statuses = array_values(array_unique($statuses));
        $primary = collect(config('module4_calendar.priority'))->first(fn (string $status): bool => in_array($status, $statuses, true))
            ?? ($statuses[0] ?? 'unrecorded');
        $workedMinutes = $rejected ? null : $this->workedMinutes($row, $details);
        $reportRequired = $details['shift']['report_required'] ?? $details['report_required'] ?? null;

        return [
            'date' => $row->date->toDateString(), 'status' => $primary,
            'additional_statuses' => array_values(array_diff($statuses, [$primary])),
            'late_minutes' => $rejected ? 0 : $row->late_minutes,
            'ot_hours' => $rejected ? 0.0 : (float) $row->ot_hours, 'pending_ot_hours' => (float) $row->pending_ot_hours,
            'time_in' => $row->time_in, 'time_out' => $row->time_out, 'remarks' => $row->remarks,
            'shift' => isset($details['shift']) ? Arr::only($details['shift'], ['starts_at', 'ends_at', 'break_minutes', 'overnight', 'report_required']) : null, 'worked_minutes' => $workedMinutes,
            'worked_hours' => $workedMinutes === null ? null : round($workedMinutes / 60, 2),
            'leave' => isset($details['leave']) ? Arr::only($details['leave'], ['type', 'approval_status', 'days']) : null,
            'holiday' => isset($details['holiday']) ? Arr::only($details['holiday'], ['name']) : null,
            'correction' => isset($details['correction']) ? Arr::only($details['correction'], ['status', 'reason', 'requested_at']) : null,
            'report' => $report ? [
                'status' => 'submitted', 'submitted_at' => $report['submitted_at'],
                'url' => route(config('module4.route_name_prefix').'calendar.employee.report', ['calendarEmployee' => $row->employee_id, 'report' => $report['id']], false),
            ] : ['status' => $reportRequired === true ? 'missing' : ($reportRequired === false ? 'not_required' : 'unavailable'), 'url' => null],
        ];
    }

    private function workedMinutes(object $row, array $details): ?int
    {
        if (isset($details['worked_minutes'])) {
            return max(0, (int) $details['worked_minutes']);
        }
        if (! $row->time_in || ! $row->time_out || ! isset($details['shift']['break_minutes'])) {
            return null;
        }
        $start = strtotime('2000-01-01 '.$row->time_in);
        $end = strtotime('2000-01-01 '.$row->time_out);
        if ($end < $start) {
            if (! ($details['shift']['overnight'] ?? false)) {
                return null;
            }
            $end += 86400;
        }

        return max(0, (int) (($end - $start) / 60) - max(0, (int) ($details['shift']['break_minutes'] ?? 0)));
    }
}
