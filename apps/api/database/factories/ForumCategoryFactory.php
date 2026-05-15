<?php

namespace Database\Factories;

use App\Models\ForumCategory;
use App\Support\Slug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumCategory>
 */
class ForumCategoryFactory extends Factory
{
    protected $model = ForumCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => Slug::fromName($name),
            'name' => ucfirst($name),
            'description' => fake()->optional()->sentence(),
            'sort_order' => fake()->numberBetween(0, 10),
            'is_published' => true,
            'published_at' => now(),
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn () => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }
}
