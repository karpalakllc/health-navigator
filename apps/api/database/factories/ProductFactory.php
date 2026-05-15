<?php

namespace Database\Factories;

use App\Models\Product;
use App\Support\Slug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'slug' => Slug::fromName($name),
            'name' => ucfirst($name),
            'description' => fake()->optional()->sentence(),
            'category' => fake()->randomElement(['analgetici', 'vitamini', 'dermatologija', null]),
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
