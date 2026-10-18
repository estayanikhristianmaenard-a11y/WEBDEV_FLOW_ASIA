<?php

namespace FlowAsia\Module4\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CompanyEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && Gate::forUser($this->user())->allows('m4.manage-events');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('title'))) {
            $this->merge(['title' => trim($this->input('title'))]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(array_keys(config('module4_calendar.event_types')))],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'department_id' => ['prohibited'],
            'employee_id' => ['prohibited'],
            'created_by' => ['prohibited'],
        ];
    }
}
