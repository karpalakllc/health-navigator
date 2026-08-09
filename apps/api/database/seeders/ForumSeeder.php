<?php

namespace Database\Seeders;

use App\Enums\ForumContentStatus;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\User;
use Database\Seeders\Concerns\SeedsLocalDemoData;
use Illuminate\Database\Seeder;

class ForumSeeder extends Seeder
{
    use SeedsLocalDemoData;

    public function run(): void
    {
        if (! $this->shouldRunLocalDemoSeeders()) {
            $this->command?->warn('ForumSeeder skipped. Use APP_ENV=local (or development), or SEED_LOCAL_DEMO=true, then php artisan db:seed');

            return;
        }

        $member = User::query()->where('email', 'member@zdravje360.test')->first();

        if ($member === null) {
            return;
        }

        $general = ForumCategory::query()->updateOrCreate(
            ['slug' => 'general-health'],
            [
                'name' => 'Општо здравје',
                'description' => 'Општа дискусија и практични совети (само информативно). Не е медицински совет.',
                'sort_order' => 1,
                'is_published' => true,
                'published_at' => now(),
            ],
        );

        $nutrition = ForumCategory::query()->updateOrCreate(
            ['slug' => 'ishrana-i-zhivot'],
            [
                'name' => 'Исхрана и навики',
                'description' => 'Оброци, хидратација, сон и движење — без диети екстремни без лекар.',
                'sort_order' => 2,
                'is_published' => true,
                'published_at' => now(),
            ],
        );

        $welcome = ForumTopic::query()->firstOrCreate(
            [
                'forum_category_id' => $general->id,
                'slug' => 'dobredojdovte-vo-zajednicata',
            ],
            [
                'user_id' => $member->id,
                'title' => 'Добредојдовте во заедницата на Zdravje360',
                'body' => 'Ова е пример тема за локален развој. Темите и одговорите се модерираат. Ако сте загрижени за симптоми, контактирајте здравствен работник или итна линија (194 / 112).',
                'status' => ForumContentStatus::Approved,
                'published_at' => now(),
            ],
        );

        ForumPost::query()->firstOrCreate(
            [
                'forum_topic_id' => $welcome->id,
                'user_id' => $member->id,
                'body' => 'Ви благодарам што одржувате разговорот почитуван и фокусиран на општа едукација.',
            ],
            [
                'status' => ForumContentStatus::Approved,
                'published_at' => now(),
            ],
        );

        $welcome->update([
            'replies_count' => 1,
            'last_post_at' => now(),
        ]);

        $hydration = ForumTopic::query()->firstOrCreate(
            [
                'forum_category_id' => $nutrition->id,
                'slug' => 'hidratacija-vo-letni-denovi',
            ],
            [
                'user_id' => $member->id,
                'title' => 'Колку вода дневно во топло време?',
                'body' => 'Лично пијам повеќе кога е жешко, но не сакам да претерам. Кои се здрави насоки за просечен возрасен без специјални дијагнози? (информативно)',
                'status' => ForumContentStatus::Approved,
                'published_at' => now()->subDay(),
            ],
        );

        $marija = User::query()->where('email', 'marija@zdravje360.test')->first();

        if ($marija !== null) {
            ForumPost::query()->firstOrCreate(
                [
                    'forum_topic_id' => $hydration->id,
                    'user_id' => $marija->id,
                    'body' => 'Слушаме често „слушајте го телото“ — ако сте жедни, пијте малку почесто. За специфични состојби на бубрези или срце, лекар знае најдобро.',
                ],
                [
                    'status' => ForumContentStatus::Approved,
                    'published_at' => now()->subHours(6),
                ],
            );

            $hydration->update([
                'replies_count' => 1,
                'last_post_at' => now()->subHours(6),
            ]);
        }
    }
}
