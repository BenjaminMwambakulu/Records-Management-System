<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $event = $this->route('event');

        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:events,slug,'.$event],
            'description' => ['sometimes', 'nullable', 'string'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'event_date' => ['sometimes', 'date', 'after:now'],
            'start_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'duration' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'entry_fee' => ['sometimes', 'numeric', 'min:0'],
            'budget' => ['sometimes', 'numeric', 'min:0'],
            'qr_code_hash' => ['sometimes', 'nullable', 'string', 'max:255', 'unique:events,qr_code_hash,'.$event],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
