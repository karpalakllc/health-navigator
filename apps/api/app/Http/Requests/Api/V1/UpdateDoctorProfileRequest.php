<?php

namespace App\Http\Requests\Api\V1;

use App\Support\DoctorAccount\DoctorProfileFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH /me/doctor: the practice details a linked doctor changes at once
 * (DoctorProfileFields::IMMEDIATE). Any other key — the slug, publication,
 * featured/sponsored, a sensitive field — is not in the rules, so it never
 * reaches validated() and is ignored.
 */
class UpdateDoctorProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return DoctorProfileFields::immediateRules();
    }
}
