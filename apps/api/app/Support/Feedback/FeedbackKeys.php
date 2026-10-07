<?php

namespace App\Support\Feedback;

use App\Enums\TriageOutcomeLevel;
use App\Models\TriageFlow;

/**
 * Which well-formed feedback keys name something that exists (config
 * `feedback.known_*`, the imported flow definitions, the outcome levels).
 * An unknown key is not an error for the visitor; it is just not counted, so
 * no one can grow the counter tables with invented keys.
 */
final class FeedbackKeys
{
    public function knownItem(string $item): bool
    {
        $parts = explode(':', $item);

        return match ($parts[0]) {
            'guide' => count($parts) === 2 && in_array($parts[1], (array) config('feedback.known_guides'), true),
            'urgent-care' => count($parts) === 2 && in_array($parts[1], (array) config('feedback.known_places'), true),
            'guidance' => count($parts) === 4
                && $parts[2] === 'outcome'
                && TriageOutcomeLevel::tryFrom($parts[3]) !== null
                && ($parts[1] === 'global' || $this->flow($parts[1]) !== null),
            default => false,
        };
    }

    public function knownStep(string $funnel, string $step): bool
    {
        $parts = explode(':', $funnel);

        if (count($parts) !== 2 || $parts[0] !== 'guidance') {
            return false;
        }

        $flow = $this->flow($parts[1]);

        if ($flow === null) {
            return false;
        }

        if ($step === 'start') {
            return true;
        }

        if (str_starts_with($step, 'outcome:')) {
            return TriageOutcomeLevel::tryFrom(substr($step, 8)) !== null;
        }

        foreach ($flow->versions()->pluck('definition') as $definition) {
            if (is_array($definition) && isset($definition['nodes'][$step])) {
                return true;
            }
        }

        return false;
    }

    private function flow(string $key): ?TriageFlow
    {
        return TriageFlow::query()->v2()->where('key', $key)->first();
    }
}
