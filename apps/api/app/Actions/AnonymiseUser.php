<?php

namespace App\Actions;

use App\Actions\DoctorAccount\ForgetDoctorAccountData;
use App\Enums\ForumContentStatus;
use App\Enums\ReviewStatus;
use App\Models\ContentReport;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Review;
use App\Models\User;
use App\Models\UsernameHistory;
use App\Support\Media\ImageOptimizer;
use App\Support\TaxonomyCache;
use App\Support\Usernames\TemporaryUsername;
use App\Support\Usernames\UsernameNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Account deletion by anonymisation in place (D5).
 *
 * Reviews and forum topics/posts reference users with restrictOnDelete, and
 * published ones stay public after the author leaves (pending ones are
 * withdrawn), so the row is kept as an empty
 * shell: every personal field is cleared, the address is freed for a new
 * registration, every way back in (tokens, password, panel sessions, reset
 * links, roles) is removed, and public surfaces render the author as a deleted
 * user (User::publicName(), ForumAuthorResource).
 *
 * Not reversible. The caller is responsible for authorising it (password
 * re-entry for self-service; AccountController refuses staff accounts).
 */
final class AnonymiseUser
{
    /** Reserved TLD (RFC 2606): never deliverable, never anyone's real address. */
    private const PLACEHOLDER_DOMAIN = 'deleted.invalid';

    /** Staff-facing: the author is gone, so nobody is mailed this. */
    private const WITHDRAWN_NOTE = 'Повлечено: сметката на авторот е избришана пред модерацијата.';

    public function __construct(
        private readonly ImageOptimizer $images,
        private readonly ForgetDoctorAccountData $doctorAccount,
    ) {}

    public function handle(User $user): void
    {
        $avatarPath = DB::transaction(function () use ($user): ?string {
            /** @var User $locked */
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isAnonymised()) {
                return null;
            }

            $previousEmail = (string) $locked->email;
            $avatarPath = $locked->avatar_path;

            // The username is cleared like the name, but held back for six
            // months (unlinked from the account) so nobody can post under it
            // straight away and be taken for the person who left. Earlier
            // names stay held too, unlinked, without staff notes about them.
            UsernameHistory::query()->where('user_id', $locked->getKey())->update([
                'user_id' => null,
                'note' => null,
            ]);

            if ($locked->username !== null && ! TemporaryUsername::isTemporary($locked->username)) {
                UsernameHistory::query()->create([
                    'user_id' => null,
                    'username' => $locked->username,
                    'username_normalized' => UsernameNormalizer::key($locked->username),
                    'username_skeleton' => UsernameNormalizer::skeleton($locked->username),
                    'reason' => 'anonymised',
                    'reserved_until' => now()->addMonths(UsernameHistory::HOLD_MONTHS),
                ]);
            }

            $locked->forceFill([
                'name' => '',
                'display_name' => null,
                'username' => null,
                'username_changed_at' => null,
                'must_choose_username' => false,
                // Unique, lower-case (EmailAddress::normalize) and unguessable, so
                // the original address is free and this one can never be claimed.
                'email' => 'deleted-'.$locked->getKey().'-'.Str::lower(Str::random(16)).'@'.self::PLACEHOLDER_DOMAIN,
                'email_verified_at' => null,
                'registration_contested_at' => null,
                'password' => Hash::make(Str::random(64)),
                'remember_token' => null,
                'avatar_path' => null,
                'app_authentication_secret' => null,
                'app_authentication_recovery_codes' => null,
                'suspended_at' => null,
                'suspension_reason' => null,
                'suspended_by_id' => null,
                'anonymised_at' => $locked->freshTimestamp(),
            ])->save();

            $locked->revokeApiTokens();
            $locked->syncRoles([]);
            $locked->syncPermissions([]);
            $locked->moderatedForumCategories()->detach();
            $this->doctorAccount->handle($locked);

            DB::table('sessions')->where('user_id', $locked->getKey())->delete();
            DB::table(config('auth.passwords.users.table', 'password_reset_tokens'))
                ->where('email', $previousEmail)
                ->delete();

            // Only published content stays. Whatever still waits for moderation
            // is withdrawn, so it can never go public under a deleted account.
            // Pending items are in no list, count or search index: a plain
            // update has nothing else to correct, and nobody is mailed.
            $withdrawn = [
                'moderated_at' => now(),
                'rejection_note' => self::WITHDRAWN_NOTE,
                'updated_at' => now(),
            ];
            Review::query()->where('user_id', $locked->getKey())->where('status', ReviewStatus::Pending)
                ->update(['status' => ReviewStatus::Rejected, ...$withdrawn]);
            ForumTopic::query()->where('user_id', $locked->getKey())->where('status', ForumContentStatus::Pending)
                ->update(['status' => ForumContentStatus::Rejected, ...$withdrawn]);
            ForumPost::query()->where('user_id', $locked->getKey())->where('status', ForumContentStatus::Pending)
                ->update(['status' => ForumContentStatus::Rejected, ...$withdrawn]);

            // Reports and helpful votes stay (queue history, counts) but their
            // free text could say who the member is.
            ContentReport::query()->where('user_id', $locked->getKey())->update(['note' => null]);

            // Dashboard counts keep working; the events stop pointing at anyone.
            DB::table('analytics_events')->where('user_id', $locked->getKey())->update(['user_id' => null]);

            return $avatarPath;
        });

        // The home page caches recent reviews with their authors' names.
        TaxonomyCache::flush(TaxonomyCache::HOME_HIGHLIGHTS);

        // Only once nothing points at it: a rolled-back deletion keeps its photo.
        if (is_string($avatarPath) && $avatarPath !== '') {
            $this->images->delete($avatarPath);
        }

        $user->refresh();
    }
}
