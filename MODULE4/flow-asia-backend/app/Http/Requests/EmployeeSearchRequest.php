<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->user()?->role;

        return $role === 'supervisor' || in_array($role, config('calendar.hr_roles'), true);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('q'))) {
            $this->merge(['q' => preg_replace('/\s+/u', ' ', trim($this->input('q')))]);
        }
    }

    public function rules(): array
    {
        return ['q' => ['required', 'string', 'min:'.config('calendar.search.min_length'), 'max:'.config('calendar.search.max_length')]];
    }
}
