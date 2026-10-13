<?php

namespace App\Http\Controllers;

use App\Contracts\CalendarContextSource;
use App\Models\Employee;
use Illuminate\Http\Response;

class CalendarReportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Employee $employee, int $report, CalendarContextSource $context): Response
    {
        $record = $context->reportForEmployee($employee->id, $report);
        abort_unless($record, 404);

        return response()->view('calendar.report', ['employee' => $employee, 'report' => $record])->header('Cache-Control', 'private, no-store');
    }
}
