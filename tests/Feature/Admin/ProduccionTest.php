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

    public function test_asignar_de_nuevo_crea_otra_produccion(): void
    {
        $soplador = $this->soplador();
        $payload = ['soplador_id' => $soplador->id, 'turno' => 'dia', 'fecha' => now()->toDateString()];

        $this->actingAs($this->jefe())->post(route('admin.produccion.asignar.store'), $payload + ['asignadas' => 100]);
        $this->actingAs($this->jefe())->post(route('admin.produccion.asignar.store'), $payload + ['asignadas' => 250]);

        // Cada "Asignar" crea una produccion independiente (varias por dia/turno).
        $this->assertSame(2, ProduccionAsignacion::where('soplador_id', $soplador->id)->count());
        $this->assertSame(2, ProduccionReporte::where('soplador_id', $soplador->id)->count());
        $this->assertDatabaseHas('produccion_asignaciones', ['soplador_id' => $soplador->id, 'asignadas' => 100]);
        $this->assertDatabaseHas('produccion_asignaciones', ['soplador_id' => $soplador->id, 'asignadas' => 250]);
    }

    public function test_asignar_no_muta_un_reporte_aprobado_existente(): void
    {
        // Regresion del bug: re-asignar a un soplador con reporte aprobado del dia
        // NO debe pisar ese aprobado; crea una produccion nueva en borrador.
        $soplador = $this->soplador();
        $aprobado = $this->reporteDe($soplador, 600, ProduccionReporte::APROBADO);

        $this->actingAs($this->jefe())->post(route('admin.produccion.asignar.store'), [
            'soplador_id' => $soplador->id, 'turno' => 'dia', 'fecha' => now()->toDateString(), 'asignadas' => 500,
        ])->assertRedirect(route('admin.produccion.index'));

        $aprobado->refresh();
        $this->assertSame('aprobado', $aprobado->estado);
        $this->assertSame(600, $aprobado->asignadas); // intacto, no pisado a 500
        $this->assertSame(2, ProduccionReporte::where('soplador_id', $soplador->id)->count());
        $this->assertSame(1, ProduccionReporte::where('soplador_id', $soplador->id)
            ->where('estado', ProduccionReporte::BORRADOR)->where('asignadas', 500)->count());
    }

    public function test_soplador_lista_varias_producciones_del_dia(): void
    {
        $soplador = $this->soplador();
        $r1 = $this->reporteDe($soplador, 100);
        $r2 = $this->reporteDe($soplador, 250);

        $this->actingAs($soplador)->get(route('produccion.mi.index'))
            ->assertOk()
            ->assertSee('100 preformas')
            ->assertSee('250 preformas')
            ->assertSee(route('produccion.mi.show', $r1))
            ->assertSee(route('produccion.mi.show', $r2));
    }

    public function test_jefe_elimina_produccion_vacia_en_borrador(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100); // borrador sin tandas
        $asignacionId = $reporte->asignacion_id;

        $this->actingAs($this->jefe())->delete(route('admin.produccion.reporte.destroy', $reporte))
            ->assertRedirect(route('admin.produccion.index'));

        $this->assertDatabaseMissing('produccion_reportes', ['id' => $reporte->id]);
        $this->assertDatabaseMissing('produccion_asignaciones', ['id' => $asignacionId]);
    }

    public function test_no_elimina_produccion_con_tandas(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100);
        [$maquina, $tipo] = [$this->maquina(), $this->tipo()];
        $this->agregarTanda($soplador, $reporte, ['primera' => 10], $maquina, $tipo);

        $this->actingAs($this->jefe())->delete(route('admin.produccion.reporte.destroy', $reporte));

        $this->assertDatabaseHas('produccion_reportes', ['id' => $reporte->id]);
    }

    public function test_no_elimina_produccion_aprobada(): void
    {
        $reporte = $this->reporteDe($this->soplador(), 100, ProduccionReporte::APROBADO);

        $this->actingAs($this->jefe())->delete(route('admin.produccion.reporte.destroy', $reporte));

        $this->assertDatabaseHas('produccion_reportes', ['id' => $reporte->id]);
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
        ], $maquina, $tipo)->assertRedirect(route('produccion.mi.show', $reporte));

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
            ->assertRedirect(route('produccion.mi.show', $reporte));

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
            ->assertRedirect(route('produccion.mi.show', $reporte));

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
            ->assertRedirect(route('produccion.mi.show', $reporte));

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

    public function test_enviar_con_diferencia_acepta_motivo_de_la_lista(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100);
        [$maquina, $tipo] = [$this->maquina(), $this->tipo()];
        $this->agregarTanda($soplador, $reporte, ['primera' => 50], $maquina, $tipo);

        $motivo = ProduccionReporte::MOTIVOS_DIFERENCIA[0];
        $this->actingAs($soplador)->patch(route('produccion.mi.update', $reporte), [
            'enviar' => 1, 'motivo' => $motivo,
        ])->assertRedirect(route('produccion.mi.index'));

        $reporte->refresh();
        $this->assertSame('enviado', $reporte->estado);
        $this->assertSame($motivo, $reporte->motivo);
    }

    public function test_enviar_con_diferencia_acepta_motivo_otro_libre(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100);
        [$maquina, $tipo] = [$this->maquina(), $this->tipo()];
        $this->agregarTanda($soplador, $reporte, ['primera' => 50], $maquina, $tipo);

        // El chip "Otro" viaja como centinela; el texto real en motivo_otro.
        $this->actingAs($soplador)->patch(route('produccion.mi.update', $reporte), [
            'enviar' => 1,
            'motivo' => ProduccionReporte::MOTIVO_OTRO,
            'motivo_otro' => 'Se cortó el aire comprimido',
        ])->assertRedirect(route('produccion.mi.index'));

        $reporte->refresh();
        $this->assertSame('enviado', $reporte->estado);
        $this->assertSame('Se cortó el aire comprimido', $reporte->motivo);
    }

    public function test_enviar_con_otro_sin_texto_exige_motivo(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100);
        [$maquina, $tipo] = [$this->maquina(), $this->tipo()];
        $this->agregarTanda($soplador, $reporte, ['primera' => 50], $maquina, $tipo);

        // "Otro" elegido pero el texto queda en blanco => se normaliza a null y
        // la diferencia sigue exigiendo motivo.
        $this->actingAs($soplador)->patch(route('produccion.mi.update', $reporte), [
            'enviar' => 1,
            'motivo' => ProduccionReporte::MOTIVO_OTRO,
            'motivo_otro' => '   ',
        ])->assertSessionHasErrors('motivo');

        $this->assertSame('borrador', $reporte->fresh()->estado);
    }

    public function test_mi_reporte_renderiza_chips_de_motivo_y_notas(): void
    {
        // Render real de la vista del operario: valida que el componente
        // reason-chips compila y que las listas centralizadas se muestran.
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100);
        $this->maquina();
        $this->tipo();

        // La pantalla de llenado de un reporte es mi.show (mi.index es la lista).
        $this->actingAs($soplador)->get(route('produccion.mi.show', $reporte))
            ->assertOk()
            ->assertSee(ProduccionReporte::MOTIVOS_DIFERENCIA[0])
            ->assertSee(ProduccionReporte::NOTAS_COMUNES[0])
            ->assertSee('Motivo de la diferencia');
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

    public function test_reporte_editado_marca_la_divergencia_en_el_detalle(): void
    {
        $reporte = $this->reporteDe($this->soplador(), 100, ProduccionReporte::ENVIADO);
        $reporte->registros()->create([
            'maquina_id' => $this->maquina()->id,
            'tipo_botellon_id' => $this->tipo()->id,
            'primera' => 50,
        ]);
        $reporte->recalcularDesdeRegistros(); // total desde tandas = 50

        // El admin edita el total a 80; el detalle por tandas sigue en 50.
        $this->actingAs($this->jefe())->post(route('admin.produccion.reporte.ajustar', $reporte), [
            'asignadas' => 100, 'primera' => 80, 'segunda' => 0, 'malo' => 0, 'danada' => 0, 'motivo_ajuste' => 'Recuento físico.',
        ]);

        $this->actingAs($this->jefe())->get(route('admin.produccion.reporte.show', $reporte))
            ->assertOk()
            ->assertSee('el admin editó las cantidades'); // nota de divergencia sobre el detalle
    }

    public function test_por_maquina_distingue_maquinas_homonimas_de_distinta_sucursal(): void
    {
        $sucA = $this->sucursal('MIRADOR');
        $sucB = $this->sucursal('COQUIMBO');
        $sopA = $this->soplador($sucA);
        $sopB = $this->soplador($sucB);
        $repA = $this->reporteDe($sopA, 100);
        $repB = $this->reporteDe($sopB, 100);
        $maqA = $this->maquina($sucA, 'Sopladora 1');
        $maqB = $this->maquina($sucB, 'Sopladora 1'); // mismo nombre, otra sucursal
        $tipo = $this->tipo();
        $this->agregarTanda($sopA, $repA, ['primera' => 10], $maqA, $tipo);
        $this->agregarTanda($sopB, $repB, ['primera' => 20], $maqB, $tipo);

        $this->actingAs($this->jefe())->get('/admin/produccion')
            ->assertOk()
            ->assertSee('Mirador')
            ->assertSee('Coquimbo');
    }

    // --- Panel del jefe: alertas + resumen de hoy + periodo ---

    public function test_panel_muestra_alertas(): void
    {
        $this->reporteDe($this->soplador(), 100, ProduccionReporte::ENVIADO);   // por aprobar
        $this->reporteDe($this->soplador(), 100, ProduccionReporte::DEVUELTO);  // devuelto
        $this->reporteDe($this->soplador(), 100, ProduccionReporte::BORRADOR);  // atrasado hoy (sin enviar)

        $this->actingAs($this->jefe())->get('/admin/produccion')
            ->assertOk()
            ->assertViewHas('alertas', fn ($a) => $a['porAprobar'] === 1 && $a['devueltos'] === 1 && $a['atrasados'] === 1)
            ->assertSee('Requiere tu atención');
    }

    public function test_panel_resumen_de_hoy(): void
    {
        $sop = $this->soplador();
        $reporte = $this->reporteDe($sop, 100);
        [$maquina, $tipo] = [$this->maquina(), $this->tipo()];
        $this->agregarTanda($sop, $reporte, [
            'primera' => 80, 'segunda' => 10, 'malo' => 5, 'danada' => 5,
            'motivo_segunda' => 'Rebaba', 'motivo_malo' => 'Rebaba',
        ], $maquina, $tipo);

        $this->actingAs($this->jefe())->get('/admin/produccion')
            ->assertOk()
            ->assertViewHas('hoy', fn ($h) => $h['producido'] === 90 && $h['merma'] === 10 && $h['asignadas'] === 100 && $h['avance'] === 90);
    }

    public function test_panel_tendencia_por_periodo_agrega_por_dia(): void
    {
        $sop = $this->soplador();
        $haceDos = now()->subDays(2)->toDateString();
        $rep = $this->reporteDe($sop, 100, ProduccionReporte::APROBADO, $haceDos);
        $rep->update(['primera' => 50, 'segunda' => 10]); // producido = 60

        $this->actingAs($this->jefe())->get('/admin/produccion')
            ->assertOk()
            ->assertViewHas('periodo', function ($p) use ($haceDos) {
                return $p['esDefault']
                    && $p['totales']['producido'] === 60
                    && collect($p['dias'])->contains(fn ($d) => $d['fecha']->toDateString() === $haceDos && $d['producido'] === 60);
            });
    }

    public function test_panel_filtra_por_rango(): void
    {
        $hoyRep = $this->reporteDe($this->soplador(), 100, ProduccionReporte::APROBADO, now()->toDateString());
        $hoyRep->update(['primera' => 30]);
        $viejo = $this->reporteDe($this->soplador(), 100, ProduccionReporte::APROBADO, now()->subDays(20)->toDateString());
        $viejo->update(['primera' => 999]);

        // Rango = solo hoy: el reporte de hace 20 días queda fuera.
        $this->actingAs($this->jefe())
            ->get('/admin/produccion?desde='.now()->toDateString().'&hasta='.now()->toDateString())
            ->assertOk()
            ->assertViewHas('periodo', fn ($p) => ! $p['esDefault'] && $p['totales']['producido'] === 30);
    }

    // --- Drill-down: día, máquina, tipo, ranking ---

    public function test_dia_lista_reportes_de_esa_fecha(): void
    {
        $sop = $this->soplador();
        $hoyRep = $this->reporteDe($sop, 100, ProduccionReporte::APROBADO, now()->toDateString());
        $hoyRep->update(['primera' => 50]);
        $ayer = $this->reporteDe($this->soplador(), 100, ProduccionReporte::APROBADO, now()->subDay()->toDateString());
        $ayer->update(['primera' => 999]);

        $this->actingAs($this->jefe())->get(route('admin.produccion.dia', ['fecha' => now()->toDateString()]))
            ->assertOk()
            ->assertViewHas('resumen', fn ($r) => $r['producido'] === 50) // solo hoy, no el de ayer
            ->assertSee($sop->name);
    }

    public function test_maquina_rendimiento_agrega_por_periodo(): void
    {
        $sop = $this->soplador();
        $reporte = $this->reporteDe($sop, 100);
        $tipo = $this->tipo();
        $maquina = $this->maquina(nombre: 'Sopladora A');
        $otra = $this->maquina(nombre: 'Sopladora B');
        $this->agregarTanda($sop, $reporte, ['primera' => 40, 'segunda' => 10, 'motivo_segunda' => 'Rebaba'], $maquina, $tipo);
        $this->agregarTanda($sop, $reporte, ['primera' => 5], $otra, $tipo);

        $this->actingAs($this->jefe())->get(route('admin.produccion.maquina', $maquina))
            ->assertOk()
            ->assertViewHas('tendencia', fn ($t) => $t['totales']['producido'] === 50); // solo esta máquina
    }

    public function test_tipo_rendimiento_agrega_por_periodo(): void
    {
        $sop = $this->soplador();
        $reporte = $this->reporteDe($sop, 100);
        $maquina = $this->maquina();
        $tipoA = $this->tipo('AZUL-20L', 'Azul 20L');
        $tipoB = $this->tipo('INCOLORO-10L-RETORNABLE', 'Incoloro 10L');
        $this->agregarTanda($sop, $reporte, ['primera' => 30], $maquina, $tipoA);
        $this->agregarTanda($sop, $reporte, ['primera' => 7], $maquina, $tipoB);

        $this->actingAs($this->jefe())->get(route('admin.produccion.tipo', $tipoA))
            ->assertOk()
            ->assertViewHas('tendencia', fn ($t) => $t['totales']['producido'] === 30); // solo este tipo
    }

    public function test_panel_ranking_sopladores(): void
    {
        $sopA = $this->soplador();
        $this->reporteDe($sopA, 100, ProduccionReporte::APROBADO, now()->toDateString())->update(['primera' => 80]);
        $sopB = $this->soplador();
        $this->reporteDe($sopB, 100, ProduccionReporte::APROBADO, now()->toDateString())->update(['primera' => 20]);

        $this->actingAs($this->jefe())->get('/admin/produccion')
            ->assertOk()
            ->assertViewHas('rankingSopladores', function ($r) use ($sopA) {
                return $r->count() === 2 && $r->first()->id === $sopA->id && $r->first()->producido === 80;
            });
    }

    public function test_drilldowns_exigen_permiso_de_jefe(): void
    {
        $sop = $this->soplador();
        $maquina = $this->maquina();
        $tipo = $this->tipo();

        foreach ([
            route('admin.produccion.dia'),
            route('admin.produccion.maquina', $maquina),
            route('admin.produccion.tipo', $tipo),
        ] as $url) {
            $this->actingAs($sop)->get($url)->assertForbidden();
        }
    }

    // --- Hardening de auditoría (2026-07-02) ---

    public function test_historial_del_soplador_incluye_el_dia_hasta(): void
    {
        $sop = $this->soplador();
        $this->reporteDe($sop, 100, ProduccionReporte::APROBADO, now()->toDateString())->update(['primera' => 60]);

        // Rango de UN dia (desde == hasta): la fecha casteada se guarda con hora
        // 00:00:00 y un whereBetween deja el borde superior fuera (bitacora
        // 2026-07-01); esta regresion cubre el whereDate del historial.
        $this->actingAs($this->jefe())
            ->get(route('admin.produccion.soplador', [$sop, 'desde' => now()->toDateString(), 'hasta' => now()->toDateString()]))
            ->assertOk()
            ->assertViewHas('totales', fn ($t) => $t['reportes'] === 1 && $t['producido'] === 60);
    }

    public function test_asignar_rechaza_cantidad_absurda(): void
    {
        $soplador = $this->soplador();

        $this->actingAs($this->jefe())->post(route('admin.produccion.asignar.store'), [
            'soplador_id' => $soplador->id,
            'turno' => 'dia',
            'fecha' => now()->toDateString(),
            'asignadas' => 10000000, // dedazo: un cero de mas
        ])->assertSessionHasErrors('asignadas');

        $this->assertDatabaseCount('produccion_asignaciones', 0);
    }

    public function test_tanda_rechaza_cantidad_absurda(): void
    {
        $sop = $this->soplador();
        $reporte = $this->reporteDe($sop);

        $this->agregarTanda($sop, $reporte, ['primera' => 10000000])
            ->assertSessionHasErrors('primera');

        $this->assertSame(0, $reporte->registros()->count());
    }

    public function test_panel_lista_pendientes_de_otros_dias(): void
    {
        $sop = $this->soplador();
        $deAyer = $this->reporteDe($sop, 100, ProduccionReporte::ENVIADO, now()->subDay()->toDateString());
        $deHoy = $this->reporteDe($sop, 100, ProduccionReporte::ENVIADO, now()->toDateString());

        // La alerta "por aprobar" es global (cuenta 2), pero la cola es de hoy:
        // el enviado de ayer necesita su propia fila para no quedar invisible.
        $this->actingAs($this->jefe())->get('/admin/produccion')
            ->assertOk()
            ->assertSee('Pendientes de otros días')
            ->assertViewHas('alertas', fn ($a) => $a['porAprobar'] === 2)
            ->assertViewHas('pendientesOtrosDias', fn ($p) => $p->count() === 1 && $p->first()->is($deAyer))
            ->assertViewHas('reportes', fn ($r) => $r->count() === 1 && $r->first()->is($deHoy));
    }
}
