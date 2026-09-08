<?php

namespace App\Http\Requests;

use App\Enums\AcademicTrack;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateMemberRequest extends FormRequest
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
        $member = $this->route('member');

        return [
            'logto_id' => ['sometimes', 'nullable', 'string', 'unique:users,logto_id,'.$member],
            'student_id' => ['sometimes', 'string', 'max:255', 'unique:users,student_id,'.$member],
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,'.$member],
            'academic_track' => ['sometimes', 'nullable', new Enum(AcademicTrack::class)],
            'enrolled_year' => ['sometimes', 'nullable', 'integer', 'min:1950', 'max:2200'],
            'study_year' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4'],
            'skills' => ['sometimes', 'nullable', 'array'],
            'skills.*' => ['string', 'max:255'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'max:255', 'distinct'],
        ];
    }
}
