<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFinancialCategoryRequest extends FormRequest
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
        $category = $this->route('financial_category');

        return [
            'name' => ['sometimes', 'string', 'max:255', 'unique:financial_categories,name,'.$category],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
