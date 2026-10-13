<?php

namespace App\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface CalendarContextSource
{
    /** Return safe leave/pay-period profiles and reports keyed by employee ID. */
    public function forEmployees(array $employeeIds, CarbonInterface $from, CarbonInterface $to): Collection;

    /** Return a report only when it belongs to the selected employee. */
    public function reportForEmployee(int $employeeId, int $reportId): ?array;
}
