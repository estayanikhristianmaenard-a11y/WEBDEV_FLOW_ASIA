<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeCalendarRequest;
use App\Models\Employee;
use App\Services\CalendarMonthService;
use Illuminate\Http\JsonResponse;

class EmployeeCalendarController extends Controller
{
    public function __invoke(EmployeeCalendarRequest $request, Employee $employee, CalendarMonthService $calendar): JsonResponse
    {
        // The route's viewCalendar policy runs before this controller reads attendance.
        $month = $request->validated('month');

        return response()->json(['month' => $month, 'read_only' => true, 'data' => $calendar->build($employee, $month)])
            ->header('Cache-Control', 'private, no-store');
    }
}
