<?php

namespace Tests\Feature;

use App\Models\LoteServicio;
use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\Sucursal;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Ingreso PÚBLICO por CANTIDAD (QR): el cliente escribe sus datos una vez y
 * agrega N máquinas; cada una queda como una orden con su propio folio,
 * fuente 'qr' sin confirmar, con 2 fotos obligatorias por máquina.
 */
class IngresoTallerLotePublicoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
    }

    private function sucursal(): Sucursal
    {
        return Sucursal::firstOrCreate(['codigo' => 'MIRADOR'], ['activa' => true, 'nombre' => 'Mirador', 'es_central' => true]);
    }

    private function fotos(): array
    {
        return [
            UploadedFile::fake()->image('a.jpg', 800, 600),
            UploadedFile::fake()->image('b.jpg', 800, 600),
        ];
    }

    private function producto(): Producto
    {
        return Producto::firstOrCreate(
            ['sku' => '1030034'],
            ['nombre' => 'DISP LB-07B', 'categoria' => 'AGUA DISP. SOBREMESA COMPRESOR']
        );
    }

    private function payload(Sucursal $sucursal, array $overrides = []): array
    {
        $prod = $this->producto();

        return array_merge([
            'sucursal_id' => $sucursal->id,
            'cliente_nombre' => 'Aguas JB SpA',
            'cliente_rut' => '12.345.678-5',
            'cliente_email' => 'jb@empresa.cl',
            'cliente_telefono' => '+56 9 1234 5678',
            'facturacion' => 'reparacion',
            'tipo_default' => 'dispensador',
            'maquinas' => [
                ['producto_id' => $prod->id, 'numero_serie' => 'SN-01', 'falla_reportada' => 'No enfría. Golpeada, sin caja.', 'fotos' => $this->fotos()],
                ['producto_id' => $prod->id, 'numero_serie' => 'SN-02', 'falla_reportada' => 'No calienta. Rayada en tapa.', 'fotos' => $this->fotos()],
            ],
        ], $overrides);
    }

    // --- Acceso ---

    public function test_get_lote_exige_firma(): void
    {
        $sucursal = $this->sucursal();

        $this->get(route('ingreso-taller.lote.create', ['sucursal' => $sucursal->id]))->assertForbidden();
        $this->get(URL::signedRoute('ingreso-taller.lote.create', ['sucursal' => $sucursal->id]))
            ->assertOk()
            ->assertSee('Ingreso por cantidad')
            // Botón de regreso a la pantalla principal del QR (elegir otro modo).
            ->assertSee('Volver al inicio');
    }

    public function test_chooser_muestra_las_dos_opciones(): void
    {
        $sucursal = $this->sucursal();

        $this->get(URL::signedRoute('ingreso-taller.create', ['sucursal' => $sucursal->id]))
            ->assertOk()
            ->assertSee('Ingreso por unidad')
            ->assertSee('Ingreso por cantidad');
    }

    // --- Creación ---

    public function test_crea_un_lote_con_folio_propio_por_maquina(): void
    {
        $sucursal = $this->sucursal();

        $res = $this->post(route('ingreso-taller.lote.store'), $this->payload($sucursal));
        $res->assertSessionHasNoErrors()->assertRedirect();

        $lote = LoteServicio::first();
        $this->assertNotNull($lote);
        $this->assertSame(2, $lote->total_ordenes);
        $this->assertNull($lote->conductor_id); // lo cargó el cliente, no un conductor

        $ordenes = $lote->ordenes;
        $this->assertCount(2, $ordenes);
        // Cada máquina con su PROPIO folio, datos del cliente replicados.
        $this->assertNotSame($ordenes[0]->codigo, $ordenes[1]->codigo);
        foreach ($ordenes as $o) {
            $this->assertSame('Aguas JB SpA', $o->cliente_nombre);
            $this->assertSame('12345678-5', $o->cliente_rut);        // normalizado
            $this->assertSame('jb@empresa.cl', $o->cliente_email);   // folio por correo al confirmar
            $this->assertSame('qr', $o->fuente);
            $this->assertNull($o->confirmada_at);
            $this->assertSame('dispensador', $o->tipo_equipo);
            $this->assertNotNull($o->producto_id);                   // código obligatorio
            $this->assertCount(2, $o->fotos);                        // 2 fotos por máquina
        }

        // La falla/estado es POR máquina (cada folio con su detalle).
        $this->assertSame('No enfría. Golpeada, sin caja.', $ordenes[0]->falla_reportada);
        $this->assertSame('No calienta. Rayada en tapa.', $ordenes[1]->falla_reportada);

        // Entran a la cola "por confirmar" del mostrador.
        $this->assertSame(2, OrdenServicio::porConfirmar()->count());
    }

    public function test_exige_dos_fotos_por_maquina(): void
    {
        $sucursal = $this->sucursal();
        $payload = $this->payload($sucursal, [
            'maquinas' => [
                ['numero_serie' => 'SN-01', 'fotos' => [UploadedFile::fake()->image('solo-una.jpg')]],
            ],
        ]);

        $this->post(route('ingreso-taller.lote.store'), $payload)
            ->assertSessionHasErrors('maquinas.0.fotos');

        $this->assertSame(0, LoteServicio::count());
    }

    public function test_serie_obligatoria_segun_tipo_efectivo(): void
    {
        $sucursal = $this->sucursal();
        // tipo_default dispensador y la máquina no trae serie → error por fila.
        $payload = $this->payload($sucursal, [
            'maquinas' => [
                ['numero_serie' => '', 'fotos' => $this->fotos()],
            ],
        ]);

        $this->post(route('ingreso-taller.lote.store'), $payload)
            ->assertSessionHasErrors('maquinas.0.numero_serie');
    }

    public function test_tipo_por_fila_puede_diferir_del_default(): void
    {
        $sucursal = $this->sucursal();
        $prod = $this->producto();
        $payload = $this->payload($sucursal, [
            'maquinas' => [
                // dispensador (default)
                ['producto_id' => $prod->id, 'numero_serie' => 'SN-01', 'falla_reportada' => 'No enfría', 'fotos' => $this->fotos()],
                // herramienta sin serie: OK
                ['tipo' => 'herramienta', 'producto_id' => $prod->id, 'numero_serie' => '', 'falla_reportada' => 'No gira', 'fotos' => $this->fotos()],
            ],
        ]);

        $this->post(route('ingreso-taller.lote.store'), $payload)->assertSessionHasNoErrors();

        $this->assertSame(1, OrdenServicio::where('tipo_equipo', 'herramienta')->count());
        $this->assertSame(1, OrdenServicio::where('tipo_equipo', 'dispensador')->count());
    }

    public function test_falla_y_estado_es_obligatoria_por_maquina(): void
    {
        $sucursal = $this->sucursal();
        $prod = $this->producto();
        $payload = $this->payload($sucursal, [
            'maquinas' => [
                ['producto_id' => $prod->id, 'numero_serie' => 'SN-01', 'falla_reportada' => '', 'fotos' => $this->fotos()],
            ],
        ]);

        $this->post(route('ingreso-taller.lote.store'), $payload)
            ->assertSessionHasErrors('maquinas.0.falla_reportada');

        $this->assertSame(0, LoteServicio::count());
    }

    public function test_garantia_exige_documento_una_vez_para_el_lote(): void
    {
        $sucursal = $this->sucursal();

        // Garantía sin documento → error.
        $this->post(route('ingreso-taller.lote.store'), $this->payload($sucursal, ['facturacion' => 'garantia']))
            ->assertSessionHasErrors(['garantia_doc_tipo', 'garantia_doc_numero', 'garantia_doc_fecha']);

        // Garantía con documento → cada orden lo lleva.
        $this->post(route('ingreso-taller.lote.store'), $this->payload($sucursal, [
            'facturacion' => 'garantia',
            'garantia_doc_tipo' => 'boleta',
            'garantia_doc_numero' => 'B-777',
            'garantia_doc_fecha' => now()->subMonth()->toDateString(),
        ]))->assertSessionHasNoErrors();

        $this->assertSame(2, OrdenServicio::where('facturacion', 'garantia')->where('garantia_doc_numero', 'B-777')->count());
    }

    public function test_honeypot_lleno_no_crea_nada(): void
    {
        $sucursal = $this->sucursal();

        $this->post(route('ingreso-taller.lote.store'), $this->payload($sucursal, ['sitio_web' => 'http://spam.example']))
            ->assertRedirect();

        $this->assertSame(0, LoteServicio::count());
        $this->assertSame(0, OrdenServicio::count());
    }

    public function test_gracias_firmada_lista_los_folios(): void
    {
        $sucursal = $this->sucursal();
        $this->post(route('ingreso-taller.lote.store'), $this->payload($sucursal));
        $lote = LoteServicio::first();

        // Sin firma: rechazada. Con firma: lista ambos folios.
        $this->get(route('ingreso-taller.lote.gracias', ['lote' => $lote->id]))->assertForbidden();

        $res = $this->get(URL::signedRoute('ingreso-taller.lote.gracias', ['lote' => $lote->id]))->assertOk();
        foreach ($lote->ordenes as $o) {
            $res->assertSee($o->folio);
        }
        $res->assertSee('Volver al inicio'); // botón de regreso a la pantalla del QR
    }

    public function test_codigo_es_obligatorio_por_maquina(): void
    {
        $sucursal = $this->sucursal();
        $prod = $this->producto();

        // Sin código → error por fila (no se crea nada).
        $this->post(route('ingreso-taller.lote.store'), $this->payload($sucursal, [
            'maquinas' => [['numero_serie' => 'SN-01', 'falla_reportada' => 'No enfría', 'fotos' => $this->fotos()]],
        ]))->assertSessionHasErrors('maquinas.0.producto_id');

        // Código inexistente → error por fila.
        $this->post(route('ingreso-taller.lote.store'), $this->payload($sucursal, [
            'maquinas' => [['numero_serie' => 'SN-01', 'producto_id' => 999999, 'falla_reportada' => 'No enfría', 'fotos' => $this->fotos()]],
        ]))->assertSessionHasErrors('maquinas.0.producto_id');

        $this->assertSame(0, LoteServicio::count());

        // Código válido → se guarda.
        $this->post(route('ingreso-taller.lote.store'), $this->payload($sucursal, [
            'maquinas' => [['numero_serie' => 'SN-02', 'producto_id' => $prod->id, 'falla_reportada' => 'No enfría', 'fotos' => $this->fotos()]],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, OrdenServicio::where('producto_id', $prod->id)->count());
    }
}
