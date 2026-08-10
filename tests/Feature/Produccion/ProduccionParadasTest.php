<?php

namespace Tests\Feature\Produccion;

use App\Models\Maquina;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionParada;
use App\Models\ProduccionRegistro;
use App\Models\ProduccionReporte;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\FechaNegocio;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P-M11-20: paradas con duracion en la PWA del soplador. Candados del dictado
 * v19: (1) la cola offline drena 2x sin duplicar; (2) fin < inicio es rojo
 * [objetivo del MUTADO del gate: quitar after_or_equal debe romper
 * test_parada_con_fin_antes_del_inicio_es_rechazada]; (3) una parada sin fin
 * no bloquea el envio y queda cerrada-al-envio con marca; (5) 403/ownership;
 * (6) scrap de arranque y cavidades activas persisten.
 */
class ProduccionParadasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function soplador(?Sucursal $sucursal = null): User
    {
        return tap(User::factory()->create(['sucursal_id' => $sucursal?->id]))->assignRole('soplador');
    }

    private function jefe(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_bodega');
    }

    private function sucursal(string $codigo = 'MIRADOR'): Sucursal
    {
        return Sucursal::firstOrCreate(['codigo' => $codigo], ['nombre' => ucfirst(strtolower($codigo))]);
    }

    private function maquina(string $nombre = 'Sopladora 1', bool $activa = true): Maquina
    {
        return Maquina::create([
            'nombre' => $nombre,
            'sucursal_id' => $this->sucursal()->id,
            'activa' => $activa,
        ]);
    }

    private function reporteDe(User $soplador, int $asignadas = 100, string $estado = ProduccionReporte::BORRADOR): ProduccionReporte
    {
        $fecha = now()->toDateString();

        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id,
            'fecha' => $fecha,
            'turno' => 'dia',
            'asignadas' => $asignadas,
        ]);

        return ProduccionReporte::create([
            'asignacion_id' => $asignacion->id,
            'soplador_id' => $soplador->id,
            'fecha' => $fecha,
            'turno' => 'dia',
            'asignadas' => $asignadas,
            'estado' => $estado,
        ]);
    }

    /** Payload valido de parada; se sobreescribe por test. */
    private function payload(Maquina $maquina, array $overrides = []): array
    {
        return array_merge([
            'parada_maquina_id' => $maquina->id,
            'parada_motivo' => 'Falla de máquina',
            'parada_origen' => 'maquina',
            'parada_inicio' => '10:00',
            'parada_fin' => '10:45',
        ], $overrides);
    }

    // --- Camino nativo y cola offline (candado 1) ---

    public function test_soplador_registra_parada_nativa_y_redirige(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador);
        $maquina = $this->maquina();

        $this->actingAs($soplador)
            ->post(route('produccion.mi.paradas.store', $reporte), $this->payload($maquina))
            ->assertRedirect(route('produccion.mi.show', $reporte));

        $this->assertDatabaseHas('produccion_paradas', [
            'reporte_id' => $reporte->id,
            'cliente_uuid' => null,
            'maquina_id' => $maquina->id,
            'motivo' => 'Falla de máquina',
            'clase' => ProduccionParada::CLASE_NO_PLANIFICADA,
            'origen' => 'maquina',
            'cerrada_al_envio' => false,
        ]);
        $this->assertSame('10:00', $reporte->paradas()->first()->inicio_corta);
        $this->assertSame('10:45', $reporte->paradas()->first()->fin_corta);
    }

    public function test_parada_offline_reintentada_no_se_duplica(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador);
        $maquina = $this->maquina();
        $uuid = (string) Str::uuid();
        $payload = $this->payload($maquina, ['cliente_uuid' => $uuid]);

        $this->actingAs($soplador)->postJson(route('produccion.mi.paradas.store', $reporte), $payload)->assertOk();
        $this->actingAs($soplador)->postJson(route('produccion.mi.paradas.store', $reporte), $payload)->assertOk();

        $this->assertSame(1, $reporte->paradas()->count());
        $this->assertDatabaseHas('produccion_paradas', [
            'reporte_id' => $reporte->id,
            'cliente_uuid' => $uuid,
        ]);
    }

    public function test_dos_paradas_offline_con_uuid_distinto_se_guardan_ambas(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador);
        $maquina = $this->maquina();

        foreach ([Str::uuid(), Str::uuid()] as $uuid) {
            $this->actingAs($soplador)
                ->postJson(route('produccion.mi.paradas.store', $reporte), $this->payload($maquina, ['cliente_uuid' => (string) $uuid]))
                ->assertOk();
        }

        $this->assertSame(2, $reporte->paradas()->count());
    }

    // --- Validaciones (candado 2: el MUTADO del gate apunta al primero) ---

    public function test_parada_con_fin_antes_del_inicio_es_rechazada(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador);
        $maquina = $this->maquina();

        $this->actingAs($soplador)
            ->postJson(route('produccion.mi.paradas.store', $reporte), $this->payload($maquina, [
                'parada_inicio' => '10:00',
                'parada_fin' => '09:00',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('parada_fin');

        $this->assertSame(0, $reporte->paradas()->count());
    }

    public function test_parada_con_motivo_fuera_de_la_lista_es_rechazada(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador);
        $maquina = $this->maquina();

        // "Preformas defectuosas" existe en MOTIVOS_DIFERENCIA pero NO es una
        // parada (es perdida de calidad): la lista cerrada debe rechazarla.
        $this->actingAs($soplador)
            ->postJson(route('produccion.mi.paradas.store', $reporte), $this->payload($maquina, [
                'parada_motivo' => 'Preformas defectuosas',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('parada_motivo');

        $this->assertSame(0, $reporte->paradas()->count());
    }

    public function test_clase_se_deriva_en_el_servidor_y_el_request_no_la_impone(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador);
        $maquina = $this->maquina();

        // El request intenta imponer 'planificada' sobre una falla: se ignora.
        $this->actingAs($soplador)
            ->post(route('produccion.mi.paradas.store', $reporte), $this->payload($maquina, [
                'parada_motivo' => 'Falla de máquina',
                'clase' => ProduccionParada::CLASE_PLANIFICADA,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('produccion_paradas', [
            'motivo' => 'Falla de máquina',
            'clase' => ProduccionParada::CLASE_NO_PLANIFICADA,
        ]);

        $this->actingAs($soplador)
            ->post(route('produccion.mi.paradas.store', $reporte), $this->payload($maquina, [
                'parada_motivo' => 'Cambio de molde',
                'parada_inicio' => '12:00',
                'parada_fin' => '12:30',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('produccion_paradas', [
            'motivo' => 'Cambio de molde',
            'clase' => ProduccionParada::CLASE_PLANIFICADA,
        ]);
    }

    public function test_parada_acepta_campos_condicionales_vacios(): void
    {
        // Un navegador real manda las claves presentes pero vacias ('' -> null
        // via ConvertEmptyStringsToNull); omitirlas seria un test irreal
        // (leccion de la bitacora 2026-07-06).
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador);
        $maquina = $this->maquina();

        $this->actingAs($soplador)
            ->post(route('produccion.mi.paradas.store', $reporte), $this->payload($maquina, [
                'cliente_uuid' => '',
                'parada_fin' => '',
            ]))
            ->assertRedirect(route('produccion.mi.show', $reporte));

        $parada = $reporte->paradas()->first();
        $this->assertNotNull($parada);
        $this->assertNull($parada->fin);
        $this->assertNull($parada->cliente_uuid);
        $this->assertNull($parada->duracion_minutos);
    }

    // --- Ownership y estado (candado 5) ---

    public function test_soplador_no_registra_paradas_en_reporte_ajeno(): void
    {
        $duenio = $this->soplador();
        $otro = $this->soplador();
        $reporte = $this->reporteDe($duenio);
        $maquina = $this->maquina();

        // 403 crudo ANTES de validar: la cola offline lo clasifica permanente.
        $this->actingAs($otro)
            ->postJson(route('produccion.mi.paradas.store', $reporte), $this->payload($maquina))
            ->assertForbidden();

        $this->assertSame(0, $reporte->paradas()->count());
    }

    public function test_no_se_registran_paradas_en_reporte_enviado(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, estado: ProduccionReporte::ENVIADO);
        $maquina = $this->maquina();

        $this->actingAs($soplador)
            ->postJson(route('produccion.mi.paradas.store', $reporte), $this->payload($maquina))
            ->assertForbidden();

        $this->assertSame(0, $reporte->paradas()->count());
    }

    public function test_eliminar_parada_de_otro_reporte_devuelve_404(): void
    {
        $soplador = $this->soplador();
        $reporteA = $this->reporteDe($soplador);
        $reporteB = $this->reporteDe($soplador);
        $maquina = $this->maquina();

        $paradaDeB = $reporteB->paradas()->create([
            'maquina_id' => $maquina->id,
            'motivo' => 'Corte de luz',
            'clase' => ProduccionParada::claseDe('Corte de luz'),
            'origen' => 'maquina',
            'inicio' => '09:00',
            'fin' => '09:10',
        ]);

        // Hijo de otro padre = 404 (scoped a mano, espejo de registroDestroy).
        $this->actingAs($soplador)
            ->delete(route('produccion.mi.paradas.destroy', [$reporteA, $paradaDeB]))
            ->assertNotFound();

        $this->assertDatabaseHas('produccion_paradas', ['id' => $paradaDeB->id]);
    }

    public function test_soplador_elimina_su_parada(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador);
        $maquina = $this->maquina();

        $parada = $reporte->paradas()->create([
            'maquina_id' => $maquina->id,
            'motivo' => 'Faltaron preformas',
            'clase' => ProduccionParada::claseDe('Faltaron preformas'),
            'origen' => 'operario',
            'inicio' => '11:00',
            'fin' => null,
        ]);

        $this->actingAs($soplador)
            ->delete(route('produccion.mi.paradas.destroy', [$reporte, $parada]))
            ->assertRedirect(route('produccion.mi.show', $reporte));

        $this->assertDatabaseMissing('produccion_paradas', ['id' => $parada->id]);
    }

    // --- Cierre al envio (candado 3) ---

    public function test_envio_cierra_paradas_abiertas_con_marca(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, asignadas: 10);
        $maquina = $this->maquina();

        // El envio exige al menos una tanda.
        ProduccionRegistro::create([
            'reporte_id' => $reporte->id,
            'maquina_id' => $maquina->id,
            'primera' => 10,
            'segunda' => 0,
            'malo' => 0,
            'danada' => 0,
        ]);
        $reporte->recalcularDesdeRegistros();

        $abierta = $reporte->paradas()->create([
            'maquina_id' => $maquina->id,
            'motivo' => 'Falla de máquina',
            'clase' => ProduccionParada::claseDe('Falla de máquina'),
            'origen' => 'maquina',
            'inicio' => '07:30',
            'fin' => null,
        ]);
        $cerrada = $reporte->paradas()->create([
            'maquina_id' => $maquina->id,
            'motivo' => 'Cambio de molde',
            'clase' => ProduccionParada::claseDe('Cambio de molde'),
            'origen' => 'maquina',
            'inicio' => '06:00',
            'fin' => '06:20',
        ]);

        $this->actingAs($soplador)
            ->patch(route('produccion.mi.update', $reporte), ['enviar' => 1])
            ->assertRedirect(route('produccion.mi.index'));

        $this->assertSame(ProduccionReporte::ENVIADO, $reporte->fresh()->estado);

        // La abierta quedo cerrada a la hora chilena del envio, con marca.
        $abierta->refresh();
        $this->assertSame(FechaNegocio::ahora()->format('H:i'), $abierta->fin_corta);
        $this->assertTrue($abierta->cerrada_al_envio);

        // La que ya tenia fin no se toca.
        $cerrada->refresh();
        $this->assertSame('06:20', $cerrada->fin_corta);
        $this->assertFalse($cerrada->cerrada_al_envio);
    }

    // --- Duracion (turno noche cruza la medianoche via cierre-al-envio) ---

    public function test_duracion_envuelve_medianoche(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador);

        $parada = $reporte->paradas()->create([
            'motivo' => 'Corte de luz',
            'clase' => ProduccionParada::claseDe('Corte de luz'),
            'origen' => 'maquina',
            'inicio' => '23:30',
            'fin' => '00:15',
        ]);

        $this->assertSame(45, $parada->duracion_minutos);
        $this->assertSame('45 min', $parada->duracion_label);

        $parada->fin = '01:45';
        $this->assertSame(135, $parada->duracion_minutos);
        $this->assertSame('2 h 15 min', $parada->duracion_label);
    }

    // --- Cavidades activas y scrap de arranque (candado 6, parte servidor) ---

    public function test_cavidades_activas_persisten(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador);

        $this->actingAs($soplador)
            ->patch(route('produccion.mi.update', $reporte), ['enviar' => 0, 'cavidades_activas' => 12])
            ->assertRedirect(route('produccion.mi.show', $reporte));

        $this->assertDatabaseHas('produccion_reportes', [
            'id' => $reporte->id,
            'cavidades_activas' => 12,
        ]);

        // Vacio = todas (NULL), sin error.
        $this->actingAs($soplador)
            ->patch(route('produccion.mi.update', $reporte), ['enviar' => 0, 'cavidades_activas' => ''])
            ->assertRedirect(route('produccion.mi.show', $reporte));

        $this->assertNull($reporte->fresh()->cavidades_activas);
    }

    public function test_cavidades_fuera_de_rango_es_rechazada(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador);

        foreach ([0, 65] as $valor) {
            $this->actingAs($soplador)
                ->patch(route('produccion.mi.update', $reporte), ['enviar' => 0, 'cavidades_activas' => $valor])
                ->assertSessionHasErrors('cavidades_activas');
        }

        $this->assertNull($reporte->fresh()->cavidades_activas);
    }

    public function test_scrap_de_arranque_persiste(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador);
        $maquina = $this->maquina();

        $this->actingAs($soplador)
            ->post(route('produccion.mi.paradas.store', $reporte), $this->payload($maquina, [
                'parada_motivo' => 'Scrap de arranque',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('produccion_paradas', [
            'reporte_id' => $reporte->id,
            'motivo' => 'Scrap de arranque',
            'clase' => ProduccionParada::CLASE_NO_PLANIFICADA,
        ]);
    }

    // --- Superficies (candados 4 y 6: el detalle se VE) ---

    public function test_jefe_ve_el_detalle_integro_de_paradas(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, estado: ProduccionReporte::ENVIADO);
        $maquina = $this->maquina('Sopladora 7');

        $reporte->cavidades_activas = 12;
        $reporte->save();

        $reporte->paradas()->create([
            'maquina_id' => $maquina->id,
            'motivo' => 'Falla de máquina',
            'clase' => ProduccionParada::claseDe('Falla de máquina'),
            'origen' => 'maquina',
            'inicio' => '07:00',
            'fin' => '09:15',
            'cerrada_al_envio' => true,
        ]);
        $reporte->paradas()->create([
            'maquina_id' => $maquina->id,
            'motivo' => 'Cambio de molde',
            'clase' => ProduccionParada::claseDe('Cambio de molde'),
            'origen' => 'operario',
            'inicio' => '10:00',
            'fin' => '10:30',
        ]);

        $respuesta = $this->actingAs($this->jefe())
            ->get(route('admin.produccion.reporte.show', $reporte))
            ->assertOk()
            // Motivo, rango horario y duracion calculada.
            ->assertSee('Falla de máquina')
            ->assertSee('07:00 a 09:15')
            ->assertSee('2 h 15 min')
            ->assertSee('Cambio de molde')
            ->assertSee('30 min')
            // Clase y origen como badges; marca del cierre automatico.
            ->assertSee('No planificada')
            ->assertSee('Planificada')
            ->assertSee('Cerrada al envío')
            ->assertSee('Operario')
            // Cavidades activas del turno.
            ->assertSeeInOrder(['Cavidades activas', '12']);

        // La maquina de la parada enlaza a su drill-down (marcador por RUTA).
        $respuesta->assertSee(route('admin.produccion.maquina', $maquina), false);
    }

    public function test_scrap_y_cavidades_se_ven_en_el_show_del_soplador(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, estado: ProduccionReporte::ENVIADO);

        $reporte->cavidades_activas = 8;
        $reporte->save();

        $reporte->paradas()->create([
            'motivo' => 'Scrap de arranque',
            'clase' => ProduccionParada::claseDe('Scrap de arranque'),
            'origen' => 'maquina',
            'inicio' => '06:00',
            'fin' => '06:40',
        ]);

        $this->actingAs($soplador)
            ->get(route('produccion.mi.show', $reporte))
            ->assertOk()
            ->assertSee('Paradas del turno')
            ->assertSee('Scrap de arranque')
            ->assertSee('06:00 a 06:40')
            ->assertSee('40 min')
            ->assertSeeInOrder(['Cavidades activas', '8']);
    }

    public function test_mi_reporte_editable_muestra_la_seccion_de_paradas(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador);
        $this->maquina();

        // Por RUTA y por marcador de campo, no por markup pegado.
        $this->actingAs($soplador)
            ->get(route('produccion.mi.show', $reporte))
            ->assertOk()
            ->assertSee(route('produccion.mi.paradas.store', $reporte), false)
            ->assertSee('name="parada_motivo"', false)
            ->assertSee('name="parada_inicio"', false)
            ->assertSee('name="cavidades_activas"', false);
    }
}
