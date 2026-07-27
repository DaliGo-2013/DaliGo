<?php

namespace Tests\Feature\Despachos;

use App\Models\Cliente;
use App\Models\Despacho;
use App\Models\DocumentoVenta;
use App\Models\EscaneoDespacho;
use App\Models\User;
use App\Models\Zona;
use App\Services\Despachos\DespachoService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * DESPACHOS-v1 · P-DSP-04: retiro anti-fraude por QR (M07).
 *
 * Lo que se blinda acá: que el MISMO QR no autorice dos retiros, que todo
 * intento —válido o no— deje evidencia, que el link no sea manipulable ni
 * enumerable, y que la entrega parcial no pueda quedar sin saldo.
 */
class RetiroQrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function jefe(): User
    {
        return User::factory()->create()->assignRole('jefe_bodega');
    }

    /** Contador propio: bsale_document_id y el nombre de zona son UNIQUE. */
    private int $n = 0;

    private function despacho(array $overrides = []): Despacho
    {
        $this->n++;
        $zona = Zona::create(['nombre' => 'Zona de prueba '.$this->n, 'activa' => true]);
        $cliente = Cliente::create(['razon_social' => 'Distribuidora Andes '.$this->n.' SpA', 'zona_id' => $zona->id]);
        $documento = DocumentoVenta::create([
            'bsale_document_id' => 900 + $this->n,
            'folio' => 4321 + $this->n,
            'emitido_at' => now()->subDay(),
            'total' => 119000,
            'cancellation_status' => 0,
            'cliente_id' => $cliente->id,
        ]);

        return Despacho::create(array_merge([
            'documento_venta_id' => $documento->id,
            'zona_id' => $zona->id,
            'estado' => Despacho::PREPARADO,
        ], $overrides));
    }

    private function service(): DespachoService
    {
        return app(DespachoService::class);
    }

    // --- El retiro y su evidencia ------------------------------------------

    public function test_primer_escaneo_autoriza_el_retiro_y_deja_evidencia(): void
    {
        $despacho = $this->despacho();
        $operador = $this->jefe();

        $resultado = $this->service()->validarRetiro($despacho, $operador);

        $this->assertSame(EscaneoDespacho::VALIDO, $resultado['resultado']);
        $this->assertSame(Despacho::RETIRADO, $despacho->fresh()->estado);
        $this->assertNotNull($despacho->fresh()->retirado_at);

        $this->assertDatabaseHas('escaneos_despacho', [
            'despacho_id' => $despacho->id,
            'user_id' => $operador->id,
            'resultado' => EscaneoDespacho::VALIDO,
        ]);
    }

    /**
     * EL caso que este paso existe para impedir: el mismo QR presentado dos
     * veces. El 2º intento NO cambia el estado y deja fila `doble_retiro`.
     */
    public function test_segundo_escaneo_es_doble_retiro_no_cambia_estado_y_alerta(): void
    {
        $despacho = $this->despacho();
        $service = $this->service();

        $service->validarRetiro($despacho, $this->jefe());
        $retiradoAt = $despacho->fresh()->retirado_at;

        $segundo = $service->validarRetiro($despacho->fresh(), $this->jefe());

        $this->assertSame(EscaneoDespacho::DOBLE_RETIRO, $segundo['resultado']);
        // El estado y la hora del PRIMER retiro quedan intactos.
        $this->assertSame(Despacho::RETIRADO, $despacho->fresh()->estado);
        $this->assertEquals($retiradoAt, $despacho->fresh()->retirado_at);
        // Dos filas: el válido y el rechazado (append-only, la evidencia).
        $this->assertSame(2, $despacho->escaneos()->count());
        $this->assertSame(1, $despacho->escaneos()->where('resultado', EscaneoDespacho::DOBLE_RETIRO)->count());
    }

    /**
     * Carrera del doble-tap: dos intentos sobre la MISMA instancia stale (la que
     * ya leyó 'preparado' en memoria). Sin el re-check con la fila bloqueada
     * dentro de la transacción, el segundo también pasaría por válido y
     * fabricaría un retiro duplicado. Muta el lock y este test se pone rojo.
     */
    public function test_dos_escaneos_sobre_la_misma_instancia_stale_solo_uno_es_valido(): void
    {
        $despacho = $this->despacho();
        $stale = Despacho::find($despacho->id); // copia con estado 'preparado'
        $service = $this->service();

        $primero = $service->validarRetiro($despacho, $this->jefe());
        $segundo = $service->validarRetiro($stale, $this->jefe()); // ¡sigue creyendo 'preparado'!

        $this->assertSame(EscaneoDespacho::VALIDO, $primero['resultado']);
        $this->assertSame(EscaneoDespacho::DOBLE_RETIRO, $segundo['resultado']);
        $this->assertSame(1, $despacho->escaneos()->where('resultado', EscaneoDespacho::VALIDO)->count());
    }

    public function test_despacho_ya_entregado_da_estado_invalido(): void
    {
        $despacho = $this->despacho(['estado' => Despacho::ENTREGADO]);

        $resultado = $this->service()->validarRetiro($despacho, $this->jefe());

        $this->assertSame(EscaneoDespacho::ESTADO_INVALIDO, $resultado['resultado']);
        $this->assertSame(Despacho::ENTREGADO, $despacho->fresh()->estado);
        $this->assertDatabaseHas('escaneos_despacho', [
            'despacho_id' => $despacho->id,
            'resultado' => EscaneoDespacho::ESTADO_INVALIDO,
        ]);
    }

    // --- El link del QR: firmado, por código, no enumerable ----------------

    /**
     * La firma es obligatoria. Ojo con el status: el handler de errores amables
     * de main convierte el 403 de una NAVEGACIÓN GET en redirect al Inicio con
     * aviso — para el operador de bodega es mejor que una pantalla técnica, y lo
     * que importa (no entra a la ficha) queda igual de verificado.
     */
    public function test_la_ficha_exige_firma_valida(): void
    {
        $despacho = $this->despacho();
        $jefe = $this->jefe();

        $this->actingAs($jefe)
            ->get(route('admin.despachos.escanear', $despacho->codigo))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('aviso');

        // Con la firma que emite el propio modelo: entra.
        $this->actingAs($jefe)->get($despacho->urlFicha())->assertOk();
    }

    public function test_una_firma_de_otro_codigo_no_sirve_para_este_despacho(): void
    {
        $otro = $this->despacho();
        // Firma emitida para OTRO código: alterar el código invalida la firma.
        $urlAjena = str_replace($otro->codigo, 'DSP-FALSIFIC', $otro->urlFicha());

        $this->actingAs($this->jefe())->get($urlAjena)
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('aviso');
    }

    public function test_el_qr_apunta_al_codigo_no_al_id(): void
    {
        $despacho = $this->despacho();

        $this->actingAs($this->jefe())
            ->get(route('admin.despachos.qr', $despacho))
            ->assertOk()
            ->assertSee($despacho->codigo)
            // El canvas lleva la URL firmada del escaneo (la dibuja app.js).
            ->assertSee('data-qr', false)
            ->assertSee(e($despacho->urlFicha()), false);
    }

    public function test_sin_permiso_no_se_escanea_ni_con_firma(): void
    {
        $despacho = $this->despacho();
        $intruso = User::factory()->create(); // sin 'manage despachos'

        // GET: el contrato de errores amables lo manda al Inicio con aviso.
        $this->actingAs($intruso)->get($despacho->urlFicha())
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('aviso');

        // POST (acción): conserva el 403 y NO deja tocar el estado.
        $this->actingAs($intruso)
            ->post(route('admin.despachos.retiro', $despacho->codigo))
            ->assertForbidden();

        $this->assertSame(Despacho::PREPARADO, $despacho->fresh()->estado);
        $this->assertSame(0, $despacho->escaneos()->count());
    }

    // --- El flujo por HTTP -------------------------------------------------

    public function test_el_post_de_retiro_vuelve_a_la_ficha_con_el_veredicto(): void
    {
        $despacho = $this->despacho();

        $this->actingAs($this->jefe())
            ->post(route('admin.despachos.retiro', $despacho->codigo))
            ->assertRedirect()
            ->assertSessionHas('escaneo', EscaneoDespacho::VALIDO);

        $this->assertSame(Despacho::RETIRADO, $despacho->fresh()->estado);
    }

    public function test_el_get_de_la_ficha_no_muta_nada(): void
    {
        $despacho = $this->despacho();

        // Tres visitas (un F5 del operador): ni retiro ni escaneos fantasma.
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($this->jefe())->get($despacho->urlFicha())->assertOk();
        }

        $this->assertSame(Despacho::PREPARADO, $despacho->fresh()->estado);
        $this->assertSame(0, $despacho->escaneos()->count(),
            'Un GET jamás debe contar como escaneo: cada F5 dispararía una alerta falsa.');
    }

    public function test_codigo_inexistente_no_abre_ficha(): void
    {
        $url = URL::signedRoute('admin.despachos.escanear', ['codigo' => 'DSP-NOEXISTE']);

        // 404 → también amable en navegación (redirect al Inicio con aviso).
        $this->actingAs($this->jefe())->get($url)
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('aviso');
    }

    // --- Cola de bodega ("McDonald's") ------------------------------------

    public function test_la_cola_lista_solo_lo_que_espera_retiro(): void
    {
        $esperando = $this->despacho();
        $yaRetirado = $this->despacho(['estado' => Despacho::RETIRADO]);

        $res = $this->actingAs($this->jefe())->get(route('admin.despachos.cola'));

        $res->assertOk()
            ->assertViewIs('admin.despachos.cola')
            ->assertSee($esperando->codigo)
            ->assertDontSee($yaRetirado->codigo);
        $this->assertSame(1, $res->viewData('total'));
    }

    public function test_el_conteo_de_la_cola_es_json_liviano(): void
    {
        $this->despacho();
        $this->despacho(['estado' => Despacho::ENTREGADO]); // no cuenta

        $this->actingAs($this->jefe())
            ->getJson(route('admin.despachos.cola.conteo'))
            ->assertOk()
            ->assertExactJson(['total' => 1]);
    }

    public function test_la_cola_baja_cuando_se_retira(): void
    {
        $despacho = $this->despacho();
        $jefe = $this->jefe();

        $this->actingAs($jefe)->getJson(route('admin.despachos.cola.conteo'))->assertExactJson(['total' => 1]);
        $this->service()->validarRetiro($despacho, $jefe);
        $this->actingAs($jefe)->getJson(route('admin.despachos.cola.conteo'))->assertExactJson(['total' => 0]);
    }

    // --- Entrega total / parcial ------------------------------------------

    public function test_entrega_total_cierra_el_despacho(): void
    {
        $despacho = $this->despacho(['estado' => Despacho::RETIRADO]);

        $this->actingAs($this->jefe())
            ->post(route('admin.despachos.entrega', $despacho))
            ->assertRedirect(route('admin.despachos.index'));

        $fresco = $despacho->fresh();
        $this->assertSame(Despacho::ENTREGADO, $fresco->estado);
        $this->assertNotNull($fresco->entregado_at);
        $this->assertNull($fresco->entrega_observacion);
    }

    public function test_entrega_parcial_exige_el_saldo_y_lo_deja_visible(): void
    {
        $despacho = $this->despacho(['estado' => Despacho::RETIRADO]);

        // Parcial SIN saldo: rechazada (un parcial ciego no se puede reclamar).
        $this->actingAs($this->jefe())
            ->from($despacho->urlFicha())
            ->post(route('admin.despachos.entrega', $despacho), ['parcial' => 1])
            ->assertSessionHasErrors('entrega_observacion');
        $this->assertSame(Despacho::RETIRADO, $despacho->fresh()->estado);

        // Con saldo: queda registrada y el saldo se ve en el listado.
        $this->actingAs($this->jefe())
            ->post(route('admin.despachos.entrega', $despacho), [
                'parcial' => 1,
                'entrega_observacion' => 'Faltaron 4 botellones de 20L',
            ])->assertRedirect(route('admin.despachos.index'));

        $fresco = $despacho->fresh();
        $this->assertSame(Despacho::ENTREGA_PARCIAL, $fresco->estado);
        $this->assertSame('Faltaron 4 botellones de 20L', $fresco->entrega_observacion);

        $this->actingAs($this->jefe())
            ->get(route('admin.despachos.index'))
            ->assertOk()
            ->assertSee('Faltaron 4 botellones de 20L');
    }

    public function test_no_se_entrega_lo_que_no_salio_de_bodega(): void
    {
        $despacho = $this->despacho(); // preparado

        $this->expectException(ValidationException::class);
        $this->service()->registrarEntrega($despacho, false);
    }

    public function test_no_se_cierra_dos_veces_el_mismo_despacho(): void
    {
        $despacho = $this->despacho(['estado' => Despacho::RETIRADO]);
        $service = $this->service();

        $service->registrarEntrega($despacho, false);
        $entregadoAt = $despacho->fresh()->entregado_at;

        try {
            // Instancia stale (cree que sigue 'retirado'): el lock + re-check la para.
            $service->registrarEntrega(Despacho::find($despacho->id)->setAttribute('estado', Despacho::RETIRADO), false);
            $this->fail('Un segundo cierre debía ser rechazado.');
        } catch (ValidationException) {
            // esperado
        }

        $this->assertEquals($entregadoAt, $despacho->fresh()->entregado_at);
    }

    /** El saldo se recorta a lo que cabe en la columna (191 en BD, I-07). */
    public function test_el_saldo_se_recorta_a_lo_que_cabe_en_la_columna(): void
    {
        $despacho = $this->despacho(['estado' => Despacho::RETIRADO]);

        $this->service()->registrarEntrega($despacho, true, str_repeat('x', 400));

        $this->assertLessThanOrEqual(191, mb_strlen($despacho->fresh()->entrega_observacion));
    }
}
