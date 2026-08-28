<?php

namespace Tests\Feature;

use App\Models\Bodega;
use App\Models\BodegaTraslado;
use App\Models\Configuracion;
use App\Models\HojaDeRuta;
use App\Models\Notificacion;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Logistica\RespaldoDeDocumento;
use Database\Seeders\ConfiguracionSeeder;
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

    // =====================================================================
    //  LOG-2: la franja «Por vencer» de la flota (hallazgo #1 del mapa §5.4,
    //  molde DASH-1/OPE-1 — quinto uso)
    // =====================================================================

    private function jefeLogistica(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_logistica');
    }

    public function test_sin_clave_en_bd_la_franja_de_la_flota_rinde_identica(): void
    {
        // BD virgen: rige el fallback (30). Un documento a 45 días está AL
        // DÍA; a 20 días, POR VENCER — y el rótulo de la tarjeta dice 30.
        $lejos = Vehiculo::factory()->alDia()->create(['permiso_circulacion_vence' => now()->addDays(45)->toDateString()]);
        $cerca = Vehiculo::factory()->alDia()->create(['permiso_circulacion_vence' => now()->addDays(20)->toDateString()]);

        $docDe = fn (Vehiculo $v) => collect($v->documentos())->firstWhere('clave', 'permiso_circulacion_vence')['estado'];
        $this->assertSame(Vehiculo::DOC_AL_DIA, $docDe($lejos));
        $this->assertSame(Vehiculo::DOC_POR_VENCER, $docDe($cerca));

        $this->actingAs($this->jefeLogistica())->get(route('admin.vehiculos.index'))
            ->assertOk()
            ->assertSee('Por vencer (30 días)');
    }

    public function test_mover_la_franja_mueve_el_badge_el_rotulo_y_el_hito_del_aviso(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $jefe = $this->jefeLogistica();
        $vehiculo = Vehiculo::factory()->alDia()->create(['permiso_circulacion_vence' => now()->addDays(45)->toDateString()]);

        // Con el default, a 45 días no pasa nada: ni badge ni aviso.
        $this->artisan('vehiculos:avisar-vencimientos')->assertSuccessful();
        $this->assertSame(0, Notificacion::where('evento', 'vehiculo.documento_por_vencer')->count());

        Configuracion::set('vehiculos_dias_aviso', 60);

        // El badge se mueve con la cifra…
        $doc = collect($vehiculo->fresh()->documentos())->firstWhere('clave', 'permiso_circulacion_vence');
        $this->assertSame(Vehiculo::DOC_POR_VENCER, $doc['estado']);

        // …el rótulo de la tarjeta deriva…
        $this->actingAs($jefe)->get(route('admin.vehiculos.index'))
            ->assertOk()
            ->assertSee('Por vencer (60 días)')
            ->assertDontSee('Por vencer (30 días)');

        // …y el hito del aviso diario también: ahora el documento SÍ avisa.
        $this->artisan('vehiculos:avisar-vencimientos')->assertSuccessful();
        $aviso = Notificacion::where('evento', 'vehiculo.documento_por_vencer')
            ->where('canal', Notificacion::CANAL_DATABASE)->firstOrFail();
        $this->assertSame($jefe->id, $aviso->user_id);
    }

    public function test_la_ventana_de_reaviso_de_vencidos_no_se_mueve_con_la_franja(): void
    {
        // DIAS_VENTANA_VENCIDO (30, constante del comando) es OTRO concepto:
        // cuánta deuda vieja re-avisa. Mover la franja del badge NO la toca —
        // un documento vencido hace 40 días sigue sin inundar la campanita.
        $this->seed(ConfiguracionSeeder::class);
        $this->jefeLogistica();
        Vehiculo::factory()->alDia()->create(['permiso_circulacion_vence' => now()->subDays(40)->toDateString()]);

        Configuracion::set('vehiculos_dias_aviso', 60);

        $this->artisan('vehiculos:avisar-vencimientos')->assertSuccessful();
        $this->assertSame(0, Notificacion::where('evento', 'vehiculo.documento_vencido')->count());
    }

    public function test_la_ui_valida_el_rango_de_la_franja_7_a_90(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $admin = $this->admin();
        $config = Configuracion::where('clave', 'vehiculos_dias_aviso')->firstOrFail();

        foreach ([6, 0, -5, 91, 'abc'] as $malo) {
            $this->actingAs($admin)
                ->put(route('admin.configuracion.update', $config), ['valor' => $malo])
                ->assertSessionHasErrors('valor');
        }

        foreach ([7, 90] as $bueno) {
            $this->actingAs($admin)
                ->put(route('admin.configuracion.update', $config), ['valor' => $bueno])
                ->assertSessionHasNoErrors();
            $this->assertSame($bueno, Configuracion::get('vehiculos_dias_aviso'));
        }
    }

    public function test_los_fuentes_de_la_flota_derivan_y_no_queda_la_franja_a_mano(): void
    {
        // La mitad estructural del molde LOG (los rótulos derivan del método
        // vivo, no de la constante ni de un literal).
        $casos = [
            ['resources/views/admin/vehiculos/index.blade.php', ['diasAviso()'], ['Por vencer (30 días)']],
            ['resources/views/admin/vehiculos/_form.blade.php', ['diasAviso()'], ['DIAS_AVISO']],
            ['resources/views/admin/vehiculos/show.blade.php', ['diasAviso()'], ['DIAS_AVISO']],
            ['app/Services/Logistica/FlotaExcel.php', ['diasAviso()'], ['(30 días)']],
            ['app/Console/Commands/VehiculosAvisarVencimientos.php', ['diasAviso()'], ['(30 días)']],
        ];

        foreach ($casos as [$ruta, $requeridos, $prohibidos]) {
            $fuente = file_get_contents(base_path($ruta));
            foreach ($requeridos as $req) {
                $this->assertStringContainsString($req, $fuente, "{$ruta}: falta la forma derivada «{$req}»");
            }
            foreach ($prohibidos as $lit) {
                $this->assertStringNotContainsString($lit, $fuente, "{$ruta}: volvió la franja a mano «{$lit}»");
            }
        }
    }
}
