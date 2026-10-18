<?php

namespace FlowAsia\Module4\Http\Requests;

use Closure;
use FlowAsia\Module4\Contracts\CalendarEmployeeDirectory;
use FlowAsia\Module4\Contracts\CalendarIdentitySource;
use Illuminate\Foundation\Http\FormRequest;

class TeamCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->user() ? app(CalendarIdentitySource::class)->forUser($this->user())['role'] : null;

        return $role === 'supervisor' || in_array($role, config('module4_calendar.hr_roles'), true);
    }

    public function rules(): array
    {
        return [
            'month' => ['required', 'date_format:Y-m'],
            'department_id' => ['bail', 'nullable', 'integer', function (string $attribute, mixed $value, Closure $fail): void {
                if (! app(CalendarEmployeeDirectory::class)->departmentExists((int) $value)) {
                    $fail('The selected department is invalid.');
                }
            }],
        ];
    }
}
