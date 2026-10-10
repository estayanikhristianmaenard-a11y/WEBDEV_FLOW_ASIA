<?php

namespace App\Http\Controllers;

use App\Contracts\CalendarDataSource;
use App\Http\Requests\EmployeeCalendarRequest;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class EmployeeCalendarController extends Controller
{
    public function __invoke(EmployeeCalendarRequest $request, Employee $employee, CalendarDataSource $source): JsonResponse
    {
        // The route's viewCalendar policy runs before this controller reads attendance.
        $month = $request->validated('month');
        $from = CarbonImmutable::createFromFormat('!Y-m', $month, config('app.timezone'))->startOfMonth();
        $employee->load('department:id,name');
        $days = $source->forEmployee($employee->id, $from, $from->endOfMonth())->map(fn ($row) => [
            'date' => $row->date->toDateString(), 'status' => $row->status,
            'additional_statuses' => $row->additional_statuses ?? [],
            'late_minutes' => $row->late_minutes, 'ot_hours' => (float) $row->ot_hours,
            'pending_ot_hours' => (float) $row->pending_ot_hours,
        ])->values();

        return response()->json(['month' => $month, 'read_only' => true, 'data' => [
            'id' => $employee->id, 'employee_no' => $employee->employee_no,
            'name' => trim($employee->first_name.' '.$employee->last_name), 'position' => $employee->position,
            'department' => $employee->department ? ['id' => $employee->department->id, 'name' => $employee->department->name] : null,
            'days' => $days,
        ]]);
    }
}
