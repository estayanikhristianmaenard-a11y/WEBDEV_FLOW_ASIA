<?php

namespace FlowAsia\Module4\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['month' => ['required', 'date_format:Y-m']];
    }
}
