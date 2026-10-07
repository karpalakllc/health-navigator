<?php

namespace App\Support\Ux;

/**
 * The bounded vocabulary of the anonymous UX statistics (docs/ux-heatmaps.md).
 *
 * Every value the web tracker may send is one of a small, fixed set — page
 * template, device class, coarse width, position bucket, structural target
 * key — so nothing a visitor typed or read can be smuggled into a counter.
 * Mirrors apps/web/src/lib/ux/schema.ts.
 */
final class UxSchema
{
    /** @var list<string> */
    public const VIEWPORT_CLASSES = ['mobile', 'tablet', 'desktop'];

    /** Viewport widths are reported rounded down to this step, in px. */
    public const WIDTH_STEP = 80;

    public const MAX_WIDTH = 3840;

    /** x is a whole percentage of the page width: 0–99. */
    public const MAX_X = 99;

    /** y is a 10 px band from the top of the document: 0–1999 (20 000 px). */
    public const Y_STEP = 10;

    public const MAX_Y = 1999;

    /** @var list<int> */
    public const SCROLL_MILESTONES = [0, 25, 50, 75, 90, 100];

    /**
     * Time to first interaction (first click), by bucket index.
     *
     * @var list<string>
     */
    public const TFI_COLUMNS = ['tfi_under_1s', 'tfi_1_3s', 'tfi_3_10s', 'tfi_10_30s', 'tfi_over_30s'];

    /**
     * `context/element`, both lowercase ASCII words: a `data-track` name or
     * landmark, then what kind of element was clicked. Never text.
     */
    public const TARGET_KEY_PATTERN = '/^[a-z][a-z0-9-]{0,47}\/[a-z][a-z0-9-]{0,31}$/';

    public const MAX_CLICKS_PER_BATCH = 50;

    public const MAX_VIEWS_PER_BATCH = 20;

    /**
     * @return list<string>
     */
    public static function routes(): array
    {
        /** @var list<string> $routes */
        $routes = config('ux.routes', []);

        return $routes;
    }
}
