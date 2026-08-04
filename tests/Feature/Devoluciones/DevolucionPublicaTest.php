<?php

namespace Tests\Feature\Devoluciones;

use App\Models\Devolucion;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Frontera pública de M13 (P-M13-01): link firmado GET y POST, honeypot con
 * respuesta idéntica al éxito, fotos obligatorias al disco privado, token no
 * enumerable, y el aviso interno que jamás tumba el envío del cliente.
 */
class DevolucionPublicaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
        Storage::fake('local');
    }

    private function sucursal(): Sucursal
    {
        return Sucursal::factory()->create(['activa' => true, 'nombre' => 'Mirador']);
    }

    /** @return array<int, UploadedFile> */
    private function fotos(): array
    {
        return [
            UploadedFile::fake()->image('foto1.jpg', 800, 600),
            UploadedFile::fake()->image('foto2.jpg', 800, 600),
        ];
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'cliente_nombre' => 'María Pérez',
            'cliente_email' => 'maria@example.com',
            'cliente_telefono' => '+56911112222',
            'cliente_rut' => '12.345.678-5',
            'canal' => 'mercado_libre',
            'folio_referencia' => '445566',
            'producto' => 'Dispensador de agua XYZ',
            'cantidad' => 1,
            'motivo' => 'Llegó con el estanque quebrado.',
            'fotos' => $this->fotos(),
        ], $overrides);
    }

    public function test_sin_firma_valida_el_formulario_es_rechazado(): void
    {
        $sucursal = $this->sucursal();

        // URL cruda (sin ?signature=) → 403 del middleware 'signed'.
        $this->get(route('devolucion.create', ['sucursal' => $sucursal->id]))->assertForbidden();
        $this->post(route('devolucion.store', ['sucursal' => $sucursal->id]), $this->payload())->assertForbidden();
    }

    public function test_cliente_declara_su_devolucion_con_fotos(): void
    {
        $sucursal = $this->sucursal();
        // Un interno con rol de aviso: sin destinatarios el fan-out no
        // despacha nada (eso lo cubre el último test).
        tap(User::factory()->create())->assignRole('jefe_bodega');

        $respuesta = $this->post(
            URL::signedRoute('devolucion.store', ['sucursal' => $sucursal->id]),
            $this->payload(),
        );

        $devolucion = Devolucion::firstOrFail();

        // Redirige al "gracias" FIRMADO de SU devolución (binding por token).
        $respuesta->assertRedirect();
        $this->assertStringContainsString($devolucion->token, $respuesta->headers->get('Location'));

        // La fila: estado inicial, RUT normalizado, rastro del envío.
        $this->assertSame(Devolucion::SOLICITADA, $devolucion->estado);
        $this->assertSame('12345678-5', $devolucion->cliente_rut);
        $this->assertSame('mercado_libre', $devolucion->canal);
        $this->assertNotEmpty($devolucion->ip);
        $this->assertStringStartsWith('DEV-', $devolucion->folio);
        $this->assertSame(64, strlen($devolucion->token));

        // El ítem y las 2 fotos del CLIENTE en el disco privado.
        $this->assertSame(1, $devolucion->items()->count());
        $fotos = $devolucion->fotos()->get();
        $this->assertCount(2, $fotos);
        $this->assertSame(['cliente'], $fotos->pluck('origen')->unique()->values()->all());
        foreach ($fotos as $foto) {
            Storage::disk('local')->assertExists($foto->ruta);
        }

        // Aviso interno (evento en la fila; el detalle fino vive en el test de notificaciones).
        $this->assertDatabaseHas('notificaciones', ['evento' => 'devolucion.solicitada']);
    }

    public function test_honeypot_corta_sin_crear_nada_con_respuesta_de_exito(): void
    {
        $sucursal = $this->sucursal();

        $respuesta = $this->post(
            URL::signedRoute('devolucion.store', ['sucursal' => $sucursal->id]),
            $this->payload(['sitio_web' => 'http://spam.example']),
        );

        // Respuesta con la MISMA forma que el éxito (redirect firmado al form),
        // para no darle pistas al bot — y cero filas creadas.
        $respuesta->assertRedirect();
        $this->assertSame(0, Devolucion::count());
        $this->assertDatabaseCount('notificaciones', 0);
    }

    public function test_las_fotos_son_obligatorias_y_con_formato_de_imagen(): void
    {
        $sucursal = $this->sucursal();
        $url = URL::signedRoute('devolucion.store', ['sucursal' => $sucursal->id]);

        // Sin fotos → error de validación, nada creado.
        $this->post($url, $this->payload(['fotos' => []]))->assertSessionHasErrors('fotos');

        // Un PDF disfrazado → rechazado por mimetype.
        $this->post($url, $this->payload([
            'fotos' => [UploadedFile::fake()->create('malicia.pdf', 100, 'application/pdf'), UploadedFile::fake()->image('ok.jpg')],
        ]))->assertSessionHasErrors('fotos.0');

        $this->assertSame(0, Devolucion::count());
    }

    public function test_rut_con_dv_k_es_aceptado(): void
    {
        // dvRut: buscar un cuerpo cuyo DV sea K para el caso real del 9%.
        $cuerpo = 1000000;
        while (\App\Models\Cliente::dvRut($cuerpo) !== 'K') {
            $cuerpo++;
        }

        $sucursal = $this->sucursal();
        $this->post(
            URL::signedRoute('devolucion.store', ['sucursal' => $sucursal->id]),
            $this->payload(['cliente_rut' => number_format($cuerpo, 0, '', '.').'-k']),
        )->assertSessionHasNoErrors();

        $this->assertSame($cuerpo.'-K', Devolucion::firstOrFail()->cliente_rut);
    }

    public function test_el_gracias_muestra_solo_su_folio_y_exige_firma(): void
    {
        $devolucion = Devolucion::factory()->create();

        // Firmado → 200 con SU folio.
        $this->get(URL::signedRoute('devolucion.gracias', ['devolucion' => $devolucion->token]))
            ->assertOk()
            ->assertSee($devolucion->folio);

        // Sin firma → 403 (ni con el token correcto).
        $this->get(route('devolucion.gracias', ['devolucion' => $devolucion->token]))->assertForbidden();

        // Un id numérico jamás resuelve (binding por token, no enumerable).
        $this->get(URL::signedRoute('devolucion.gracias', ['devolucion' => $devolucion->id]))->assertNotFound();
    }

    public function test_un_aviso_que_falla_no_tumba_el_envio_del_cliente(): void
    {
        // Sin usuarios internos con los roles de aviso, el fan-out no despacha
        // nada — y el cliente igual recibe su folio (el aviso es secundario).
        $sucursal = $this->sucursal();

        $this->post(
            URL::signedRoute('devolucion.store', ['sucursal' => $sucursal->id]),
            $this->payload(),
        )->assertRedirect();

        $this->assertSame(1, Devolucion::count());
    }
}
