<?php

namespace FlowAsia\Module4\Http\Controllers;

use FlowAsia\Module4\Http\Requests\EmployeeCalendarRequest;
use FlowAsia\Module4\Services\CalendarEmployee;
use FlowAsia\Module4\Services\CalendarMonthService;
use Illuminate\Http\JsonResponse;

class EmployeeCalendarController extends Controller
{
    public function __invoke(EmployeeCalendarRequest $request, CalendarEmployee $employee, CalendarMonthService $calendar): JsonResponse
    {
        // The route's viewCalendar policy runs before this controller reads attendance.
        $month = $request->validated('month');

        return response()->json(['month' => $month, 'read_only' => true, 'data' => $calendar->build($employee, $month)])
            ->header('Cache-Control', 'private, no-store');
    }
}
