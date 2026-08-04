<?php

namespace Tests\Feature\Despachos;

use App\Models\Despacho;
use App\Models\DocumentoVenta;
use App\Models\HojaDeRuta;
use App\Models\HojaRutaParada;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Despachos\HojaRutaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * La lógica de la hoja de ruta: folio correlativo, generación de paradas
 * eligiendo documentos, la cadena de 3 llaves secuenciales y la salida a
 * ruta. Los candados estructurales (uniques, forma del mapa) viven en
 * HojaRutaTest.
 *
 * El lock del folio y de las transiciones NO es asertable acá
 * (SQLiteGrammar::compileLock() devuelve ''): su cobertura honesta está en
 * tests/Unit/LockParaMySqlTest. Lo que estos tests sí cubren es la
 * RE-LECTURA bajo instancia stale y la secuencia no saltable.
 */
class HojaRutaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        // crearDesdeDocumento re-verifica cada documento contra Bsale
        // (fail-closed): el doc puntual responde vigente.
        Http::fake(function (Request $request) {
            if (preg_match('#documents/\d+\.json#', $request->url())) {
                return Http::response(['id' => 900, 'state' => 0, 'commercialState' => 0, 'cancellationStatus' => 0]);
            }

            return Http::response([], 404);
        });
    }

    private function service(): HojaRutaService
    {
        return app(HojaRutaService::class);
    }

    private function datosDeHoja(array $overrides = []): array
    {
        $hoja = HojaDeRuta::factory()->make();   // resuelve sucursal/zona/conductor sin tocar folio real

        return array_merge([
            'sucursal_id' => $hoja->sucursal_id,
            'zona_id' => $hoja->zona_id,
            'vehiculo_id' => Vehiculo::factory()->create(['ppu' => 'TT'.fake()->unique()->numberBetween(1000, 9999)])->id,
            'conductor_id' => $hoja->conductor_id,
        ], $overrides);
    }

    // ─── Folio (R25) ────────────────────────────────────────────────

    public function test_el_primer_folio_es_1000_y_el_correlativo_avanza(): void
    {
        $primera = $this->service()->crear($this->datosDeHoja());
        $segunda = $this->service()->crear($this->datosDeHoja());

        $this->assertSame(1000, $primera->folio);
        $this->assertSame(1001, $segunda->folio);
    }

    public function test_el_folio_no_reusa_huecos_hacia_atras(): void
    {
        // Si existiera una hoja 2000 (restore, carga manual), el correlativo
        // sigue desde ahí: max(folio)+1, nunca un número ya visto.
        HojaDeRuta::factory()->create(['folio' => 2000]);

        $this->assertSame(2001, $this->service()->crear($this->datosDeHoja())->folio);
    }

    // ─── Snapshot del vehículo (micro-decisión 1 del parte) ─────────

    public function test_el_vehiculo_queda_congelado_como_texto(): void
    {
        $vehiculo = Vehiculo::factory()->create(['ppu' => 'ABCD12', 'alias' => 'Camión 7']);
        $hoja = $this->service()->crear($this->datosDeHoja(['vehiculo_id' => $vehiculo->id]));

        $this->assertSame('ABCD12', $hoja->patente);
        $this->assertSame($vehiculo->nombre, $hoja->vehiculo);

        // Renombrar el vehículo NO reescribe la hoja histórica...
        $vehiculo->update(['alias' => 'Renombrado']);
        $this->assertSame('ABCD12', $hoja->fresh()->patente);

        // ...y borrarlo tampoco la rompe: la FK es blanda (nullOnDelete).
        $vehiculo->delete();
        $fresca = $hoja->fresh();
        $this->assertNull($fresca->vehiculo_id);
        $this->assertSame('ABCD12', $fresca->patente);
    }

    // ─── Generación de paradas (R1: se ELIGEN documentos) ───────────

    public function test_genera_paradas_creando_los_despachos_que_faltan(): void
    {
        $hoja = $this->service()->crear($this->datosDeHoja());
        $docs = DocumentoVenta::factory()->count(3)->create();

        $this->service()->generarParadas($hoja, $docs->pluck('id')->all(), [
            $docs[0]->id => HojaRutaParada::COBRO_PAGADO,
        ]);

        $paradas = $hoja->paradas;
        $this->assertCount(3, $paradas);
        $this->assertSame([1, 2, 3], $paradas->pluck('orden')->all());

        // El primero venía pagado; los otros caen al default fail-safe.
        $this->assertSame(HojaRutaParada::COBRO_PAGADO, $paradas[0]->estado_cobro);
        $this->assertSame(HojaRutaParada::COBRO_EN_ENTREGA, $paradas[1]->estado_cobro);

        // Los despachos nacieron heredando zona y conductor de la hoja.
        $despacho = $paradas[0]->despacho;
        $this->assertSame($hoja->zona_id, $despacho->zona_id);
        $this->assertSame($hoja->conductor_id, $despacho->conductor_id);
    }

    public function test_reusa_el_despacho_existente_de_un_documento(): void
    {
        $hoja = $this->service()->crear($this->datosDeHoja());
        $despacho = Despacho::factory()->create();   // preparado, sin hoja

        $this->service()->generarParadas($hoja, [$despacho->documento_venta_id]);

        $this->assertSame($despacho->id, $hoja->paradas()->first()->despacho_id);
        $this->assertSame(1, Despacho::count(), 'No debe crear un despacho duplicado.');
    }

    public function test_rechaza_documentos_anulados(): void
    {
        $hoja = $this->service()->crear($this->datosDeHoja());
        $anulado = DocumentoVenta::factory()->anulado()->create();

        $this->expectException(ValidationException::class);

        $this->service()->generarParadas($hoja, [$anulado->id]);
    }

    public function test_rechaza_un_documento_cuyo_despacho_ya_esta_en_otra_hoja(): void
    {
        $hojaA = $this->service()->crear($this->datosDeHoja());
        $hojaB = $this->service()->crear($this->datosDeHoja());
        $despacho = Despacho::factory()->create();

        $this->service()->generarParadas($hojaA, [$despacho->documento_venta_id]);

        try {
            $this->service()->generarParadas($hojaB, [$despacho->documento_venta_id]);
            $this->fail('Debió rechazar: el despacho ya está en otra hoja.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('ya está en otra hoja', $e->errors()['documentos'][0]);
        }
    }

    public function test_rechaza_un_despacho_ya_entregado(): void
    {
        $hoja = $this->service()->crear($this->datosDeHoja());
        $entregado = Despacho::factory()->create(['estado' => Despacho::ENTREGADO]);

        $this->expectException(ValidationException::class);

        $this->service()->generarParadas($hoja, [$entregado->documento_venta_id]);
    }

    public function test_las_paradas_solo_se_generan_en_borrador(): void
    {
        $hoja = $this->service()->crear($this->datosDeHoja());
        $this->service()->autorizarPagos($hoja, $this->jefe('jefe_ventas'));

        $this->expectException(ValidationException::class);

        $this->service()->generarParadas($hoja->fresh(), [DocumentoVenta::factory()->create()->id]);
    }

    // ─── La cadena de llaves (R11: secuencial estricta) ─────────────

    public function test_la_cadena_completa_estampa_quien_y_cuando_en_cada_llave(): void
    {
        $hoja = $this->service()->crear($this->datosDeHoja());
        $ventas = $this->jefe('jefe_ventas');
        $despacho = $this->jefe('jefe_despacho');
        $bodega = $this->jefe('jefe_bodega');

        $hoja = $this->service()->autorizarPagos($hoja, $ventas);
        $this->assertSame(HojaDeRuta::PAGOS_OK, $hoja->estado);
        $this->assertSame($ventas->id, $hoja->pagos_ok_por);
        $this->assertNotNull($hoja->pagos_ok_at);

        $hoja = $this->service()->autorizarRuta($hoja, $despacho);
        $this->assertSame(HojaDeRuta::RUTA_AUTORIZADA, $hoja->estado);
        $this->assertSame($despacho->id, $hoja->ruta_autorizada_por);

        $hoja = $this->service()->autorizarCarga($hoja, $bodega);
        $this->assertSame(HojaDeRuta::CARGADA, $hoja->estado);
        $this->assertSame($bodega->id, $hoja->cargada_por);

        $hoja = $this->service()->salirARuta($hoja, $bodega);
        $this->assertSame(HojaDeRuta::EN_RUTA, $hoja->estado);
        $this->assertSame($bodega->id, $hoja->en_ruta_por);
        $this->assertNotNull($hoja->en_ruta_at, 'La hora de salida la exige la guía electrónica (R5).');
    }

    public function test_la_maquina_no_admite_saltos(): void
    {
        $hoja = $this->service()->crear($this->datosDeHoja());
        $user = $this->jefe('jefe_despacho');

        // borrador → ruta_autorizada (saltándose pagos): NO.
        try {
            $this->service()->autorizarRuta($hoja, $user);
            $this->fail('Debió rechazar el salto borrador→ruta_autorizada.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('borrador', $e->errors()['estado'][0]);
        }

        // borrador → en_ruta (saltándose todo): NO.
        $this->expectException(ValidationException::class);
        $this->service()->salirARuta($hoja->fresh(), $user);
    }

    public function test_una_llave_no_se_da_dos_veces_ni_con_instancia_stale(): void
    {
        $hoja = $this->service()->crear($this->datosDeHoja());
        $ventas = $this->jefe('jefe_ventas');

        $stale = HojaDeRuta::find($hoja->id);       // instancia vieja, aún "borrador"
        $this->service()->autorizarPagos($hoja, $ventas);

        // El doble-tap con la instancia stale se rechaza: el service re-lee
        // el estado con la fila anclada, no confía en el modelo recibido.
        $this->expectException(ValidationException::class);
        $this->service()->autorizarPagos($stale, $ventas);
    }

    public function test_la_hora_de_cada_llave_la_pone_el_servidor(): void
    {
        // No existe NINGÚN camino para inyectar la hora: la firma solo
        // recibe (hoja, usuario). R5 por construcción — este test fija la
        // firma para que un parámetro de hora futuro no entre sin discusión.
        $firmas = ['autorizarPagos', 'autorizarRuta', 'autorizarCarga', 'salirARuta'];
        foreach ($firmas as $metodo) {
            $params = (new \ReflectionMethod(HojaRutaService::class, $metodo))->getParameters();
            $this->assertCount(2, $params, "{$metodo} solo recibe (hoja, usuario): la hora es del servidor.");
        }
    }

    // ─── La salida a ruta escribe EN_RUTA (el estado que nadie escribía) ──

    public function test_salir_a_ruta_propaga_en_ruta_a_los_despachos_retirados(): void
    {
        $hoja = $this->service()->crear($this->datosDeHoja());
        $retirado = Despacho::factory()->retirado()->create();
        $preparado = Despacho::factory()->create();
        $ajeno = Despacho::factory()->retirado()->create();   // sin parada: no se toca

        $this->service()->generarParadas($hoja, [
            $retirado->documento_venta_id,
            $preparado->documento_venta_id,
        ]);

        $hoja = $this->service()->autorizarPagos($hoja->fresh(), $this->jefe('jefe_ventas'));
        $hoja = $this->service()->autorizarRuta($hoja, $this->jefe('jefe_despacho'));
        $hoja = $this->service()->autorizarCarga($hoja, $this->jefe('jefe_bodega'));
        $this->service()->salirARuta($hoja, $this->jefe('jefe_bodega'));

        $this->assertSame(Despacho::EN_RUTA, $retirado->fresh()->estado);
        $this->assertSame(Despacho::PREPARADO, $preparado->fresh()->estado, 'Un despacho sin retirar no avanza solo.');
        $this->assertSame(Despacho::RETIRADO, $ajeno->fresh()->estado, 'Un despacho fuera de la hoja no se toca.');
    }

    // ─── Reordenar (R3) ─────────────────────────────────────────────

    public function test_reordenar_regenera_la_secuencia_completa(): void
    {
        $hoja = $this->service()->crear($this->datosDeHoja());
        $docs = DocumentoVenta::factory()->count(3)->create();
        $this->service()->generarParadas($hoja, $docs->pluck('id')->all());

        $paradas = $hoja->paradas;
        $this->service()->reordenar($hoja, [$paradas[2]->id, $paradas[0]->id, $paradas[1]->id]);

        $this->assertSame(
            [$paradas[2]->id, $paradas[0]->id, $paradas[1]->id],
            $hoja->fresh()->paradas->pluck('id')->all(),
        );
    }

    public function test_reordenar_rechaza_ids_que_no_calzan(): void
    {
        $hoja = $this->service()->crear($this->datosDeHoja());
        $this->service()->generarParadas($hoja, [DocumentoVenta::factory()->create()->id]);
        $ajena = HojaRutaParada::create([
            'hoja_de_ruta_id' => $this->service()->crear($this->datosDeHoja())->id,
            'despacho_id' => Despacho::factory()->create()->id,
            'orden' => 1,
        ]);

        $this->expectException(ValidationException::class);

        $this->service()->reordenar($hoja, [$ajena->id]);
    }

    public function test_el_orden_no_se_toca_con_la_hoja_en_ruta(): void
    {
        $hoja = $this->service()->crear($this->datosDeHoja());
        $this->service()->generarParadas($hoja, [DocumentoVenta::factory()->create()->id]);
        $parada = $hoja->paradas()->first();

        $hoja = $this->service()->autorizarPagos($hoja->fresh(), $this->jefe('jefe_ventas'));
        $hoja = $this->service()->autorizarRuta($hoja, $this->jefe('jefe_despacho'));
        $hoja = $this->service()->autorizarCarga($hoja, $this->jefe('jefe_bodega'));
        $hoja = $this->service()->salirARuta($hoja, $this->jefe('jefe_bodega'));

        $this->expectException(ValidationException::class);

        $this->service()->reordenar($hoja, [$parada->id]);
    }

    private function jefe(string $rol): User
    {
        return tap(User::factory()->create())->assignRole($rol);
    }
}
