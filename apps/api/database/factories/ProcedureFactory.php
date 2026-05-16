<?php

namespace Database\Factories;

use App\Models\Procedure;
use App\Support\Slug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Procedure>
 */
class ProcedureFactory extends Factory
{
    protected $model = Procedure::class;

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
