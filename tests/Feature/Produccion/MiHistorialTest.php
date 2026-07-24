<?php

namespace Tests\Feature\Produccion;

use App\Models\ProduccionAsignacion;
use App\Models\ProduccionReporte;
use App\Models\User;
use App\Support\FechaNegocio;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Historial propio del soplador (tarjeta 02-07): ultimos 45 dias por defecto,
 * con filtro desde/hasta.
 *
 * Doctrina anti verde-enganoso (bitacora 2026-07-20): se asserta por
 * viewData('reportes')->pluck('id') EXACTO (mata las mutaciones "muestra todo" /
 * "muestra nada", que un assertSee de texto no ve) y por href COMPLETO con la
 * comilla de cierre (/mi-reporte/1 vive dentro de /mi-reporte/12). Los numeros
 * 45/44/180 van LITERALES: si alguien cambia la constante sin decision del dueno,
 * estos tests se ponen rojos (derivarlos de la constante seria verde enganoso).
 */
class MiHistorialTest extends TestCase
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

    /** Reporte de un dia concreto (Y-m-d), con cantidades opcionales. */
    private function reporteEn(User $soplador, string $fecha, array $cantidades = [], string $estado = ProduccionReporte::APROBADO): ProduccionReporte
    {
        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id,
            'fecha' => $fecha,
            'turno' => 'dia',
            'asignadas' => 100,
        ]);

        return ProduccionReporte::create(array_merge([
            'asignacion_id' => $asignacion->id,
            'soplador_id' => $soplador->id,
            'fecha' => $fecha,
            'turno' => 'dia',
            'asignadas' => 100,
            'estado' => $estado,
        ], $cantidades));
    }

    /** El dia de negocio de hoy menos N dias, en Y-m-d. */
    private function hoyMenos(int $dias): string
    {
        return FechaNegocio::ahora()->startOfDay()->subDays($dias)->toDateString();
    }

    private function idsDe($response): array
    {
        return $response->viewData('reportes')->pluck('id')->all();
    }

    public function test_default_son_45_dias_e_incluye_el_borde_inferior(): void
    {
        $soplador = $this->soplador();
        $hoy = $this->reporteEn($soplador, $this->hoyMenos(0));
        $borde = $this->reporteEn($soplador, $this->hoyMenos(44));   // dentro
        $fuera = $this->reporteEn($soplador, $this->hoyMenos(45));   // fuera

        $res = $this->actingAs($soplador)->get(route('produccion.mi.historial'))->assertOk();

        // Orden: fecha desc → hoy primero, borde despues; el de 45 dias no esta.
        $this->assertSame([$hoy->id, $borde->id], $this->idsDe($res));
        $res->assertDontSee('href="'.route('produccion.mi.show', $fuera).'"', false);
    }

    public function test_el_default_precarga_los_inputs_con_la_ventana(): void
    {
        $soplador = $this->soplador();

        $this->actingAs($soplador)->get(route('produccion.mi.historial'))
            ->assertOk()
            ->assertSee('value="'.$this->hoyMenos(44).'"', false)  // desde
            ->assertSee('value="'.$this->hoyMenos(0).'"', false);  // hasta
    }

    public function test_el_filtro_acota_el_rango_en_los_dos_bordes(): void
    {
        $soplador = $this->soplador();
        $desde = $this->hoyMenos(20);
        $hasta = $this->hoyMenos(10);

        $antes = $this->reporteEn($soplador, $this->hoyMenos(21));
        $primero = $this->reporteEn($soplador, $desde);
        $ultimo = $this->reporteEn($soplador, $hasta);
        $despues = $this->reporteEn($soplador, $this->hoyMenos(9));

        $res = $this->actingAs($soplador)
            ->get(route('produccion.mi.historial', ['desde' => $desde, 'hasta' => $hasta]))
            ->assertOk();

        $this->assertSame([$ultimo->id, $primero->id], $this->idsDe($res));
        $res->assertDontSee('href="'.route('produccion.mi.show', $antes).'"', false);
        $res->assertDontSee('href="'.route('produccion.mi.show', $despues).'"', false);

        // ECO del rango pedido: la lista puede estar bien y el formulario/titulo
        // mostrar OTRO rango (hueco cazado por el gate R-31 — hardcodear el
        // default dejaba la suite verde con el operario viendo un rango falso).
        $res->assertSee('value="'.$desde.'"', false);
        $res->assertSee('value="'.$hasta.'"', false);
    }

    public function test_el_filtro_activo_ofrece_volver_al_default(): void
    {
        // La unica salida del filtro para un operario en planta (sin ella queda
        // atrapado en su rango). Sin filtro no se ofrece (no aplica).
        $soplador = $this->soplador();
        $volver = 'Volver a los últimos 45 días';

        $this->actingAs($soplador)
            ->get(route('produccion.mi.historial', ['desde' => $this->hoyMenos(3)]))
            ->assertOk()
            ->assertSee($volver);

        $this->actingAs($soplador)->get(route('produccion.mi.historial'))
            ->assertOk()
            ->assertDontSee($volver);
    }

    public function test_un_rango_de_un_solo_dia_incluye_el_reporte_de_ese_dia(): void
    {
        // CANDADO anti-whereBetween: la columna `fecha` es cast date y se guarda
        // "Y-m-d 00:00:00", asi que un whereBetween deja fuera el borde superior
        // (bitacora 2026-07-01 y su reincidencia del 07-02). Con desde == hasta
        // el bug se manifiesta como lista vacia.
        $soplador = $this->soplador();
        $dia = $this->hoyMenos(5);
        $reporte = $this->reporteEn($soplador, $dia);

        $res = $this->actingAs($soplador)
            ->get(route('produccion.mi.historial', ['desde' => $dia, 'hasta' => $dia]))
            ->assertOk();

        $this->assertSame([$reporte->id], $this->idsDe($res));
    }

    public function test_el_soplador_no_ve_el_historial_de_otro_soplador(): void
    {
        $yo = $this->soplador();
        $otro = $this->soplador();

        $mio = $this->reporteEn($yo, $this->hoyMenos(3));
        $ajeno = $this->reporteEn($otro, $this->hoyMenos(3));

        $res = $this->actingAs($yo)->get(route('produccion.mi.historial'))->assertOk();

        $this->assertSame([$mio->id], $this->idsDe($res));
        $res->assertDontSee('href="'.route('produccion.mi.show', $ajeno).'"', false);
    }

    public function test_sin_permiso_report_production_no_entra(): void
    {
        $sinPermiso = tap(User::factory()->create())->assignRole('member');

        $this->actingAs($sinPermiso)->get(route('produccion.mi.historial'))->assertForbidden();
    }

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get(route('produccion.mi.historial'))->assertRedirect(route('login'));
    }

    public function test_la_ruta_del_historial_no_la_captura_mi_show(): void
    {
        // El {reporte} de mi.show captura cualquier segmento: si el historial
        // colgara de mi-reporte/, el binding buscaria el id "historial" (404) y
        // el resultado dependeria del matcher (route:cache en prod vs orden de
        // registro en local). Este test fija que la URL resuelve a SU vista.
        $soplador = $this->soplador();

        $this->actingAs($soplador)->get('/produccion/mi-historial')
            ->assertOk()
            ->assertViewIs('produccion.mi-historial');
    }

    public function test_mi_produccion_enlaza_al_historial_aunque_no_haya_produccion_hoy(): void
    {
        $soplador = $this->soplador();
        $enlace = 'href="'.route('produccion.mi.historial').'"';

        // Sin produccion hoy: el enlace debe estar IGUAL (rojo si queda dentro
        // del @else de la lista).
        $this->actingAs($soplador)->get(route('produccion.mi.index'))
            ->assertOk()
            ->assertSee('No tienes producciones asignadas para hoy')
            ->assertSee($enlace, false);

        // Y con produccion de hoy tambien.
        $this->reporteEn($soplador, $this->hoyMenos(0), [], ProduccionReporte::BORRADOR);

        $this->actingAs($soplador)->get(route('produccion.mi.index'))
            ->assertOk()
            ->assertSee($enlace, false);
    }

    public function test_puede_abrir_un_reporte_antiguo_propio(): void
    {
        $soplador = $this->soplador();
        $viejo = $this->reporteEn($soplador, $this->hoyMenos(30));

        $this->actingAs($soplador)->get(route('produccion.mi.show', $viejo))->assertOk();
    }

    public function test_no_puede_abrir_un_reporte_antiguo_ajeno(): void
    {
        $yo = $this->soplador();
        $ajeno = $this->reporteEn($this->soplador(), $this->hoyMenos(30));

        $this->actingAs($yo)->get(route('produccion.mi.show', $ajeno))->assertForbidden();
    }

    /**
     * Toda la familia de basura cae al default SIN 500. Cada caso es un modo de
     * fallo distinto y real (los 3 ultimos los caza el gate R-31):
     *  - 'chao'/'' : no parece fecha (lo rechaza cualquier guarda).
     *  - '99999-01-01' : el patron 'Y' de Carbon::hasFormat acepta 5 digitos pero
     *    el 'Y' de createFromFormat consume 4 => lanzaba InvalidFormatException.
     *  - '2026-02-31' : dia valido para el regex, inexistente en el mes => antes
     *    se DESBORDABA callado a 2026-03-03 (rango que el operario no pidio).
     *  - '2026-07-19extra' : basura pegada a una fecha valida.
     */
    public static function fechasIlegibles(): array
    {
        return [
            'texto' => ['chao'],
            'vacio' => [''],
            'anio de 5 digitos' => ['99999-01-01'],
            'dia inexistente' => ['2026-02-31'],
            'sufijo pegado' => ['2026-07-19extra'],
            'separadores raros' => ['2026/07/19'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('fechasIlegibles')]
    public function test_fecha_ilegible_cae_al_default_sin_romper(string $basura): void
    {
        $soplador = $this->soplador();

        $this->actingAs($soplador)
            ->get(route('produccion.mi.historial', ['desde' => $basura, 'hasta' => '']))
            ->assertOk()   // rojo con 500 si la guarda deja pasar algo no parseable
            ->assertSee('value="'.$this->hoyMenos(44).'"', false);
    }

    public function test_un_hasta_futuro_no_deja_la_ventana_en_el_futuro(): void
    {
        // Sin techo, un 'hasta' lejano empujaba el piso (clamp de 180 dias anclado
        // en hasta) y la ventana quedaba ENTERA en el futuro: lista vacia y ambos
        // inputs repintados con fechas futuras, sin explicacion. Alcanzable desde
        // el date picker, no hace falta editar la URL.
        $soplador = $this->soplador();
        $reciente = $this->reporteEn($soplador, $this->hoyMenos(1));

        $res = $this->actingAs($soplador)
            ->get(route('produccion.mi.historial', ['hasta' => '9999-12-31']))
            ->assertOk();

        $this->assertSame([$reciente->id], $this->idsDe($res));
        $res->assertSee('value="'.$this->hoyMenos(0).'"', false);   // hasta = hoy
        $res->assertDontSee('value="9999-12-31"', false);
    }

    public function test_rango_invertido_se_ordena_en_vez_de_rechazar(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteEn($soplador, $this->hoyMenos(5));

        $res = $this->actingAs($soplador)
            ->get(route('produccion.mi.historial', [
                'desde' => $this->hoyMenos(0),   // invertidos a proposito
                'hasta' => $this->hoyMenos(10),
            ]))
            ->assertOk();

        $this->assertSame([$reporte->id], $this->idsDe($res));
    }

    public function test_el_tope_de_ventana_recorta_a_180_dias(): void
    {
        $soplador = $this->soplador();
        $dentro = $this->reporteEn($soplador, $this->hoyMenos(179));
        $fuera = $this->reporteEn($soplador, $this->hoyMenos(200));

        $res = $this->actingAs($soplador)
            ->get(route('produccion.mi.historial', ['desde' => '2015-01-01', 'hasta' => $this->hoyMenos(0)]))
            ->assertOk();

        $this->assertSame([$dentro->id], $this->idsDe($res));
        $res->assertDontSee('href="'.route('produccion.mi.show', $fuera).'"', false);
    }

    public function test_los_totales_suman_solo_el_rango_y_no_confunden_vendibles_con_consumido(): void
    {
        $soplador = $this->soplador();
        // Dentro: vendibles 30+20=50, merma 5+3=8 (consumido seria 58).
        $this->reporteEn($soplador, $this->hoyMenos(2), [
            'primera' => 30, 'segunda' => 20, 'malo' => 5, 'danada' => 3,
        ]);
        // Fuera de la ventana: no debe sumar.
        $this->reporteEn($soplador, $this->hoyMenos(50), [
            'primera' => 999, 'segunda' => 999, 'malo' => 999, 'danada' => 999,
        ]);

        $totales = $this->actingAs($soplador)
            ->get(route('produccion.mi.historial'))
            ->assertOk()
            ->viewData('totales');

        $this->assertSame(50, $totales['vendibles']); // rojo si suma `total` (58)
        $this->assertSame(8, $totales['merma']);
        $this->assertSame(1, $totales['turnos']);
    }

    public function test_el_historial_no_carga_las_tandas_de_cada_reporte(): void
    {
        // Rojo si alguien pega el with(['registros.maquina', ...]) del historial
        // admin: en 45 dias serian cientos de filas cargadas para nada.
        $soplador = $this->soplador();
        $this->reporteEn($soplador, $this->hoyMenos(1));

        $primero = $this->actingAs($soplador)
            ->get(route('produccion.mi.historial'))
            ->assertOk()
            ->viewData('reportes')
            ->first();

        $this->assertFalse($primero->relationLoaded('registros'));
    }
}
