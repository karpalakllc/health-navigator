<?php

namespace App\Support\Ux;

use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ForumCategory;
use App\Models\ForumTag;
use App\Models\ForumTopic;
use App\Models\Product;

/**
 * A real public address for a page template, so the heatmap link from the
 * admin panel opens a page the overlay can draw on (the counters are per
 * template: any doctor profile shows the clicks of all of them).
 */
final class UxSamplePath
{
    public static function for(string $route): string
    {
        $slug = match ($route) {
            '/doctors/[slug]' => Doctor::query()->published()->orderBy('id')->value('slug'),
            '/facilities/[slug]' => Facility::query()->published()->clinical()->orderBy('id')->value('slug'),
            '/pharmacies/[slug]' => Facility::query()->published()->pharmacy()->orderBy('id')->value('slug'),
            '/products/[slug]' => Product::query()->published()->orderBy('id')->value('slug'),
            '/forum/[categorySlug]' => ForumCategory::query()->published()->orderBy('id')->value('slug'),
            '/forum/tags/[tag]' => ForumTag::query()->orderBy('id')->value('slug'),
            '/forum/[categorySlug]/[topicSlug]' => self::topicPath(),
            // Static pages with fixed slugs (apps/web: content/guides, lib/mk-places).
            '/urgent-care/[city]' => 'skopje',
            '/guides/[slug]' => 'kako-do-uput',
            default => null,
        };

        if (! str_contains($route, '[')) {
            return $route;
        }

        if (! is_string($slug) || $slug === '') {
            // Nothing published yet: the listing the template belongs to.
            return '/'.explode('/', $route)[1];
        }

        return $route === '/forum/[categorySlug]/[topicSlug]'
            ? $slug
            : preg_replace('/\[[A-Za-z]+\]$/', rawurlencode($slug), $route) ?? $route;
    }

    private static function topicPath(): ?string
    {
        $topic = ForumTopic::query()->approved()->with('category')->orderBy('id')->first();
        $category = $topic?->category;

        if ($topic === null || $category === null) {
            return null;
        }

        return '/forum/'.rawurlencode((string) $category->slug).'/'.rawurlencode((string) $topic->slug);
    }
}
