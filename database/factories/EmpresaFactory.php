<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Empresa>
 */
class EmpresaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'razon_social'       => fake()->company(),
            'nit'                => fake()->unique()->numerify('##########-#'),
            'representante_legal' => fake()->name(),
            'tipo_societario'    => 'S.A.S.',
            'direccion'          => fake()->address(),
            'telefono'           => fake()->phoneNumber(),
            'email_contacto'     => fake()->companyEmail(),
            'ciudad'             => fake()->city(),
            'departamento'       => fake()->state(),
            'dias_laborales'     => 'lunes_viernes',
            'active'             => true,
        ];
    }
}
