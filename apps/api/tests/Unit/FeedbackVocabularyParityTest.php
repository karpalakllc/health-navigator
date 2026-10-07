<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * „Дали ви помогна?“ keeps its vocabulary twice: the web relay
 * (apps/web/src/lib/feedback.ts) decides what to send, config/feedback.php
 * what the API accepts. A reason or pattern only one side knows is silently
 * dropped.
 */
class FeedbackVocabularyParityTest extends TestCase
{
    private function source(): string
    {
        $path = base_path('../web/src/lib/feedback.ts');

        if (! is_file($path)) {
            $this->markTestSkipped('The web app is not next to the API in this checkout.');
        }

        return (string) file_get_contents($path);
    }

    /**
     * @return list<string>
     */
    private function webList(string $marker): array
    {
        $this->assertSame(1, preg_match('#// '.$marker.':start(.*?)// '.$marker.':end#s', $this->source(), $block), "No {$marker} block.");
        preg_match_all('/"([^"]+)"/', $block[1], $matches);

        return $matches[1];
    }

    private function webPattern(string $constant): string
    {
        $this->assertSame(1, preg_match('#'.$constant.'\s*=\s*(/\^.*?\$/);#s', $this->source(), $match), "No {$constant}.");

        return $match[1];
    }

    public function test_reasons_match(): void
    {
        $this->assertSame(config('feedback.reasons.helpful'), $this->webList('feedback-helpful'));
        $this->assertSame(config('feedback.reasons.not_helpful'), $this->webList('feedback-not-helpful'));
    }

    public function test_patterns_match(): void
    {
        $this->assertSame(config('feedback.item_pattern'), $this->webPattern('FEEDBACK_ITEM_PATTERN'));
        $this->assertSame(config('feedback.funnel_pattern'), $this->webPattern('FEEDBACK_FUNNEL_PATTERN'));
        $this->assertSame(config('feedback.step_pattern'), $this->webPattern('FEEDBACK_STEP_PATTERN'));
    }

    public function test_known_guides_and_places_match_the_web_content(): void
    {
        $guides = base_path('../web/src/content/guides/guides.tsx');
        $places = base_path('../web/src/lib/mk-places.ts');

        if (! is_file($guides) || ! is_file($places)) {
            $this->markTestSkipped('The web app is not next to the API in this checkout.');
        }

        preg_match_all('/^    slug: "([^"]+)"/m', (string) file_get_contents($guides), $slugs);
        $this->assertSame($slugs[1], config('feedback.known_guides'));

        preg_match_all('/(?:place|skopje)\(\s*"[^"]+",\s*"([^"]+)"/', (string) file_get_contents($places), $latin);
        $ids = array_map(
            fn (string $name): string => preg_replace('/[^a-z0-9]+/', '-', preg_replace('/\p{Mn}/u', '', \Normalizer::normalize(mb_strtolower($name), \Normalizer::FORM_D))),
            $latin[1],
        );
        $this->assertSame(['all', ...$ids], config('feedback.known_places'));
    }
}
