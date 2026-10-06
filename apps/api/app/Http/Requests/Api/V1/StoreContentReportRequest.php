<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ReportReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContentReportRequest extends FormRequest
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
            'reason' => ['required', 'string', Rule::enum(ReportReason::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function reason(): ReportReason
    {
        return ReportReason::from($this->string('reason')->toString());
    }

    /**
     * Plain text for the moderator; markup is stripped rather than escaped
     * because the note is never rendered as HTML anywhere.
     */
    public function note(): ?string
    {
        $note = trim(strip_tags($this->string('note')->toString()));

        return $note === '' ? null : $note;
    }
}
