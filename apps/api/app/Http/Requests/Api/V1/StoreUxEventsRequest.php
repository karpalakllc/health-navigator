<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Ux\UxSchema;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /ux/events — one batch from the web tracker (docs/ux-heatmaps.md).
 *
 * Every field is a bounded number or one of a fixed set of words (the target
 * key is two words from closed lists: UxSchema::isTargetKey), so a batch
 * can only ever increment a known counter: a free-text value, an unknown page
 * or an out-of-range position fails the whole batch.
 *
 *   clicks[]: r route template, vc device class, wb width bucket, x % of page
 *             width, y 10 px band (null on a fixed/sticky element), k target
 *             key, d dead click, g rage click
 *   views[]:  r, vc, s deepest scroll milestone, t time-to-first-click bucket
 *             (null: no click)
 */
class StoreUxEventsRequest extends FormRequest
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
        $route = ['required', 'string', Rule::in(UxSchema::routes())];
        $viewport = ['required', 'string', Rule::in(UxSchema::VIEWPORT_CLASSES)];

        return [
            'clicks' => ['present', 'array', 'max:'.UxSchema::MAX_CLICKS_PER_BATCH],
            'clicks.*' => ['array:r,vc,wb,x,y,k,d,g'],
            'clicks.*.r' => $route,
            'clicks.*.vc' => $viewport,
            'clicks.*.wb' => ['required', 'integer', 'min:0', 'max:'.UxSchema::MAX_WIDTH, 'multiple_of:'.UxSchema::WIDTH_STEP],
            'clicks.*.x' => ['required', 'integer', 'min:0', 'max:'.UxSchema::MAX_X],
            'clicks.*.y' => ['present', 'nullable', 'integer', 'min:0', 'max:'.UxSchema::MAX_Y],
            'clicks.*.k' => ['required', 'string', 'max:'.UxSchema::MAX_TARGET_KEY_LENGTH, function (string $attribute, mixed $value, Closure $fail): void {
                if (! UxSchema::isTargetKey($value)) {
                    $fail('Unknown target key.');
                }
            }],
            'clicks.*.d' => ['required', 'boolean'],
            'clicks.*.g' => ['required', 'boolean'],
            'views' => ['present', 'array', 'max:'.UxSchema::MAX_VIEWS_PER_BATCH],
            'views.*' => ['array:r,vc,s,t'],
            'views.*.r' => $route,
            'views.*.vc' => $viewport,
            'views.*.s' => ['required', 'integer', Rule::in(UxSchema::SCROLL_MILESTONES)],
            'views.*.t' => ['present', 'nullable', 'integer', 'min:0', 'max:'.(count(UxSchema::TFI_COLUMNS) - 1)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('clicks') === [] && $this->input('views') === []) {
                $validator->errors()->add('clicks', 'Empty batch.');
            }
        });
    }

    /**
     * @return list<array{r: string, vc: string, wb: int, x: int, y: ?int, k: string, d: bool, g: bool}>
     */
    public function clicks(): array
    {
        /** @var array<int, array<string, mixed>> $clicks */
        $clicks = $this->validated('clicks', []);

        return array_values(array_map(fn (array $click): array => [
            'r' => (string) $click['r'],
            'vc' => (string) $click['vc'],
            'wb' => (int) $click['wb'],
            'x' => (int) $click['x'],
            'y' => $click['y'] === null ? null : (int) $click['y'],
            'k' => (string) $click['k'],
            'd' => filter_var($click['d'], FILTER_VALIDATE_BOOLEAN),
            'g' => filter_var($click['g'], FILTER_VALIDATE_BOOLEAN),
        ], $clicks));
    }

    /**
     * @return list<array{r: string, vc: string, s: int, t: ?int}>
     */
    public function views(): array
    {
        /** @var array<int, array<string, mixed>> $views */
        $views = $this->validated('views', []);

        return array_values(array_map(fn (array $view): array => [
            'r' => (string) $view['r'],
            'vc' => (string) $view['vc'],
            's' => (int) $view['s'],
            't' => $view['t'] === null ? null : (int) $view['t'],
        ], $views));
    }
}
