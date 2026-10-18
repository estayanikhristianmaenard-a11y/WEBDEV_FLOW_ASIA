<?php

namespace FlowAsia\Module4\Http\Controllers;

use FlowAsia\Module4\Contracts\CalendarContextSource;
use FlowAsia\Module4\Services\CalendarEmployee;
use Illuminate\Http\Response;

class CalendarReportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(CalendarEmployee $employee, int $report, CalendarContextSource $context): Response
    {
        $record = $context->reportForEmployee($employee->id, $report);
        abort_unless($record, 404);

        return response()->view('m4::calendar.report', ['employee' => $employee, 'report' => $record])->header('Cache-Control', 'private, no-store');
    }
}
