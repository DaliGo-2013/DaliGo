<?php

namespace Tests\Feature\Admin;

use App\Models\OrdenServicio;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regla del dueño (07-08-2026): NO existe un estado de espera de repuesto.
 *
 * El taller no es bodega de acopio y un repuesto importado puede tardar hasta un
 * año, así que la máquina no se queda esperando: el técnico define en el momento,
 * contra el stock que hay, si se puede arreglar o no — y si no se puede, se le
 * dice al cliente. Estos candados cubren las dos mitades: que el estado ya no se
 * ofrezca ni se acepte, y que las órdenes que lo tenían no queden colgando (el
 * daño real de sacar un valor de una columna `string` sin migrar los datos).
 */
class EstadoEsperandoRepuestoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        return tap(User::factory()->create())->assignRole('admin');
    }

    private function migracion(): object
    {
        return require database_path('migrations/2026_08_07_120000_quita_estado_esperando_repuesto_de_ordenes_servicio.php');
    }

    /** Orden ya guardada en el estado que se elimina (como las que hay en producción). */
    private function ordenEsperando(): OrdenServicio
    {
        $orden = OrdenServicio::factory()->create(['facturacion' => 'reparacion']);
        // Sin pasar por el modelo: el estado ya no es asignable desde el código.
        OrdenServicio::withoutEvents(fn () => OrdenServicio::where('id', $orden->id)
            ->update(['estado' => 'esperando_repuesto']));

        return $orden->fresh();
    }

    // --- El estado ya no existe ---

    public function test_no_esta_en_la_lista_de_estados(): void
    {
        $this->assertNotContains('esperando_repuesto', OrdenServicio::ESTADOS);
        // Tampoco en el contador de activas ni en el mapa de colores del badge.
        $this->assertNotContains('esperando_repuesto', OrdenServicio::ESTADOS_PENDIENTES_TECNICO);
        $this->assertArrayNotHasKey('esperando_repuesto', OrdenServicio::ESTADO_VARIANTES);
    }

    public function test_el_parte_del_tecnico_no_ofrece_la_etapa(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.reparacion', OrdenServicio::factory()->create()))
            ->assertOk()
            ->assertDontSee('Esperando Repuesto')
            ->assertDontSee('esperando_repuesto')
            // Las etapas que sí siguen (para que el test no pase por no cargar nada).
            ->assertSee('En Revision')
            ->assertSee('Cotizacion');
    }

    public function test_el_servidor_rechaza_guardar_ese_estado(): void
    {
        // Que no esté en el <select> no alcanza: el formulario se puede manipular.
        $orden = OrdenServicio::factory()->create(['estado' => 'en_revision']);

        $this->actingAs($this->admin())
            ->put(route('admin.servicio-tecnico.reparacion.guardar', $orden), [
                'estado' => 'esperando_repuesto',
                'trabajo_realizado' => 'Cambio de caldera — funciona normal',
            ])
            ->assertSessionHasErrors('estado');

        $this->assertSame('en_revision', $orden->fresh()->estado);
    }

    // --- Las órdenes que ya estaban en ese estado ---

    public function test_la_migracion_las_deja_en_revision_y_es_idempotente(): void
    {
        $orden = $this->ordenEsperando();
        $this->assertSame('esperando_repuesto', $orden->estado);   // punto de partida

        $this->migracion()->up();
        $this->assertSame('en_revision', $orden->fresh()->estado);

        // 2ª corrida: ya no matchea nada, así que no vuelve a mover ni a auditar.
        $auditsAntes = DB::table('audits')->count();
        $this->migracion()->up();
        $this->assertSame('en_revision', $orden->fresh()->estado);
        $this->assertSame($auditsAntes, DB::table('audits')->count());
    }

    /**
     * El motivo por el que la migración es obligatoria y no cosmética: mientras la
     * orden siga en el estado viejo NO cuenta como activa, o sea una máquina real
     * en el taller se vuelve invisible en el contador de la barra.
     */
    public function test_una_orden_en_el_estado_viejo_esta_invisible_hasta_migrarla(): void
    {
        $orden = $this->ordenEsperando();

        $this->assertSame(0, OrdenServicio::pendientesTecnico()->where('id', $orden->id)->count());

        $this->migracion()->up();

        $this->assertSame(1, OrdenServicio::pendientesTecnico()->where('id', $orden->id)->count());
    }

    /**
     * La migración mueve con Eloquent a propósito (no con DB::table) para que quede
     * el rastro por orden: si alguna hay que devolverla a mano, el old/new está en
     * la pantalla de auditoría. Cambiarla a una query cruda rompe este candado.
     */
    public function test_la_migracion_deja_rastro_en_la_auditoria(): void
    {
        $orden = $this->ordenEsperando();

        $this->migracion()->up();

        $this->assertDatabaseHas('audits', [
            'auditable_type' => OrdenServicio::class,
            'auditable_id' => $orden->id,
            'event' => 'updated',
        ]);
    }
}
