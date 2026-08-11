<?php

namespace Tests\Feature\Produccion;

use App\Http\Controllers\Admin\AuditController;
use App\Models\ProduccionMejora;
use App\Models\ProduccionNota;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P-M11-23 (kaizen digital): el soplador propone mejoras desde mi-reporte,
 * el jefe decide (revisada|aplicada|descartada) con respuesta opcional desde
 * el panel, y el soplador ve SU historial con el estado. La propuesta viaja
 * por la misma cola offline que tandas/paradas (cliente_uuid idempotente).
 */
class ProduccionMejorasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function jefe(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_bodega');
    }

    private function soplador(): User
    {
        return tap(User::factory()->create())->assignRole('soplador');
    }

    private function mejora(User $soplador, array $attrs = []): ProduccionMejora
    {
        return ProduccionMejora::create(array_merge([
            'soplador_id' => $soplador->id,
            'texto' => 'Mover el rack de preformas mas cerca de la M2',
        ], $attrs));
    }

    // ---------------------------------------------------------------
    // Proponer (soplador)
    // ---------------------------------------------------------------

    public function test_el_soplador_propone_y_nace_pendiente(): void
    {
        $soplador = $this->soplador();

        $this->actingAs($soplador)
            ->post(route('produccion.mi.mejoras.store'), ['texto' => 'Marcar el piso frente a la M1'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('produccion_mejoras', [
            'soplador_id' => $soplador->id,
            'texto' => 'Marcar el piso frente a la M1',
            'estado' => ProduccionMejora::PENDIENTE,
            'respuesta' => null,
        ]);
    }

    public function test_texto_obligatorio_y_tope_191(): void
    {
        $soplador = $this->soplador();

        $this->actingAs($soplador)
            ->from(route('produccion.mi.index'))
            ->post(route('produccion.mi.mejoras.store'), ['texto' => ''])
            ->assertSessionHasErrors('texto');

        $this->actingAs($soplador)
            ->from(route('produccion.mi.index'))
            ->post(route('produccion.mi.mejoras.store'), ['texto' => str_repeat('a', 192)])
            ->assertSessionHasErrors('texto');

        $this->assertDatabaseCount('produccion_mejoras', 0);
    }

    public function test_el_soplador_id_del_payload_se_ignora(): void
    {
        $soplador = $this->soplador();
        $otro = $this->soplador();

        // El autor SIEMPRE es quien esta autenticado; un payload manipulado
        // no puede proponer a nombre de otro (candado 2 del dictado).
        $this->actingAs($soplador)->post(route('produccion.mi.mejoras.store'), [
            'texto' => 'Propuesta con payload manipulado',
            'soplador_id' => $otro->id,
        ]);

        $this->assertDatabaseHas('produccion_mejoras', [
            'texto' => 'Propuesta con payload manipulado',
            'soplador_id' => $soplador->id,
        ]);
    }

    public function test_el_jefe_no_propone(): void
    {
        // La ruta exige 'report production' (el jefe tiene 'manage production').
        $this->actingAs($this->jefe())
            ->post(route('produccion.mi.mejoras.store'), ['texto' => 'X'])
            ->assertForbidden();
    }

    // ---------------------------------------------------------------
    // Idempotencia de la cola offline
    // ---------------------------------------------------------------

    public function test_drenar_tres_veces_el_mismo_uuid_crea_una_sola_fila(): void
    {
        $soplador = $this->soplador();
        $uuid = '11111111-2222-3333-4444-555555555555';

        // El drenado manda Accept: application/json con el cliente_uuid.
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($soplador)
                ->postJson(route('produccion.mi.mejoras.store'), [
                    'texto' => 'Propuesta encolada sin señal',
                    'cliente_uuid' => $uuid,
                ])
                ->assertOk()
                ->assertJson(['ok' => true]);
        }

        $this->assertDatabaseCount('produccion_mejoras', 1);
    }

    public function test_uuid_invalido_es_422_para_el_drenado(): void
    {
        // 422 = rechazo PERMANENTE para la cola (no reintenta para siempre).
        $this->actingAs($this->soplador())
            ->postJson(route('produccion.mi.mejoras.store'), [
                'texto' => 'X',
                'cliente_uuid' => 'no-es-un-uuid',
            ])
            ->assertUnprocessable();
    }

    // ---------------------------------------------------------------
    // Decidir (jefe)
    // ---------------------------------------------------------------

    public function test_el_jefe_decide_con_respuesta(): void
    {
        $mejora = $this->mejora($this->soplador());

        $this->actingAs($this->jefe())
            ->patch(route('admin.produccion.mejoras.update', $mejora), [
                'estado' => ProduccionMejora::APLICADA,
                'respuesta' => 'Buena idea: se movio el rack esta semana.',
            ])
            ->assertRedirect(route('admin.produccion.index').'#mejoras')
            ->assertSessionHas('status');

        $this->assertDatabaseHas('produccion_mejoras', [
            'id' => $mejora->id,
            'estado' => ProduccionMejora::APLICADA,
            'respuesta' => 'Buena idea: se movio el rack esta semana.',
        ]);
    }

    public function test_los_tres_destinos_del_jefe_son_validos(): void
    {
        foreach (ProduccionMejora::DECISIONES as $destino) {
            $mejora = $this->mejora($this->soplador());

            $this->actingAs($this->jefe())
                ->patch(route('admin.produccion.mejoras.update', $mejora), ['estado' => $destino])
                ->assertSessionHasNoErrors();

            $this->assertSame($destino, $mejora->fresh()->estado);
        }
    }

    public function test_pendiente_no_es_un_destino(): void
    {
        // Pendiente es el estado de NACIMIENTO: el jefe no puede "des-decidir"
        // hacia el (si quiere reabrir, marca revisada).
        $mejora = $this->mejora($this->soplador(), ['estado' => ProduccionMejora::REVISADA]);

        $this->actingAs($this->jefe())
            ->from(route('admin.produccion.index'))
            ->patch(route('admin.produccion.mejoras.update', $mejora), ['estado' => ProduccionMejora::PENDIENTE])
            ->assertSessionHasErrors('estado');

        $this->assertSame(ProduccionMejora::REVISADA, $mejora->fresh()->estado);
    }

    public function test_la_respuesta_tiene_tope_191(): void
    {
        $mejora = $this->mejora($this->soplador());

        $this->actingAs($this->jefe())
            ->from(route('admin.produccion.index'))
            ->patch(route('admin.produccion.mejoras.update', $mejora), [
                'estado' => ProduccionMejora::REVISADA,
                'respuesta' => str_repeat('r', 192),
            ])
            ->assertSessionHasErrors('respuesta');
    }

    public function test_el_soplador_no_decide(): void
    {
        $soplador = $this->soplador();
        $mejora = $this->mejora($soplador);

        // Ni siquiera sobre su propia propuesta: decidir es del jefe.
        $this->actingAs($soplador)
            ->patch(route('admin.produccion.mejoras.update', $mejora), ['estado' => ProduccionMejora::APLICADA])
            ->assertForbidden();
    }

    // ---------------------------------------------------------------
    // Auditoria
    // ---------------------------------------------------------------

    public function test_mejoras_y_notas_estan_en_el_visor_de_auditoria(): void
    {
        // Auditable sin estar en MODELOS = traza invisible en el visor (el
        // miss exacto que P-M11-22 cometio con ProduccionNota; se arregla aqui).
        $this->assertArrayHasKey(ProduccionMejora::class, AuditController::MODELOS);
        $this->assertArrayHasKey(ProduccionNota::class, AuditController::MODELOS);
    }
}
