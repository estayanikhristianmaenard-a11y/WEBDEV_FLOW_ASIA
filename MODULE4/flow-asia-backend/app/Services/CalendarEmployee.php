<?php

namespace FlowAsia\Module4\Services;

use FlowAsia\Module4\Models\Employee;

final readonly class CalendarEmployee
{
    public function __construct(
        public int $id,
        public string $employee_no,
        public string $first_name,
        public string $last_name,
        public ?string $position = null,
        public ?int $department_id = null,
        public ?object $department = null,
        public ?int $supervisor_id = null,
    ) {}

    public static function fromModel(Employee $employee): self
    {
        $employee->loadMissing('department:id,name');

        return new self(
            (int) $employee->id, $employee->employee_no, $employee->first_name, $employee->last_name,
            $employee->position, $employee->department_id,
            $employee->department ? (object) $employee->department->only(['id', 'name']) : null,
            $employee->supervisor_id,
        );
    }
}
