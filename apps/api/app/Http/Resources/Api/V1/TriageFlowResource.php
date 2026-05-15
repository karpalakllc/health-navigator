<?php

namespace App\Http\Resources\Api\V1;

use App\Models\TriageFlow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TriageFlow */
class TriageFlowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'intro_body' => $this->intro_body,
            'red_flags' => $this->redFlags->map(fn ($flag) => [
                'code' => $flag->code,
                'label' => $flag->label,
            ])->values(),
            'steps' => $this->steps->map(fn ($step) => [
                'key' => $step->step_key,
                'type' => $step->type->value,
                'label' => $step->label,
                'required' => $step->is_required,
                'options' => $step->options->map(fn ($option) => [
                    'value' => $option->value,
                    'label' => $option->label,
                ])->values(),
            ])->values(),
        ];
    }
}
