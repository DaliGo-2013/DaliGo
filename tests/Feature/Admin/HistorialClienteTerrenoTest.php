<?php

namespace Tests\Feature\Admin;

use App\Models\AgendaTrabajo;
use App\Models\AgendaTrabajoRepuesto;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * QUÉ SE LE HIZO A CADA CLIENTE (pedido del técnico Carlos, 14-08-2026): en el
 * informe industrial, «Clientes que más solicitan» decía cuántas veces vino cada
 * cliente pero no qué se hizo en esas visitas — y eso es lo que hace falta cuando
 * el cliente llama de vuelta: «a esa lavadora ya le cambiamos los rodamientos en
 * junio».
 *
 * Ahora cada cliente se despliega y muestra su historial del período, con el texto
 * que el propio técnico escribió al cerrar («cambio de rodamientos lavadora»,
 * «cambio de membranas osmosis») y los repuestos que declaró.
 *
 * Lo que estos candados protegen:
 *
 * 1. QUE EL HISTORIAL SEA DEL CLIENTE QUE DICE SER. El ranking agrupa en SQL y el
 *    historial agrupa la misma colección en PHP: si las dos reglas se separan, el
 *    detalle deja de corresponder con el número que tiene al lado y NADA lo
 *    delata. Por eso la regla vive una sola vez en el modelo, y hay un test que
 *    compara las dos formas.
 * 2. Que respete el período elegido, que es lo que le da sentido al «en el mes o
 *    en el año» que pidió Carlos.
 * 3. Que la etiqueta del detalle se derive del estado de CADA trabajo: en un
 *    historial mezclado, un pendiente rotulado «Lo que se hizo» es información
 *    falsa sobre trabajo que todavía no existe.
 */
class HistorialClienteTerrenoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Mail::fake();
    }

    private function admin(): User
    {
        return tap(User::factory()->create())->assignRole('admin');
    }

    /** @param  array<string, mixed>  $extra */
    private function trabajo(array $extra = []): AgendaTrabajo
    {
        // El `tipo` se FIJA: la factory lo sortea entre los 4 y el informe trata
        // distinto a la visita técnica (bitácora 2026-07-13 y el flaky del 14-08).
        return AgendaTrabajo::factory()->create(array_merge([
            'fecha' => '2026-08-10',
            'tipo' => 'mantencion',
            'estado' => 'realizado',
        ], $extra));
    }

    private function informe(array $filtros = ['anio' => 2026, 'mes' => 8])
    {
        return $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.informe.industrial', $filtros))
            ->assertOk();
    }

    // --- 1. El historial es del cliente correcto -----------------------------

    /**
     * EL CANDADO DE LA REGLA DUPLICADA. `SQL_CLAVE_CLIENTE` agrupa el ranking y
     * `claveCliente()` agrupa el historial: tienen que dar exactamente lo mismo
     * para todos los casos de borde (con RUT, sin RUT, RUT vacío).
     *
     * Muta con cualquier divergencia: cambiar una de las dos —normalizar el RUT en
     * PHP, o quitarle el NULLIF al SQL— pone este test rojo.
     */
    public function test_la_clave_del_cliente_es_la_misma_en_sql_y_en_php(): void
    {
        $this->trabajo(['cliente_rut' => '11.111.111-1', 'cliente_nombre' => 'Con RUT SA']);
        $this->trabajo(['cliente_rut' => null, 'cliente_nombre' => 'Sin RUT SpA']);
        $this->trabajo(['cliente_rut' => '', 'cliente_nombre' => 'RUT vacío Ltda']);

        $enSql = DB::table('agenda_trabajos')
            ->selectRaw(AgendaTrabajo::SQL_CLAVE_CLIENTE.' AS clave, id')
            ->pluck('clave', 'id');

        $enPhp = AgendaTrabajo::all()->mapWithKeys(
            fn (AgendaTrabajo $t) => [$t->id => $t->claveCliente()]
        );

        $this->assertSame(
            $enSql->sort()->all(),
            $enPhp->sort()->all(),
            'El ranking (SQL) y el historial (PHP) están agrupando por claves distintas.'
        );
    }

    public function test_el_historial_de_un_cliente_no_trae_los_trabajos_de_otro(): void
    {
        $this->trabajo([
            'cliente_rut' => '11.111.111-1', 'cliente_nombre' => 'Aguas del Maule',
            'notas_tecnico' => 'Cambio de rodamientos lavadora.',
        ]);
        $this->trabajo([
            'cliente_rut' => '22.222.222-2', 'cliente_nombre' => 'Embotelladora Curicó',
            'notas_tecnico' => 'Cambio de membranas osmosis.',
        ]);

        $historial = $this->informe()->viewData('historialClientes');

        $this->assertCount(1, $historial['11.111.111-1'], 'El historial mezcló clientes.');
        $this->assertSame(
            'Cambio de rodamientos lavadora.',
            $historial['11.111.111-1']->first()->notas_tecnico
        );
        $this->assertSame(
            'Cambio de membranas osmosis.',
            $historial['22.222.222-2']->first()->notas_tecnico
        );
    }

    public function test_un_cliente_sin_rut_igual_tiene_historial(): void
    {
        $this->trabajo([
            'cliente_rut' => null, 'cliente_nombre' => 'Planta sin RUT',
            'notas_tecnico' => 'Cambio de sedimento osmosis.',
        ]);

        $historial = $this->informe()->viewData('historialClientes');

        // Sin RUT la clave es el nombre: el cliente que entró por el QR sin RUT
        // sigue siendo un cliente y su historial no puede desaparecer.
        $this->assertArrayHasKey('Planta sin RUT', $historial->all());
        $this->assertStringContainsString(
            'sedimento',
            (string) $historial['Planta sin RUT']->first()->notas_tecnico
        );
    }

    // --- 2. El período -------------------------------------------------------

    public function test_el_historial_respeta_el_mes_elegido(): void
    {
        $this->trabajo(['fecha' => '2026-08-10', 'cliente_rut' => '11.111.111-1', 'notas_tecnico' => 'Trabajo de agosto']);
        $this->trabajo(['fecha' => '2026-07-10', 'cliente_rut' => '11.111.111-1', 'notas_tecnico' => 'Trabajo de julio']);

        $agosto = $this->informe(['anio' => 2026, 'mes' => 8])->viewData('historialClientes');
        $this->assertCount(1, $agosto['11.111.111-1']);
        $this->assertSame('Trabajo de agosto', $agosto['11.111.111-1']->first()->notas_tecnico);

        // Sin mes = el AÑO completo, que es la otra mitad de lo que pidió Carlos.
        $anio = $this->informe(['anio' => 2026])->viewData('historialClientes');
        $this->assertCount(2, $anio['11.111.111-1'], 'El año completo tiene que traer los dos.');
    }

    public function test_el_historial_ordena_lo_mas_reciente_primero(): void
    {
        foreach (['2026-08-05', '2026-08-20', '2026-08-12'] as $fecha) {
            $this->trabajo(['fecha' => $fecha, 'cliente_rut' => '11.111.111-1', 'notas_tecnico' => "Visita {$fecha}"]);
        }

        $historial = $this->informe()->viewData('historialClientes');

        $this->assertSame(
            ['2026-08-20', '2026-08-12', '2026-08-05'],
            $historial['11.111.111-1']->map(fn ($t) => $t->fecha->toDateString())->all()
        );
    }

    // --- 3. Lo que se ve en la pantalla --------------------------------------

    public function test_la_pantalla_muestra_el_detalle_y_los_repuestos_de_cada_visita(): void
    {
        $t = $this->trabajo([
            'cliente_rut' => '11.111.111-1', 'cliente_nombre' => 'Aguas del Maule',
            'notas_tecnico' => 'Cambio de rodamientos lavadora y ajuste de correa.',
        ]);
        AgendaTrabajoRepuesto::create([
            'agenda_trabajo_id' => $t->id, 'nombre' => 'Rodamiento 6204', 'sku' => 'ROD-6204', 'cantidad' => 2,
        ]);

        $html = $this->informe()->getContent();

        $this->assertStringContainsString('Cambio de rodamientos lavadora', $html);
        $this->assertStringContainsString('Lo que se hizo', $html);
        $this->assertStringContainsString('2 × Rodamiento 6204', $html);
        // Y la fila es un botón desplegable, no texto muerto.
        $this->assertStringContainsString('aria-expanded', $html);
    }

    /**
     * El candado del punto 3. Un historial mezcla estados, así que la etiqueta se
     * deriva de CADA fila: si fuera una por lista, el trabajo agendado saldría
     * rotulado «Lo que se hizo» — afirmando que se hizo algo que no pasó.
     */
    public function test_en_un_historial_mezclado_cada_fila_lleva_su_propia_etiqueta(): void
    {
        $rut = '11.111.111-1';
        $this->trabajo(['cliente_rut' => $rut, 'estado' => 'realizado', 'notas_tecnico' => 'Cambié la membrana']);
        $this->trabajo(['cliente_rut' => $rut, 'estado' => 'no_realizado', 'notas_tecnico' => 'Faltaba el filtro']);
        $this->trabajo(['cliente_rut' => $rut, 'estado' => 'agendado', 'descripcion' => 'Cambiar sedimento']);

        $html = $this->informe()->getContent();

        $this->assertStringContainsString('Lo que se hizo', $html);
        $this->assertStringContainsString('Por qué no se pudo', $html);
        $this->assertStringContainsString('Lo que se va a realizar', $html);
    }

    /**
     * Los otros dos rankings de la página comparten el partial y NO son
     * desplegables: el cambio tenía que ser opcional para no tocarlos. Un «Visita
     * técnica 100%» que se abre en un panel vacío sería peor que antes.
     *
     * SE PRUEBA EL PARTIAL AISLADO, no la página: `aria-expanded` lo emiten también
     * el menú, los ⓘ y las tarjetas de la cabecera, así que contarlo sobre el HTML
     * completo mide el shell y no esto (mi primera versión contó 17 y esperaba 1).
     */
    public function test_el_ranking_sin_detalles_no_es_desplegable(): void
    {
        $items = collect([(object) ['clave' => 'x', 'nombre' => 'Visita técnica', 'cantidad' => 3]]);

        $conDetalle = view('admin.servicio-tecnico.partials._ranking', [
            'items' => $items,
            'detalles' => collect(['x' => AgendaTrabajo::factory()->count(1)->make()]),
        ])->render();

        $sinDetalle = view('admin.servicio-tecnico.partials._ranking', [
            'items' => $items,
        ])->render();

        // Con detalle: botón desplegable. Sin detalle: la fila de siempre.
        $this->assertStringContainsString('aria-expanded', $conDetalle);
        $this->assertStringContainsString('<button', $conDetalle);
        $this->assertStringNotContainsString('aria-expanded', $sinDetalle);
        $this->assertStringNotContainsString('<button', $sinDetalle);

        // Y lo que la fila SIEMPRE muestra sigue igual en los dos casos.
        foreach ([$conDetalle, $sinDetalle] as $html) {
            $this->assertStringContainsString('Visita técnica', $html);
            $this->assertStringContainsString('bg-brand-500', $html);
        }
    }

    /**
     * Un cliente del ranking sin historial (no debería pasar, pero el mapa podría
     * no traerlo) NO se vuelve un botón que abre un panel vacío.
     */
    public function test_una_fila_sin_historial_no_se_abre(): void
    {
        $html = view('admin.servicio-tecnico.partials._ranking', [
            'items' => collect([(object) ['clave' => 'sin-datos', 'nombre' => 'Cliente X', 'cantidad' => 1]]),
            'detalles' => collect(['otra-clave' => AgendaTrabajo::factory()->count(1)->make()]),
        ])->render();

        $this->assertStringContainsString('Cliente X', $html);
        $this->assertStringNotContainsString('aria-expanded', $html);
    }

    public function test_sin_trabajos_el_historial_viene_vacio(): void
    {
        $respuesta = $this->informe();

        $this->assertStringContainsString('Sin trabajos en el período.', $respuesta->getContent());
        $this->assertTrue($respuesta->viewData('historialClientes')->isEmpty());
    }

    public function test_el_historial_no_dispara_una_consulta_por_cliente(): void
    {
        // Cinco clientes con dos trabajos cada uno. El historial tiene que salir en
        // UNA consulta (más las de sus relaciones), no en una por cliente: el
        // ranking llega hasta 10 y el patrón N+1 crecería con él.
        for ($i = 1; $i <= 5; $i++) {
            foreach (['2026-08-05', '2026-08-06'] as $fecha) {
                $this->trabajo(['fecha' => $fecha, 'cliente_rut' => "1{$i}.111.111-1", 'notas_tecnico' => "Cliente {$i}"]);
            }
        }

        $admin = $this->admin();
        DB::enableQueryLog();
        $this->actingAs($admin)
            ->get(route('admin.servicio-tecnico.informe.industrial', ['anio' => 2026, 'mes' => 8]))
            ->assertOk();
        $consultas = collect(DB::getQueryLog());
        DB::disableQueryLog();

        // La firma del historial es que la CLAVE DEL CLIENTE sea la columna del IN.
        // Dos intentos anteriores no servían: buscar 'coalesce' en minúsculas daba
        // 0 (la constante está en mayúsculas), y buscar «coalesce Y ' in ('» daba 2
        // —el ranking de clientes también tiene las dos cosas, porque agrupa por
        // COALESCE y filtra `whereIn('estado', …)`—. Atar el assert a la constante
        // deja una sola consulta posible y de paso lo amarra a la fuente única.
        $firma = AgendaTrabajo::SQL_CLAVE_CLIENTE.' in (';
        $delHistorial = $consultas->filter(fn ($q) => str_contains($q['query'], $firma));

        $this->assertCount(
            1,
            $delHistorial,
            'El historial se está consultando por cliente (N+1): '.$delHistorial->count().' consultas.'
        );
    }

    public function test_el_historial_es_una_coleccion_de_trabajos(): void
    {
        $this->trabajo(['cliente_rut' => '11.111.111-1']);

        $historial = $this->informe()->viewData('historialClientes');

        $this->assertInstanceOf(EloquentCollection::class, $historial['11.111.111-1']);
        $this->assertInstanceOf(AgendaTrabajo::class, $historial['11.111.111-1']->first());
    }
}
