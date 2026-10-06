<?php

namespace App\Http\Requests\Api\V1;

use App\Support\DoctorAccount\DoctorProfileFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /me/doctor/change-requests: the sensitive fields a linked doctor asks
 * staff to change (DoctorProfileFields::SENSITIVE_*), plus an optional note.
 */
class StoreDoctorChangeRequestRequest extends FormRequest
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
        return DoctorProfileFields::sensitiveRules();
    }
}
