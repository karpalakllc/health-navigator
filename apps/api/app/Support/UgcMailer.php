<?php

namespace App\Support;

use App\Mail\UgcApprovedMail;
use App\Mail\UgcRejectedMail;
use App\Mail\UgcSubmittedMail;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

final class UgcMailer
{
    public static function notifySubmitted(Model $model): void
    {
        self::sendToAuthor($model, function (User $user, array $payload): void {
            Mail::to($user)->queue(new UgcSubmittedMail(
                recipientName: $user->name,
                contentLabel: $payload['label'],
                contentTitle: $payload['title'],
                actionUrl: $payload['account_url'],
                actionLabel: $payload['account_action_label'],
            ));
        });
    }

    public static function notifyApproved(Model $model): void
    {
        self::sendToAuthor($model, function (User $user, array $payload): void {
            if ($payload['public_url'] === null) {
                return;
            }

            Mail::to($user)->queue(new UgcApprovedMail(
                recipientName: $user->name,
                contentLabel: $payload['label'],
                contentTitle: $payload['title'],
                actionUrl: $payload['public_url'],
                actionLabel: $payload['public_action_label'],
            ));
        });
    }

    public static function notifyRejected(Model $model): void
    {
        self::sendToAuthor($model, function (User $user, array $payload): void {
            Mail::to($user)->queue(new UgcRejectedMail(
                recipientName: $user->name,
                contentLabel: $payload['label'],
                contentTitle: $payload['title'],
                actionUrl: $payload['account_url'],
                actionLabel: $payload['account_action_label'],
                rejectionNote: $payload['rejection_note'] ?? null,
            ));
        });
    }

    /**
     * @param  callable(User, array<string, mixed>): void  $send
     */
    private static function sendToAuthor(Model $model, callable $send): void
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

        $send($user, $payload);
    }

    /**
     * @return array{
     *     label: string,
     *     title: string,
     *     account_url: string,
     *     account_action_label: string,
     *     public_url: string|null,
     *     public_action_label: string,
     *     rejection_note: string|null
     * }|null
     */
    private static function payloadFor(Model $model): ?array
    {
        if ($model instanceof ForumTopic) {
            $model->loadMissing('category');

            return [
                'label' => 'тема на форумот',
                'title' => $model->title,
                'account_url' => FrontendUrl::to('/account/forum'),
                'account_action_label' => 'Мој форум',
                'public_url' => FrontendUrl::to("/forum/{$model->category->slug}/{$model->slug}"),
                'public_action_label' => 'Види ја темата',
                'rejection_note' => $model->rejection_note,
            ];
        }

        if ($model instanceof ForumPost) {
            $model->loadMissing(['topic.category']);

            return [
                'label' => 'одговор на форумот',
                'title' => $model->topic->title,
                'account_url' => FrontendUrl::to('/account/forum'),
                'account_action_label' => 'Мој форум',
                'public_url' => FrontendUrl::to("/forum/{$model->topic->category->slug}/{$model->topic->slug}"),
                'public_action_label' => 'Види го одговорот',
                'rejection_note' => $model->rejection_note,
            ];
        }

        if ($model instanceof Review) {
            $model->loadMissing('reviewable');
            $reviewable = $model->reviewable;

            if ($reviewable === null) {
                return null;
            }

            $publicPath = match (true) {
                $reviewable instanceof Doctor => "/doctors/{$reviewable->slug}",
                $reviewable instanceof Facility => $reviewable->isPharmacy()
                    ? "/pharmacies/{$reviewable->slug}"
                    : "/facilities/{$reviewable->slug}",
                default => null,
            };

            $name = $reviewable->full_name ?? $reviewable->name ?? 'профилот';

            return [
                'label' => 'рецензија',
                'title' => $name,
                'account_url' => FrontendUrl::to('/account/reviews'),
                'account_action_label' => 'Мои рецензии',
                'public_url' => $publicPath !== null ? FrontendUrl::to($publicPath) : null,
                'public_action_label' => 'Види го профилот',
                'rejection_note' => $model->rejection_note,
            ];
        }

        return null;
    }
}
