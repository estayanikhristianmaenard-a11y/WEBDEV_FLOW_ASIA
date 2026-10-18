<?php

namespace FlowAsia\Module4\Http\Requests;

use FlowAsia\Module4\Contracts\CalendarIdentitySource;
use Illuminate\Foundation\Http\FormRequest;

class EmployeeSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->user() ? app(CalendarIdentitySource::class)->forUser($this->user())['role'] : null;

        return $role === 'supervisor' || in_array($role, config('module4_calendar.hr_roles'), true);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('q'))) {
            $this->merge(['q' => preg_replace('/\s+/u', ' ', trim($this->input('q')))]);
        }
    }

    public function rules(): array
    {
        return ['q' => ['required', 'string', 'min:'.config('module4_calendar.search.min_length'), 'max:'.config('module4_calendar.search.max_length')]];
    }
}
