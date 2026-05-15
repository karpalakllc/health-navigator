<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\Doctor;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reviewable_type' => Doctor::class,
            'reviewable_id' => Doctor::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'body' => fake()->optional(0.8)->paragraph(),
            'status' => ReviewStatus::Pending,
            'published_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => ReviewStatus::Approved,
            'published_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => ReviewStatus::Rejected,
            'published_at' => null,
        ]);
    }
}
