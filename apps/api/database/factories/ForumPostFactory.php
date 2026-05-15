<?php

namespace Database\Factories;

use App\Enums\ForumContentStatus;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumPost>
 */
class ForumPostFactory extends Factory
{
    protected $model = ForumPost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'forum_topic_id' => ForumTopic::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
            'status' => ForumContentStatus::Approved,
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
}
