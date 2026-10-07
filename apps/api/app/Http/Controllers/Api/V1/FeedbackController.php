<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Feedback\FeedbackRecorder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Anonymous „Дали ви помогна?“ votes and step drop-off counters
 * (docs/urgent-care.md § Feedback). No account, no free text: the item, reason
 * and step values come from closed lists or short slug patterns
 * (config/feedback.php). The address is used only for the rate limit, as an
 * HMAC of its network under the app key (`api-feedback`, `api-funnel`), a
 * cache key that expires with its window.
 */
class FeedbackController extends Controller
{
    public function vote(Request $request, FeedbackRecorder $recorder): Response
    {
        $validated = $request->validate([
            'item' => $this->itemRules(),
            'helpful' => ['required', 'boolean'],
        ]);

        $recorder->vote((string) $validated['item'], (bool) $validated['helpful']);

        return response()->noContent();
    }

    public function reasons(Request $request, FeedbackRecorder $recorder): Response
    {
        $helpful = $request->boolean('helpful');
        $allowed = (array) config('feedback.reasons.'.($helpful ? 'helpful' : 'not_helpful'));

        $validated = $request->validate([
            'item' => $this->itemRules(),
            'helpful' => ['required', 'boolean'],
            'reasons' => ['required', 'array', 'min:1', 'max:'.(int) config('feedback.max_reasons')],
            'reasons.*' => ['required', 'string', 'distinct', Rule::in($allowed)],
        ]);

        $recorder->reasons((string) $validated['item'], $helpful, array_map('strval', $validated['reasons']));

        return response()->noContent();
    }

    public function step(Request $request, FeedbackRecorder $recorder): Response
    {
        $validated = $request->validate([
            'funnel' => ['required', 'string', 'max:'.(int) config('feedback.max_length'), 'regex:'.config('feedback.funnel_pattern')],
            'step' => ['required', 'string', 'max:64', 'regex:'.config('feedback.step_pattern')],
            'depth' => ['required', 'integer', 'min:0', 'max:'.(int) config('feedback.max_depth')],
        ]);

        $recorder->step((string) $validated['funnel'], (string) $validated['step'], (int) $validated['depth']);

        return response()->noContent();
    }

    /**
     * @return list<string>
     */
    private function itemRules(): array
    {
        return ['required', 'string', 'max:'.(int) config('feedback.max_length'), 'regex:'.config('feedback.item_pattern')];
    }
}
