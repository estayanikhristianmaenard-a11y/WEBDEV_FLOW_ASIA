<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TeamCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'supervisor'
            || in_array($this->user()?->role, config('calendar.hr_roles'), true);
    }

    public function rules(): array
    {
        return [
            'month' => ['required', 'date_format:Y-m'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ];
    }
}
