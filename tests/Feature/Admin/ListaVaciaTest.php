<?php

namespace Tests\Feature\Admin;

use App\Models\OrdenServicio;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un listado vacío tiene que decir POR QUÉ está vacío (pedido del dueño, 31-jul-2026).
 *
 * El caso que lo motivó: las tarjetas del Inicio enlazan a listados ya filtrados
 * («Reparadas 0» → `?estado=reparado`). Con el mensaje fijo anterior, hacer clic en
 * una tarjeta en 0 afirmaba «No hay órdenes registradas» aunque hubiera 2.732 órdenes
 * en el sistema y ninguna en ese estado. El mensaje mentía y tapaba el motivo real.
 *
 * Los dos casos no se responden igual y estos candados fijan la diferencia:
 *   · sin filtros  → «no hay nada todavía» + cómo cargar el primero
 *   · con filtros  → «nada coincide» + ofrecer quitar los filtros
 */
class ListaVaciaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        return tap(User::factory()->create())->assignRole('admin');
    }

    public function test_sin_datos_y_sin_filtros_dice_como_cargar_el_primero(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.index'))
            ->assertOk()
            ->assertSee('No hay órdenes registradas', false)
            ->assertDontSee('Quitar los filtros');
    }

    /**
     * El candado que importa: HAY datos, el filtro no matchea, y el mensaje NO puede
     * afirmar que el sistema está vacío.
     */
    public function test_con_datos_pero_filtro_sin_coincidencias_no_dice_que_no_hay_nada(): void
    {
        $admin = $this->admin();
        OrdenServicio::factory()->count(3)->create(['estado' => 'recibido']);

        $respuesta = $this->actingAs($admin)
            ->get(route('admin.servicio-tecnico.index', ['estado' => 'entregado']))
            ->assertOk();

        // Lo prohibido: negar que existan órdenes cuando existen.
        $respuesta->assertDontSee('No hay órdenes registradas', false);
        // Lo esperado: explicar que no hay coincidencias y ofrecer la salida.
        $respuesta->assertSee('Ninguna orden coincide', false);
        $respuesta->assertSee('Quitar los filtros');
    }

    /**
     * Un filtro cuyo valor legítimo es «0» sigue siendo un filtro activo. Se prueba
     * sobre productos (`activo=0` = ver los inactivos): con `array_filter()` este
     * valor se descartaba y el mensaje volvía a mentir, esta vez al revés.
     */
    public function test_un_filtro_con_valor_cero_cuenta_como_filtro_activo(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.productos.index', ['activo' => '0']))
            ->assertOk()
            ->assertSee('Ningún producto coincide', false)
            ->assertDontSee('No hay productos. Crea uno o importa un CSV.', false);
    }

    /**
     * En Facturación el texto explicativo («acá van a aparecer los documentos») es
     * deliberado y se conserva sin filtros, pero con filtros seria engañoso: ya hay
     * documentos emitidos, solo que ninguno coincide.
     */
    public function test_facturacion_conserva_su_texto_explicativo_solo_sin_filtros(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.dte.index'))
            ->assertOk()
            ->assertSee('Acá van a aparecer los documentos emitidos', false);

        $this->actingAs($admin)
            ->get(route('admin.dte.index', ['estado_sii' => 'rechazado']))
            ->assertOk()
            ->assertDontSee('Acá van a aparecer los documentos emitidos', false)
            ->assertSee('Ningún documento emitido coincide', false);
    }
}
