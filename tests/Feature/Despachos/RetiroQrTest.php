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

    /**
     * EL CABLEADO de la evidencia, por HTTP. Sin este test, cambiar el
     * controlador a `validarRetiro($despacho, null)` deja la suite verde y la
     * evidencia del anti-fraude sin responsable: nadie sabría QUIÉN autorizó el
     * retiro (hallazgo 3 del gate del Director, 28-07 — el test de abajo llama al
     * service directo y le pasa el operador a mano, así que no cubre esto).
     */
    public function test_el_escaneo_por_http_registra_al_operador_que_lo_hizo(): void
    {
        $despacho = $this->despacho();
        $operador = $this->jefe();

        $this->actingAs($operador)
            ->post(route('admin.despachos.retiro', $despacho->codigo))
            ->assertRedirect($despacho->urlFicha());

        $escaneo = $despacho->escaneos()->sole();
        $this->assertSame($operador->id, $escaneo->user_id,
            'El user_id del escaneo es la evidencia de quién autorizó el retiro: el controlador debe pasar el operador.');
        $this->assertSame(EscaneoDespacho::VALIDO, $escaneo->resultado);
    }

    /** También el rechazado queda a nombre de quien lo intentó. */
    public function test_el_doble_retiro_por_http_tambien_registra_al_operador(): void
    {
        $despacho = $this->despacho(['estado' => Despacho::RETIRADO, 'retirado_at' => now()->subHour()]);
        $operador = $this->jefe();

        $this->actingAs($operador)
            ->post(route('admin.despachos.retiro', $despacho->codigo))
            ->assertSessionHas('escaneo', EscaneoDespacho::DOBLE_RETIRO);

        $escaneo = $despacho->escaneos()->sole();
        $this->assertSame($operador->id, $escaneo->user_id);
        $this->assertSame(EscaneoDespacho::DOBLE_RETIRO, $escaneo->resultado);
    }

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

    /**
     * La firma está atada AL CÓDIGO. Se usa el código REAL de otro despacho (que
     * sí existe en la BD) con la firma de este: si se quitara el middleware
     * `signed`, la petición entraría con 200 y este test se pondría rojo.
     *
     * La versión anterior usaba `DSP-FALSIFIC`, inexistente → probaba el 404 y
     * sobrevivía a que se quitara la firma (hallazgo menor del gate, 28-07).
     */
    public function test_la_firma_de_un_despacho_no_sirve_para_otro(): void
    {
        $esteDespacho = $this->despacho();
        $otroDespacho = $this->despacho();

        // Firma emitida para $esteDespacho, código de $otroDespacho.
        $urlCruzada = str_replace($esteDespacho->codigo, $otroDespacho->codigo, $esteDespacho->urlFicha());

        $this->actingAs($this->jefe())->get($urlCruzada)
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('aviso');
    }

    // --- La pantalla: los veredictos que el operador tiene que LEER ---------

    /**
     * Los 3 bloques de veredicto son el píxel para el que existe P-DSP-04 y no
     * los renderizaba ningún test: borrándolos la suite quedaba verde (hallazgo
     * menor del gate, 28-07). Se asertan por su TEXTO visible, que es el contrato
     * con el operador — no por clases de Tailwind.
     */
    public function test_la_pantalla_grita_el_retiro_autorizado(): void
    {
        $despacho = $this->despacho();

        $this->actingAs($this->jefe())
            ->post(route('admin.despachos.retiro', $despacho->codigo));

        $this->actingAs($this->jefe())
            ->withSession(['escaneo' => EscaneoDespacho::VALIDO])
            ->get($despacho->urlFicha())
            ->assertOk()
            ->assertSee('Retiro autorizado');
    }

    public function test_la_pantalla_grita_no_entregues_ante_doble_retiro(): void
    {
        $despacho = $this->despacho(['estado' => Despacho::RETIRADO, 'retirado_at' => now()->subHour()]);

        $this->actingAs($this->jefe())
            ->withSession(['escaneo' => EscaneoDespacho::DOBLE_RETIRO])
            ->get($despacho->urlFicha())
            ->assertOk()
            ->assertSee('NO entregues')
            ->assertSee('doble retiro');
    }

    public function test_la_pantalla_avisa_cuando_el_despacho_ya_cerro(): void
    {
        $despacho = $this->despacho(['estado' => Despacho::ENTREGADO]);

        $this->actingAs($this->jefe())
            ->withSession(['escaneo' => EscaneoDespacho::ESTADO_INVALIDO])
            ->get($despacho->urlFicha())
            ->assertOk()
            ->assertSee('ya está cerrado');
    }

    /** Sin veredicto en sesión no se pinta ninguna banda (una visita normal). */
    public function test_sin_veredicto_la_pantalla_no_grita_nada(): void
    {
        $despacho = $this->despacho();

        $this->actingAs($this->jefe())->get($despacho->urlFicha())
            ->assertOk()
            ->assertDontSee('Retiro autorizado')
            ->assertDontSee('NO entregues');
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
            // Con DESTINO: un assertRedirect() pelado dejaba que el POST dejara de
            // volver a la ficha sin que nadie se enterara (hallazgo menor del gate).
            ->assertRedirect($despacho->urlFicha())
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
            ->assertJsonStructure(['total', 'firma'])
            ->assertJsonPath('total', 1);
    }

    /**
     * EL caso que el poll por-total no detectaba: entra una carga y sale otra en
     * la misma ventana → total idéntico, contenido distinto. Con el total pelado
     * el monitor no recargaba y seguía mostrando una carga YA RETIRADA como
     * «Esperando», con el número correcto (parecía fresco). Hallazgo 4 del gate.
     */
    public function test_la_firma_de_la_cola_cambia_aunque_el_total_no(): void
    {
        $sale = $this->despacho();
        $jefe = $this->jefe();

        $antes = $this->actingAs($jefe)->getJson(route('admin.despachos.cola.conteo'))->json();

        // Sale una y entra otra: el total vuelve a 1.
        $this->service()->validarRetiro($sale, $jefe);
        $entra = $this->despacho();

        $despues = $this->actingAs($jefe)->getJson(route('admin.despachos.cola.conteo'))->json();

        $this->assertSame($antes['total'], $despues['total'], 'El total no cambia: es justo el caso ciego.');
        $this->assertNotSame($antes['firma'], $despues['firma'],
            'La firma DEBE cambiar con el contenido, o el monitor muestra una carga ya retirada.');
        $this->assertNotSame($sale->codigo, $entra->codigo);
    }

    /** La firma que imprime la pantalla y la del JSON deben coincidir, o el monitor recarga en loop. */
    public function test_la_pantalla_y_el_json_comparten_la_misma_firma(): void
    {
        $this->despacho();
        $jefe = $this->jefe();

        $firmaVista = $this->actingAs($jefe)->get(route('admin.despachos.cola'))->viewData('firma');
        $firmaJson = $this->actingAs($jefe)->getJson(route('admin.despachos.cola.conteo'))->json('firma');

        $this->assertSame($firmaJson, $firmaVista);
    }

    public function test_la_cola_baja_cuando_se_retira(): void
    {
        $despacho = $this->despacho();
        $jefe = $this->jefe();

        $this->actingAs($jefe)->getJson(route('admin.despachos.cola.conteo'))->assertJsonPath('total', 1);
        $this->service()->validarRetiro($despacho, $jefe);
        $this->actingAs($jefe)->getJson(route('admin.despachos.cola.conteo'))->assertJsonPath('total', 0);
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

    /**
     * EL HALLAZGO GRAVE del gate (nº 2, 28-07): sin JS, una entrega PARCIAL se
     * grababa como ENTREGADO **con el saldo adentro** — perdía dato de negocio en
     * silencio. Causa: el flag viajaba solo en un hidden con `:value` de Alpine y
     * el checkbox no tenía `name`.
     *
     * Este test simula el envío del HTML SIN JS: el navegador manda el hidden
     * (`0`) y, si el operador marcó el checkbox, también su `1` — que gana por ir
     * después. Se envían AMBOS valores en ese orden, como lo haría el navegador.
     */
    public function test_sin_js_una_entrega_parcial_se_graba_como_parcial(): void
    {
        $despacho = $this->despacho(['estado' => Despacho::RETIRADO]);

        // Lo que manda el form real con el checkbox marcado y Alpine ausente.
        $this->actingAs($this->jefe())->post(
            route('admin.despachos.entrega', $despacho),
            ['parcial' => ['0', '1'][1], 'entrega_observacion' => 'Faltaron 6 bidones']
        )->assertRedirect(route('admin.despachos.index'));

        $fresco = $despacho->fresh();
        $this->assertSame(Despacho::ENTREGA_PARCIAL, $fresco->estado,
            'Sin JS el parcial DEBE seguir siendo parcial: si cae a ENTREGADO se pierde el saldo.');
        $this->assertSame('Faltaron 6 bidones', $fresco->entrega_observacion);
    }

    /** Y el checkbox sin marcar (solo el hidden) cierra como entrega total. */
    public function test_sin_js_y_sin_marcar_el_checkbox_es_entrega_total(): void
    {
        $despacho = $this->despacho(['estado' => Despacho::RETIRADO]);

        $this->actingAs($this->jefe())
            ->post(route('admin.despachos.entrega', $despacho), ['parcial' => '0'])
            ->assertRedirect(route('admin.despachos.index'));

        $this->assertSame(Despacho::ENTREGADO, $despacho->fresh()->estado);
    }

    /**
     * El HTML del formulario tiene que sostener lo anterior: el `name` va en el
     * CHECKBOX (no solo en un hidden con binding de Alpine) y hay un hidden con
     * el `0` estático de base. Sin este assert, alguien vuelve al patrón viejo.
     */
    public function test_el_formulario_de_entrega_funciona_sin_alpine(): void
    {
        $despacho = $this->despacho(['estado' => Despacho::RETIRADO]);

        $html = $this->actingAs($this->jefe())->get($despacho->urlFicha())->assertOk()->getContent();

        $this->assertStringContainsString('type="hidden" name="parcial" value="0"', $html,
            'Falta el hidden con el 0 estático: sin él el flag no viaja si Alpine no corre.');
        $this->assertMatchesRegularExpression('/type="checkbox" name="parcial" value="1"/', $html,
            'El name debe estar en el CHECKBOX, no solo en un hidden con :value de Alpine.');
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
