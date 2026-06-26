<?php

namespace Tests\Feature\Admin;

use App\Models\Maquina;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionRegistro;
use App\Models\ProduccionReporte;
use App\Models\Sucursal;
use App\Models\TipoBotellon;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProduccionTest extends TestCase
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

    private function soplador(?Sucursal $sucursal = null): User
    {
        return tap(User::factory()->create(['sucursal_id' => $sucursal?->id]))->assignRole('soplador');
    }

    private function sucursal(string $codigo): Sucursal
    {
        return Sucursal::firstOrCreate(['codigo' => $codigo], ['nombre' => ucfirst(strtolower($codigo))]);
    }

    private function maquina(?Sucursal $sucursal = null, string $nombre = 'Sopladora 1', bool $activa = true): Maquina
    {
        return Maquina::create([
            'nombre' => $nombre,
            'sucursal_id' => ($sucursal ?? $this->sucursal('MIRADOR'))->id,
            'activa' => $activa,
        ]);
    }

    private function tipo(string $codigo = 'AZUL-20L', string $nombre = 'Azul 20L s/manilla'): TipoBotellon
    {
        return TipoBotellon::firstOrCreate(['codigo' => $codigo], ['nombre' => $nombre, 'activo' => true]);
    }

    private function reporteDe(User $soplador, int $asignadas = 100, string $estado = ProduccionReporte::BORRADOR, ?string $fecha = null): ProduccionReporte
    {
        $fecha ??= now()->toDateString();

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

    /** Agrega una tanda válida actuando como el soplador dueño del reporte. */
    private function agregarTanda(User $soplador, ProduccionReporte $reporte, array $cantidades, ?Maquina $maquina = null, ?TipoBotellon $tipo = null)
    {
        $payload = array_merge([
            'maquina_id' => $maquina?->id,
            'tipo_botellon_id' => $tipo?->id,
            'primera' => 0,
            'segunda' => 0,
            'malo' => 0,
            'danada' => 0,
        ], $cantidades);

        // El motivo es obligatorio cuando hay defectuosas; los tests que no lo
        // fijan reciben uno valido por defecto.
        if (($payload['segunda'] ?? 0) > 0 && ! array_key_exists('motivo_segunda', $payload)) {
            $payload['motivo_segunda'] = ProduccionRegistro::MOTIVOS_DEFECTO[0];
        }
        if (($payload['malo'] ?? 0) > 0 && ! array_key_exists('motivo_malo', $payload)) {
            $payload['motivo_malo'] = ProduccionRegistro::MOTIVOS_DEFECTO[0];
        }

        return $this->actingAs($soplador)->post(route('produccion.mi.registros.store', $reporte), $payload);
    }

    // --- Acceso / gating ---

    public function test_jefe_puede_ver_el_panel(): void
    {
        $this->actingAs($this->jefe())->get('/admin/produccion')->assertOk();
    }

    public function test_soplador_no_puede_ver_el_panel_del_jefe(): void
    {
        $this->actingAs($this->soplador())->get('/admin/produccion')->assertForbidden();
    }

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get('/admin/produccion')->assertRedirect('/login');
        $this->get('/produccion/mi-reporte')->assertRedirect('/login');
    }

    public function test_member_sin_permiso_no_entra_a_mi_produccion(): void
    {
        $member = tap(User::factory()->create())->assignRole('member');
        $this->actingAs($member)->get('/produccion/mi-reporte')->assertForbidden();
    }

    // --- Asignacion (jefe) ---

    public function test_jefe_asigna_y_crea_reporte_en_borrador(): void
    {
        $soplador = $this->soplador();

        $this->actingAs($this->jefe())->post(route('admin.produccion.asignar.store'), [
            'soplador_id' => $soplador->id,
            'turno' => 'dia',
            'fecha' => now()->toDateString(),
            'asignadas' => 1200,
        ])->assertRedirect(route('admin.produccion.index'));

        $this->assertDatabaseHas('produccion_asignaciones', [
            'soplador_id' => $soplador->id, 'asignadas' => 1200, 'turno' => 'dia',
        ]);
        $this->assertDatabaseHas('produccion_reportes', [
            'soplador_id' => $soplador->id, 'asignadas' => 1200, 'estado' => 'borrador',
        ]);
    }

    public function test_reasignar_actualiza_cantidad_sin_duplicar(): void
    {
        $soplador = $this->soplador();
        $payload = ['soplador_id' => $soplador->id, 'turno' => 'dia', 'fecha' => now()->toDateString()];

        $this->actingAs($this->jefe())->post(route('admin.produccion.asignar.store'), $payload + ['asignadas' => 100]);
        $this->actingAs($this->jefe())->post(route('admin.produccion.asignar.store'), $payload + ['asignadas' => 250]);

        $this->assertSame(1, ProduccionAsignacion::where('soplador_id', $soplador->id)->count());
        $this->assertDatabaseHas('produccion_asignaciones', ['soplador_id' => $soplador->id, 'asignadas' => 250]);
    }

    // --- Registros (tandas) del soplador ---

    public function test_soplador_ve_su_reporte_del_dia(): void
    {
        $soplador = $this->soplador();
        $this->reporteDe($soplador);

        $this->actingAs($soplador)->get('/produccion/mi-reporte')->assertOk();
    }

    public function test_tanda_crea_registro_y_sincroniza_totales(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100);
        [$maquina, $tipo] = [$this->maquina(), $this->tipo()];

        $this->agregarTanda($soplador, $reporte, [
            'primera' => 50, 'segunda' => 10, 'malo' => 5, 'danada' => 3,
            'motivo_segunda' => 'Rebaba', 'motivo_malo' => 'Material quemado',
        ], $maquina, $tipo)->assertRedirect(route('produccion.mi.index'));

        $this->assertDatabaseHas('produccion_registros', [
            'reporte_id' => $reporte->id,
            'maquina_id' => $maquina->id,
            'tipo_botellon_id' => $tipo->id,
            'primera' => 50, 'segunda' => 10, 'malo' => 5, 'danada' => 3,
            'motivo_segunda' => 'Rebaba', 'motivo_malo' => 'Material quemado',
        ]);

        $reporte->refresh();
        $this->assertSame('borrador', $reporte->estado);
        $this->assertSame(50, $reporte->primera);
        $this->assertSame(10, $reporte->segunda);
        $this->assertSame(5, $reporte->malo);
        $this->assertSame(3, $reporte->danada);
        // Las preformas dañadas suman al total producido.
        $this->assertSame(68, $reporte->total);
    }

    public function test_dos_tandas_del_mismo_combo_no_se_funden_y_suman(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100);
        [$maquina, $tipo] = [$this->maquina(), $this->tipo()];

        $this->agregarTanda($soplador, $reporte, ['primera' => 20], $maquina, $tipo);
        $this->agregarTanda($soplador, $reporte, ['primera' => 30], $maquina, $tipo);

        $this->assertSame(2, $reporte->registros()->count());
        $this->assertSame(50, $reporte->fresh()->primera);
    }

    public function test_eliminar_tanda_recalcula_totales(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100);
        [$maquina, $tipo] = [$this->maquina(), $this->tipo()];

        $this->agregarTanda($soplador, $reporte, ['primera' => 20], $maquina, $tipo);
        $this->agregarTanda($soplador, $reporte, ['primera' => 30, 'malo' => 2], $maquina, $tipo);
        $registro = $reporte->registros()->where('primera', 30)->first();

        $this->actingAs($soplador)
            ->delete(route('produccion.mi.registros.destroy', [$reporte, $registro]))
            ->assertRedirect(route('produccion.mi.index'));

        $reporte->refresh();
        $this->assertSame(1, $reporte->registros()->count());
        $this->assertSame(20, $reporte->primera);
        $this->assertSame(0, $reporte->malo);
    }

    public function test_tanda_exige_alguna_cantidad(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100);
        [$maquina, $tipo] = [$this->maquina(), $this->tipo()];

        $this->agregarTanda($soplador, $reporte, [], $maquina, $tipo)
            ->assertSessionHasErrors('primera');

        $this->assertSame(0, $reporte->registros()->count());
    }

    public function test_tanda_con_segunda_exige_motivo(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100);
        [$maquina, $tipo] = [$this->maquina(), $this->tipo()];

        // motivo_segunda explicitamente vacio: no debe pasar.
        $this->agregarTanda($soplador, $reporte, ['segunda' => 5, 'motivo_segunda' => ''], $maquina, $tipo)
            ->assertSessionHasErrors('motivo_segunda');

        $this->assertSame(0, $reporte->registros()->count());
    }

    public function test_tanda_rechaza_motivo_fuera_de_la_lista(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100);
        [$maquina, $tipo] = [$this->maquina(), $this->tipo()];

        $this->agregarTanda($soplador, $reporte, ['malo' => 3, 'motivo_malo' => 'Inventado'], $maquina, $tipo)
            ->assertSessionHasErrors('motivo_malo');

        $this->assertSame(0, $reporte->registros()->count());
    }

    public function test_motivo_se_descarta_si_la_categoria_queda_en_cero(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100);
        [$maquina, $tipo] = [$this->maquina(), $this->tipo()];

        // primera > 0 hace valida la tanda; segunda = 0 con motivo elegido => motivo se anula.
        $this->agregarTanda($soplador, $reporte, ['primera' => 10, 'segunda' => 0, 'motivo_segunda' => 'Rebaba'], $maquina, $tipo)
            ->assertRedirect(route('produccion.mi.index'));

        $this->assertNull($reporte->registros()->first()->motivo_segunda);
    }

    public function test_tanda_exige_maquina_si_hay_activas(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100);
        $this->maquina();
        $tipo = $this->tipo();

        $this->agregarTanda($soplador, $reporte, ['primera' => 10], null, $tipo)
            ->assertSessionHasErrors('maquina_id');
    }

    public function test_sin_maquinas_ni_tipos_la_tanda_entra_sin_ellos(): void
    {
        // Transicion post-deploy: aun no se crean maquinas ni tipos.
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100);

        $this->agregarTanda($soplador, $reporte, ['primera' => 10])
            ->assertRedirect(route('produccion.mi.index'));

        $this->assertDatabaseHas('produccion_registros', [
            'reporte_id' => $reporte->id, 'maquina_id' => null, 'tipo_botellon_id' => null, 'primera' => 10,
        ]);
    }

    public function test_maquina_de_otra_sucursal_es_rechazada(): void
    {
        $sucursalA = $this->sucursal('MIRADOR');
        $sucursalB = $this->sucursal('COQUIMBO');
        $soplador = $this->soplador($sucursalA);
        $reporte = $this->reporteDe($soplador, 100);
        $this->maquina($sucursalA, 'Sopladora A');
        $ajena = $this->maquina($sucursalB, 'Sopladora B');
        $tipo = $this->tipo();

        $this->agregarTanda($soplador, $reporte, ['primera' => 10], $ajena, $tipo)
            ->assertSessionHasErrors('maquina_id');
    }

    public function test_maquina_inactiva_es_rechazada(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100);
        $activa = $this->maquina(nombre: 'Activa');
        $inactiva = $this->maquina(nombre: 'Inactiva', activa: false);
        $tipo = $this->tipo();

        $this->agregarTanda($soplador, $reporte, ['primera' => 10], $inactiva, $tipo)
            ->assertSessionHasErrors('maquina_id');
    }

    public function test_soplador_no_agrega_tandas_a_reporte_ajeno(): void
    {
        $reporte = $this->reporteDe($this->soplador());
        $otro = $this->soplador();

        $this->agregarTanda($otro, $reporte, ['primera' => 10])->assertForbidden();
    }

    public function test_no_se_agregan_tandas_a_reporte_enviado(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100, ProduccionReporte::ENVIADO);

        $this->agregarTanda($soplador, $reporte, ['primera' => 10])->assertForbidden();
    }

    public function test_registro_de_otro_reporte_devuelve_404_al_borrar(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100);
        $this->agregarTanda($soplador, $reporte, ['primera' => 10]);

        $otroSoplador = $this->soplador();
        $otroReporte = $this->reporteDe($otroSoplador, 100);
        $registroAjeno = $reporte->registros()->first();

        $this->actingAs($otroSoplador)
            ->delete(route('produccion.mi.registros.destroy', [$otroReporte, $registroAjeno]))
            ->assertNotFound();
    }

    // --- Envio del reporte (soplador) ---

    public function test_soplador_envia_reporte_cuadrado(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100);
        [$maquina, $tipo] = [$this->maquina(), $this->tipo()];
        $this->agregarTanda($soplador, $reporte, ['primera' => 90, 'segunda' => 5, 'malo' => 5], $maquina, $tipo);

        $this->actingAs($soplador)->patch(route('produccion.mi.update', $reporte), [
            'enviar' => 1,
        ])->assertRedirect(route('produccion.mi.index'));

        $reporte->refresh();
        $this->assertSame('enviado', $reporte->estado);
        $this->assertNotNull($reporte->enviado_at);
    }

    public function test_enviar_sin_tandas_es_rechazado(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100);

        $this->actingAs($soplador)->patch(route('produccion.mi.update', $reporte), [
            'enviar' => 1,
        ])->assertSessionHasErrors('enviar');

        $this->assertSame('borrador', $reporte->fresh()->estado);
    }

    public function test_enviar_con_diferencia_exige_motivo(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100);
        [$maquina, $tipo] = [$this->maquina(), $this->tipo()];
        $this->agregarTanda($soplador, $reporte, ['primera' => 50], $maquina, $tipo);

        $this->actingAs($soplador)->patch(route('produccion.mi.update', $reporte), [
            'enviar' => 1,
        ])->assertSessionHasErrors('motivo');

        $this->assertSame('borrador', $reporte->fresh()->estado);
    }

    public function test_reenviar_limpia_el_motivo_de_devolucion(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100, ProduccionReporte::DEVUELTO);
        $reporte->update(['devuelto_motivo' => 'Faltan los malos.']);
        [$maquina, $tipo] = [$this->maquina(), $this->tipo()];
        $this->agregarTanda($soplador, $reporte, ['primera' => 100], $maquina, $tipo);

        $this->actingAs($soplador)->patch(route('produccion.mi.update', $reporte), ['enviar' => 1]);

        $reporte->refresh();
        $this->assertSame('enviado', $reporte->estado);
        $this->assertNull($reporte->devuelto_motivo);
    }

    public function test_recalculo_limpia_ajuste_previo_del_jefe(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100, ProduccionReporte::DEVUELTO);
        $reporte->update(['motivo_ajuste' => 'Recuento físico previo.']);
        [$maquina, $tipo] = [$this->maquina(), $this->tipo()];

        $this->agregarTanda($soplador, $reporte, ['primera' => 10], $maquina, $tipo);

        $this->assertNull($reporte->fresh()->motivo_ajuste);
    }

    public function test_soplador_no_edita_reporte_ajeno(): void
    {
        $reporte = $this->reporteDe($this->soplador());
        $otro = $this->soplador();

        $this->actingAs($otro)->patch(route('produccion.mi.update', $reporte), [
            'enviar' => 0,
        ])->assertForbidden();
    }

    public function test_reporte_enviado_no_es_editable_por_el_soplador(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100, ProduccionReporte::ENVIADO);

        $this->actingAs($soplador)->patch(route('produccion.mi.update', $reporte), [
            'enviar' => 0,
        ])->assertForbidden();
    }

    // --- Reportes devueltos de otros dias ---

    public function test_devuelto_de_ayer_es_visible_en_su_propia_vista(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100, ProduccionReporte::DEVUELTO, now()->subDay()->toDateString());

        $this->actingAs($soplador)->get(route('produccion.mi.show', $reporte))
            ->assertOk()
            ->assertSee('Preformas asignadas');
    }

    public function test_devuelto_de_ayer_aparece_en_el_banner_de_hoy(): void
    {
        $soplador = $this->soplador();
        $this->reporteDe($soplador, 100, ProduccionReporte::DEVUELTO, now()->subDay()->toDateString());

        $this->actingAs($soplador)->get('/produccion/mi-reporte')
            ->assertOk()
            ->assertSee('por corregir');
    }

    public function test_show_de_reporte_ajeno_es_rechazado(): void
    {
        $reporte = $this->reporteDe($this->soplador());
        $otro = $this->soplador();

        $this->actingAs($otro)->get(route('produccion.mi.show', $reporte))->assertForbidden();
    }

    // --- Backfill de transicion ---

    public function test_backfill_crea_registro_desde_totales_preexistentes(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100);
        // Reporte del flujo viejo: cantidades directas, sin registros.
        $reporte->update(['primera' => 80, 'segunda' => 15, 'malo' => 5]);

        $migration = include database_path('migrations/2026_06_12_120003_backfill_produccion_registros_desde_reportes.php');
        $migration->up();

        $this->assertDatabaseHas('produccion_registros', [
            'reporte_id' => $reporte->id,
            'maquina_id' => null,
            'tipo_botellon_id' => null,
            'primera' => 80, 'segunda' => 15, 'malo' => 5,
        ]);

        $migration->up(); // idempotente: no duplica
        $this->assertSame(1, $reporte->registros()->count());
    }

    // --- Revision (jefe) ---

    public function test_jefe_aprueba_reporte_enviado(): void
    {
        $reporte = $this->reporteDe($this->soplador(), 100, ProduccionReporte::ENVIADO);

        $this->actingAs($this->jefe())->post(route('admin.produccion.reporte.aprobar', $reporte))
            ->assertRedirect(route('admin.produccion.index'));

        $this->assertSame('aprobado', $reporte->fresh()->estado);
    }

    public function test_jefe_no_aprueba_borrador(): void
    {
        $reporte = $this->reporteDe($this->soplador(), 100, ProduccionReporte::BORRADOR);

        $this->actingAs($this->jefe())->post(route('admin.produccion.reporte.aprobar', $reporte));

        $this->assertSame('borrador', $reporte->fresh()->estado);
    }

    public function test_jefe_devuelve_con_motivo(): void
    {
        $reporte = $this->reporteDe($this->soplador(), 100, ProduccionReporte::ENVIADO);

        $this->actingAs($this->jefe())->post(route('admin.produccion.reporte.devolver', $reporte), [
            'devuelto_motivo' => 'Faltan los malos del segundo molde.',
        ])->assertRedirect(route('admin.produccion.index'));

        $reporte->refresh();
        $this->assertSame('devuelto', $reporte->estado);
        $this->assertSame('Faltan los malos del segundo molde.', $reporte->devuelto_motivo);
    }

    public function test_devolver_exige_motivo(): void
    {
        $reporte = $this->reporteDe($this->soplador(), 100, ProduccionReporte::ENVIADO);

        $this->actingAs($this->jefe())->post(route('admin.produccion.reporte.devolver', $reporte), [])
            ->assertSessionHasErrors('devuelto_motivo');
    }

    public function test_jefe_ajusta_cantidades_con_motivo(): void
    {
        $reporte = $this->reporteDe($this->soplador(), 100, ProduccionReporte::ENVIADO);

        $this->actingAs($this->jefe())->post(route('admin.produccion.reporte.ajustar', $reporte), [
            'asignadas' => 120, 'primera' => 80, 'segunda' => 15, 'malo' => 5, 'danada' => 0, 'motivo_ajuste' => 'Recuento físico.',
        ])->assertRedirect(route('admin.produccion.reporte.show', $reporte));

        $reporte->refresh();
        $this->assertSame(80, $reporte->primera);
        $this->assertSame('Recuento físico.', $reporte->motivo_ajuste);
        // Editar asignadas sincroniza la asignacion (fuente de verdad).
        $this->assertSame(120, $reporte->asignadas);
        $this->assertSame(120, $reporte->asignacion->fresh()->asignadas);
    }

    public function test_admin_edita_reporte_aprobado_en_cualquier_estado(): void
    {
        $reporte = $this->reporteDe($this->soplador(), 100, ProduccionReporte::APROBADO);

        $this->actingAs($this->jefe())->post(route('admin.produccion.reporte.ajustar', $reporte), [
            'asignadas' => 90, 'primera' => 70, 'segunda' => 10, 'malo' => 10, 'danada' => 0, 'motivo_ajuste' => 'Corrección post-aprobación.',
        ])->assertRedirect(route('admin.produccion.reporte.show', $reporte));

        $reporte->refresh();
        $this->assertSame(70, $reporte->primera);
        $this->assertSame(90, $reporte->asignadas);
    }

    public function test_panel_del_jefe_muestra_produccion_por_maquina(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100);
        $maquina = $this->maquina(nombre: 'Sopladora Norte');
        $tipo = $this->tipo();
        $this->agregarTanda($soplador, $reporte, ['primera' => 40], $maquina, $tipo);

        $this->actingAs($this->jefe())->get('/admin/produccion')
            ->assertOk()
            ->assertSee('Sopladora Norte');
    }

    public function test_detalle_del_reporte_muestra_las_tandas(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100);
        $maquina = $this->maquina(nombre: 'Sopladora Sur');
        $tipo = $this->tipo('INCOLORO-10L-RETORNABLE', 'Incoloro 10L retornable');
        $this->agregarTanda($soplador, $reporte, ['primera' => 25], $maquina, $tipo);

        $this->actingAs($this->jefe())->get(route('admin.produccion.reporte.show', $reporte))
            ->assertOk()
            ->assertSee('Detalle por máquina y tipo')
            ->assertSee('Sopladora Sur')
            ->assertSee('Incoloro 10L retornable');
    }
}
