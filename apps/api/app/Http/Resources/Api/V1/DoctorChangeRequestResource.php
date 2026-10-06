<?php

namespace App\Http\Resources\Api\V1;

use App\Models\DoctorChangeRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A change request as the doctor who made it sees it. The reviewer is never
 * named; the reason is, on a rejection.
 *
 * @mixin DoctorChangeRequest
 */
class DoctorChangeRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'changes' => (object) $this->changes,
            'message' => $this->message,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
        ];
    }
}
