<?php

namespace App\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface CalendarDataSource
{
    /** Return source rows including simultaneous statuses and optional calendar_details metadata. */
    public function forEmployee(int $employeeId, CarbonInterface $from, CarbonInterface $to): Collection;

    /** Return rows grouped by employee ID, using one attendance query. */
    public function forEmployees(array $employeeIds, CarbonInterface $from, CarbonInterface $to): Collection;
}
