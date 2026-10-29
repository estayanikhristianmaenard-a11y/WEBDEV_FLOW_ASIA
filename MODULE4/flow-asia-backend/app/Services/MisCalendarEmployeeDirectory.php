<?php

namespace FlowAsia\Module4\Services;

use FlowAsia\Module4\Contracts\CalendarEmployeeDirectory;
use FlowAsia\Module4\Contracts\CalendarIdentitySource;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MisCalendarEmployeeDirectory implements CalendarEmployeeDirectory
{
    public function find(int $employeeId): ?CalendarEmployee
    {
        $employee = $this->query()->where('e.employee_id', $employeeId)->first();

        return $employee ? $this->employee($employee) : null;
    }

    public function visibleTo(Authenticatable $user, ?int $departmentId = null): Collection
    {
        return $this->scoped($user)
            ->when($departmentId !== null, fn (Builder $query) => $query->where('e.department_id', $departmentId))
            ->orderBy('e.last_name')->orderBy('e.first_name')->orderBy('e.employee_id')->get()
            ->map(fn (object $row): CalendarEmployee => $this->employee($row));
    }

    public function search(Authenticatable $user, string $term): Collection
    {
        $pattern = fn (string $value): string => '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value).'%';

        return $this->scoped($user)->where(function (Builder $match) use ($term, $pattern): void {
            $match->where(function (Builder $names) use ($term, $pattern): void {
                foreach (preg_split('/\s+/', trim($term)) as $word) {
                    $names->where(fn (Builder $name) => $name
                        ->whereRaw("LOWER(e.first_name) LIKE LOWER(?) ESCAPE '!'", [$pattern($word)])
                        ->orWhereRaw("LOWER(e.last_name) LIKE LOWER(?) ESCAPE '!'", [$pattern($word)]));
                }
            })->orWhereRaw("LOWER(e.employee_number) LIKE LOWER(?) ESCAPE '!'", [$pattern($term)])
                ->orWhereRaw("LOWER(d.department_name) LIKE LOWER(?) ESCAPE '!'", [$pattern($term)]);
        })->orderBy('e.last_name')->orderBy('e.first_name')->orderBy('e.employee_id')
            ->limit(config('module4_calendar.search.limit'))->get()
            ->map(fn (object $row): CalendarEmployee => $this->employee($row));
    }

    public function departmentExists(int $departmentId): bool
    {
        return DB::connection(config('module4.mis_connection'))->table('departments')
            ->where('department_id', $departmentId)->exists();
    }

    private function query(): Builder
    {
        return DB::connection(config('module4.mis_connection'))->table('employees as e')
            ->leftJoin('departments as d', 'd.department_id', '=', 'e.department_id')
            ->leftJoin('positions as p', 'p.position_id', '=', 'e.position_id')
            ->select(['e.employee_id', 'e.employee_number', 'e.first_name', 'e.last_name',
                'e.department_id', 'e.supervisor_id', 'd.department_name', 'p.position_name']);
    }

    private function scoped(Authenticatable $user): Builder
    {
        $query = $this->query();
        $identity = app(CalendarIdentitySource::class)->forUser($user);
        if (in_array($identity['role'], config('module4_calendar.hr_roles'), true)) {
            return $query;
        }
        if ($identity['employee_id'] === null || ! in_array($identity['role'], ['employee', 'supervisor'], true)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scope) use ($identity): void {
            $scope->where('e.employee_id', $identity['employee_id']);
            if ($identity['role'] === 'supervisor') {
                $scope->orWhere('e.supervisor_id', $identity['employee_id']);
            }
        });
    }

    private function employee(object $row): CalendarEmployee
    {
        return new CalendarEmployee(
            (int) $row->employee_id, $row->employee_number, $row->first_name, $row->last_name,
            $row->position_name, $row->department_id === null ? null : (int) $row->department_id,
            $row->department_name === null ? null : (object) ['id' => (int) $row->department_id, 'name' => $row->department_name],
            $row->supervisor_id === null ? null : (int) $row->supervisor_id,
        );
    }
}
