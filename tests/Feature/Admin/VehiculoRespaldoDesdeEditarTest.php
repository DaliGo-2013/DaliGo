<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\VehiculoDocumentoController;
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
 * LA FOTO SE GUARDA CON EL BOTÓN DE «GUARDAR CAMBIOS» (pedido del dueño 11-08-2026:
 * «necesito un botón de guardar para guardar las fotos»).
 *
 * Un documento son DOS datos: la foto y hasta cuándo vale. Estaban en pantallas
 * distintas —la foto se subía en la ficha, la fecha se escribía en Editar— así que
 * cargar un permiso de circulación eran dos viajes.
 *
 * Lo que estos candados protegen:
 *
 *  1. que la foto y la fecha se guarden JUNTAS con un solo botón (el pedido);
 *  2. que el formulario lleve `enctype` — sin eso el archivo no viaja, el resto SÍ
 *     se guarda y el fallo es invisible: «guardé y la foto no quedó»;
 *  3. que los dos caminos (la ficha y Editar) dejen el MISMO rastro, o sea que la
 *     compresión del servidor y el `subido_por` valen igual en los dos;
 *  4. que subir desde acá no pise el historial;
 *  5. que la subida de a una de la ficha —la del teléfono, sin botón de guardar—
 *     siga viva: son dos caminos para dos situaciones, no un reemplazo.
 */
class VehiculoRespaldoDesdeEditarTest extends TestCase
{
    use RefreshDatabase;

    private Vehiculo $camion;

    private User $gestor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake(VehiculoDocumentoController::DISCO);

