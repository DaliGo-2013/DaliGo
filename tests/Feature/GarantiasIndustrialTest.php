<?php

namespace Tests\Feature;

use App\Models\AgendaTrabajo;
use App\Models\Instalacion;
use App\Models\OrdenServicio;
use App\Support\GarantiasIndustrial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GARANTÍAS DEL SERVICIO INDUSTRIAL en el correo del cliente (dueño, 20-08-2026:
 * «esto es importante para que todos los clientes sepan al momento de llevar a cabo
 * un arreglo»).
 *
 * Los plazos que dio: equipo NUEVO desde su instalación —llenadora 1 año, lavadora
 * 6 meses, planta de osmosis 1 año—, reparación 1 mes e instalación 1 mes.
 *
 * LO QUE MÁS IMPORTA DE ESTOS CANDADOS es lo que protege el último: que estos
 * plazos NO se confundan con los del taller de dispensadores, que son otros dos y
 * ya viajan en tres cartas desde el 14-08. Al preguntarle al dueño el 20-08 lo
 * confirmó: son servicios distintos con plazos distintos. Si alguien «unifica» los
 * números, la app queda prometiéndole al cliente una cobertura que el negocio no
 * dio — por escrito, en un correo que el cliente guarda.
 */
class GarantiasIndustrialTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<string, mixed>  $extra */
    private function trabajo(array $extra = []): AgendaTrabajo
    {
        return AgendaTrabajo::factory()->create(array_merge([
            'fecha' => '2026-09-10',
            'tipo' => 'instalacion',
            'estado' => 'agendado',
            'cliente_nombre' => 'AQUANDES',
            'cliente_email' => 'cliente@aquandes.cl',
            'direccion' => 'Av Mirador',
            'ciudad' => 'Santiago',
        ], $extra));
    }

    private function correo(string $motivo = 'agendada'): string
    {
        return view('emails.terreno.aviso', [
            'trabajo' => $this->trabajo(),
            'motivo' => $motivo,
            'urlConfirmar' => null,
        ])->render();
    }

    // --- Los plazos que dio el dueño -----------------------------------------

    public function test_los_plazos_son_los_que_dio_el_dueno(): void
    {
        $equipos = GarantiasIndustrial::equipoNuevo();

        $this->assertSame('1 año', $equipos['llenadora']['plazo']);
        $this->assertSame('6 meses', $equipos['lavadora']['plazo']);
        $this->assertSame('1 año', $equipos['planta']['plazo']);

        $this->assertSame('1 mes', GarantiasIndustrial::reparacion());
        $this->assertSame('1 mes', GarantiasIndustrial::instalacion());
    }

    /**
     * 12 meses se dicen «1 año» porque el cliente lo entiende más rápido; los que
     * no son múltiplos de 12 se quedan en meses en vez de inventar «un año y
     * medio», que abre la discusión de cuándo vence.
     */
    public function test_el_plazo_se_redacta_como_lo_lee_un_cliente(): void
    {
        $this->assertSame('1 mes', GarantiasIndustrial::plazo(1));
        $this->assertSame('6 meses', GarantiasIndustrial::plazo(6));
        $this->assertSame('1 año', GarantiasIndustrial::plazo(12));
        $this->assertSame('18 meses', GarantiasIndustrial::plazo(18));
        $this->assertSame('2 años', GarantiasIndustrial::plazo(24));
        $this->assertSame('sin garantía', GarantiasIndustrial::plazo(0));
    }

    /**
     * La tabla no puede hablar de un equipo que el sistema no conoce: sus claves
     * son las categorías de Instalacion, y si mañana entra una categoría nueva sin
     * plazo configurado, este test la nombra.
     */
    public function test_hay_un_plazo_para_cada_categoria_de_instalacion(): void
    {
        $configuradas = array_keys((array) config('servicio_tecnico.garantias_industrial.equipo_nuevo_meses'));

        $this->assertSame(
            [],
            array_diff(Instalacion::CATEGORIAS, $configuradas),
            'Hay una categoría de instalación sin garantía configurada: el correo la omitiría en silencio.'
        );
        $this->assertSame(
            [],
            array_diff($configuradas, Instalacion::CATEGORIAS),
            'Hay una garantía configurada para una categoría que no existe.'
        );
    }

    // --- El correo del cliente ------------------------------------------------

    public function test_el_correo_de_la_visita_trae_las_garantias(): void
    {
        $html = $this->correo();

        $this->assertStringContainsString('Garantías', $html);
        // Los tres equipos con su plazo, y los dos trabajos. La planta se nombra
        // como se la nombra al CLIENTE: «Planta de osmosis», no el «Planta» del
        // catálogo interno, que en un correo no dice de qué planta le hablan.
        foreach (['Llenadora', 'Lavadora', 'Planta de osmosis'] as $equipo) {
            $this->assertStringContainsString($equipo, $html);
        }
        $this->assertStringContainsString('1 año', $html);
        $this->assertStringContainsString('6 meses', $html);
        $this->assertStringContainsString('Reparación', $html);
        $this->assertStringContainsString('Instalación', $html);
        $this->assertStringContainsString('desde su instalación', $html);
    }

    /**
     * En una visita ANULADA no van: no va a haber trabajo, y prometerle plazos de
     * garantía a alguien al que se le acaba de cancelar el servicio no informa,
     * confunde.
     */
    public function test_una_visita_anulada_no_habla_de_garantias(): void
    {
        $this->assertStringNotContainsString('Garantías', $this->correo('anulada'));

        // Pero las otras dos variantes sí (el trabajo está por hacerse).
        foreach (['agendada', 'reprogramada'] as $motivo) {
            $this->assertStringContainsString('Garantías', $this->correo($motivo), "Falta en «{$motivo}».");
        }
    }

    // --- Lo que NO se puede mezclar ------------------------------------------

    /**
     * EL CANDADO CENTRAL. El taller de dispensadores tiene sus PROPIAS garantías y
     * siguen vigentes (confirmado por el dueño el 20-08 al preguntarle si el «1 mes»
     * las reemplazaba): 6 meses el producto desde la compra —número que además
     * decide si un ingreso al taller se cobra— y 3 meses la reparación desde el día
     * en que se repara.
     *
     * Si alguien unifica los números «para simplificar», la app pasa a prometer por
     * escrito una cobertura distinta de la que el negocio dio. Y si toca los 6
     * meses, cambia qué ingresos son gratis: eso es plata.
     */
    public function test_las_garantias_del_taller_no_se_tocan(): void
    {
        $this->assertSame(6, OrdenServicio::GARANTIA_MESES, 'La garantía del producto del taller cambió.');
        $this->assertSame(3, OrdenServicio::GARANTIA_REPARACION_MESES, 'La garantía de la reparación del taller cambió.');

        // Y son DISTINTAS de las industriales: una planta reparada en terreno tiene
        // 1 mes, un dispensador reparado en el taller tiene 3.
        $this->assertNotSame(
            OrdenServicio::GARANTIA_REPARACION_MESES,
            (int) config('servicio_tecnico.garantias_industrial.reparacion_meses'),
            'La reparación de terreno y la del taller quedaron con el mismo plazo: son servicios distintos.'
        );
    }

    /**
     * El correo industrial no puede mencionar los plazos del taller: son otro
     * servicio, y verlos juntos en la misma carta es exactamente cómo un cliente
     * termina reclamando por la cobertura equivocada.
     */
    public function test_el_correo_industrial_no_menciona_los_plazos_del_taller(): void
    {
        $html = $this->correo();

        $this->assertStringNotContainsString('3 meses', $html);
    }
}
