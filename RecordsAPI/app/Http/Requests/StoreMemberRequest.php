<?php

namespace App\Http\Requests;

use App\Enums\AcademicTrack;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreMemberRequest extends FormRequest
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
            'logto_id' => ['nullable', 'string', 'unique:users,logto_id'],
            'student_id' => ['required', 'string', 'max:255', 'unique:users,student_id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'academic_track' => ['nullable', new Enum(AcademicTrack::class)],
            'enrolled_year' => ['nullable', 'integer', 'min:1950', 'max:2200'],
            'study_year' => ['nullable', 'integer', 'min:1', 'max:4'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:255'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'max:255', 'distinct'],
        ];
    }
}
