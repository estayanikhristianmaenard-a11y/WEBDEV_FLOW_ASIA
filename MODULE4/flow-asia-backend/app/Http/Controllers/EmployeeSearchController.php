<?php

namespace FlowAsia\Module4\Http\Controllers;

use FlowAsia\Module4\Contracts\CalendarEmployeeDirectory;
use FlowAsia\Module4\Http\Requests\EmployeeSearchRequest;
use FlowAsia\Module4\Services\CalendarEmployee;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class EmployeeSearchController extends Controller
{
    public function __invoke(EmployeeSearchRequest $request, CalendarEmployeeDirectory $directory): JsonResponse
    {
        $results = $directory->search($request->user(), $request->validated('q'))
            ->filter(fn (CalendarEmployee $employee): bool => Gate::forUser($request->user())->allows('viewCalendar', $employee))
            ->take(config('module4_calendar.search.limit'))
            ->map(fn (CalendarEmployee $employee): array => [
                'id' => $employee->id,
                'employee_no' => $employee->employee_no,
                'name' => $employee->first_name.' '.$employee->last_name,
                'position' => $employee->position,
                'department' => $employee->department?->name,
            ])->values();

        return response()->json(['data' => $results])->header('Cache-Control', 'private, no-store');
    }
}
