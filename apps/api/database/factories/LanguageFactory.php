<?php

namespace Database\Factories;

use App\Models\Language;
use App\Support\Slug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Language>
 */
class LanguageFactory extends Factory
{
    protected $model = Language::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => $name,
            'slug' => Slug::fromName($name),
            'sort_order' => fake()->numberBetween(0, 100),
            'is_published' => true,
        ];
    }
}
