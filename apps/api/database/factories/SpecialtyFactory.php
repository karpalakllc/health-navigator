<?php

namespace Database\Factories;

use App\Models\Specialty;
use App\Support\Slug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Specialty>
 */
class SpecialtyFactory extends Factory
{
    protected $model = Specialty::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => Slug::fromName($name),
            'description' => fake()->optional()->sentence(),
            'sort_order' => fake()->numberBetween(0, 100),
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }
}
