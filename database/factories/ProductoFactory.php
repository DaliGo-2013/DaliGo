<?php

namespace Database\Factories;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Producto>
 */
class ProductoFactory extends Factory
{
    protected $model = Producto::class;

    public function definition(): array
    {
        return [
            'sku' => strtoupper(fake()->unique()->bothify('SKU-#####')),
            'nombre' => ucfirst(fake()->words(3, true)),
            'descripcion' => fake()->optional()->sentence(),
            'categoria' => fake()->randomElement(['Botellones', 'Dispensadores', 'Accesorios']),
            'marca' => fake()->randomElement(['DALI', 'Genérica']),
            'peso_kg' => fake()->randomFloat(3, 0.1, 25),
            'alto_cm' => fake()->randomFloat(2, 1, 100),
            'ancho_cm' => fake()->randomFloat(2, 1, 100),
            'largo_cm' => fake()->randomFloat(2, 1, 100),
            'atributos' => null,
            'activo' => true,
            'bsale_variant_id' => null,
            'bsale_product_id' => null,
        ];
    }
}
