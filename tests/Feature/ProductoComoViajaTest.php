<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\TipoBulto;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «CÓMO VIAJA» EN LA FICHA DEL PRODUCTO: el enlace producto → tipo de bulto que le faltaba al
 * simulador para traer una factura (jefe de logística, 10-09-2026; forma por defecto por
 * producto, decisión del dueño el mismo día).
 *
 * El candado que importa es el segundo: el campo se valida contra el MISMO scope que ofrece
 * el <select> (solo bultos activos). Con un `exists` a secas, un bulto desactivado entraría
 * por la URL y armaría planes de carga con algo que nadie puede elegir en pantalla — la
 * misma regla que el `preforma_id` del kardex (bitácora [2026-06-30]).
 */
class ProductoComoViajaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private TipoBulto $bolsa;

    private TipoBulto $inactivo;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = tap(User::factory()->create())->assignRole('admin');

        $this->bolsa = TipoBulto::create([
            'nombre' => 'Bolsa 5× botellón 20 L (vacío)', 'categoria' => 'botellones',
            'largo_cm' => 130, 'ancho_cm' => 26, 'alto_cm' => 51, 'peso_kg' => 5,
            'unidades' => 5, 'apilable_max' => 6, 'soporta_peso_encima' => true, 'activo' => true,
        ]);
        $this->inactivo = TipoBulto::create([
            'nombre' => 'Caja vieja descontinuada', 'categoria' => 'cajas',
            'largo_cm' => 40, 'ancho_cm' => 30, 'alto_cm' => 30, 'peso_kg' => 4,
            'unidades' => 1, 'apilable_max' => 4, 'soporta_peso_encima' => true, 'activo' => false,
        ]);
        $this->producto = Producto::create(['sku' => 'BOT20', 'nombre' => 'Botellón 20 L', 'activo' => true]);
    }

    private function guardar(array $extra): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->put(route('admin.productos.update', $this->producto), array_merge([
            'sku' => 'BOT20',
            'nombre' => 'Botellón 20 L',
        ], $extra));
    }

    public function test_la_ficha_guarda_como_viaja(): void
    {
        $this->guardar(['tipo_bulto_id' => $this->bolsa->id])->assertSessionHasNoErrors();

        $this->assertSame($this->bolsa->id, $this->producto->fresh()->tipo_bulto_id);
        $this->assertSame($this->bolsa->nombre, $this->producto->fresh()->tipoBulto->nombre);
    }

    /** El <select> manda '' cuando no se elige nada: eso es «sin declarar», no un error. */
    public function test_sin_declarar_es_valido_y_queda_en_null(): void
    {
        $this->producto->update(['tipo_bulto_id' => $this->bolsa->id]);

        $this->guardar(['tipo_bulto_id' => ''])->assertSessionHasNoErrors();

        $this->assertNull($this->producto->fresh()->tipo_bulto_id);
    }

    /** Un bulto DESACTIVADO no se acepta aunque exista: el mismo scope que ofrece el selector. */
    public function test_rechaza_un_bulto_desactivado(): void
    {
        $this->guardar(['tipo_bulto_id' => $this->inactivo->id])->assertSessionHasErrors('tipo_bulto_id');

        $this->assertNull($this->producto->fresh()->tipo_bulto_id);
    }

    public function test_el_formulario_ofrece_solo_los_bultos_activos(): void
    {
        $res = $this->actingAs($this->admin)->get(route('admin.productos.edit', $this->producto))->assertOk();

        $res->assertSee('Cómo viaja');
        $res->assertSee($this->bolsa->nombre);
        $res->assertDontSee($this->inactivo->nombre);
    }
}
