<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ProfileReportReason;
use App\Http\Controllers\Api\V1\ProfileReportController;
use App\Support\DoctorAccount\PlainText;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * POST /doctors|facilities|pharmacies/{slug}/profile-reports: a reason code
 * and an optional note (≤ 500). `website` is a honeypot, answered like a
 * success with nothing stored, as on the correction form.
 */
class StoreProfileReportRequest extends FormRequest
{
    public const HONEYPOT = 'website';

    public const NOTE_MAX_LENGTH = 500;

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
            'reason' => ['required', 'string', Rule::enum(ProfileReportReason::class)],
            'note' => ['nullable', 'string', 'max:'.self::NOTE_MAX_LENGTH],
            self::HONEYPOT => ['nullable'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->isBot()) {
            throw new HttpResponseException(ProfileReportController::received());
        }

        parent::failedValidation($validator);
    }

    public function isBot(): bool
    {
        $trap = $this->input(self::HONEYPOT);

        return $trap !== null && $trap !== '' && $trap !== false;
    }

    public function reason(): ProfileReportReason
    {
        return ProfileReportReason::from($this->string('reason')->toString());
    }

    public function note(): ?string
    {
        return PlainText::paragraphs($this->string('note')->toString());
    }
}
