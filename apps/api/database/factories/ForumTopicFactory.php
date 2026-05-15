<?php

namespace Database\Factories;

use App\Enums\ForumContentStatus;
use App\Models\ForumCategory;
use App\Models\ForumTopic;
use App\Models\User;
use App\Support\Slug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumTopic>
 */
class ForumTopicFactory extends Factory
{
    protected $model = ForumTopic::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'forum_category_id' => ForumCategory::factory(),
            'user_id' => User::factory(),
            'slug' => Slug::fromName($title),
            'title' => $title,
            'body' => fake()->paragraphs(2, true),
            'status' => ForumContentStatus::Approved,
            'is_locked' => false,
            'is_pinned' => false,
            'replies_count' => 0,
            'last_post_at' => null,
            'published_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => ForumContentStatus::Pending,
            'published_at' => null,
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn () => ['is_locked' => true]);
    }

    public function pinned(): static
    {
        return $this->state(fn () => ['is_pinned' => true]);
    }
}
