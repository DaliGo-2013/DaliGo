<?php

namespace Tests\Feature\Admin;

use App\Models\Maquina;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionReporte;
use App\Models\Sucursal;
use App\Models\TipoBotellon;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * La máquina y el tipo de botellón los decide el JEFE al asignar (dueño
 * 09-09): «el soplador no puede elegir en qué máquina soplar». Antes se
 * elegían tanda por tanda en la pantalla del soplador; ahora la asignación
 * los lleva y cada tanda y cada parada los HEREDAN del servidor.
 *
 * Acá viven, mudados desde ProduccionTest, los candados que antes vigilaban
 * la elección en la tanda: exige máquina si hay activas, rechaza la de otra
 * sucursal y la inactiva, y sin catálogo entra con nulls.
 */
class AsignacionMaquinaTipoTest extends TestCase
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

    private function tipo(string $codigo = 'AZUL-20L', string $nombre = 'Azul 20L c/manilla', bool $activo = true): TipoBotellon
    {
        return TipoBotellon::firstOrCreate(['codigo' => $codigo], ['nombre' => $nombre, 'activo' => $activo]);
    }

    private function asignar(User $soplador, array $extra = [])
    {
        return $this->actingAs($this->jefe())->post(route('admin.produccion.asignar.store'), array_merge([
            'soplador_id' => $soplador->id,
            'turno' => 'dia',
            'fecha' => now()->toDateString(),
            'asignadas' => 800,
        ], $extra));
    }

    /** Reporte en borrador con su asignación (máquina y tipo opcionales). */
    private function reporteDe(User $soplador, ?Maquina $maquina = null, ?TipoBotellon $tipo = null): ProduccionReporte
    {
        $fecha = now()->toDateString();
        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id, 'fecha' => $fecha, 'turno' => 'dia', 'asignadas' => 800,
            'maquina_id' => $maquina?->id, 'tipo_botellon_id' => $tipo?->id,
        ]);

        return ProduccionReporte::create([
            'asignacion_id' => $asignacion->id, 'soplador_id' => $soplador->id, 'fecha' => $fecha,
            'turno' => 'dia', 'asignadas' => 800, 'estado' => ProduccionReporte::BORRADOR,
        ]);
    }

    // --- El jefe asigna máquina y tipo -----------------------------------------

    public function test_asignar_guarda_maquina_y_tipo_en_la_asignacion(): void
    {
        $soplador = $this->soplador();
        $maquina = $this->maquina();
        $tipo = $this->tipo();

        $this->asignar($soplador, ['maquina_id' => $maquina->id, 'tipo_botellon_id' => $tipo->id])
            ->assertRedirect(route('admin.produccion.index'));

        $this->assertDatabaseHas('produccion_asignaciones', [
            'soplador_id' => $soplador->id, 'maquina_id' => $maquina->id, 'tipo_botellon_id' => $tipo->id,
        ]);
    }

    public function test_asignar_exige_maquina_y_tipo_si_hay_activos(): void
    {
        $soplador = $this->soplador();
        $this->maquina();
        $this->tipo();

        $this->asignar($soplador)->assertSessionHasErrors(['maquina_id', 'tipo_botellon_id']);
        $this->assertSame(0, ProduccionAsignacion::count());
    }

    public function test_sin_catalogo_la_asignacion_entra_sin_maquina_ni_tipo(): void
    {
        // Transición / planta sin máquinas ni tipos: asignar no se bloquea y
        // la tanda hereda los nulls (mismo criterio que la tanda de antes).
        $soplador = $this->soplador();

        $this->asignar($soplador)->assertRedirect(route('admin.produccion.index'));

        $asignacion = ProduccionAsignacion::firstOrFail();
        $this->assertNull($asignacion->maquina_id);
        $this->assertNull($asignacion->tipo_botellon_id);

        $this->actingAs($soplador)->post(route('produccion.mi.registros.store', $asignacion->reporte), [
            'primera' => 10, 'segunda' => 0, 'malo' => 0, 'danada' => 0,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('produccion_registros', [
            'reporte_id' => $asignacion->reporte->id, 'maquina_id' => null, 'tipo_botellon_id' => null, 'primera' => 10,
        ]);
    }

    public function test_maquina_de_otra_sucursal_es_rechazada_al_asignar(): void
    {
        $sucursalA = $this->sucursal('MIRADOR');
        $sucursalB = $this->sucursal('COQUIMBO');
        $soplador = $this->soplador($sucursalA);
        $this->maquina($sucursalA, 'Sopladora A');
        $ajena = $this->maquina($sucursalB, 'Sopladora B');
        $tipo = $this->tipo();

        $this->asignar($soplador, ['maquina_id' => $ajena->id, 'tipo_botellon_id' => $tipo->id])
            ->assertSessionHasErrors('maquina_id');
        $this->assertSame(0, ProduccionAsignacion::count());
    }

    public function test_maquina_inactiva_y_tipo_inactivo_son_rechazados_al_asignar(): void
    {
        $soplador = $this->soplador();
        $this->maquina(nombre: 'Activa');
        $inactiva = $this->maquina(nombre: 'Inactiva', activa: false);
        $this->tipo();
        $retirado = $this->tipo('VIEJO', 'Tipo retirado', activo: false);

        $this->asignar($soplador, ['maquina_id' => $inactiva->id, 'tipo_botellon_id' => $retirado->id])
            ->assertSessionHasErrors(['maquina_id', 'tipo_botellon_id']);
        $this->assertSame(0, ProduccionAsignacion::count());
    }

    public function test_el_form_de_asignar_ofrece_maquinas_y_tipos(): void
    {
        $this->maquina(nombre: 'Sopladora 7');
        $this->tipo('AZUL-10L', 'Azul 10L retornable');

        $this->actingAs($this->jefe())->get(route('admin.produccion.asignar'))
            ->assertOk()
            ->assertSee('name="maquina_id"', false)
            ->assertSee('Sopladora 7')
            ->assertSee('name="tipo_botellon_id"', false)
            ->assertSee('Azul 10L retornable');
    }

    // --- El soplador hereda -------------------------------------------------

    public function test_la_tanda_hereda_maquina_y_tipo_de_la_asignacion_y_no_lo_que_mande_el_cliente(): void
    {
        $soplador = $this->soplador();
        $asignada = $this->maquina(nombre: 'Asignada');
        $otra = $this->maquina(nombre: 'Otra');
        $tipoAsignado = $this->tipo();
        $otroTipo = $this->tipo('AZUL-10L', 'Azul 10L');
        $reporte = $this->reporteDe($soplador, $asignada, $tipoAsignado);

        // El payload intenta colar otra máquina y otro tipo: se ignoran.
        $this->actingAs($soplador)->post(route('produccion.mi.registros.store', $reporte), [
            'maquina_id' => $otra->id, 'tipo_botellon_id' => $otroTipo->id,
            'primera' => 50, 'segunda' => 0, 'malo' => 0, 'danada' => 0,
        ])->assertRedirect(route('produccion.mi.show', $reporte));

        $this->assertDatabaseHas('produccion_registros', [
            'reporte_id' => $reporte->id, 'maquina_id' => $asignada->id, 'tipo_botellon_id' => $tipoAsignado->id, 'primera' => 50,
        ]);
        $this->assertDatabaseMissing('produccion_registros', ['reporte_id' => $reporte->id, 'maquina_id' => $otra->id]);
    }

    public function test_la_parada_hereda_la_maquina_asignada(): void
    {
        $soplador = $this->soplador();
        $asignada = $this->maquina(nombre: 'Asignada');
        $otra = $this->maquina(nombre: 'Otra');
        $reporte = $this->reporteDe($soplador, $asignada, $this->tipo());

        $this->actingAs($soplador)->post(route('produccion.mi.paradas.store', $reporte), [
            'parada_maquina_id' => $otra->id, // cola offline vieja: se ignora
            'parada_motivo' => 'Falla de máquina', 'parada_origen' => 'maquina',
            'parada_inicio' => '10:00', 'parada_fin' => '10:20',
        ])->assertRedirect(route('produccion.mi.show', $reporte));

        $this->assertDatabaseHas('produccion_paradas', ['reporte_id' => $reporte->id, 'maquina_id' => $asignada->id]);
    }

    public function test_tanda_offline_vieja_con_maquina_id_no_da_422(): void
    {
        // Una tanda encolada ANTES del cambio todavía trae maquina_id (incluso
        // de una máquina hoy inactiva). Ya no se valida: entra con el combo
        // asignado. Un 422 la perdería en silencio (rechazo permanente de la
        // cola, sin UI de rechazadas).
        $soplador = $this->soplador();
        $asignada = $this->maquina();
        $inactiva = $this->maquina(nombre: 'Inactiva', activa: false);
        $reporte = $this->reporteDe($soplador, $asignada, $this->tipo());

        $this->actingAs($soplador)->postJson(route('produccion.mi.registros.store', $reporte), [
            'cliente_uuid' => (string) Str::uuid(),
            'maquina_id' => $inactiva->id, 'tipo_botellon_id' => 999,
            'primera' => 10, 'segunda' => 0, 'malo' => 0, 'danada' => 0,
        ])->assertOk();

        $this->assertDatabaseHas('produccion_registros', ['reporte_id' => $reporte->id, 'maquina_id' => $asignada->id, 'primera' => 10]);
    }

    // --- Las pantallas ---------------------------------------------------------

    public function test_mi_reporte_no_ofrece_elegir_maquina_ni_tipo_y_muestra_lo_asignado(): void
    {
        $soplador = $this->soplador();
        $this->maquina(nombre: 'Sopladora 7');
        $reporte = $this->reporteDe($soplador, $this->maquina(nombre: 'Sopladora 3'), $this->tipo('AZUL-10L', 'Azul 10L retornable'));

        $html = $this->actingAs($soplador)->get(route('produccion.mi.show', $reporte))->assertOk()->getContent();

        // Ningún control de elección; la línea de solo lectura con el combo.
        $this->assertStringNotContainsString('name="maquina_id"', $html);
        $this->assertStringNotContainsString('name="tipo_botellon_id"', $html);
        $this->assertStringNotContainsString('name="parada_maquina_id"', $html);
        $this->assertStringContainsString('Sopladora 3 · Azul 10L retornable', $html);
        $this->assertStringNotContainsString('Sopladora 7', $html); // la otra máquina de la sucursal no aparece
    }

    public function test_mi_reporte_cerrado_tambien_muestra_lo_asignado(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, $this->maquina(nombre: 'Sopladora 3'), $this->tipo('AZUL-10L', 'Azul 10L retornable'));
        $reporte->update(['estado' => ProduccionReporte::APROBADO]);

        $this->actingAs($soplador)->get(route('produccion.mi.show', $reporte))
            ->assertOk()
            ->assertSee('Sopladora 3 · Azul 10L retornable');
    }

    public function test_el_jefe_ve_maquina_y_tipo_asignados_en_el_detalle(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, $this->maquina(nombre: 'Sopladora 3'), $this->tipo('AZUL-10L', 'Azul 10L retornable'));

        $this->actingAs($this->jefe())->get(route('admin.produccion.reporte.show', $reporte))
            ->assertOk()
            ->assertSee('Máquina y tipo asignados')
            ->assertSee('Sopladora 3 · Azul 10L retornable');
    }
}
