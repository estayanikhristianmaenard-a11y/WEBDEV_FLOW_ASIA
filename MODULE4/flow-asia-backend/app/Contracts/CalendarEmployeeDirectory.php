<?php

namespace FlowAsia\Module4\Contracts;

use FlowAsia\Module4\Services\CalendarEmployee;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

interface CalendarEmployeeDirectory
{
    /** Canonical M2 employee ID. Return safe data, never salary/credentials/photos. */
    public function find(int $employeeId): ?CalendarEmployee;

    /**
     * Scope self/direct reports/HR using the authenticated server identity.
     *
     * @return Collection<int, CalendarEmployee>
     */
    public function visibleTo(Authenticatable $user, ?int $departmentId = null): Collection;

    /**
     * Same visibility, name/employee number/department search, deterministic ordering.
     *
     * @return Collection<int, CalendarEmployee>
     */
    public function search(Authenticatable $user, string $term): Collection;

    public function departmentExists(int $departmentId): bool;
}
