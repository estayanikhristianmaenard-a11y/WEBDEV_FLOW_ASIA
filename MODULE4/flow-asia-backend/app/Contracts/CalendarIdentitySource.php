<?php

namespace FlowAsia\Module4\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface CalendarIdentitySource
{
    /** @return array{id: int|string, name: string, role: string, employee_id: ?int} */
    public function forUser(Authenticatable $user): array;
}
