<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\VehiculoDocumentoController;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoDocumento;
use App\Models\VehiculoDocumentoFecha;
use App\Models\VehiculoDocumentoTipo;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * DOCUMENTOS QUE SE PUEDEN CREAR (pedido del dueño 11-08-2026: «otra opción para
 * crear uno nuevo si a futuro pidieran»).
 *
 * Lo que estos candados protegen:
 *
 *  1. que un documento creado se comporte como los de la ley: fecha, foto, semáforo
 *     y aviso — si no, sería una lista decorativa;
 *  2. que NO ensucie el semáforo de vehículos a los que no les toca, que es el riesgo
 *     real de dejar crear documentos (17 unidades en rojo de un día para el otro);
 *  3. que un tipo con datos cargados no se pueda borrar de un clic: se desactiva, y
 *     las fechas y fotos ya cargadas quedan;
 *  4. que los cinco de la ley sigan siendo intocables.
 */
class VehiculoTipoDocumentoTest extends TestCase
{
    use RefreshDatabase;

    private User $gestor;

    private Vehiculo $camion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake(VehiculoDocumentoController::DISCO);

        $this->gestor = tap(User::factory()->create())->assignRole('jefe_logistica');
        $this->camion = Vehiculo::factory()->alDia()->create(['ppu' => 'ABCD12', 'tipo' => 'camion']);
    }

    private function crear(array $datos = []): VehiculoDocumentoTipo
    {
        $this->actingAs($this->gestor)->post(route('admin.vehiculos.tipos-documento.store'), array_merge([
            'nombre' => 'Póliza de carga peligrosa',
            'aplica_a' => ['camion'],
        ], $datos))->assertRedirect();

        // El aviso del alta es flash y sobrevive al request siguiente: sin esto, un
        // `assertDontSee` del nombre del documento da falso positivo contra el cartel
        // verde y no contra la pantalla.
        session()->forget('status');
        Vehiculo::olvidarTiposCreados();

        return VehiculoDocumentoTipo::where('nombre', $datos['nombre'] ?? 'Póliza de carga peligrosa')->sole();
    }

    // ── Se comporta como un documento de verdad ─────────────────────────────

    public function test_un_documento_creado_aparece_en_la_ficha_con_su_fecha_y_su_foto(): void
    {
        $tipo = $this->crear();

        $doc = collect($this->camion->fresh()->documentos())->firstWhere('clave', $tipo->clave);
        $this->assertNotNull($doc, 'El documento creado no llegó a la ficha del vehículo.');
        $this->assertSame('Póliza de carga peligrosa', $doc['label']);
        $this->assertSame(Vehiculo::DOC_SIN_REGISTRO, $doc['estado']);

        $this->actingAs($this->gestor)
            ->get(route('admin.vehiculos.show', $this->camion))
            ->assertOk()->assertSee('Póliza de carga peligrosa');
    }

    public function test_la_fecha_y_la_foto_de_un_creado_se_guardan_con_el_mismo_boton(): void
    {
        $tipo = $this->crear();

        $this->actingAs($this->gestor)->put(route('admin.vehiculos.update', $this->camion), [
            'ppu' => $this->camion->ppu,
            'tipo' => $this->camion->tipo,
            'estado' => Vehiculo::ESTADO_ACTIVO,
            'doc_creado' => [$tipo->id => '2027-05-20'],
            'respaldos' => [$tipo->clave => UploadedFile::fake()->image('poliza.jpg', 900, 600)],
        ])->assertRedirect();

        Vehiculo::olvidarTiposCreados();
        $this->assertSame('2027-05-20', VehiculoDocumentoFecha::sole()->vence->toDateString());

        $respaldo = VehiculoDocumento::sole();
        $this->assertSame($tipo->clave, $respaldo->documento);
        $this->assertTrue(Storage::disk(VehiculoDocumentoController::DISCO)->exists($respaldo->ruta));
    }

    public function test_guardar_dos_veces_no_duplica_la_fecha(): void
    {
        // El formulario manda la fecha de todos los tipos en cada guardado. Insertando
        // en vez de actualizar, el semáforo terminaría leyendo cualquiera de dos filas.
        $tipo = $this->crear();

        foreach (['2027-05-20', '2028-01-10'] as $fecha) {
            $this->actingAs($this->gestor)->put(route('admin.vehiculos.update', $this->camion), [
                'ppu' => $this->camion->ppu,
                'tipo' => $this->camion->tipo,
                'estado' => Vehiculo::ESTADO_ACTIVO,
                'doc_creado' => [$tipo->id => $fecha],
            ])->assertRedirect();
        }

        $this->assertSame(1, VehiculoDocumentoFecha::count());
        $this->assertSame('2028-01-10', VehiculoDocumentoFecha::sole()->vence->toDateString());
    }

    public function test_un_creado_vencido_pone_al_vehiculo_en_rojo_como_cualquier_otro(): void
    {
        // Es la prueba de que no es una lista decorativa: si no moviera el semáforo,
        // crear el documento no serviría de nada.
        $tipo = $this->crear();
        VehiculoDocumentoFecha::create([
            'vehiculo_id' => $this->camion->id, 'tipo_id' => $tipo->id,
            'vence' => now()->subDays(3)->toDateString(),
        ]);

        $this->assertSame(Vehiculo::DOC_VENCIDO, $this->camion->fresh()->estado_documental);
        $this->actingAs($this->gestor)
            ->get(route('admin.vehiculos.index'))
            ->assertOk()->assertSee('Póliza de carga peligrosa');
    }

    // ── No ensucia a quien no le toca ───────────────────────────────────────

    public function test_un_creado_no_toca_a_los_vehiculos_a_los_que_no_le_aplica(): void
    {
        // EL RIESGO REAL de dejar crear documentos: un tipo que aplica a todo deja la
        // flota entera en «sin fecha» de un día para el otro. Por eso se elige a qué
        // tipos les toca, y para el resto el estado es NO APLICA, no SIN FECHA.
        $remolque = Vehiculo::factory()->alDia()->create(['ppu' => 'ZZZZ99', 'tipo' => 'semirremolque']);
        $tipo = $this->crear(['aplica_a' => ['camion']]);

        $doc = collect($remolque->fresh()->documentos())->firstWhere('clave', $tipo->clave);
        $this->assertSame(Vehiculo::DOC_NO_APLICA, $doc['estado']);
        $this->assertSame(Vehiculo::DOC_AL_DIA, $remolque->fresh()->estado_documental);

        // Y al camión, que sí lo lleva, le falta la fecha.
        $this->assertSame(Vehiculo::DOC_SIN_REGISTRO, $this->camion->fresh()->estado_documental);
    }

    public function test_marcar_todos_los_tipos_se_guarda_como_a_todos(): void
    {
        // Guardar la lista completa dejaría afuera a un tipo de vehículo que se agregue
        // mañana al catálogo, sin que nadie lo note. «A todos» se guarda vacío.
        $tipo = $this->crear(['aplica_a' => array_keys(Vehiculo::TIPOS)]);

        $this->assertSame([], $tipo->aplica_a);
        $this->assertTrue($tipo->aplicaA('semirremolque'));
    }

    public function test_un_tipo_desactivado_sale_del_semaforo_y_del_formulario(): void
    {
        $tipo = $this->crear();
        VehiculoDocumentoFecha::create([
            'vehiculo_id' => $this->camion->id, 'tipo_id' => $tipo->id,
            'vence' => now()->subDays(3)->toDateString(),
        ]);
        $this->assertSame(Vehiculo::DOC_VENCIDO, $this->camion->fresh()->estado_documental);

        $tipo->update(['activo' => false]);
        Vehiculo::olvidarTiposCreados();

        $this->assertSame(Vehiculo::DOC_AL_DIA, $this->camion->fresh()->estado_documental);
        // Se mira el CAMPO y no el nombre: el nombre puede aparecer en un aviso o en un
        // rótulo suelto, y lo que importa es que el formulario ya no lo ofrezca.
        $this->actingAs($this->gestor)
            ->get(route('admin.vehiculos.edit', $this->camion))
            ->assertOk()->assertDontSee("doc_creado[{$tipo->id}]", false);
    }

    // ── Borrar no se lleva el historial ─────────────────────────────────────

    public function test_un_tipo_con_datos_cargados_se_desactiva_en_vez_de_borrarse(): void
    {
        $tipo = $this->crear();
        VehiculoDocumentoFecha::create([
            'vehiculo_id' => $this->camion->id, 'tipo_id' => $tipo->id, 'vence' => '2027-05-20',
        ]);

        $this->actingAs($this->gestor)
            ->delete(route('admin.vehiculos.tipos-documento.destroy', $tipo))
            ->assertSessionHas('status', fn (string $s) => str_contains($s, 'desactivó'));

        $this->assertFalse($tipo->fresh()->activo);
        $this->assertSame(1, VehiculoDocumentoFecha::count(), 'Se perdió la fecha ya cargada.');
    }

    public function test_un_tipo_que_nunca_se_uso_si_se_borra(): void
    {
        $tipo = $this->crear();

        $this->actingAs($this->gestor)
            ->delete(route('admin.vehiculos.tipos-documento.destroy', $tipo))
            ->assertSessionHas('status', fn (string $s) => str_contains($s, 'eliminado'));

        $this->assertSame(0, VehiculoDocumentoTipo::count());
    }

    public function test_no_se_pueden_crear_dos_documentos_con_el_mismo_nombre(): void
    {
        $this->crear();

        $this->actingAs($this->gestor)
            ->post(route('admin.vehiculos.tipos-documento.store'), ['nombre' => 'Póliza de carga peligrosa'])
            ->assertSessionHasErrors('nombre');

        $this->assertSame(1, VehiculoDocumentoTipo::count());
    }

    // ── Los cinco de la ley y el permiso ────────────────────────────────────

    public function test_los_cinco_de_la_ley_no_se_administran_desde_aca(): void
    {
        // Son columnas del vehículo, están en el Excel y en el comando de avisos: la
        // pantalla los muestra como fijos, sin botón de borrar.
        $html = $this->actingAs($this->gestor)
            ->get(route('admin.vehiculos.tipos-documento.index'))
            ->assertOk()->getContent();

        foreach (Vehiculo::DOCUMENTOS as $label) {
            $this->assertStringContainsString($label, $html);
        }
        $this->assertStringContainsString('no se pueden borrar', $html);
    }

    public function test_solo_quien_gestiona_la_flota_puede_crear_tipos(): void
    {
        // Crear un documento cambia el semáforo de TODA la flota: no es una pantalla
        // de consulta. El conductor, que sí ve los documentos, no entra.
        $conductor = tap(User::factory()->create())->assignRole('conductor');

        $this->actingAs($conductor)
            ->get(route('admin.vehiculos.tipos-documento.index'))
            ->assertRedirect();

        $this->actingAs($conductor)
            ->post(route('admin.vehiculos.tipos-documento.store'), ['nombre' => 'Lo que sea'])
            ->assertForbidden();

        $this->assertSame(0, VehiculoDocumentoTipo::count());
    }
}
