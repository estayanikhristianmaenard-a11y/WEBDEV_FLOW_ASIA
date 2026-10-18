<?php

namespace FlowAsia\Module4\Services;

use Carbon\CarbonInterface;
use FlowAsia\Module4\Contracts\CalendarContextSource;
use FlowAsia\Module4\Models\CalendarReport;
use FlowAsia\Module4\Models\Employee;
use Illuminate\Support\Collection;

class StubCalendarContextSource implements CalendarContextSource
{
    public function forEmployees(array $employeeIds, CarbonInterface $from, CarbonInterface $to): Collection
    {
        if ($employeeIds === []) {
            return collect();
        }
        $reports = CalendarReport::query()->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')->get()->groupBy('employee_id');

        return Employee::query()->whereIn('id', $employeeIds)->get(['id', 'calendar_profile'])
            ->mapWithKeys(fn (Employee $employee): array => [$employee->id => [
                'profile' => $employee->calendar_profile ?? [],
                'reports' => $reports->get($employee->id, collect())->mapWithKeys(fn (CalendarReport $report): array => [
                    $report->date->toDateString() => ['id' => $report->id, 'submitted_at' => $report->submitted_at->toIso8601String()],
                ])->all(),
            ]]);
    }

    public function reportForEmployee(int $employeeId, int $reportId): ?array
    {
        $report = CalendarReport::query()->where('employee_id', $employeeId)->find($reportId);

        return $report ? $report->only(['title', 'body']) + ['date' => $report->date->toDateString()] : null;
    }
}
