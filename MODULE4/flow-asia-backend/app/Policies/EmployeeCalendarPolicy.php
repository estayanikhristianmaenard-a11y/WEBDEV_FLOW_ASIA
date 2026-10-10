<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeeCalendarPolicy
{
    public function viewCalendar(User $user, Employee $employee): bool
    {
        if (in_array($user->role, config('calendar.hr_roles'), true)) {
            return true;
        }

        if ($user->employee_id === null || ! in_array($user->role, ['employee', 'supervisor'], true)) {
            return false;
        }

        $ownCalendar = (int) $user->employee_id === (int) $employee->id;

        return $ownCalendar || ($user->role === 'supervisor'
            && $employee->supervisor_id !== null
            && (int) $employee->supervisor_id === (int) $user->employee_id);
    }

    public function updateAttendance(User $user, Employee $employee): bool
    {
        return false;
    }

    public function updateLeave(User $user, Employee $employee): bool
    {
        return false;
    }

    public function updateOvertime(User $user, Employee $employee): bool
    {
        return false;
    }
}
