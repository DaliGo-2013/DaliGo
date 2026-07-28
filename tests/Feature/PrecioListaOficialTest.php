<?php

namespace Tests\Feature;

use App\Models\ListaPrecio;
use App\Models\Precio;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Candados de la lista de precios OFICIAL de ventas (decisión del dueño,
 * 2026-07-28: GENERAL).
 *
 * El criterio anterior era «la primera lista activa que aparezca», copiado en 4
 * lugares. Con 5 listas activas espejadas de Bsale eso devolvía una lista
 * arbitraria: una reparación de Mirador podía cotizarse con precios de Coquimbo
 * sin que nada lo delatara en pantalla. Estos tests fijan el criterio nuevo.
 */
class PrecioListaOficialTest extends TestCase
{
    use RefreshDatabase;

    private function lista(string $nombre, bool $activa = true): ListaPrecio
    {
        return ListaPrecio::factory()->create(['nombre' => $nombre, 'activa' => $activa]);
    }

    public function test_toma_el_precio_de_la_lista_oficial_aunque_haya_otras_activas(): void
    {
        $producto = Producto::factory()->create();

        // La de Coquimbo se crea PRIMERO a propósito: con el criterio viejo
        // («la primera activa») ganaba esta y el test fallaría.
        Precio::factory()->create([
            'producto_id' => $producto->id,
            'lista_precio_id' => $this->lista('COQUIMBO-1')->id,
            'precio_con_iva' => 9990,
        ]);
        Precio::factory()->create([
            'producto_id' => $producto->id,
            'lista_precio_id' => $this->lista('GENERAL')->id,
            'precio_con_iva' => 12990,
        ]);

        $this->assertSame(12990, $producto->precioVentaConIva());
    }

    public function test_devuelve_null_si_el_producto_no_esta_en_la_lista_oficial(): void
    {
        $producto = Producto::factory()->create();

        // Existe la lista oficial, pero este producto solo tiene precio en otra.
        $this->lista('GENERAL');
        Precio::factory()->create([
            'producto_id' => $producto->id,
            'lista_precio_id' => $this->lista('EXTERIOR 1')->id,
            'precio_con_iva' => 7500,
        ]);

        // Null y no 7500: mejor sin precio que con uno de origen incierto.
        $this->assertNull($producto->precioVentaConIva());
    }

    public function test_el_estado_en_otra_lista_de_la_factory_no_cuenta_como_precio_de_venta(): void
    {
        $producto = Producto::factory()->create();
        Precio::factory()->enOtraLista()->create([
            'producto_id' => $producto->id,
            'precio_con_iva' => 4500,
        ]);

        $this->assertNull($producto->precioVentaConIva());
    }

    public function test_compara_el_nombre_sin_importar_mayusculas_ni_espacios(): void
    {
        $producto = Producto::factory()->create();
        Precio::factory()->create([
            'producto_id' => $producto->id,
            'lista_precio_id' => $this->lista(' general ')->id,
            'precio_con_iva' => 3990,
        ]);

        $this->assertSame(3990, $producto->precioVentaConIva());
    }

    public function test_sin_lista_oficial_configurada_vuelve_al_criterio_antiguo(): void
    {
        // Escape hatch para entornos de prueba sin catálogo espejado.
        config(['daligo.lista_precios_ventas' => null]);

        $producto = Producto::factory()->create();
        Precio::factory()->create([
            'producto_id' => $producto->id,
            'lista_precio_id' => $this->lista('CSTS')->id,
            'precio_con_iva' => 6600,
        ]);

        $this->assertSame(6600, $producto->precioVentaConIva());
    }

    public function test_una_lista_inactiva_igual_sirve_si_es_la_oficial(): void
    {
        // La regla es el NOMBRE, no el flag `activa`: si alguien desactiva GENERAL
        // en Bsale por error, el precio sigue siendo el de la lista que el dueño
        // eligió — no se salta en silencio a otra.
        $producto = Producto::factory()->create();
        Precio::factory()->create([
            'producto_id' => $producto->id,
            'lista_precio_id' => $this->lista('GENERAL', activa: false)->id,
            'precio_con_iva' => 5500,
        ]);

        $this->assertSame(5500, $producto->precioVentaConIva());
    }

    public function test_producto_sin_ningun_precio_devuelve_null(): void
    {
        $this->assertNull(Producto::factory()->create()->precioVentaConIva());
    }

    public function test_redondea_a_peso_entero(): void
    {
        $producto = Producto::factory()->create();
        Precio::factory()->create([
            'producto_id' => $producto->id,
            'lista_precio_id' => $this->lista('GENERAL')->id,
            'precio_con_iva' => 1234.6,
        ]);

        $this->assertSame(1235, $producto->precioVentaConIva());
    }
}
