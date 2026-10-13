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

        $term = $request->validated('q');
        $patternFor = fn (string $value) => '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value).'%';
        $query->where(function (Builder $match) use ($term, $patternFor) {
            $match->where(function (Builder $names) use ($term, $patternFor) {
                foreach (explode(' ', $term) as $word) {
                    $pattern = $patternFor($word);
                    $names->where(fn (Builder $name) => $name
                        ->whereRaw("LOWER(first_name) LIKE LOWER(?) ESCAPE '!'", [$pattern])
                        ->orWhereRaw("LOWER(last_name) LIKE LOWER(?) ESCAPE '!'", [$pattern]));
                }
            })->orWhereRaw("LOWER(employee_no) LIKE LOWER(?) ESCAPE '!'", [$patternFor($term)])
                ->orWhereHas('department', fn (Builder $department) => $department
                    ->whereRaw("LOWER(name) LIKE LOWER(?) ESCAPE '!'", [$patternFor($term)]));
        });

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
