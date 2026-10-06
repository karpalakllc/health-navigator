<?php

namespace App\Filament\Support;

use App\Models\ContentReport;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Review;

/**
 * How the report queue names the reported item and quotes it.
 */
final class ReportedContentLabel
{
    public static function target(ContentReport $report): string
    {
        $content = $report->reportable;

        return match (true) {
            $content instanceof Review => 'Review · '.ReviewableLabel::forReview($content),
            $content instanceof ForumTopic => 'Forum topic · '.$content->title,
            $content instanceof ForumPost => 'Forum reply · '.($content->topic->title ?? '—'),
            default => 'Deleted content',
        };
    }

    public static function body(ContentReport $report): string
    {
        $content = $report->reportable;

        return match (true) {
            $content instanceof Review, $content instanceof ForumTopic, $content instanceof ForumPost => (string) $content->body,
            default => '',
        };
    }

    public static function status(ContentReport $report): string
    {
        $content = $report->reportable;

        if ($content === null) {
            return 'deleted';
        }

        return $report->contentIsPublished() ? 'published' : 'not published';
    }
}
