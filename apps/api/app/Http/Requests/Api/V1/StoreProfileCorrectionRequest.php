<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ProfileCorrectionField;
use App\Enums\ProfileCorrectionType;
use App\Http\Controllers\Api\V1\ProfileCorrectionController;
use App\Models\ProfileCorrection;
use App\Support\DoctorAccount\PlainText;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * POST /doctors/{slug}/corrections and /facilities/{slug}/corrections.
 *
 * - correction: which part of the profile (`field`, from the subject's own
 *   list), what is wrong (`message`), and an optional e-mail address for a
 *   reply (`contact`).
 * - objection (doctors only): the listed doctor says who they are and why
 *   (`message`), with a required e-mail or phone (`contact`) so staff can
 *   verify them; a field, if sent, is ignored.
 *
 * `website` is a honeypot: the web form hides it from people, so a filled
 * one is a bot. It is answered like a success and nothing is stored, also
 * when the rest of the request would not validate, so the answer teaches a
 * bot nothing.
 */
class StoreProfileCorrectionRequest extends FormRequest
{
    public const HONEYPOT = 'website';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $objection = $this->correctionType() === ProfileCorrectionType::Objection;
        $fields = $this->routeIs('*.facility')
            ? ProfileCorrectionField::forFacility()
            : ProfileCorrectionField::forDoctor();

        return [
            'type' => ['sometimes', 'string', Rule::in($this->allowedTypes())],
            'field' => $objection
                ? ['nullable']
                : ['required', 'string', Rule::in(array_map(fn (ProfileCorrectionField $field): string => $field->value, $fields))],
            'message' => ['required', 'string', 'min:10', 'max:'.ProfileCorrection::MESSAGE_MAX_LENGTH],
            'contact' => $objection
                ? ['required', 'string', 'min:5', 'max:'.ProfileCorrection::CONTACT_MAX_LENGTH]
                : ['nullable', 'string', 'email:rfc', 'max:'.ProfileCorrection::CONTACT_MAX_LENGTH],
            self::HONEYPOT => ['nullable'],
        ];
    }

    /**
     * @return list<string>
     */
    private function allowedTypes(): array
    {
        // A facility cannot object on a doctor's behalf: objections are the
        // listed person's own right (memo §2.1).
        return $this->routeIs('*.facility')
            ? [ProfileCorrectionType::Correction->value]
            : ProfileCorrectionType::values();
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->isBot()) {
            throw new HttpResponseException(ProfileCorrectionController::received($this->correctionType()));
        }

        parent::failedValidation($validator);
    }

    public function correctionType(): ProfileCorrectionType
    {
        return ProfileCorrectionType::tryFrom($this->string('type')->toString())
            ?? ProfileCorrectionType::Correction;
    }

    public function isBot(): bool
    {
        $trap = $this->input(self::HONEYPOT);

        return $trap !== null && $trap !== '' && $trap !== false;
    }

    public function correctionField(): ?ProfileCorrectionField
    {
        return $this->correctionType() === ProfileCorrectionType::Objection
            ? null
            : ProfileCorrectionField::from($this->string('field')->toString());
    }

    public function correctionMessage(): string
    {
        return (string) PlainText::paragraphs($this->string('message')->toString());
    }

    public function correctionContact(): ?string
    {
        return PlainText::line($this->string('contact')->toString());
    }
}
