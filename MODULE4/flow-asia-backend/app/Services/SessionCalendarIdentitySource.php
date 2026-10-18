<?php

namespace FlowAsia\Module4\Services;

use FlowAsia\Module4\Contracts\CalendarIdentitySource;
use Illuminate\Contracts\Auth\Authenticatable;

class SessionCalendarIdentitySource implements CalendarIdentitySource
{
    public function forUser(Authenticatable $user): array
    {
        $fields = config('module4.identity.fields');
        $role = data_get($user, $fields['role']);
        $role = is_string($role) ? (config('module4.identity.roles')[$role] ?? $role) : '';
        $employeeId = data_get($user, $fields['employee_id']);

        return [
            'id' => $user->getAuthIdentifier(),
            'name' => (string) data_get($user, $fields['name'], ''),
            'role' => $role,
            'employee_id' => filter_var($employeeId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false ? (int) $employeeId : null,
        ];
    }
}
