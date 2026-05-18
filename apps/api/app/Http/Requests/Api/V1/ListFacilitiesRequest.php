<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\FacilityType;
use App\Http\Requests\Api\V1\Concerns\NormalizesSearchQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListFacilitiesRequest extends FormRequest
{
    use NormalizesSearchQuery;

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
            'type' => ['nullable', 'string', Rule::in(FacilityType::clinicalValues())],
            'city' => ['nullable', 'string', 'max:255'],
            'q' => ['nullable', 'string', 'max:255'],
            'has_emergency' => ['nullable', 'boolean'],
            'department' => ['nullable', 'string', 'max:255'],
            'featured' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
