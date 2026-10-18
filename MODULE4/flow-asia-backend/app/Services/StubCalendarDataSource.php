<?php

namespace FlowAsia\Module4\Services;

use Carbon\CarbonInterface;
use FlowAsia\Module4\Contracts\CalendarDataSource;
use FlowAsia\Module4\Models\AttendanceDaily;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class StubCalendarDataSource implements CalendarDataSource
{
    public function forEmployee(int $employeeId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->forEmployees([$employeeId], $from, $to)->get($employeeId, collect());
    }

    public function forEmployees(array $employeeIds, CarbonInterface $from, CarbonInterface $to): Collection
    {
        if ($from->toDateString() > $to->toDateString()) {
            throw new InvalidArgumentException('The end date must be on or after the start date.');
        }

        $employeeIds = array_values(array_unique($employeeIds));
        $grouped = collect($employeeIds)->mapWithKeys(fn ($id) => [$id => collect()]);

        if ($employeeIds === []) {
            return $grouped;
        }

        $rows = AttendanceDaily::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get()
            ->groupBy('employee_id');

        return $grouped->replace($rows);
    }
}
