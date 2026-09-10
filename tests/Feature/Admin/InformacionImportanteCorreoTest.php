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
 *   · el PLAZO sale de la sucursal (cada una tiene el suyo). Con un «10» fijo, el correo
 *     prometería 10 días hábiles en una sucursal que tarda 15. Desde el 14-08-2026 es además
 *     el ÚNICO compromiso de tiempo del correo: la fecha de entrega calculada ya no viaja
 *     (ver PlazoSinFechaPrometidaTest).
 *   · la GARANTÍA es la misma constante que promete el correo de retiro. Si fueran dos números,
 *     un día se prometería una cosa al ingresar y otra al entregar.
 *
 * Y EL HORARIO TAMPOCO, desde el 10-09-2026: estaba escrito en la plantilla, entre condiciones
 * que sí salían de config, y el correo siguió prometiendo un horario que el taller ya había
 * cambiado. Un recuadro que se lee como texto fijo es donde un dato viejo pasa más desapercibido.
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

        // Con los espacios COLAPSADOS a propósito: las frases de este recuadro son largas y
        // cruzan los saltos de línea del Blade, así que un assert sobre el HTML crudo se pone
        // rojo cuando alguien re-acomoda la plantilla sin cambiar una sola palabra del texto.
        return preg_replace('/\s+/u', ' ', (new IngresoTallerRecibido($orden->fresh()))->render());
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

        $this->assertStringContainsString('No nos hacemos responsables por entrega de equipos sin caja', $html);
        $this->assertStringContainsString('garantía de 3 meses', $html);
        $this->assertStringContainsString('$3.000 + IVA mensual', $html);
        $this->assertStringContainsString('Ley 19.496', $html);
    }

    /**
     * EL BODEGAJE Y LA LEY HABLAN DEL DISPENSADOR, a propósito (dueño, 10-09-2026): «que se
     * especifique con el tema de dispensadores la ley; las herramientas la verdad llegan muy
     * pocas a servicio técnico y se van rápido, o sea se retiran».
     *
     * Este candado existe porque la tentación es exactamente la contraria: el resto del recuadro
     * dice «equipo» —al taller entran lavadoras, bombas y herramientas— y un barrido de
     * consistencia generalizaría estos dos puntos sin pensar. Sería un error de fondo, no de
     * estilo: lo que se acumula meses en la bodega, y por lo tanto lo único que se puede llegar
     * a vender o dar de baja, es un dispensador. Decir «equipo» le prometería a quien trajo una
     * herramienta un régimen de bodegaje y disposición que no le corresponde.
     */
    public function test_el_bodegaje_y_la_ley_hablan_del_dispensador_no_del_equipo(): void
    {
        $html = $this->correo();

        $this->assertStringContainsString('bodegaje del dispensador', $html);
        $this->assertStringContainsString('dar de baja el dispensador según la Ley 19.496', $html);
    }

    /**
     * EL ESTADO SE INFORMA AL DEJAR EL EQUIPO, NO AL RETIRARLO (dueño, 10-09-2026): «que al
     * momento de ingresar su dispensador o herramienta, lo que sea, si no informa algún defecto
     * como golpes, rayones o sin caja, que después al retirar no reclame».
     *
     * Las tres mitades del pedido y por qué las tres tienen que estar: sin el «avísanos al
     * ingresar» el cliente no sabe que tiene algo que hacer; sin el «no se puede reclamar al
     * retirar» no sabe qué se juega si no lo hace; y sin las FOTOS —lo único que zanja la
     * discusión meses después— la regla queda en la palabra de cada uno.
     */
    public function test_pide_informar_el_estado_al_ingresar_y_recomienda_fotos(): void
    {
        $html = $this->correo();

        $this->assertStringContainsString('avísanos al momento del ingreso', $html);
        $this->assertStringContainsString('no se puede reclamar al retirar', $html);
        $this->assertStringContainsString('fotos', $html);
        // Golpes, rayones y sin caja: los tres casos que nombró, porque son los tres que
        // llegan al mostrador.
        foreach (['golpes', 'rayones', 'sin caja'] as $defecto) {
            $this->assertStringContainsString($defecto, $html, "El correo no nombra «{$defecto}».");
        }
    }

    public function test_lleva_el_horario_de_atencion(): void
    {
        $html = $this->correo();

        $this->assertStringContainsString('Horario de atención', $html);
        $this->assertStringContainsString('lunes y martes de 08:00 a 17:30', $html);
        $this->assertStringContainsString('miércoles a viernes de 08:00 a 16:30', $html);
    }

    /**
     * Y EL HORARIO SALE DE CONFIG, no del texto de la plantilla. Es el candado del defecto que
     * originó este cambio: el horario estaba escrito dentro del Blade, entre condiciones que sí
     * salían de config, y el correo siguió prometiendo «lunes a jueves de 09:00 a 13:00 y de
     * 14:00 a 17:00» después de que el taller cambiara de horario. Nadie lo miró porque el
     * recuadro se lee como un bloque de texto fijo.
     */
    public function test_el_horario_de_atencion_sale_de_configuracion(): void
    {
        config()->set('servicio_tecnico.horario_atencion', [
            ['dias' => 'lunes a viernes', 'horas' => '07:30 a 18:00'],
            ['dias' => 'sábado', 'horas' => '09:00 a 13:00'],
        ]);

        $html = $this->correo();

        $this->assertStringContainsString('lunes a viernes de 07:30 a 18:00 · sábado de 09:00 a 13:00', $html);
        // Y el horario viejo no puede quedar de respaldo en ninguna parte del correo.
        $this->assertStringNotContainsString('08:00 a 17:30', $html);
    }

    // ─────────────────────────────────────────────────── los dos números que no se escriben

    /**
     * EL PLAZO ES EL DE LA SUCURSAL, confirmado por el dueño el 14-08-2026: «para la sucursal
     * de Mirador son 10 días hábiles, pero para Abate Molina y Coquimbo son 15 días hábiles».
     * Mirador repara; las otras dos mandan el equipo a Mirador y por eso tardan más. Un número
     * fijo en el texto prometería 10 días en una sucursal que tarda 15.
     */
    public function test_el_plazo_es_el_de_la_sucursal_que_recibio(): void
    {
        $mirador = $this->correo();
        $this->assertStringContainsString('hasta 10 días hábiles', $mirador);

        $coquimbo = $this->correo(['sucursal_id' => $this->sucursal('COQUIMBO', 'Coquimbo')->id]);
        $this->assertStringContainsString('hasta 15 días hábiles', $coquimbo);

        $abate = $this->correo(['sucursal_id' => $this->sucursal('ABATE-MOLINA', 'Abate Molina')->id]);
        $this->assertStringContainsString('hasta 15 días hábiles', $abate);
    }

    /** Sin sucursal (ingreso por ruta) no se promete ningún plazo, en vez de inventar uno. */
    public function test_sin_sucursal_no_promete_plazo(): void
    {
        $html = $this->correo(['sucursal_id' => null]);

        // La frase entera, no un fragmento con punto: el correo nombra los días hábiles en otra
        // línea («el plazo se cuenta en días hábiles») y ese texto general sí va siempre.
        $this->assertStringNotContainsString('El plazo de reparación es de hasta', $html);
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
