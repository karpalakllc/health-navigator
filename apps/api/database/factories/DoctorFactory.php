<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Support\Slug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doctor>
 */
class DoctorFactory extends Factory
{
    protected $model = Doctor::class;

    public function definition(): array
    {
        $fullName = 'д-р '.fake()->name();

        return [
            'slug' => Slug::fromName($fullName),
            'full_name' => $fullName,
            'title' => 'д-р',
            'bio' => fake()->optional()->paragraph(),
            'city' => fake()->randomElement(['Скопје', 'Битола', 'Охрид', 'Прилеп']),
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'is_published' => true,
            'published_at' => now(),
            'accepts_new_patients' => true,
            'is_featured' => false,
            'is_sponsored' => false,
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
