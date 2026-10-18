<?php

namespace FlowAsia\Module4\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface CalendarContextSource
{
    /**
     * Inclusive bounds; keyed by canonical integer employee ID.
     * Values contain profile: {leave_balances: [{type, remaining_days}],
     * pay_periods: [{start, end, payday}]} and reports: {YYYY-MM-DD: {id, submitted_at}}.
     * Dates use YYYY-MM-DD; submitted_at uses an ISO timestamp. Report IDs are integers.
     * Unknown context is omitted, not calculated from sample or payroll data.
     * Balances and paydays come from their owning modules; batch all employee reads.
     */
    public function forEmployees(array $employeeIds, CarbonInterface $from, CarbonInterface $to): Collection;

    /** Return {title, body, date} only for this employee/report pair; otherwise null. */
    public function reportForEmployee(int $employeeId, int $reportId): ?array;
}
