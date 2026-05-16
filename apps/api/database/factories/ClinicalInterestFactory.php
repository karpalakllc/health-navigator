<?php

namespace Database\Factories;

use App\Models\ClinicalInterest;
use App\Support\Slug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClinicalInterest>
 */
class ClinicalInterestFactory extends Factory
{
    protected $model = ClinicalInterest::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => Slug::fromName($name),
            'sort_order' => fake()->numberBetween(0, 100),
            'is_published' => true,
        ];
    }
}
