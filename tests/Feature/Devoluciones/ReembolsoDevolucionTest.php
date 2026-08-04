<?php

namespace Tests\Feature\Devoluciones;

use App\Models\Aprobacion;
use App\Models\Devolucion;
use App\Models\DevolucionItem;
use App\Models\User;
use App\Services\Aprobaciones\Aprobaciones;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\ReglasAprobacionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P-M13-03 · el reembolso pasa SIEMPRE por el motor M14: bajo el umbral
 * (umbral_aprobacion_clp = 1.000.000) el handler aplica INLINE con registro;
 * sobre él queda pendiente y la devolución NO cambia hasta que jefatura de
 * ventas (o admin) la apruebe. El payload stale se rechaza solo (conflicto).
 */
class ReembolsoDevolucionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
        $this->seed(ReglasAprobacionSeeder::class);
    }

    private function jefeBodega(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_bodega');
    }

    private function jefeVentas(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_ventas');
    }

    private function evaluada(): Devolucion
    {
        $devolucion = Devolucion::factory()->evaluada()->create();
        $devolucion->items()->create([
            'descripcion' => 'Dispensador', 'cantidad' => 1,
            'estado_producto' => DevolucionItem::DANADO,
        ]);

        return $devolucion;
    }

    public function test_bajo_el_umbral_se_auto_aprueba_y_aplica_inline(): void
    {
        $devolucion = $this->evaluada();

        $this->actingAs($this->jefeBodega())->post(route('admin.devoluciones.resolver', $devolucion->id), [
            'salida' => 'reembolso',
            'monto_reembolso' => 45000, // < 1.000.000
            'resolucion_motivo' => 'Producto irreparable, se devuelve el pago.',
        ])->assertSessionHasNoErrors();

        $devolucion->refresh();
        $this->assertSame(Devolucion::REEMBOLSADA, $devolucion->estado);
        $this->assertSame(45000, (int) $devolucion->monto_reembolso);

        // Con REGISTRO: la aprobación existe, auto-aprobada.
        $aprobacion = Aprobacion::firstOrFail();
        $this->assertSame(Aprobacion::ESTADO_AUTO_APROBADA, $aprobacion->estado);
        $this->assertSame(Aprobacion::ACCION_DEVOLUCION_REEMBOLSO, $aprobacion->tipo_accion);

        // Y el cliente avisado del resultado (lo dispara el handler).
        $this->assertDatabaseHas('notificaciones', [
            'evento' => 'devolucion.resuelta',
            'destinatario' => $devolucion->cliente_email,
        ]);
    }

    public function test_sobre_el_umbral_queda_pendiente_y_la_devolucion_no_cambia(): void
    {
        $devolucion = $this->evaluada();

        $this->actingAs($this->jefeBodega())->post(route('admin.devoluciones.resolver', $devolucion->id), [
            'salida' => 'reembolso',
            'monto_reembolso' => 2500000, // > 1.000.000
        ])->assertSessionHasNoErrors();

        // La devolución NO cambia hasta que la aprueben.
        $devolucion->refresh();
        $this->assertSame(Devolucion::EVALUADA, $devolucion->estado);
        $this->assertNull($devolucion->monto_reembolso);

        $aprobacion = Aprobacion::firstOrFail();
        $this->assertSame(Aprobacion::ESTADO_PENDIENTE, $aprobacion->estado);
        $this->assertSame('jefe_ventas', $aprobacion->rol_aprobador);

        // Y el cliente NO fue avisado todavía (nada se resolvió).
        $this->assertDatabaseMissing('notificaciones', ['evento' => 'devolucion.resuelta']);
    }

    public function test_al_aprobarse_el_pendiente_se_aplica_y_el_cliente_se_entera(): void
    {
        $devolucion = $this->evaluada();
        $this->actingAs($this->jefeBodega())->post(route('admin.devoluciones.resolver', $devolucion->id), [
            'salida' => 'reembolso',
            'monto_reembolso' => 2500000,
        ]);
        $aprobacion = Aprobacion::firstOrFail();

        app(Aprobaciones::class)->aprobar($aprobacion, $this->jefeVentas());

        $devolucion->refresh();
        $this->assertSame(Devolucion::REEMBOLSADA, $devolucion->estado);
        $this->assertSame(2500000, (int) $devolucion->monto_reembolso);
        $this->assertSame(Aprobacion::ESTADO_APROBADA, $aprobacion->fresh()->estado);
        $this->assertDatabaseHas('notificaciones', [
            'evento' => 'devolucion.resuelta',
            'destinatario' => $devolucion->cliente_email,
        ]);
    }

    public function test_payload_stale_se_rechaza_como_conflicto(): void
    {
        $devolucion = $this->evaluada();
        $this->actingAs($this->jefeBodega())->post(route('admin.devoluciones.resolver', $devolucion->id), [
            'salida' => 'reembolso',
            'monto_reembolso' => 2500000,
        ]);
        $aprobacion = Aprobacion::firstOrFail();

        // La devolución cambia DESPUÉS de la solicitud (otro camino la rechazó).
        $devolucion->refresh()->update([
            'estado' => Devolucion::RECHAZADA,
            'resolucion_motivo' => 'Resuelta por otro lado',
            'resuelta_at' => now(),
        ]);

        app(Aprobaciones::class)->aprobar($aprobacion, $this->jefeVentas());

        // Conflicto → rechazo automático con motivo legible; el payload viejo
        // JAMÁS pisa la resolución que ya existía.
        $fresh = $aprobacion->fresh();
        $this->assertSame(Aprobacion::ESTADO_RECHAZADA, $fresh->estado);
        $this->assertStringContainsString('Conflicto', $fresh->resultado_motivo);
        $this->assertSame(Devolucion::RECHAZADA, $devolucion->fresh()->estado);
    }

    public function test_la_bandeja_muestra_el_tipo_nuevo_con_su_diff(): void
    {
        $devolucion = $this->evaluada();
        $this->actingAs($this->jefeBodega())->post(route('admin.devoluciones.resolver', $devolucion->id), [
            'salida' => 'reembolso',
            'monto_reembolso' => 2500000,
        ]);

        // La bandeja del aprobador: etiqueta del tipo, el objeto descrito
        // (folio + cliente) y el diff del monto — sin la rama por tipo, esto
        // degradaba MUDO (gotcha PLAN-M13 §3).
        $this->actingAs($this->jefeVentas())->get(route('aprobaciones.index'))
            ->assertOk()
            ->assertSee('Reembolso de devolución')
            ->assertSee($devolucion->folio)
            ->assertSee('Reembolso $');
    }
}
