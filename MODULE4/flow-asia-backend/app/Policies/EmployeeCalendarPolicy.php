<?php

namespace FlowAsia\Module4\Policies;

use FlowAsia\Module4\Contracts\CalendarIdentitySource;
use FlowAsia\Module4\Models\Employee;
use FlowAsia\Module4\Services\CalendarEmployee;
use Illuminate\Contracts\Auth\Authenticatable;

class EmployeeCalendarPolicy
{
    public function viewCalendar(Authenticatable $user, Employee|CalendarEmployee $employee): bool
    {
        $identity = app(CalendarIdentitySource::class)->forUser($user);
        if (in_array($identity['role'], config('module4_calendar.hr_roles'), true)) {
            return true;
        }

        if ($identity['employee_id'] === null || ! in_array($identity['role'], ['employee', 'supervisor'], true)) {
            return false;
        }

        $ownCalendar = (int) $identity['employee_id'] === (int) $employee->id;

        return $ownCalendar || ($identity['role'] === 'supervisor'
            && $employee->supervisor_id !== null
            && (int) $employee->supervisor_id === (int) $identity['employee_id']);
    }

    public function updateAttendance(Authenticatable $user, Employee|CalendarEmployee $employee): bool
    {
        return false;
    }

    public function updateLeave(Authenticatable $user, Employee|CalendarEmployee $employee): bool
    {
        return false;
    }

    public function updateOvertime(Authenticatable $user, Employee|CalendarEmployee $employee): bool
    {
        return false;
    }
}
