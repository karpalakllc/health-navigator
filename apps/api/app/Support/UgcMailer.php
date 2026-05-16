<?php

namespace App\Support;

use App\Mail\UgcApprovedMail;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

final class UgcMailer
{
    public static function notifyApproved(Model $model): void
    {
        $user = match (true) {
            $model instanceof ForumTopic, $model instanceof ForumPost, $model instanceof Review => $model->user,
            default => null,
        };

        if (! $user instanceof User || $user->email === null) {
            return;
        }

        $payload = self::payloadFor($model);

        if ($payload === null) {
            return;
        }

        Mail::to($user)->queue(new UgcApprovedMail(
            recipientName: $user->name,
            contentLabel: $payload['label'],
            contentTitle: $payload['title'],
            actionUrl: $payload['url'],
            actionLabel: $payload['action_label'],
        ));
    }

    /**
     * @return array{label: string, title: string, url: string, action_label: string}|null
     */
    private static function payloadFor(Model $model): ?array
    {
        if ($model instanceof ForumTopic) {
            $model->loadMissing('category');

            return [
                'label' => 'тема на форумот',
                'title' => $model->title,
                'url' => FrontendUrl::to("/forum/{$model->category->slug}/{$model->slug}"),
                'action_label' => 'Види ја темата',
            ];
        }

        if ($model instanceof ForumPost) {
            $model->loadMissing(['topic.category']);

            return [
                'label' => 'одговор на форумот',
                'title' => $model->topic->title,
                'url' => FrontendUrl::to("/forum/{$model->topic->category->slug}/{$model->topic->slug}"),
                'action_label' => 'Види го одговорот',
            ];
        }

        if ($model instanceof Review) {
            $model->loadMissing('reviewable');
            $reviewable = $model->reviewable;

            if ($reviewable === null) {
                return null;
            }

            $path = match ($reviewable::class) {
                \App\Models\Doctor::class => "/doctors/{$reviewable->slug}",
                \App\Models\Facility::class => "/facilities/{$reviewable->slug}",
                default => null,
            };

            if ($path === null) {
                return null;
            }

            $name = $reviewable->full_name ?? $reviewable->name ?? 'профилот';

            return [
                'label' => 'рецензија',
                'title' => $name,
                'url' => FrontendUrl::to($path),
                'action_label' => 'Види го профилот',
            ];
        }

        return null;
    }
}
