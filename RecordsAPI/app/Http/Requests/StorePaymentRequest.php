<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
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
            'payable_type' => ['required', 'string', 'in:App\Models\Event'],
            'payable_id' => ['required', 'integer', 'exists:events,id'],
            'amount' => ['required', 'numeric', 'min:1'],
        ];
    }
}
