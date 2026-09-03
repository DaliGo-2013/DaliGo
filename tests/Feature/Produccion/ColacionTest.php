<?php

namespace Tests\Feature\Produccion;

use App\Models\Configuracion;
use App\Models\Maquina;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionParada;
use App\Models\ProduccionReporte;
use App\Models\Sucursal;
use App\Models\TipoBotellon;
use App\Models\User;
use App\Support\FechaNegocio;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Colación del soplador (dueño 02-09): «Salir a colación» / «Volví». Es una
 * PARADA PLANIFICADA de origen operario, sin máquina, con la hora de negocio
 * del servidor — reusa las Paradas del turno: el jefe la ve en «Hoy en vivo»
 * con el reloj, el OEE no la descuenta (planificada) y el envío del reporte la
 * cierra si el soplador olvidó «Volví».
 */
class ColacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        // Mediodía chileno fijo (16:00 UTC = 12:00 en Chile, UTC-4): la hora
        // de la colación es la de NEGOCIO, no la del servidor.
        $this->travelTo(Carbon::parse('2026-09-02 16:00:00', 'UTC'));
    }

    private function soplador(): User
    {
        return tap(User::factory()->create())->assignRole('soplador');
    }

    private function reporteDe(User $soplador, string $estado = ProduccionReporte::BORRADOR): ProduccionReporte
    {
        $fecha = FechaNegocio::hoy();
        $asignacion = ProduccionAsignacion::create(['soplador_id' => $soplador->id, 'fecha' => $fecha, 'turno' => 'dia', 'asignadas' => 100]);

        return ProduccionReporte::create([
            'asignacion_id' => $asignacion->id, 'soplador_id' => $soplador->id, 'fecha' => $fecha,
            'turno' => 'dia', 'asignadas' => 100, 'estado' => $estado,
        ]);
    }

    private function salir(User $s, ProduccionReporte $r)
    {
        return $this->actingAs($s)->post(route('produccion.mi.colacion.salir', $r));
    }

    private function volver(User $s, ProduccionReporte $r)
    {
        return $this->actingAs($s)->patch(route('produccion.mi.colacion.volver', $r));
    }

    public function test_salir_registra_una_parada_planificada_de_operario_sin_maquina(): void
    {
        $s = $this->soplador();
        $r = $this->reporteDe($s);

        $this->salir($s, $r)->assertRedirect(route('produccion.mi.show', $r));

        $this->assertDatabaseHas('produccion_paradas', [
            'reporte_id' => $r->id,
            'motivo' => ProduccionParada::MOTIVO_COLACION,
            'clase' => ProduccionParada::CLASE_PLANIFICADA,
            'origen' => 'operario',
            'maquina_id' => null,
            'fin' => null,
        ]);
        $this->assertSame('12:00', $r->colacionAbierta()->inicio_corta);

        $html = $this->actingAs($s)->get(route('produccion.mi.show', $r))->assertOk()->getContent();
        $this->assertStringContainsString('En colación desde las 12:00', $html);
        $this->assertStringContainsString('Volví', $html);
        $this->assertStringNotContainsString('Salir a colación', $html);
    }

    public function test_volver_cierra_la_colacion_con_la_hora_de_negocio(): void
    {
        $s = $this->soplador();
        $r = $this->reporteDe($s);
        $this->salir($s, $r);

        $this->travel(32)->minutes();
        $this->volver($s, $r)->assertRedirect(route('produccion.mi.show', $r));

        $parada = $r->paradas()->first();
        $this->assertSame('12:32', $parada->fin_corta);
        $this->assertSame(32, $parada->duracion_minutos);
        $this->assertNull($r->colacionAbierta());

        $html = $this->actingAs($s)->get(route('produccion.mi.show', $r))->assertOk()->getContent();
        $this->assertStringContainsString('Salir a colación', $html);
        $this->assertStringNotContainsString('En colación desde', $html);
    }

    public function test_salir_dos_veces_no_duplica(): void
    {
        $s = $this->soplador();
        $r = $this->reporteDe($s);

        $this->salir($s, $r);
        $this->travel(5)->minutes();
        $this->salir($s, $r);

        $this->assertSame(1, $r->paradas()->count());
        $this->assertSame('12:00', $r->colacionAbierta()->inicio_corta); // la primera manda
    }

    public function test_volver_sin_colacion_abierta_no_rompe(): void
    {
        $s = $this->soplador();
        $r = $this->reporteDe($s);

        $this->volver($s, $r)->assertRedirect(route('produccion.mi.show', $r));

        $this->assertSame(0, $r->paradas()->count());
    }

    public function test_reporte_ajeno_y_reporte_enviado_dan_403(): void
    {
        $s = $this->soplador();
        $ajeno = $this->reporteDe($this->soplador());
        $enviado = $this->reporteDe($s, ProduccionReporte::ENVIADO);

        // 403 CRUDO como lo ve la app (JSON): el form navegable, por D-014,
        // se va al Inicio con aviso — mismo idioma que ProduccionParadasTest.
        $this->actingAs($s)->postJson(route('produccion.mi.colacion.salir', $ajeno))->assertForbidden();
        $this->actingAs($s)->patchJson(route('produccion.mi.colacion.volver', $ajeno))->assertForbidden();
        $this->actingAs($s)->postJson(route('produccion.mi.colacion.salir', $enviado))->assertForbidden();
        $this->actingAs($s)->patchJson(route('produccion.mi.colacion.volver', $enviado))->assertForbidden();
        $this->assertSame(0, ProduccionParada::count());
    }

    public function test_enviar_el_reporte_cierra_la_colacion_olvidada(): void
    {
        $s = $this->soplador();
        $r = $this->reporteDe($s);
        // Una tanda para poder enviar (la pantalla exige total > 0).
        $suc = Sucursal::firstOrCreate(['codigo' => 'MIRADOR'], ['nombre' => 'Mirador']);
        $m = Maquina::create(['nombre' => 'Sopladora 1', 'sucursal_id' => $suc->id, 'activa' => true]);
        $t = TipoBotellon::firstOrCreate(['codigo' => 'AZUL-20L'], ['nombre' => 'Azul 20L', 'activo' => true]);
        $this->actingAs($s)->post(route('produccion.mi.registros.store', $r), [
            'maquina_id' => $m->id, 'tipo_botellon_id' => $t->id, 'primera' => 100, 'segunda' => 0, 'malo' => 0, 'danada' => 0,
        ])->assertSessionHasNoErrors();

        $this->salir($s, $r);
        $this->travel(40)->minutes();

        $this->actingAs($s)->patch(route('produccion.mi.update', $r), ['enviar' => 1])->assertSessionHasNoErrors();

        $parada = $r->paradas()->where('motivo', ProduccionParada::MOTIVO_COLACION)->first();
        $this->assertSame('12:40', $parada->fin_corta);
        $this->assertTrue($parada->cerrada_al_envio);
    }

    public function test_la_clase_de_colacion_es_planificada_aunque_la_perilla_no_la_liste(): void
    {
        Configuracion::create([
            'clave' => 'produccion_motivos_planificados', 'valor' => json_encode(['Cambio de molde']),
            'tipo' => Configuracion::TIPO_JSON, 'grupo' => 'produccion', 'descripcion' => 'test',
        ]);

        $this->assertSame(ProduccionParada::CLASE_PLANIFICADA, ProduccionParada::claseDe(ProduccionParada::MOTIVO_COLACION));
        // Y la lista sigue mandando para los demás motivos.
        $this->assertSame(ProduccionParada::CLASE_NO_PLANIFICADA, ProduccionParada::claseDe('Mantención de máquina'));
    }

    public function test_el_jefe_la_ve_en_vivo_con_el_reloj(): void
    {
        $s = $this->soplador();
        $r = $this->reporteDe($s);
        $this->salir($s, $r);
        $this->travel(23)->minutes();

        $jefe = tap(User::factory()->create())->assignRole('jefe_bodega');
        $html = $this->actingAs($jefe)->get(route('admin.produccion.vivo'))->assertOk()->getContent();

        $this->assertStringContainsString('Colación', $html);
        $this->assertStringContainsString('Operario', $html); // sin máquina: no sale con hueco
        $this->assertStringContainsString('desde las 12:00', $html);
        $this->assertStringContainsString('23 min', $html);
    }

    public function test_los_botones_se_apagan_sin_senal(): void
    {
        $s = $this->soplador();
        $r = $this->reporteDe($s);

        $html = $this->actingAs($s)->get(route('produccion.mi.show', $r))->assertOk()->getContent();
        $this->assertStringContainsString('x-bind:disabled="! $store.red.online"', $html);
        $this->assertStringContainsString('Necesita señal.', $html);
    }
}
