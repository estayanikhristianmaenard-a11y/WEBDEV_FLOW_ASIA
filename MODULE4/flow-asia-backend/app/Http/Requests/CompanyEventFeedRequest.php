<?php

namespace FlowAsia\Module4\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanyEventFeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        // FullCalendar's ISO datetime bounds are accepted, with an exclusive end.
        return [
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
        ];
    }
}
