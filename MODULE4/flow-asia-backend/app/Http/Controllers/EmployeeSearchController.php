<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeSearchRequest;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class EmployeeSearchController extends Controller
{
    public function __invoke(EmployeeSearchRequest $request): JsonResponse
    {
        $query = Employee::query()->visibleTo($request->user())
            ->select(['id', 'employee_no', 'first_name', 'last_name', 'position', 'department_id'])
            ->with('department:id,name');

        // Every word must match; grouping keeps OR clauses inside the role scope.
        foreach (explode(' ', $request->validated('q')) as $word) {
            // Treat SQL wildcard characters as literal user input on MySQL and SQLite.
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $word).'%';
            $query->where(function (Builder $match) use ($pattern) {
                $match->whereRaw("LOWER(first_name) LIKE LOWER(?) ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(last_name) LIKE LOWER(?) ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(employee_no) LIKE LOWER(?) ESCAPE '!'", [$pattern])
                    ->orWhereHas('department', fn (Builder $department) =>
                        $department->whereRaw("LOWER(name) LIKE LOWER(?) ESCAPE '!'", [$pattern])
                    );
            });
        }

        $results = $query->orderBy('last_name')->orderBy('first_name')->orderBy('id')
            ->limit(config('calendar.search.limit'))->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'employee_no' => $employee->employee_no,
                'name' => $employee->first_name.' '.$employee->last_name,
                'position' => $employee->position,
                'department' => $employee->department->name,
            ]);

        return response()->json(['data' => $results]);
    }
}
