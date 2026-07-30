<?php

namespace Tests\Feature\Despachos;

use App\Models\Cliente;
use App\Models\Despacho;
use App\Models\DocumentoVenta;
use App\Models\User;
use App\Models\Zona;
use App\Services\Despachos\DespachoService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * DESPACHOS-v1 · P-DSP-05: la PWA del conductor (M08-MVP).
 *
 * Lo que se blinda: el SCOPING (un conductor solo ve y confirma LO SUYO), la
 * IDEMPOTENCIA por entrega_uuid (la cola offline puede reintentar el mismo
 * envío tras un corte y no debe duplicar ni pisar nada), y que la evidencia
 * (firma + foto + hora del dispositivo) persista de verdad.
 */
class EntregaConductorTest extends TestCase
{
    use RefreshDatabase;

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
    }

    private function conductor(): User
    {
        return User::factory()->create()->assignRole('conductor');
    }

    private function despacho(User $conductor = null, array $overrides = []): Despacho
    {
        $this->n++;
        $zona = Zona::create(['nombre' => 'Zona '.$this->n, 'activa' => true]);
        $cliente = Cliente::create(['razon_social' => 'Cliente '.$this->n.' SpA', 'zona_id' => $zona->id]);
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
            'conductor_id' => $conductor?->id,
            'estado' => Despacho::RETIRADO,
            'retirado_at' => now()->subHour(),
        ], $overrides));
    }

    /** El payload multipart que manda el form / la cola offline. */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'entrega_uuid' => (string) Str::uuid(),
            'capturado_at' => '2026-07-28T15:42:11.000Z',
            'foto' => UploadedFile::fake()->image('entrega.jpg', 800, 600),
            'firma' => UploadedFile::fake()->image('firma.png', 600, 300),
        ], $overrides);
    }

    // --- Hoja de ruta -------------------------------------------------------

    public function test_hoja_de_ruta_solo_muestra_lo_del_conductor_en_reparto(): void
    {
        $conductor = $this->conductor();
        $mio = $this->despacho($conductor);
        $deOtro = $this->despacho($this->conductor());
        $sinConductor = $this->despacho(null);
        $mioPeroPreparado = $this->despacho($conductor, ['estado' => Despacho::PREPARADO]);
        $mioPeroEntregado = $this->despacho($conductor, ['estado' => Despacho::ENTREGADO]);

        $this->actingAs($conductor)->get(route('entregas.index'))
            ->assertOk()
            ->assertViewIs('entregas.index')
            ->assertSee($mio->codigo)
            ->assertDontSee($deOtro->codigo)
            ->assertDontSee($sinConductor->codigo)
            ->assertDontSee($mioPeroPreparado->codigo)
            ->assertDontSee($mioPeroEntregado->codigo);
    }

    public function test_hoja_de_ruta_agrupa_por_zona_incluida_la_sin_zona(): void
    {
        $conductor = $this->conductor();
        $conZona = $this->despacho($conductor);
        $sinZona = $this->despacho($conductor, ['zona_id' => null]);

        $res = $this->actingAs($conductor)->get(route('entregas.index'))->assertOk();

        $porZona = $res->viewData('porZona');
        $this->assertTrue($porZona->has($conZona->zona->nombre));
        $this->assertTrue($porZona->has('Sin zona'));
        $res->assertSee('Sin zona');
    }

    public function test_get_sin_permiso_redirige_amable_y_post_conserva_403(): void
    {
        $intruso = User::factory()->create(); // sin 'confirmar entrega'
        $despacho = $this->despacho($this->conductor());

        $this->actingAs($intruso)->get(route('entregas.index'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('aviso');

        $this->actingAs($intruso)
            ->post(route('entregas.confirmar', $despacho), $this->payload())
            ->assertForbidden();
    }

    // --- Confirmación: el camino feliz --------------------------------------

    public function test_confirma_entrega_total_con_firma_foto_y_hora_del_dispositivo(): void
    {
        $conductor = $this->conductor();
        $despacho = $this->despacho($conductor);
        $payload = $this->payload();

        $this->actingAs($conductor)
            ->post(route('entregas.confirmar', $despacho), $payload, ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['ok' => true, 'despacho' => $despacho->codigo, 'duplicado' => false]);

        $fresco = $despacho->fresh();
        $this->assertSame(Despacho::ENTREGADO, $fresco->estado);
        $this->assertSame($payload['entrega_uuid'], $fresco->entrega_uuid);
        // La hora del DISPOSITIVO persiste tal cual (offline-safe)...
        $this->assertSame('2026-07-28 15:42:11', $fresco->capturado_at->format('Y-m-d H:i:s'));
        // ...y la del server queda como verdad de auditoría.
        $this->assertNotNull($fresco->entregado_at);
        // La evidencia existe en disco (comprimida por ImagenComprimida).
        $this->assertNotNull($fresco->foto_path);
        $this->assertNotNull($fresco->firma_path);
        Storage::disk('local')->assertExists($fresco->foto_path);
        Storage::disk('local')->assertExists($fresco->firma_path);
    }

    public function test_rama_web_redirige_a_la_hoja_con_status(): void
    {
        $conductor = $this->conductor();
        $despacho = $this->despacho($conductor);

        $this->actingAs($conductor)
            ->post(route('entregas.confirmar', $despacho), $this->payload())
            ->assertRedirect(route('entregas.index'))
            ->assertSessionHas('status');
    }

    // --- Idempotencia (el corazón de la cola offline) ------------------------

    public function test_es_idempotente_por_entrega_uuid_y_no_pisa_la_evidencia(): void
    {
        $conductor = $this->conductor();
        $despacho = $this->despacho($conductor);
        $uuid = (string) Str::uuid();

        $this->actingAs($conductor)
            ->post(route('entregas.confirmar', $despacho), $this->payload(['entrega_uuid' => $uuid]), ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['duplicado' => false]);

        $primeraHora = $despacho->fresh()->capturado_at;
        $primeraFirma = $despacho->fresh()->firma_path;

        // El reintento de la cola: mismo uuid, otra hora (el device siguió corriendo).
        $this->actingAs($conductor)
            ->post(route('entregas.confirmar', $despacho), $this->payload([
                'entrega_uuid' => $uuid,
                'capturado_at' => '2026-07-28T18:00:00.000Z',
            ]), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['ok' => true, 'duplicado' => true]);

        $fresco = $despacho->fresh();
        $this->assertEquals($primeraHora, $fresco->capturado_at, 'El duplicado no debe pisar la hora original.');
        $this->assertSame($primeraFirma, $fresco->firma_path, 'El duplicado no debe pisar la firma original.');
    }

    /**
     * La carrera REAL del unique: dos drenados de la cola con el mismo uuid
     * pasan AMBOS el pre-check (ninguno ve nada), uno escribe primero y el otro
     * choca con la BD. En un test single-thread la ventana no se puede abrir
     * por la API pública, así que se simula con un mock parcial: nuestro
     * `registrarEntrega` primero deja escrita la fila del "otro drenado" y
     * luego lanza la QueryException de unique — exactamente lo que este proceso
     * observaría. El catch debe re-buscar por uuid y responder duplicado, jamás
     * reventar con 500 (para la cola offline un 500 es transitorio y
     * REINTENTARÍA para siempre un envío que en realidad ya está registrado).
     */
    public function test_la_carrera_del_unique_responde_idempotente_sin_500(): void
    {
        $conductor = $this->conductor();
        $ganador = $this->despacho($conductor, ['estado' => Despacho::ENTREGADO]);
        $perdedor = $this->despacho($conductor);
        $uuid = (string) Str::uuid();

        $unique = new QueryException(
            'sqlite',
            'insert into "despachos" ...',
            [],
            new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: despachos.entrega_uuid'),
        );

        $service = Mockery::mock(DespachoService::class)->makePartial();
        $service->shouldReceive('registrarEntrega')->once()
            ->andReturnUsing(function () use ($ganador, $uuid, $unique) {
                // El "otro drenado" gana la carrera justo aquí...
                $ganador->forceFill(['entrega_uuid' => $uuid])->save();
                // ...y nuestro update choca con el unique.
                throw $unique;
            });

        $resultado = $service->confirmarEntregaConductor($perdedor, [
            'entrega_uuid' => $uuid,
            'capturado_at' => now()->toIso8601String(),
        ]);

        $this->assertTrue($resultado['yaExistia'], 'La carrera debe resolverse como duplicado, no como error.');
        $this->assertTrue($resultado['despacho']->is($ganador), 'Debe devolver la fila que ganó la carrera.');
        // El perdedor quedó intacto: nadie le escribió nada.
        $this->assertSame(Despacho::RETIRADO, $perdedor->fresh()->estado);
    }

    public function test_entrega_uuid_es_unique_estructural_y_los_null_no_chocan(): void
    {
        $conductor = $this->conductor();
        $uuid = (string) Str::uuid();

        $a = $this->despacho($conductor, ['estado' => Despacho::ENTREGADO]);
        $a->forceFill(['entrega_uuid' => $uuid])->save();

        // Dos NULL conviven (los despachos sin entrega no chocan entre sí).
        $this->despacho($conductor);
        $this->despacho($conductor);
        $this->assertSame(2, Despacho::whereNull('entrega_uuid')->where('conductor_id', $conductor->id)->count());

        // El mismo uuid en otra fila: la BD lo rechaza (la red estructural).
        $this->expectException(QueryException::class);
        $b = $this->despacho($conductor, ['estado' => Despacho::ENTREGADO]);
        $b->forceFill(['entrega_uuid' => $uuid])->save();
    }

    // --- Scoping -------------------------------------------------------------

    public function test_conductor_ajeno_no_confirma(): void
    {
        $despacho = $this->despacho($this->conductor());

        $this->actingAs($this->conductor())
            ->post(route('entregas.confirmar', $despacho), $this->payload(), ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->assertSame(Despacho::RETIRADO, $despacho->fresh()->estado);
    }

    public function test_despacho_sin_conductor_no_se_confirma(): void
    {
        $despacho = $this->despacho(null);

        $this->actingAs($this->conductor())
            ->post(route('entregas.confirmar', $despacho), $this->payload(), ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    // --- Reglas de negocio ----------------------------------------------------

    public function test_parcial_exige_saldo(): void
    {
        $conductor = $this->conductor();
        $despacho = $this->despacho($conductor);

        $this->actingAs($conductor)
            ->post(route('entregas.confirmar', $despacho), $this->payload(['parcial' => 1]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('entrega_observacion');

        $this->assertSame(Despacho::RETIRADO, $despacho->fresh()->estado);
    }

    public function test_parcial_valido_queda_entrega_parcial_con_saldo_visible(): void
    {
        $conductor = $this->conductor();
        $despacho = $this->despacho($conductor);

        $this->actingAs($conductor)
            ->post(route('entregas.confirmar', $despacho), $this->payload([
                'parcial' => 1,
                'entrega_observacion' => 'Faltaron 3 botellones de 20L',
            ]), ['Accept' => 'application/json'])
            ->assertOk();

        $fresco = $despacho->fresh();
        $this->assertSame(Despacho::ENTREGA_PARCIAL, $fresco->estado);
        $this->assertSame('Faltaron 3 botellones de 20L', $fresco->entrega_observacion);
    }

    public function test_firma_y_foto_son_obligatorias(): void
    {
        $conductor = $this->conductor();
        $despacho = $this->despacho($conductor);

        $this->actingAs($conductor)
            ->post(route('entregas.confirmar', $despacho),
                collect($this->payload())->except(['firma', 'foto'])->all(),
                ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['firma', 'foto']);
    }

    public function test_archivos_sobre_el_tope_se_rechazan(): void
    {
        $conductor = $this->conductor();
        $despacho = $this->despacho($conductor);

        $this->actingAs($conductor)
            ->post(route('entregas.confirmar', $despacho), $this->payload([
                'foto' => UploadedFile::fake()->create('gigante.jpg', 9000, 'image/jpeg'),
                'firma' => UploadedFile::fake()->create('firma.png', 3000, 'image/png'),
            ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['foto', 'firma']);
    }

    public function test_no_se_confirma_lo_que_no_salio_de_bodega(): void
    {
        $conductor = $this->conductor();
        $despacho = $this->despacho($conductor, ['estado' => Despacho::PREPARADO]);

        $this->actingAs($conductor)
            ->post(route('entregas.confirmar', $despacho), $this->payload(), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('estado');

        $this->assertSame(Despacho::PREPARADO, $despacho->fresh()->estado);
    }

    /**
     * Smoke de la UI: los ganchos de la cola offline están en la hoja. Sin esto,
     * quitar el x-data del form o la sección de rechazadas dejaría la suite
     * verde con la PWA muda (lección de los veredictos de P-DSP-04).
     */
    public function test_la_hoja_renderiza_los_ganchos_de_la_cola(): void
    {
        $conductor = $this->conductor();
        $despacho = $this->despacho($conductor);

        $this->actingAs($conductor)->get(route('entregas.index'))
            ->assertOk()
            ->assertSee('entregaForm', false)                                    // el form Alpine por tarjeta
            ->assertSee(route('entregas.confirmar', $despacho), false)           // apunta al endpoint real
            ->assertSee('data-firma-pad', false)                                 // el pad de firma
            ->assertSee('data-foto', false)                                      // el input de foto (via x-archivo-input; entregaForm lo busca por este marcador, no por $refs)
            ->assertSee('data-rechazadas', false)                                // la sección de rechazadas
            ->assertSee('No se pudieron enviar')
            ->assertSee('se envía sola al volver la señal');                     // el mensaje de encolada
    }

    /**
     * Estructural (patrón RutConKTest): la validación de la foto va por
     * `mimetypes` y NUNCA por la regla `image` — el HEIC de iPhone rompe
     * `image` y es el formato por defecto de las fotos de media flota de
     * celulares (gotcha M12). Se lee el fuente porque un test funcional con
     * HEIC real necesitaría un fixture binario.
     */
    public function test_la_validacion_de_foto_tolera_heic_estructuralmente(): void
    {
        $fuente = file_get_contents(app_path('Http/Controllers/Entregas/EntregaConductorController.php'));

        $this->assertStringContainsString('image/heic', $fuente,
            'La foto debe aceptar HEIC (mimetypes), el formato nativo de iPhone.');
        $this->assertDoesNotMatchRegularExpression(
            "/'foto' => \[[^\]]*'image'/",
            $fuente,
            "La regla 'image' rompe con HEIC: usar mimetypes explícitos (gotcha M12).",
        );
    }
}
