<?php

namespace Tests\Feature;

use App\Models\ProduccionAsignacion;
use App\Models\ProduccionReporte;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * «Mi semana» del operario en el Inicio (pedido del dueño 01-09): cinco cards
 * L-V de la semana en curso — el día y el % de 1ª en grande, «pidieron X ·
 * 1ª · 2ª · malas» en chico — más una sexta con el mes CALENDARIO acumulado.
 * Reemplaza a la mini-serie de 7 días del 31-08 (cuyos candados murieron con
 * esa tarjeta).
 *
 * Todo con travelTo a fechas FIJAS: semana y mes son bordes de calendario y
 * un fixture relativo a hoy se pone rojo solo (familia [2026-07-31]).
 */
class DashboardSopladorSemanaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function soplador(): User
    {
        return tap(User::factory()->create())->assignRole('soplador');
    }

    /** Asignación + reporte del soplador en una fecha, con sus cifras. */
    private function reporteDe(User $soplador, string $fecha, int $asignadas, array $cifras = []): ProduccionReporte
    {
        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id, 'fecha' => $fecha, 'turno' => 'dia', 'asignadas' => $asignadas,
        ]);

        return ProduccionReporte::create($cifras + [
            'asignacion_id' => $asignacion->id, 'soplador_id' => $soplador->id, 'fecha' => $fecha,
            'turno' => 'dia', 'asignadas' => $asignadas, 'estado' => ProduccionReporte::APROBADO,
        ]);
    }

    private function viajarA(string $fecha): void
    {
        // Mediodía UTC = misma fecha en Chile (idioma del TestCase base).
        $this->travelTo(Carbon::parse($fecha.' 12:00:00', 'UTC'));
    }

    public function test_las_cinco_cards_son_la_semana_en_curso_y_solo_lo_mio(): void
    {
        $this->viajarA('2026-09-02'); // miércoles
        $yo = $this->soplador();
        $otro = $this->soplador();

        // Lunes: el ejemplo literal del dueño — pidieron 700; 680 de 1ª, 15 de
        // 2ª, 5 malas (3 malos + 2 dañadas) → 97 % de 1ª sobre lo procesado.
        $this->reporteDe($yo, '2026-08-31', 700, ['primera' => 680, 'segunda' => 15, 'malo' => 3, 'danada' => 2]);
        // El OTRO soplador el mismo lunes: no es mío, no suma.
        $this->reporteDe($otro, '2026-08-31', 999, ['primera' => 999]);
        // Miércoles (hoy) con producción; martes sin nada.
        $this->reporteDe($yo, '2026-09-02', 500, ['primera' => 400, 'segunda' => 100]);

        $res = $this->actingAs($yo)->get('/dashboard')->assertOk();
        $semana = $res->viewData('semanaSoplador');

        $this->assertCount(5, $semana['dias']);
        [$lun, $mar, $mie, $jue, $vie] = $semana['dias'];

        $this->assertSame('con', $lun['estado']);
        $this->assertSame(97, $lun['tasa1']);
        $this->assertSame(700, $lun['asignadas']);
        $this->assertSame(680, $lun['primera']);
        $this->assertSame(15, $lun['segunda']);
        $this->assertSame(5, $lun['malas']); // malos + dañadas, un solo número
        $this->assertFalse($lun['esHoy']);

        $this->assertSame('sin', $mar['estado']);
        $this->assertSame('con', $mie['estado']);
        $this->assertTrue($mie['esHoy']);
        $this->assertSame(80, $mie['tasa1']);
        $this->assertSame('futuro', $jue['estado']);
        $this->assertSame('futuro', $vie['estado']);

        $res->assertSee('Lunes 31')
            ->assertSee('97%')
            ->assertSee('Pidieron 700 · 680 de 1ª · 15 de 2ª · 5 malas')
            ->assertSee('Mi semana · 31-08 al 04-09')
            ->assertSee('Aún no llega')
            ->assertSee('Sin producción');
    }

    public function test_el_fin_de_semana_no_tiene_card_pero_suma_al_mes(): void
    {
        $this->viajarA('2026-09-02');
        $yo = $this->soplador();
        // Sábado 5 de septiembre: fuera de las cards L-V, dentro del mes.
        $this->reporteDe($yo, '2026-09-05', 100, ['primera' => 100]);

        $semana = $this->actingAs($yo)->get('/dashboard')->viewData('semanaSoplador');

        // Hoy (miércoles) queda «sin»; jueves y viernes, futuro. Ninguna card
        // trae el sábado.
        $this->assertSame(['sin', 'sin', 'sin', 'futuro', 'futuro'], array_column($semana['dias'], 'estado'));
        $this->assertSame(0, array_sum(array_column($semana['dias'], 'primera')));
        $this->assertSame(100, $semana['mes']['primera']);
        $this->assertSame('con', $semana['mes']['estado']);
    }

    public function test_el_mes_es_calendario_exacto_en_los_bordes(): void
    {
        $yo = $this->soplador();
        // Los cuatro bordes de septiembre: día 1 y 30 DENTRO; 31-08 y 01-10 FUERA.
        $this->reporteDe($yo, '2026-08-31', 100, ['primera' => 1]);
        $this->reporteDe($yo, '2026-09-01', 100, ['primera' => 10]);
        $this->reporteDe($yo, '2026-09-30', 100, ['primera' => 20]);
        $this->reporteDe($yo, '2026-10-01', 100, ['primera' => 1000]);

        // Y se mira desde tres días peligrosos del mismo mes.
        foreach (['2026-09-01', '2026-09-15', '2026-09-30'] as $hoy) {
            $this->viajarA($hoy);
            $mes = $this->actingAs($yo)->get('/dashboard')->viewData('semanaSoplador')['mes'];
            $this->assertSame(30, $mes['primera'], "Visto desde {$hoy}: el mes debe sumar SOLO el 1 y el 30 de septiembre.");
            $this->assertSame(200, $mes['asignadas']);
            $this->assertSame('Septiembre', $mes['rotulo']);
        }
    }

    public function test_la_semana_parte_el_lunes_aunque_hoy_sea_domingo(): void
    {
        $this->viajarA('2026-09-06'); // domingo
        $yo = $this->soplador();
        $this->reporteDe($yo, '2026-08-31', 100, ['primera' => 100]); // el lunes de ESA semana

        $semana = $this->actingAs($yo)->get('/dashboard')->viewData('semanaSoplador');

        // Domingo cierra la semana que partió el lunes 31: ningún día es futuro.
        $this->assertSame('31-08 al 04-09', $semana['rangoSemana']);
        $this->assertSame('con', $semana['dias'][0]['estado']);
        $this->assertNotContains('futuro', array_column($semana['dias'], 'estado'));
    }

    public function test_sin_permiso_de_reporte_no_hay_semana(): void
    {
        $jefe = tap(User::factory()->create())->assignRole('jefe_bodega');

        $res = $this->actingAs($jefe)->get('/dashboard')->assertOk();

        $this->assertNull($res->viewData('semanaSoplador'));
        $res->assertDontSee('Mi semana ·');
    }

    public function test_soplador_nuevo_ve_las_seis_cards_honestas_en_cero(): void
    {
        $this->viajarA('2026-09-02');

        $res = $this->actingAs($this->soplador())->get('/dashboard')->assertOk();
        $semana = $res->viewData('semanaSoplador');

        $this->assertCount(5, $semana['dias']);
        $this->assertSame(['sin', 'sin', 'sin', 'futuro', 'futuro'], array_column($semana['dias'], 'estado'));
        $this->assertSame('sin', $semana['mes']['estado']);
        $res->assertSee('Sin producción este mes');
    }

    public function test_los_rotulos_salen_en_espanol(): void
    {
        // Protege el locale de Carbon en esta superficie (IdiomaEspanolTest
        // no visita el Inicio del soplador).
        $this->viajarA('2026-09-02');

        $res = $this->actingAs($this->soplador())->get('/dashboard')->assertOk();

        $res->assertSee('Miércoles 2')
            ->assertSee('Septiembre')
            ->assertDontSee('Wednesday')
            ->assertDontSee('September');
    }

    public function test_es_solo_para_sopladores_aunque_el_jefe_tenga_el_permiso(): void
    {
        // Dueño 02-09: entró como jefe de bodega y como admin y vio las cards
        // y el botón. El permiso `report production` no alcanza — la vista es
        // por ROL soplador (paramétrico produccion_roles_soplador, la misma
        // fuente que el selector de asignar producción).
        $this->viajarA('2026-09-02');
        // La perilla se siembra ANTES de la primera consulta: Configuracion::get
        // cachea «clave ausente» y create() no invalida ese cache (set() sí).
        \App\Models\Configuracion::create([
            'clave' => 'produccion_roles_soplador', 'valor' => json_encode(['soplador']),
            'tipo' => \App\Models\Configuracion::TIPO_JSON, 'grupo' => 'produccion', 'descripcion' => 'test',
        ]);

        foreach (['jefe_bodega', 'admin'] as $rol) {
            $u = tap(User::factory()->create())->assignRole($rol);
            $u->givePermissionTo('report production');

            $res = $this->actingAs($u)->get('/dashboard')->assertOk();
            $this->assertNull($res->viewData('semanaSoplador'), "El rol {$rol} no debe ver la semana del soplador.");
            $res->assertDontSee('Mi semana ·')->assertDontSee('Ir a Mi producción');
        }

        // Y la perilla manda: si el negocio declara que los jefes de bodega
        // también sopla, la vista aparece — sin tocar código.
        \App\Models\Configuracion::set('produccion_roles_soplador', json_encode(['soplador', 'jefe_bodega']));
        $jefe = tap(User::factory()->create())->assignRole('jefe_bodega');
        $jefe->givePermissionTo('report production');

        $res = $this->actingAs($jefe)->get('/dashboard')->assertOk();
        $this->assertNotNull($res->viewData('semanaSoplador'));
        $res->assertSee('Ir a Mi producción');
    }
}
