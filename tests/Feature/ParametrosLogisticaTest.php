<?php

namespace Tests\Feature;

use App\Models\Bodega;
use App\Models\BodegaTraslado;
use App\Models\HojaDeRuta;
use App\Models\User;
use App\Services\Logistica\RespaldoDeDocumento;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Candados de LOG-1 (PLAN-PARAMETRICOS §5.4 #9): los textos-que-mienten. Las
 * fuentes de estos rótulos son CONSTANTES (no claves movibles en runtime), así
 * que el molde tiene dos mitades que se necesitan mutuamente:
 *  1. Regla de oro EN PANTALLA: los textos renderizados dicen hoy exactamente
 *     lo histórico (y el del traslado, la verdad nueva) — verifica que la
 *     derivación RENDERIZA bien.
 *  2. Candado ESTRUCTURAL sobre los fuentes: el rótulo usa la forma DERIVADA
 *     y el literal viejo no existe — es el único que discrimina «derivado» de
 *     «literal que hoy coincide» (un assert de pantalla contra la constante
 *     pasa igual con el número a mano).
 */
class ParametrosLogisticaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        return tap(User::factory()->create())->assignRole('admin');
    }

    public function test_la_ficha_del_traslado_dice_la_cadencia_real_del_espejo(): void
    {
        // El fix urgente del v82: la promesa «cada 15 minutos» era FALSA
        // (bsale:sync-stock corre hourlyAt(45) — una vez por hora). La grilla
        // */15 del scheduler (I-01) es del cron, no del sync.
        $origen = Bodega::factory()->create();
        $destino = Bodega::factory()->create();
        $orden = BodegaTraslado::create([
            'bodega_id' => $origen->id,
            'bodega_destino_id' => $destino->id,
            'estado' => BodegaTraslado::PENDIENTE,
            'solicitante_id' => $this->admin()->id,
            'solicitante_nombre' => 'Prueba',
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.bodegas.traslados.show', $orden))
            ->assertOk()
            ->assertSee('el espejo se refresca una vez por hora')
            ->assertDontSee('cada 15 minutos');
    }

    public function test_los_rotulos_derivados_rinden_identico_al_historico(): void
    {
        // Regla de oro: derivar no cambió ni una letra de lo que se lee hoy.
        $this->actingAs($this->admin())->get(route('admin.hojas-ruta.index'))
            ->assertOk()
            ->assertSee('La primera parte con el folio 1000.');

        // Con el catálogo vacío la página corta en «No hay camiones» y la
        // prosa del pallet ni se emite: hay que sembrar la flota del simulador.
        $this->seed(\Database\Seeders\CamionesSimulacionSeeder::class);
        $this->actingAs($this->admin())->get(route('admin.carga.index'))
            ->assertOk()
            ->assertSee('se usan 15 enteros');

        // Los valores históricos de las fuentes, fijados: mover cualquiera de
        // estas constantes es una decisión (y pone rojo acá a propósito).
        $this->assertSame(999, HojaDeRuta::FOLIO_PISO);
        $this->assertSame(3, HojaDeRuta::TOTAL_LLAVES);
        $this->assertSame('15 MB', RespaldoDeDocumento::topeLegible());
        $this->assertContains('max:'.RespaldoDeDocumento::MAX_KB, RespaldoDeDocumento::reglas());
    }

    public function test_los_fuentes_usan_la_forma_derivada_y_no_el_literal_viejo(): void
    {
        // El candado que DISCRIMINA: un assert de pantalla contra la constante
        // pasa igual con el número a mano (hoy coinciden por construcción);
        // este mira los FUENTES. Rutas relativas normalizadas \→/ (el gotcha
        // Windows de ArchivoInputTest, bitácora 30-07).
        $casos = [
            ['resources/views/admin/hojas-ruta/index.blade.php', ['FOLIO_PISO'], ['folio 1000']],
            ['resources/views/admin/carga/index.blade.php', ['PalletSimulado::BASE_CM'], ['usan 15 enteros']],
            ['resources/views/admin/bodegas/traslados/show.blade.php', ['una vez por hora'], ['cada 15 minutos']],
            ['app/Http/Controllers/Admin/VehiculoController.php', ['topeLegible()'], ['los 15 MB']],
            ['app/Http/Controllers/Admin/VehiculoDocumentoController.php', ['topeLegible()'], ['los 15 MB']],
            ['app/Services/Logistica/RespaldoDeDocumento.php', ["'max:'.self::MAX_KB"], ['max:15360']],
        ];

        foreach ($casos as [$ruta, $requeridos, $prohibidos]) {
            $fuente = file_get_contents(base_path($ruta));
            foreach ($requeridos as $req) {
                $this->assertStringContainsString($req, $fuente, "{$ruta}: falta la forma derivada «{$req}»");
            }
            foreach ($prohibidos as $lit) {
                $this->assertStringNotContainsString($lit, $fuente, "{$ruta}: volvió el literal viejo «{$lit}»");
            }
        }

        // Las 3 llaves: la forma derivada aparece una vez POR MENSAJE (contar,
        // no buscar — doctrina del aria-controls, bitácora 29-07).
        $controller = file_get_contents(base_path('app/Http/Controllers/Admin/HojaRutaController.php'));
        $this->assertSame(3, substr_count($controller, "de '.HojaDeRuta::TOTAL_LLAVES.'"), 'HojaRutaController: los 3 mensajes de llave deben derivar de TOTAL_LLAVES');
        $this->assertStringNotContainsString('de 3).', $controller, 'HojaRutaController: volvió un «de 3).» a mano');
    }
}
