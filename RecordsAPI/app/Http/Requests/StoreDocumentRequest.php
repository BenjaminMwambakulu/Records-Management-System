<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
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
            'category_id' => ['nullable', 'integer', 'exists:document_categories,id'],
            'status' => ['sometimes', 'string', 'in:active,archived'],
            'is_public' => ['sometimes', 'boolean'],
            'file' => ['required', 'file', 'max:10240'],
        ];
    }
}
