<?php

namespace Database\Factories;

use App\Models\ListaPrecio;
use App\Models\Precio;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Precio>
 */
class PrecioFactory extends Factory
{
    protected $model = Precio::class;

    public function definition(): array
    {
        $bruto = fake()->numberBetween(500, 500000);

        return [
            // Por defecto en la lista OFICIAL de ventas (config
            // daligo.lista_precios_ventas): es el caso normal, «un producto con
            // precio vendible». Producto::precioVentaConIva() lee SOLO esa lista,
            // así que un precio en otra no cuenta como precio de venta. Para ese
            // caso está el estado enOtraLista().
            'lista_precio_id' => fn () => ListaPrecio::firstOrCreate(
                ['nombre' => config('daligo.lista_precios_ventas') ?: 'GENERAL'],
                [
                    'descripcion' => null,
                    'bsale_coin_id' => ListaPrecio::COIN_CLP,
                    'activa' => true,
                    'canal' => null,
                    'bsale_price_list_id' => fake()->unique()->numberBetween(1, 999999),
                ],
            )->id,
            'producto_id' => Producto::factory(),
            'precio_neto' => round($bruto / 1.19, 4),
            'precio_con_iva' => $bruto,
            'bsale_detail_id' => fake()->unique()->numberBetween(1, 999999),
        ];
    }

    /**
     * Precio en una lista que NO es la oficial de ventas (Coquimbo, exterior…).
     * No se ve como precio de venta: es el escenario que la regla de la lista
     * oficial existe para evitar.
     */
    public function enOtraLista(): static
    {
        return $this->state(['lista_precio_id' => ListaPrecio::factory()]);
    }
}