        $this->camion = Vehiculo::factory()->create(['ppu' => 'ABCD12', 'tipo' => 'camion']);
        $this->gestor = tap(User::factory()->create())->assignRole('jefe_logistica');
    }

    /** Una foto «de teléfono»: grande y pesada, como llega de verdad. */
    private function foto(string $nombre = 'permiso.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($nombre, 3000, 2000);
    }

    /** Los campos que el formulario manda siempre, con lo que se le agregue encima. */
    private function guardar(array $extra = [])
    {
        return $this->actingAs($this->gestor)->put(route('admin.vehiculos.update', $this->camion), array_merge([
            'ppu' => $this->camion->ppu,
            'tipo' => $this->camion->tipo,
            'estado' => Vehiculo::ESTADO_ACTIVO,
        ], $extra));
    }

    public function test_un_solo_guardar_deja_la_foto_y_la_fecha(): void
    {
        // El caso de la captura del dueño: el extintor tenía foto y decía «Sin fecha
        // cargada», porque cada dato se cargaba en una pantalla distinta.
        $this->guardar([
            'extintor_vence' => '2027-03-15',
            'respaldos' => ['extintor_vence' => $this->foto('extintor.jpg')],
        ])->assertRedirect(route('admin.vehiculos.show', $this->camion));

        $this->assertSame('2027-03-15', $this->camion->fresh()->extintor_vence->toDateString());

        $doc = VehiculoDocumento::sole();
        $this->assertSame('extintor_vence', $doc->documento);
        $this->assertSame($this->gestor->id, $doc->subido_por);
        $this->assertTrue(Storage::disk(VehiculoDocumentoController::DISCO)->exists($doc->ruta));
    }

    public function test_la_pantalla_de_editar_manda_el_formulario_como_multipart(): void
    {
        // Sin `enctype`, el navegador manda el nombre del archivo como texto: las
        // fechas se guardan, la foto no llega, y no hay ningún error que lo delate.
        $html = $this->actingAs($this->gestor)
            ->get(route('admin.vehiculos.edit', $this->camion))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<form[^>]*enctype="multipart\/form-data"[^>]*action="[^"]*'.$this->camion->id.'/',
            $html,
            'El formulario de Editar perdió el enctype: la foto no viaja y el fallo es silencioso.',
        );
        $this->assertStringContainsString('respaldos[soap_vence]', $html, 'Falta el campo de la foto junto a su fecha.');
    }

    public function test_lo_guardado_desde_editar_tambien_es_un_jpeg_chico(): void
    {
        // La compresión es del SERVIDOR y vale igual por los dos caminos: lo que se
        // abre en un control con mala señal no puede depender de por dónde se subió.
        $this->guardar(['respaldos' => ['soap_vence' => $this->foto()]]);

        $bytes = Storage::disk(VehiculoDocumentoController::DISCO)->get(VehiculoDocumento::sole()->ruta);

        $this->assertStringStartsWith("\xFF\xD8", $bytes);
        [$w, $h] = getimagesizefromstring($bytes);
        $this->assertLessThanOrEqual(CompresorDeDocumentos::MAX_LADO, max($w, $h));
    }

    public function test_se_pueden_subir_varios_documentos_de_una(): void
    {
        $this->guardar([
            'soap_vence' => '2026-09-30',
            'permiso_circulacion_vence' => '2026-09-30',
            'respaldos' => [
                'soap_vence' => $this->foto('soap.jpg'),
                'permiso_circulacion_vence' => $this->foto('permiso.jpg'),
            ],
        ]);

        $this->assertSame(
            ['permiso_circulacion_vence', 'soap_vence'],
            VehiculoDocumento::pluck('documento')->sort()->values()->all(),
        );
    }

    public function test_el_aviso_dice_que_se_subieron_los_respaldos(): void
    {
        // Fue UNA acción —un clic en Guardar—, así que va en el MISMO aviso del
        // guardado: dos avisos harían pensar que pasaron dos cosas.
        $this->guardar(['respaldos' => ['soap_vence' => $this->foto()]])
            ->assertSessionHas('status', fn (string $s) => str_contains($s, 'actualizado')
                && str_contains($s, 'respaldo')
                && str_contains(mb_strtolower($s), 'soap'));
    }

    public function test_guardar_sin_elegir_foto_no_toca_los_respaldos(): void
    {
        // Lo normal es entrar a Editar solo a corregir una fecha. Si eso borrara o
        // duplicara el respaldo, la pantalla sería una trampa.
        $this->guardar(['respaldos' => ['soap_vence' => $this->foto()]]);
        $original = VehiculoDocumento::sole();

        $this->guardar(['soap_vence' => '2027-01-01'])->assertRedirect();

        $this->assertSame(1, VehiculoDocumento::count());
        $this->assertSame($original->id, VehiculoDocumento::sole()->id);
        $this->assertSame('2027-01-01', $this->camion->fresh()->soap_vence->toDateString());
    }

    public function test_subir_de_nuevo_no_pisa_la_version_anterior(): void
    {
        $this->guardar(['respaldos' => ['soap_vence' => $this->foto('vieja.jpg')]]);
        $vieja = VehiculoDocumento::sole();

        $this->travel(1)->second();
        $this->guardar(['respaldos' => ['soap_vence' => $this->foto('nueva.jpg')]]);

        $this->assertSame(2, VehiculoDocumento::count());
        $this->assertTrue(
            Storage::disk(VehiculoDocumentoController::DISCO)->exists($vieja->ruta),
            'La versión anterior se borró: el historial del documento tiene que quedar.',
        );
    }

    public function test_un_archivo_que_no_es_documento_se_rechaza_y_no_guarda_nada(): void
    {
        $this->guardar([
            'soap_vence' => '2026-09-30',
            'respaldos' => ['soap_vence' => UploadedFile::fake()->create('planilla.xlsx', 40)],
        ])->assertSessionHasErrors('respaldos.soap_vence');

        $this->assertSame(0, VehiculoDocumento::count());
        // Y el vehículo NO se actualizó: la validación corre antes de tocar nada.
        $this->assertNull($this->camion->fresh()->soap_vence);
    }

    public function test_un_vehiculo_nuevo_se_puede_dar_de_alta_con_su_respaldo(): void
    {
        $this->actingAs($this->gestor)->post(route('admin.vehiculos.store'), [
            'ppu' => 'ZZZZ99',
            'tipo' => 'camion',
            'estado' => Vehiculo::ESTADO_ACTIVO,
            'soap_vence' => '2026-12-31',
            'respaldos' => ['soap_vence' => $this->foto()],
        ])->assertRedirect();

        $nuevo = Vehiculo::where('ppu', 'ZZZZ99')->sole();
        $this->assertSame($nuevo->id, VehiculoDocumento::sole()->vehiculo_id);
    }

    public function test_la_subida_de_a_una_de_la_ficha_sigue_estando(): void
    {
        // Es el camino del TELÉFONO, parado al lado del camión: elegir la foto ya es
        // la acción y por eso no lleva botón de guardar. El de Editar no lo
        // reemplaza — son dos situaciones distintas.
        $this->actingAs($this->gestor)
            ->get(route('admin.vehiculos.show', $this->camion))
            ->assertOk()
            ->assertSee('Subir el documento');

        $this->actingAs($this->gestor)->post(
            route('admin.vehiculos.documentos.store', [$this->camion, 'soap_vence']),
            ['archivo' => $this->foto()],
        )->assertRedirect(route('admin.vehiculos.show', $this->camion));

        $this->assertSame(1, VehiculoDocumento::count());
    }
}
