<?php

namespace App\Http\Requests;

use App\Enums\AssetStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAssetRequest extends FormRequest
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
        $assetId = $this->route('asset');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'serial_number' => ['sometimes', 'nullable', 'string', 'max:255', "unique:assets,serial_number,{$assetId}"],
            'category' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'enum:'.AssetStatus::class],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
