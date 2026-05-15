<?php

namespace Database\Factories;

use App\Enums\FacilityType;
use App\Models\Facility;
use App\Support\Slug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Facility>
 */
class FacilityFactory extends Factory
{
    protected $model = Facility::class;

    public function definition(): array
    {
        $name = fake()->company().' '.fake()->randomElement(['Клиника', 'Болница', 'Лабораторија']);

        return [
            'slug' => Slug::fromName($name),
            'name' => $name,
            'type' => fake()->randomElement([
                FacilityType::Clinic,
                FacilityType::Hospital,
                FacilityType::Laboratory,
            ]),
            'description' => fake()->optional()->paragraph(),
            'city' => fake()->randomElement(['Скопје', 'Битола', 'Охрид', 'Прилеп']),
            'address' => fake()->optional()->streetAddress(),
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
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

    public function pharmacy(): static
    {
        return $this->state(fn () => [
            'type' => FacilityType::Pharmacy,
        ]);
    }
}
