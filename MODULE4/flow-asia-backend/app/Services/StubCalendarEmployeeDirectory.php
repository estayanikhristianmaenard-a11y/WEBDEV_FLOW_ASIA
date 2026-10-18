<?php

namespace FlowAsia\Module4\Services;

use FlowAsia\Module4\Contracts\CalendarEmployeeDirectory;
use FlowAsia\Module4\Models\Department;
use FlowAsia\Module4\Models\Employee;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StubCalendarEmployeeDirectory implements CalendarEmployeeDirectory
{
    public function find(int $employeeId): ?CalendarEmployee
    {
        $employee = Employee::query()->with('department:id,name')->find($employeeId);

        return $employee ? CalendarEmployee::fromModel($employee) : null;
    }

    public function visibleTo(Authenticatable $user, ?int $departmentId = null): Collection
    {
        return Employee::query()->visibleTo($user)->with('department:id,name')
            ->when($departmentId !== null, fn (Builder $query) => $query->where('department_id', $departmentId))
            ->orderBy('last_name')->orderBy('first_name')->orderBy('id')->get()
            ->map(fn (Employee $employee): CalendarEmployee => CalendarEmployee::fromModel($employee));
    }

    public function search(Authenticatable $user, string $term): Collection
    {
        $query = Employee::query()->visibleTo($user)
            ->select(['id', 'employee_no', 'first_name', 'last_name', 'position', 'department_id', 'supervisor_id'])
            ->with('department:id,name');
        $patternFor = fn (string $value): string => '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value).'%';
        $query->where(function (Builder $match) use ($term, $patternFor): void {
            $match->where(function (Builder $names) use ($term, $patternFor): void {
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

        return $query->orderBy('last_name')->orderBy('first_name')->orderBy('id')
            ->limit(config('module4_calendar.search.limit'))->get()
            ->map(fn (Employee $employee): CalendarEmployee => CalendarEmployee::fromModel($employee));
    }

    public function departmentExists(int $departmentId): bool
    {
        return Department::query()->whereKey($departmentId)->exists();
    }
}
