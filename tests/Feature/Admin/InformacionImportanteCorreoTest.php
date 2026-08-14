<?php

namespace Tests\Feature\Admin;

use App\Mail\IngresoTallerRecibido;
use App\Models\OrdenServicio;
use App\Models\Sucursal;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «INFORMACIÓN IMPORTANTE» EN EL CORREO DE INGRESO.
 *
 * Pedido del dueño (14-08-2026): «esta información importante hay que ponerla en el correo de
 * cuando se ingresa la máquina y le llega al cliente una copia de su servicio técnico
 * ingresado… que diga en negrita "INFORMACIÓN IMPORTANTE"». Es el recuadro del comprobante
 * IMPRESO del taller.
 *
 * POR QUÉ IMPORTA QUE VIAJE EN EL CORREO: el cliente que ingresa por QR nunca ve el papel, y
 * estas son justo las condiciones que después se discuten en el mostrador — el bodegaje que se
 * cobra, la responsabilidad por el equipo sin caja, el plazo.
 *
 * DOS NÚMEROS NO SE ESCRIBEN A MANO, y por eso hay candados:
 *   · el PLAZO sale de la sucursal (cada una tiene el suyo). Con un «10» fijo, el mismo correo
 *     que muestra una entrega estimada a 15 días hábiles se contradiría solo.
 *   · la GARANTÍA es la misma constante que promete el correo de retiro. Si fueran dos números,
 *     un día se prometería una cosa al ingresar y otra al entregar.
 */
class InformacionImportanteCorreoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
    }

    private function sucursal(string $codigo = 'MIRADOR', string $nombre = 'El Mirador'): Sucursal
    {
        return Sucursal::firstOrCreate(['codigo' => $codigo], ['activa' => true, 'nombre' => $nombre, 'es_central' => $codigo === 'MIRADOR']);
    }

    private function correo(array $overrides = []): string
    {
        $orden = OrdenServicio::factory()->create(array_merge([
            'estado' => 'recibido',
            'cliente_nombre' => 'fernando rojas',
            'cliente_email' => 'cliente@example.com',
            'falla_reportada' => 'NO ENFRIA, NO CALIENTA',
            'sucursal_id' => $this->sucursal()->id,
        ], $overrides));

        return (new IngresoTallerRecibido($orden->fresh()))->render();
    }

    // ─────────────────────────────────────────────────── el bloque

    public function test_el_correo_de_ingreso_lleva_el_bloque_en_negrita(): void
    {
        $html = $this->correo();

        $this->assertStringContainsString('INFORMACIÓN IMPORTANTE', $html);
        // En negrita, como lo pidió: el bloque tiene que saltar a la vista.
        $this->assertMatchesRegularExpression('/font-weight:bold;[^>]*>\s*INFORMACIÓN IMPORTANTE/u', $html);
    }

    public function test_lleva_las_tres_condiciones_del_comprobante(): void
    {
        $html = $this->correo();

        $this->assertStringContainsString('No nos hacemos responsables por entrega de dispensadores sin caja', $html);
        $this->assertStringContainsString('garantía de 3 meses', $html);
        $this->assertStringContainsString('$3.000 + IVA mensual por concepto de bodegaje', $html);
        $this->assertStringContainsString('Ley 19.496', $html);
    }

    public function test_lleva_el_horario_de_atencion(): void
    {
        $html = $this->correo();

        $this->assertStringContainsString('Horario de atención', $html);
        $this->assertStringContainsString('lunes a jueves', $html);
        $this->assertStringContainsString('viernes hasta las 16:00', $html);
    }

    // ─────────────────────────────────────────────────── los dos números que no se escriben

    /**
     * EL PLAZO ES EL DE LA SUCURSAL. Mirador repara (10 días hábiles); las otras mandan el
     * equipo a Mirador y por eso tardan más (15). Un número fijo en el texto haría que el correo
     * se contradijera con la entrega estimada que él mismo muestra dos renglones arriba.
     */
    public function test_el_plazo_es_el_de_la_sucursal_que_recibio(): void
    {
        $mirador = $this->correo();
        $this->assertStringContainsString('hasta 10 días hábiles', $mirador);

        $coquimbo = $this->correo(['sucursal_id' => $this->sucursal('COQUIMBO', 'Coquimbo')->id]);
        $this->assertStringContainsString('hasta 15 días hábiles', $coquimbo);
    }

    /** Sin sucursal (ingreso por ruta) no se promete ningún plazo, en vez de inventar uno. */
    public function test_sin_sucursal_no_promete_plazo(): void
    {
        $html = $this->correo(['sucursal_id' => null]);

        $this->assertStringNotContainsString('días hábiles.', $html);
        // Pero el resto del bloque va igual: el bodegaje y la garantía no dependen de la sucursal.
        $this->assertStringContainsString('INFORMACIÓN IMPORTANTE', $html);
        $this->assertStringContainsString('bodegaje', $html);
    }

    /**
     * LA GARANTÍA ES LA MISMA QUE PROMETE EL CORREO DE RETIRO. Son dos cartas distintas
     * hablando del mismo trabajo: si un día alguien cambia una y no la otra, el cliente tiene
     * dos promesas por escrito y la que vale es la que le convenga a él.
     */
    public function test_la_garantia_es_la_misma_del_correo_de_retiro(): void
    {
        $html = $this->correo();

        $this->assertStringContainsString(
            'garantía de '.OrdenServicio::GARANTIA_REPARACION_MESES.' meses',
            $html,
            'El correo de ingreso promete una garantía distinta de la del correo de retiro.',
        );
    }

    /** Y los montos del bodegaje salen de config, no del texto: el precio va a cambiar. */
    public function test_los_montos_del_bodegaje_salen_de_configuracion(): void
    {
        config()->set('servicio_tecnico.bodegaje', ['desde_meses' => 4, 'mensual_clp' => 4500, 'limite_meses' => 10]);

        $html = $this->correo();

        $this->assertStringContainsString('a partir de los 4 meses', mb_strtolower($html));
        $this->assertStringContainsString('$4.500 + IVA', $html);
        $this->assertStringContainsString('cumplir 10 meses', $html);
    }
}
