<?php

namespace App\Support\Ux;

/**
 * The bounded vocabulary of the anonymous UX statistics (docs/ux-heatmaps.md).
 *
 * Every value the web tracker may send is one of a small, fixed set — page
 * template, device class, coarse width, position bucket, target key built
 * from two closed word lists — so nothing a visitor typed or read can be
 * smuggled into a counter, and the number of distinct rows is bounded.
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

    /*
     * Target keys are `context/element`, both from closed lists, so a key can
     * never carry free text and the number of distinct keys is fixed. Mirrors
     * the marked lists in apps/web/src/lib/ux/schema.ts (UxRoutesParityTest).
     */

    /**
     * Our `data-track` names, then the landmarks, then `page` (none of those).
     *
     * @var list<string>
     */
    public const TARGET_CONTEXTS = [
        'doctor-card', 'facility-card', 'pharmacy-card', 'forum-topic', 'site-nav', 'tab-bar',
        'breadcrumbs', 'pagination', 'home-how-it-works', 'home-forum-band',
        'home-help', 'review-prompt',
        'header', 'nav', 'main', 'footer', 'aside', 'search', 'dialog', 'form', 'page',
    ];

    /**
     * Kinds of element, besides `input-<type>` and `role-<role>`.
     *
     * @var list<string>
     */
    public const TARGET_ELEMENTS = [
        'link', 'button', 'select', 'textarea', 'summary', 'label', 'focusable', 'pointer', 'disabled',
        'heading', 'img', 'icon', 'text', 'table', 'media', 'area',
    ];

    /**
     * `input-<type>`; any other type is `input-other`.
     *
     * @var list<string>
     */
    public const INPUT_TYPES = [
        'text', 'search', 'email', 'password', 'tel', 'url', 'number', 'checkbox', 'radio', 'range',
        'date', 'time', 'file', 'submit', 'button', 'reset',
    ];

    /**
     * ARIA roles that make an element interactive: `role-<role>`, except
     * button and link, which are reported as such.
     *
     * @var list<string>
     */
    public const INTERACTIVE_ROLES = [
        'button', 'link', 'checkbox', 'radio', 'switch', 'tab', 'menuitem', 'menuitemcheckbox',
        'menuitemradio', 'option', 'combobox', 'slider', 'spinbutton', 'textbox', 'searchbox', 'treeitem',
    ];

    /** Longest `context/element` the lists allow, with room to spare. */
    public const MAX_TARGET_KEY_LENGTH = 64;

    public const MAX_CLICKS_PER_BATCH = 50;

    public const MAX_VIEWS_PER_BATCH = 20;

    /**
     * Every element kind: the plain ones, `input-<type>` and `role-<role>`.
     *
     * @return list<string>
     */
    public static function targetElements(): array
    {
        $roles = array_values(array_diff(self::INTERACTIVE_ROLES, ['button', 'link']));

        return [
            ...self::TARGET_ELEMENTS,
            ...array_map(fn (string $type): string => 'input-'.$type, self::INPUT_TYPES),
            'input-other',
            ...array_map(fn (string $role): string => 'role-'.$role, $roles),
        ];
    }

    /**
     * Every target key the tracker can produce.
     *
     * @return list<string>
     */
    public static function targetKeys(): array
    {
        $keys = [];

        foreach (self::TARGET_CONTEXTS as $context) {
            foreach (self::targetElements() as $element) {
                $keys[] = $context.'/'.$element;
            }
        }

        return $keys;
    }

    public static function isTargetKey(mixed $value): bool
    {
        if (! is_string($value) || strlen($value) > self::MAX_TARGET_KEY_LENGTH) {
            return false;
        }

        [$context, $element] = array_pad(explode('/', $value, 2), 2, '');

        return in_array($context, self::TARGET_CONTEXTS, true)
            && in_array($element, self::targetElements(), true);
    }

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
