<?php

namespace Database\Factories;

use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Clinic>
 */
class ClinicFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Veterinaria '.fake()->unique()->lastName();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'email' => fake()->unique()->companyEmail(),
            'phone' => '+57'.fake()->numerify('3#########'),
            'timezone' => 'America/Bogota',
            'currency' => 'COP',
            'status' => Clinic::STATUS_ACTIVE,
            'settings' => [],
        ];
    }

    public function suspended(): static
    {
        return $this->state(['status' => Clinic::STATUS_SUSPENDED]);
    }
}
