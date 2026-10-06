<?php

namespace App\Support;

use App\Mail\ContentReportOutcomeMail;
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
                masculine: $payload['masculine'],
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
                masculine: $payload['masculine'],
            ));
        });
    }

    /**
     * @param  bool  $removed  taken down after a report (it had been public)
     */
    public static function notifyRejected(Model $model, bool $removed = false): void
    {
        self::sendToAuthor($model, function (User $user, array $payload) use ($removed): void {
            Mail::to($user)->queue(new UgcRejectedMail(
                recipientName: $user->name,
                contentLabel: $payload['label'],
                contentTitle: $payload['title'],
                actionUrl: $payload['account_url'],
                actionLabel: $payload['account_action_label'],
                masculine: $payload['masculine'],
                rejectionNote: $payload['rejection_note'] ?? null,
                removed: $removed,
            ));
        });
    }

    /**
     * Tell each reporter how their report about $content ended. A deleted
     * account is skipped, like any other recipient.
     *
     * @param  iterable<User>  $reporters
     */
    public static function notifyReportResolved(Model $content, iterable $reporters, bool $removed): void
    {
        $subject = self::reportSubject($content);

        if ($subject === null) {
            return;
        }

        foreach ($reporters as $user) {
            if ($user->isAnonymised()) {
                continue;
            }

            Mail::to($user)->queue(new ContentReportOutcomeMail(
                recipientName: $user->name,
                contentLabel: $subject['label'],
                contentTitle: $subject['title'],
                removed: $removed,
            ));
        }
    }

    /**
     * @return array{label: string, title: string}|null
     */
    private static function reportSubject(Model $content): ?array
    {
        $payload = self::payloadFor($content);

        if ($payload === null) {
            return null;
        }

        return [
            'label' => match (true) {
                $content instanceof ForumTopic => 'темата',
                $content instanceof ForumPost => 'одговорот во темата',
                default => 'рецензијата за',
            },
            'title' => $payload['title'],
        ];
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

        // A deleted account's address is a non-deliverable placeholder.
        if (! $user instanceof User || $user->isAnonymised()) {
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
     *     masculine: bool,
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
                'label' => 'тема',
                'masculine' => false,
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
                'label' => 'одговор во темата',
                'masculine' => true,
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
                'label' => 'рецензија за',
                'masculine' => false,
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
