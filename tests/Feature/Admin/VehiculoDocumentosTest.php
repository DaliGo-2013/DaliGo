<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoDocumento;
use App\Services\Logistica\CompresorDeDocumentos;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Respaldo digital de los documentos del vehículo (pedido del dueño 11-08-2026):
 * la foto del permiso / SOAP para mostrarla desde el teléfono en un control de
 * ruta. Lo que estos candados protegen, en orden de gravedad:
 *
 *  1. el archivo NUNCA se sirve sin login ni sin permiso (lleva la patente —
 *     dato personal, 21.719);
 *  2. la compresión es del SERVIDOR: pase lo que pase con el que sube, lo
 *     guardado es un JPEG chico (el conductor lo abre con la señal que haya);
 *  3. subir es de quien gestiona la flota; el conductor VE;
 *  4. el historial no se pisa: subir de nuevo conserva la versión anterior.
 */
class VehiculoDocumentosTest extends TestCase
{
    use RefreshDatabase;

    private Vehiculo $camion;

    private User $gestor;

    private User $conductor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake(\App\Http\Controllers\Admin\VehiculoDocumentoController::DISCO);

        $this->camion = Vehiculo::factory()->create(['ppu' => 'ABCD12']);
        $this->gestor = tap(User::factory()->create())->assignRole('jefe_logistica');
        $this->conductor = tap(User::factory()->create())->assignRole('conductor');
    }

    /** Una foto "de teléfono": grande y pesada, como llega de verdad. */
    private function foto(): UploadedFile
    {
        return UploadedFile::fake()->image('permiso.jpg', 3000, 2000);
    }

    private function subir(User $como, ?UploadedFile $archivo = null, string $clave = 'soap_vence')
    {
        return $this->actingAs($como)->post(
            route('admin.vehiculos.documentos.store', [$this->camion, $clave]),
            ['archivo' => $archivo ?? $this->foto()],
        );
    }

    // ── La compresión es del servidor ───────────────────────────────────────

    public function test_lo_guardado_es_un_jpeg_chico_aunque_la_foto_llegue_gigante(): void
    {
        $this->subir($this->gestor)->assertRedirect(route('admin.vehiculos.show', $this->camion));

        $doc = VehiculoDocumento::sole();
        $bytes = Storage::disk(\App\Http\Controllers\Admin\VehiculoDocumentoController::DISCO)->get($doc->ruta);

        // Es JPEG de verdad (magia FFD8), no lo que haya llegado.
        $this->assertStringStartsWith("\xFF\xD8", $bytes);
        // Reducida al lado máximo del compresor: 3000 px no viajan a un control.
        [$w, $h] = getimagesizefromstring($bytes);
        $this->assertLessThanOrEqual(CompresorDeDocumentos::MAX_LADO, max($w, $h));
        // Y el registro dice lo que pesa de verdad.
        $this->assertSame((int) max(1, round(strlen($bytes) / 1024)), $doc->tamano_kb);
    }

    public function test_un_png_con_transparencia_no_queda_negro(): void
    {
        // JPEG no tiene alfa: sin el fondo blanco explícito, la transparencia de
        // una captura de pantalla sale NEGRA y el documento queda ilegible.
        $png = imagecreatetruecolor(80, 60);
        imagesavealpha($png, true);
        imagefill($png, 0, 0, imagecolorallocatealpha($png, 0, 0, 0, 127));
        ob_start();
        imagepng($png);
        $tmp = tempnam(sys_get_temp_dir(), 'doc').'.png';
        file_put_contents($tmp, ob_get_clean());

        $jpeg = (new CompresorDeDocumentos)->aJpeg(new UploadedFile($tmp, 'captura.png', 'image/png', null, true));

        $gd = imagecreatefromstring($jpeg);
        $rgb = imagecolorsforindex($gd, imagecolorat($gd, 40, 30));
        $this->assertGreaterThan(200, $rgb['red'], 'La transparencia del PNG quedó oscura en el JPEG.');
    }

    public function test_un_pdf_sin_imagick_se_rechaza_con_el_camino_alternativo(): void
    {
        // En el hosting puede no estar Imagick: el PDF no se guarda tal cual (5 MB
        // con visor distinto por teléfono), se rechaza DICIENDO qué hacer.
        $this->app->instance(CompresorDeDocumentos::class, new CompresorDeDocumentos(pdfDisponible: false));

        $pdf = UploadedFile::fake()->create('soap.pdf', 500, 'application/pdf');

        $this->subir($this->gestor, $pdf)->assertSessionHasErrors('archivo');
        $this->assertSame(0, VehiculoDocumento::count());
        $this->assertStringContainsString('foto', session('errors')->first('archivo'));
    }

    // ── Quién sube y quién ve ───────────────────────────────────────────────

    public function test_el_conductor_ve_el_documento_pero_no_puede_subir(): void
    {
        $this->subir($this->gestor);
        $doc = VehiculoDocumento::sole();

        // VE la pantalla del documento y los bytes del archivo…
        $this->actingAs($this->conductor)
            ->get(route('admin.vehiculos.documentos.show', [$this->camion, 'soap_vence']))
            ->assertOk()->assertSee('SOAP')->assertSee('ABCD12');
        $this->actingAs($this->conductor)
            ->get(route('admin.vehiculos.documentos.archivo', $doc))
            ->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        // …pero subir es de quien gestiona la flota.
        $this->subir($this->conductor)->assertForbidden();
    }

    public function test_el_archivo_jamas_se_sirve_sin_login_ni_sin_permiso(): void
    {
        // El documento se crea DIRECTO (sin pasar por actingAs, que quedaría
        // pegado al resto del test) — lo que se prueba acá es el acceso.
        Storage::disk(\App\Http\Controllers\Admin\VehiculoDocumentoController::DISCO)
            ->put('vehiculos-documentos/prueba.jpg', "\xFF\xD8prueba");
        $doc = VehiculoDocumento::create([
            'vehiculo_id' => $this->camion->id, 'documento' => 'soap_vence',
            'ruta' => 'vehiculos-documentos/prueba.jpg', 'tamano_kb' => 1,
        ]);

        // Sin login → al login. Es LA regla: el documento lleva la patente.
        $this->get(route('admin.vehiculos.documentos.archivo', $doc))->assertRedirect(route('login'));

        // Logueado pero sin 'ver vehiculos': el proyecto convierte el 403 de un
        // GET navegado en redirect al Inicio con aviso (bootstrap/app.php) — lo
        // que importa acá es que los BYTES no salgan.
        $pelado = User::factory()->create();
        $res = $this->actingAs($pelado)->get(route('admin.vehiculos.documentos.archivo', $doc));
        $res->assertRedirect();
        $this->assertStringNotContainsString("\xFF\xD8", (string) $res->getContent());
    }

    public function test_el_archivo_viaja_con_cache_privado(): void
    {
        // `private`: lo cachea el TELÉFONO del conductor (la segunda vez abre al
        // toque aunque la señal sea mala), ningún proxy intermedio.
        $this->subir($this->gestor);

        $this->actingAs($this->conductor)
            ->get(route('admin.vehiculos.documentos.archivo', VehiculoDocumento::sole()))
            ->assertHeader('Cache-Control', 'max-age=86400, private');
    }

    // ── Historial ───────────────────────────────────────────────────────────

    public function test_subir_de_nuevo_conserva_la_version_anterior(): void
    {
        $this->subir($this->gestor);
        $this->subir($this->gestor);

        $this->assertSame(2, VehiculoDocumento::count(), 'La segunda subida pisó a la primera.');

        // La pantalla muestra el vigente y pliega el anterior como historial.
        $this->actingAs($this->gestor)
            ->get(route('admin.vehiculos.documentos.show', [$this->camion, 'soap_vence']))
            ->assertOk()->assertSee('Versiones anteriores (1)');
    }

    // ── Bordes ──────────────────────────────────────────────────────────────

    public function test_una_clave_de_documento_inventada_no_pasa(): void
    {
        // El POST conserva su 404 crudo (el manejador global solo suaviza GET
        // navegados); nada llega a guardarse.
        $this->actingAs($this->gestor)
            ->post(route('admin.vehiculos.documentos.store', [$this->camion, 'clave_falsa']), ['archivo' => $this->foto()])
            ->assertNotFound();
        $this->assertSame(0, VehiculoDocumento::count());

        // El GET navegado sigue la convención del proyecto (bootstrap/app.php):
        // 404 autenticado → al Inicio con el aviso, no la pantalla de Symfony.
        $this->actingAs($this->gestor)
            ->get(route('admin.vehiculos.documentos.show', [$this->camion, 'clave_falsa']))
            ->assertRedirect(route('dashboard'));
    }

    public function test_un_documento_sin_respaldo_no_abre_y_la_ficha_ofrece_subirlo(): void
    {
        $this->actingAs($this->gestor)
            ->get(route('admin.vehiculos.documentos.show', [$this->camion, 'soap_vence']))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($this->gestor)
            ->get(route('admin.vehiculos.show', $this->camion))
            ->assertOk()->assertSee('Subir el documento');
    }
}
