<?php

namespace Tests\Feature\Carga;

use App\Models\CamionSimulacion;
use App\Models\CargaReal;
use App\Models\TipoBulto;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HISTORIAL SIMULADO VS. REAL (lote 4).
 *
 * El motor promete un TECHO y lo dice en cada pantalla. Esta es la única fuente de un
 * factor de corrección propio, porque cuánto queda por debajo no se deduce: se cuenta.
 */
class CargasRealesTest extends TestCase
{
    use RefreshDatabase;

    private User $vendedor;

    private CamionSimulacion $hd35;

    private TipoBulto $bolsa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->vendedor = tap(User::factory()->create())->assignRole('vendedor');

        $this->hd35 = CamionSimulacion::create([
            'nombre' => 'Hyundai HD35',
            'largo_cm' => 430, 'ancho_cm' => 200, 'alto_cm' => 220,
            'peso_max_kg' => 1500, 'pasillo_cm' => 0, 'activo' => true, 'silueta' => 'camion_liviano',
        ]);
        $this->bolsa = TipoBulto::create([
            'nombre' => 'Bolsa 5× botellón 20 L (vacío)', 'categoria' => 'botellones',
            'largo_cm' => 130, 'ancho_cm' => 26, 'alto_cm' => 51, 'peso_kg' => 3.75,
            'unidades' => 5, 'apilable_max' => 30, 'soporta_peso_encima' => true,
            'orientacion_fija' => true, 'activo' => true,
        ]);
    }

    private function anotar(array $extra = []): CargaReal
    {
        return CargaReal::create($extra + [
            'fecha' => '2026-08-11',
            'camion_simulacion_id' => $this->hd35->id,
            'tipo_bulto_id' => $this->bolsa->id,
            'estiba' => 'pie',
            'simulado' => 420,
            'real' => 380,
            'user_id' => $this->vendedor->id,
        ]);
    }

    public function test_se_anota_una_carga_y_la_pantalla_calcula_el_factor(): void
    {
        $this->actingAs($this->vendedor)
            ->post(route('admin.cargas-reales.store'), [
                'fecha' => '2026-08-11',
                'camion_simulacion_id' => $this->hd35->id,
                'tipo_bulto_id' => $this->bolsa->id,
                'estiba' => 'pie',
                'simulado' => 420,
                'real' => 380,
                'observaciones' => 'Quedó lugar para pasar al fondo.',
            ])
            ->assertRedirect(route('admin.cargas-reales.index'));

        $carga = CargaReal::firstOrFail();
        $this->assertSame(420, $carga->simulado);
        $this->assertSame(380, $carga->real);
        $this->assertSame($this->vendedor->id, $carga->user_id, 'Queda quién la anotó.');

        // El factor NO se guarda: es una división de dos columnas que ya están. Un número
        // derivado persistido se desactualiza en silencio el día que se corrige un dato.
        $this->assertSame(0.9048, $carga->factor());
        $this->assertSame(-40, $carga->diferencia());
        $this->assertArrayNotHasKey('factor', $carga->getAttributes());

        $this->actingAs($this->vendedor)->get(route('admin.cargas-reales.index'))
            ->assertOk()
            ->assertSee('90%')
            ->assertSee('Quedó lugar para pasar al fondo.');
    }

    public function test_entrar_MAS_de_lo_calculado_se_marca_porque_es_la_senal_valiosa(): void
    {
        // Un factor por encima de 1 no es un error de tipeo: significa que alguna medida
        // del catálogo está corta. Fue EXACTAMENTE el caso del HD35 el 11-08 — el dueño
        // reportó 480 acostados contra 360 calculados, y en vez de anotarlo se dedujo un
        // ancho que sobrevivió cuatro días hasta que la huincha lo desmintió.
        $this->anotar(['estiba' => 'costado', 'simulado' => 360, 'real' => 480]);

        $r = $this->actingAs($this->vendedor)->get(route('admin.cargas-reales.index'))->assertOk();

        $this->assertSame(1.3333, CargaReal::firstOrFail()->factor());
        $this->assertStringContainsString('entró MÁS de lo calculado', $r->getContent());
        $this->assertStringContainsString('alguna medida del catálogo puede estar corta', $r->getContent());
    }

    public function test_el_promedio_se_agrupa_por_camion_producto_Y_estiba(): void
    {
        // La misma bolsa da 420 de pie y 360 acostada en el mismo camión, así que
        // promediar entre estibas daría un factor que no describe a ninguna de las dos.
        $this->anotar(['estiba' => 'pie', 'simulado' => 420, 'real' => 420]);        // 1,00
        $this->anotar(['estiba' => 'pie', 'simulado' => 420, 'real' => 336]);        // 0,80
        $this->anotar(['estiba' => 'costado', 'simulado' => 360, 'real' => 180]);    // 0,50

        $resumen = $this->actingAs($this->vendedor)
            ->get(route('admin.cargas-reales.index'))->assertOk()->viewData('resumen');

        $this->assertCount(2, $resumen, 'Dos estibas, dos combinaciones.');

        $porEstiba = collect($resumen)->keyBy('estiba');
        $this->assertSame(0.9, $porEstiba['pie']['factor'], 'Promedio de 1,00 y 0,80.');
        $this->assertSame(0.5, $porEstiba['costado']['factor'], 'La acostada NO se mezcla.');
        $this->assertSame(2, $porEstiba['pie']['veces']);
    }

    public function test_con_pocas_cargas_el_promedio_se_muestra_pero_avisa_que_no_alcanza(): void
    {
        // Esconderlo hasta juntar tres sería peor —el dueño no sabría que hay algo
        // anotado— pero presentarlo como factor sería llamarle promedio a una anécdota.
        $this->anotar();

        $resumen = $this->actingAs($this->vendedor)
            ->get(route('admin.cargas-reales.index'))->assertOk()->viewData('resumen');

        $this->assertFalse($resumen[0]['confiable']);

        $this->anotar();
        $this->anotar();
        $resumen = $this->actingAs($this->vendedor)
            ->get(route('admin.cargas-reales.index'))->assertOk()->viewData('resumen');

        $this->assertTrue($resumen[0]['confiable'], 'Con tres ya se promedia.');
    }

    public function test_el_simulador_muestra_lo_medido_al_lado_de_su_techo(): void
    {
        // EL LAZO QUE CIERRA EL LOTE. Sin esto el historial es un cuaderno: el valor está
        // en que el número aparezca donde se toma la decisión.
        $sinDatos = $this->actingAs($this->vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->hd35->id, 'tipo_bulto_id' => $this->bolsa->id, 'estiba' => 'pie',
        ]))->assertOk();

        $this->assertNull($sinDatos->viewData('medido'), 'Sin cargas anotadas no inventa nada.');
        $this->assertStringNotContainsString('En terreno entraron', $sinDatos->getContent());

        $this->anotar(['simulado' => 420, 'real' => 400]);
        $this->anotar(['simulado' => 420, 'real' => 380]);

        $con = $this->actingAs($this->vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->hd35->id, 'tipo_bulto_id' => $this->bolsa->id, 'estiba' => 'pie',
        ]))->assertOk();

        $medido = $con->viewData('medido');
        $this->assertSame(2, $medido['veces']);
        $this->assertSame(390, $medido['promedio'], 'Promedio de 400 y 380.');
        $this->assertStringContainsString('En terreno entraron', $con->getContent());

        // Y NO reemplaza el cupo: los 420 del techo siguen ahí. Cambiar un número
        // verificable por el promedio de dos anécdotas sería perder información.
        //
        // El techo se lee de la FILA de la carga: desde la fusión de las dos preguntas
        // (21-08) un `tipo_bulto_id` sin líneas se traduce a una línea abierta, así que el
        // número vive ahí y no en `viewData('resultado')`. Y se exige que los DOS estén en
        // pantalla a la vez, que es de lo que habla el nombre de este candado: el medido
        // «al lado de» su techo, no en su lugar.
        $mixta = $con->viewData('mixta');
        $this->assertSame(420, $mixta['lineas'][array_key_first($mixta['lineas'])]['cargadas_unidades']);
        $this->assertStringContainsString('Entran 420', $con->getContent());
    }

    public function test_lo_medido_no_se_le_pega_al_numero_de_otro_producto(): void
    {
        // LA TRAMPA DE LA MUDANZA (21-08). La calibración se pedía con el `tipo_bulto_id`
        // del FORMULARIO. Desde la fusión de las dos preguntas el armador manda `lineas[]`
        // y puede no mandar `tipo_bulto_id`: ahí el selector cae al PRIMERO del catálogo
        // —la bolsa, que sí tiene cargas anotadas— y la pantalla habría puesto «en terreno
        // entraron 380» al lado del número de una caja de cartón. Un dato correcto, en el
        // lugar equivocado, sin nada que lo delate.
        $this->anotar();
        $this->anotar();

        $caja = TipoBulto::create([
            'nombre' => 'Caja de tapas', 'categoria' => 'cajas',
            'largo_cm' => 46, 'ancho_cm' => 37, 'alto_cm' => 42, 'peso_kg' => 10,
            'unidades' => 1, 'apilable_max' => 6, 'soporta_peso_encima' => true,
            'orientacion_fija' => false, 'activo' => true,
        ]);

        // El orden del catálogo es categoría y después nombre, así que la bolsa
        // ('botellones') es la primera y es exactamente la que el código viejo elegía.
        $this->assertSame(
            $this->bolsa->id,
            TipoBulto::where('activo', true)->orderBy('categoria')->orderBy('nombre')->first()->id,
            'Si la bolsa deja de ser la primera, este candado dejó de reproducir la trampa.',
        );

        $r = $this->actingAs($this->vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->hd35->id,
            'lineas' => [['tipo' => $caja->id, 'cantidad' => 20, 'estiba' => 'pie']],
        ]))->assertOk();

        $this->assertNull($r->viewData('medido'), 'La caja no tiene cargas anotadas.');
        $this->assertStringNotContainsString('En terreno entraron', $r->getContent());

        // Y con DOS líneas tampoco, ni aunque una de ellas sea la bolsa: una carga real es
        // de un producto, y su promedio al lado de una mezcla es el número creíble y
        // equivocado.
        $mezcla = $this->actingAs($this->vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->hd35->id,
            'lineas' => [
                ['tipo' => $this->bolsa->id, 'cantidad' => 100, 'estiba' => 'pie'],
                ['tipo' => $caja->id, 'cantidad' => 20, 'estiba' => 'pie'],
            ],
        ]))->assertOk();

        $this->assertNull($mezcla->viewData('medido'));
        $this->assertStringNotContainsString('En terreno entraron', $mezcla->getContent());

        // El control POSITIVO, sin el cual los dos assert de arriba pasan por vacío: la
        // MISMA bolsa, sola, sí trae su calibración.
        $sola = $this->actingAs($this->vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->hd35->id,
            'lineas' => [['tipo' => $this->bolsa->id, 'cantidad' => 100, 'estiba' => 'pie']],
        ]))->assertOk();

        $this->assertSame(380, $sola->viewData('medido')['promedio']);
        $this->assertStringContainsString('En terreno entraron', $sola->getContent());
    }

    public function test_lo_medido_no_se_mezcla_entre_estibas_ni_entre_camiones(): void
    {
        $this->anotar(['estiba' => 'costado', 'simulado' => 360, 'real' => 200]);

        $otraEstiba = $this->actingAs($this->vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->hd35->id, 'tipo_bulto_id' => $this->bolsa->id, 'estiba' => 'pie',
        ]))->assertOk();

        $this->assertNull($otraEstiba->viewData('medido'), 'Lo acostado no describe lo de pie.');
    }

    public function test_no_se_puede_anotar_una_carga_futura_ni_con_simulado_cero(): void
    {
        // Una carga «que ya se hizo» no puede ser de mañana, y un simulado en cero haría
        // una división imposible — el factor quedaría sin definir y la fila sería basura.
        $base = [
            'camion_simulacion_id' => $this->hd35->id,
            'tipo_bulto_id' => $this->bolsa->id,
            'estiba' => 'pie', 'simulado' => 420, 'real' => 380,
        ];

        $this->actingAs($this->vendedor)
            ->post(route('admin.cargas-reales.store'), ['fecha' => now()->addDay()->toDateString()] + $base)
            ->assertSessionHasErrors('fecha');

        $this->actingAs($this->vendedor)
            ->post(route('admin.cargas-reales.store'), ['fecha' => '2026-08-11', 'simulado' => 0] + $base)
            ->assertSessionHasErrors('simulado');

        $this->assertSame(0, CargaReal::count());
    }

    public function test_sin_permiso_de_simular_no_se_entra(): void
    {
        // Mismo permiso que el simulador: esto calibra esa calculadora y no otra cosa.
        $ajeno = User::factory()->create();

        // NAVEGAR sin permiso lleva al Inicio con el aviso que explica por qué (D-014).
        $this->actingAs($ajeno)->get(route('admin.cargas-reales.index'))->assertRedirect(route('dashboard'));

        // ESCRIBIR sin permiso es 403 y no redirect, y la diferencia importa: un POST que
        // termina en el Inicio con la pantalla normal se lee como que guardó. El 403 no
        // deja lugar a dudas y es lo que ya hace el resto de la app.
        $this->actingAs($ajeno)->post(route('admin.cargas-reales.store'), [])->assertForbidden();

        // La fila tiene que EXISTIR para que el 403 signifique algo: el binding del
        // modelo corre antes que el permiso, así que con un id inventado saldría 404 y el
        // test pasaría sin haber probado el permiso.
        $this->actingAs($ajeno)->delete(route('admin.cargas-reales.destroy', $this->anotar()))->assertForbidden();
        $this->assertSame(1, CargaReal::count(), 'Y no la borró.');
    }

    public function test_se_puede_borrar_una_fila_mal_anotada(): void
    {
        $carga = $this->anotar();

        $this->actingAs($this->vendedor)
            ->delete(route('admin.cargas-reales.destroy', $carga))
            ->assertRedirect();

        $this->assertSame(0, CargaReal::count());
    }
}
