<?php

namespace Database\Seeders;

use App\Enums\ForumContentStatus;
use App\Enums\UserKind;
use App\Models\ForumCategory;
use App\Models\ForumTopic;
use App\Models\User;
use App\Support\EmailAddress;
use App\Support\Usernames\UsernameNormalizer;
use App\Support\Usernames\UsernameValidator;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The forum's categories and pinned starter topics, written by a staff
 * account shown as „Администратор“. Content: database/seeders/data/forum_starter.php.
 *
 * Meant for production as well as local (no demo guard):
 *
 *     php artisan db:seed --class=ForumStarterSeeder --force
 *
 * Idempotent and keyed by slug. A second run creates only what is missing and
 * never rewrites what the team has since edited, unpublished, unpinned or
 * rejected in the panel. Categories it finds are updated only while they
 * still carry a name/description the seeders themselves gave them earlier.
 * A starter topic that was deleted outright comes back on the next run.
 *
 * Topics are created already approved, through the model, so the usual
 * bookkeeping runs: publication timestamps, the taxonomy cache flush that
 * refreshes category counts (counted live from approved topics), and the
 * Scout index entry.
 */
class ForumStarterSeeder extends Seeder
{
    /** Private account name; the public one is the username. */
    public const AUTHOR_NAME = 'Zdravje360 — форум';

    /** @var array{categories_created: int, categories_updated: int, topics_created: int} */
    private array $stats = ['categories_created' => 0, 'categories_updated' => 0, 'topics_created' => 0];

    public function run(): void
    {
        /** @var list<array<string, mixed>> $categories */
        $categories = require database_path('seeders/data/forum_starter.php');

        $author = $this->author();
        $now = now();
        // Each topic a minute older than the one before it, so listings
        // (pinned first, then newest activity) keep the order written here.
        $position = 0;

        foreach ($categories as $data) {
            $category = $this->category($data);

            foreach ($data['topics'] as $index => $topic) {
                $this->topic($category, $author, $topic, $now->copy()->subMinutes($position++), $index === 0 && $data['slug'] === 'general-health');
            }
        }

        $this->command->info(sprintf(
            'ForumStarterSeeder: %d categories created, %d updated; %d topics created (author: %s).',
            $this->stats['categories_created'],
            $this->stats['categories_updated'],
            $this->stats['topics_created'],
            $author->username,
        ));
    }

    /**
     * The staff author. „Администратор“ is on the reserved list, which only
     * stops members from taking it; seeders bypass the validator. It must
     * still not collide with an existing account's folded name, which the
     * unique indexes would refuse anyway.
     */
    private function author(): User
    {
        $email = EmailAddress::normalize((string) config('zdravje.seed.forum_author.email'));
        $username = UsernameNormalizer::prepare((string) config('zdravje.seed.forum_author.username'));

        $author = User::query()->where('email', $email)->first();

        if ($author !== null) {
            return $author;
        }

        if (UsernameValidator::isTaken($username)) {
            throw new RuntimeException("ForumStarterSeeder: the username „{$username}“ already belongs to another account. Set FORUM_STARTER_AUTHOR_EMAIL to that account's address, or free the name first.");
        }

        $author = new User;
        $author->forceFill([
            'name' => self::AUTHOR_NAME,
            'username' => $username,
            'email' => $email,
            // Never shown or stored anywhere: the account does not sign in.
            'password' => Str::random(64),
            'user_kind' => UserKind::Staff,
            'email_verified_at' => now(),
            'must_choose_username' => false,
        ])->save();

        return $author;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function category(array $data): ForumCategory
    {
        $category = ForumCategory::query()->where('slug', $data['slug'])->first();

        if ($category === null) {
            $this->stats['categories_created']++;

            return ForumCategory::query()->create([
                'slug' => $data['slug'],
                'name' => $data['name'],
                'description' => $data['description'],
                'sort_order' => $data['sort_order'],
                'is_published' => true,
                'published_at' => now(),
            ]);
        }

        /** @var array<string, list<mixed>> $previous */
        $previous = $data['previous'] ?? [];

        foreach (['name', 'description', 'sort_order'] as $field) {
            if (in_array($category->getAttribute($field), $previous[$field] ?? [], true)) {
                $category->setAttribute($field, $data[$field]);
            }
        }

        if ($category->isDirty()) {
            $category->save();
            $this->stats['categories_updated']++;
        }

        return $category;
    }

    /**
     * @param  array{slug: string, title: string, body: string, tags?: list<string>}  $data
     */
    private function topic(ForumCategory $category, User $author, array $data, CarbonInterface $publishedAt, bool $isRules): void
    {
        // By author and slug, not category: a topic a moderator moved
        // elsewhere is still the same topic.
        $exists = ForumTopic::query()
            ->where('user_id', $author->id)
            ->where('slug', $data['slug'])
            ->exists();

        if ($exists) {
            return;
        }

        // A member's topic already holds the slug in this category.
        $slugTaken = ForumTopic::query()
            ->where('forum_category_id', $category->id)
            ->where('slug', $data['slug'])
            ->exists();

        if ($slugTaken) {
            $this->command->warn("ForumStarterSeeder: skipped „{$data['title']}“ — the slug {$data['slug']} is taken in {$category->slug}.");

            return;
        }

        $topic = ForumTopic::query()->create([
            'forum_category_id' => $category->id,
            'user_id' => $author->id,
            'slug' => $data['slug'],
            'title' => $data['title'],
            'body' => $data['body'],
            'status' => ForumContentStatus::Approved,
            'is_pinned' => true,
            // The rules topic reads as a notice, not a thread.
            'is_locked' => $isRules,
            'published_at' => $publishedAt,
            'last_post_at' => $publishedAt,
            'community_rules_accepted_at' => now(),
        ]);

        // Staff-chosen keywords count as confirmed; syncTags() re-indexes.
        $topic->syncTags($data['tags'] ?? [], confirmed: true);

        $this->stats['topics_created']++;
    }
}
