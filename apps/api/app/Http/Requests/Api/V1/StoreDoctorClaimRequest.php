<?php

namespace App\Http\Requests\Api\V1;

use App\Models\DoctorClaimRequest;
use App\Support\DoctorAccount\PlainText;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /doctors/{slug}/claim-requests („Ова е мој профил“): a short message
 * and a way for staff to reach the person to verify them. No documents.
 */
class StoreDoctorClaimRequest extends FormRequest
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
        return [
            'message' => ['required', 'string', 'min:10', 'max:'.DoctorClaimRequest::MESSAGE_MAX_LENGTH],
            'contact' => ['required', 'string', 'min:5', 'max:'.DoctorClaimRequest::CONTACT_MAX_LENGTH],
        ];
    }

    public function claimMessage(): string
    {
        return (string) PlainText::paragraphs($this->string('message')->toString());
    }

    public function claimContact(): string
    {
        return (string) PlainText::line($this->string('contact')->toString());
    }
}
