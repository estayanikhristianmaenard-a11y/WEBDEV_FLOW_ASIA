<?php

namespace FlowAsia\Module4\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface CalendarDataSource
{
    /**
     * Inclusive dates. Return one normalized object per recorded employee/date.
     * Each object exposes employee_id (int), date (CarbonInterface), status (string),
     * additional_statuses (array), time_in/time_out/remarks (nullable strings),
     * late_minutes (int), ot_hours/pending_ot_hours (numeric hours), and
     * calendar_details (array). Missing days stay absent; do not fabricate attendance.
     * Shared source failures must throw, rather than returning a successful empty month.
     */
    public function forEmployee(int $employeeId, CarbonInterface $from, CarbonInterface $to): Collection;

    /**
     * Same row contract, grouped by integer employee ID, using batched source reads.
     * Include an empty collection for requested employees with no recorded days.
     * The inclusive range can cover the selected month, today and a payroll cutoff.
     */
    public function forEmployees(array $employeeIds, CarbonInterface $from, CarbonInterface $to): Collection;
}
