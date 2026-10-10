<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeCalendarRequest;
use App\Models\Employee;
use App\Services\CalendarPdfData;
use Barryvdh\DomPDF\Facade\Pdf;

class CalendarPdfController extends Controller
{
    public function __invoke(EmployeeCalendarRequest $request, Employee $employee, CalendarPdfData $data)
    {
        // The route authorizes viewCalendar before reading or rendering.
        $month = $request->validated('month');
        $response = Pdf::loadView('pdf.employee-calendar', $data->build($employee, $request->user(), $month))
            ->setPaper('a4', 'landscape')->setOption('isRemoteEnabled', false)->setOption('isPhpEnabled', false)
            ->download('calendar-'.$employee->id.'-'.$month.'.pdf');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
