<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Support\SearchQuery;

trait NormalizesSearchQuery
{
    protected function prepareForValidation(): void
    {
        if ($this->has('q')) {
            $raw = $this->input('q');

            $this->merge([
                'q' => SearchQuery::normalize(is_string($raw) ? $raw : null),
            ]);
        }
    }
}
