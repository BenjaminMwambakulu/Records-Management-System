<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMobileMoneyRequest extends FormRequest
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
            'phone_number' => ['required', 'string', 'min:9', 'max:15'],
            'operator_ref_id' => ['required', 'string', 'in:27494cb5-ba9e-437f-a114-4e7a7686bcca,20be6c20-adeb-4b5b-a7ba-0769820df4fb,550c35a2-86aa-4590-9931-8f23e664cee9'],
        ];
    }
}
