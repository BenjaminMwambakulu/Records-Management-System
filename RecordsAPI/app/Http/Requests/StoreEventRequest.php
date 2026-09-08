<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
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
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:events,slug'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'event_date' => ['required', 'date', 'after:now'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'duration' => ['nullable', 'integer', 'min:1'],
            'entry_fee' => ['required', 'numeric', 'min:0'],
            'budget' => ['required', 'numeric', 'min:0'],
            'qr_code_hash' => ['nullable', 'string', 'max:255', 'unique:events,qr_code_hash'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
