<?php

namespace FlowAsia\Module4\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use FlowAsia\Module4\Http\Requests\EmployeeCalendarRequest;
use FlowAsia\Module4\Services\CalendarEmployee;
use FlowAsia\Module4\Services\CalendarPdfData;

class CalendarPdfController extends Controller
{
    public function __invoke(EmployeeCalendarRequest $request, CalendarEmployee $employee, CalendarPdfData $data)
    {
        // The route authorizes viewCalendar before reading or rendering.
        $month = $request->validated('month');
        $response = Pdf::loadView('m4::pdf.employee-calendar', $data->build($employee, $request->user(), $month))
            ->setPaper('a4', 'landscape')->setOption('isRemoteEnabled', false)->setOption('isPhpEnabled', false)
            ->download('calendar-'.$employee->id.'-'.$month.'.pdf');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
