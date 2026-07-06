<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bufete>
 */
class BufeteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre'          => fake()->company() . ' Abogados',
            'nit'             => fake()->unique()->numerify('##########-#'),
            'representante'   => fake()->name(),
            'email_contacto'  => fake()->companyEmail(),
            'telefono'        => fake()->numerify('##########'),
            'active'          => true,
        ];
    }
}
