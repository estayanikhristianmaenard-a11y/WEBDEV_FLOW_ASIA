<?php

namespace App\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface CalendarDataSource
{
    /** Return attendance rows, including all statuses for priority handling later. */
    public function forEmployee(int $employeeId, CarbonInterface $from, CarbonInterface $to): Collection;

    /** Return rows grouped by employee ID, using one attendance query. */
    public function forEmployees(array $employeeIds, CarbonInterface $from, CarbonInterface $to): Collection;
}
