<?php

namespace Tests\Feature;

use App\Models\OrdenServicio;
use App\Models\Sucursal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Candados de COHERENCIA entre los dos formularios PUBLICOS del QR: «Ingreso por
 * unidad» (una maquina) e «Ingreso por cantidad» (varias).
 *
 * Por que existe esta clase aparte: los dos los llena LA MISMA PERSONA, en el
 * MISMO mostrador, en el MISMO momento — solo cambia cuantas maquinas trae. Toda
 * diferencia de rotulo, de ayuda o de regla entre ellos es arbitraria desde el
 * lado del cliente, y la auditoria de la ruta completa (30-07) encontro varias:
 * la ayuda del N° de serie existia solo en el de UNA maquina (justo el que menos
 * la necesita), la condicion se explicaba distinto en cada uno, y ambos rotulaban
 * «Garantia»/«Reparacion» SIN TILDE.
 *
 * El lote del CONDUCTOR (admin/servicio-tecnico/lote) NO entra aca a proposito:
 * ese lo llena un trabajador en ruta, con otras prioridades (1 foto en vez de 2,
 * codigo del catalogo, conductor y ciudad de origen). Sus diferencias son
 * deliberadas, no deuda.
 */
class IngresoTallerCoherenciaFormulariosTest extends TestCase
{
    use RefreshDatabase;

    private function sucursal(): Sucursal
    {
        return Sucursal::firstOrCreate(
            ['codigo' => 'MIRADOR'],
            ['activa' => true, 'nombre' => 'Mirador', 'es_central' => true],
        );
    }

    /** El HTML de los dos formularios, para comparar los mismos textos en ambos. */
    private function ambosFormularios(): array
    {
        $sucursal = $this->sucursal();

        return [
            'unidad' => $this->get(URL::signedRoute('ingreso-taller.create', ['sucursal' => $sucursal->id]))
                ->assertOk()->getContent(),
            'cantidad' => $this->get(URL::signedRoute('ingreso-taller.lote.create', ['sucursal' => $sucursal->id]))
                ->assertOk()->getContent(),
        ];
    }

    // --- Condicion: el rotulo que ve el cliente ---

    /**
     * La condicion se rotula CON TILDE. Se compara el `<option>` completo y no la
     * palabra suelta: el HTML trae un token CSRF de 40 caracteres al azar y una
     * afirmacion por substring sobre texto corto es una fuente de intermitencias.
     */
    public function test_la_condicion_se_rotula_con_tilde_en_los_dos_formularios(): void
    {
        foreach ($this->ambosFormularios() as $donde => $html) {
            $this->assertStringContainsString('>Garantía</option>', $html, "[{$donde}] falta el rótulo con tilde.");
            $this->assertStringContainsString('>Reparación</option>', $html, "[{$donde}] falta el rótulo con tilde.");
            $this->assertStringNotContainsString('>Garantia</option>', $html, "[{$donde}] el cliente lee «Garantia» sin tilde.");
            $this->assertStringNotContainsString('>Reparacion</option>', $html, "[{$donde}] el cliente lee «Reparacion» sin tilde.");
        }
    }

    /** La etiqueta sale de una fuente unica (no de un ucfirst en cada vista). */
    public function test_etiqueta_facturacion_mapea_y_tiene_fallback(): void
    {
        $this->assertSame('Garantía', OrdenServicio::etiquetaFacturacion('garantia'));
        $this->assertSame('Reparación', OrdenServicio::etiquetaFacturacion('reparacion'));
        // Condicion historica fuera del mapa: no revienta ni queda en blanco.
        $this->assertSame('Otracosa', OrdenServicio::etiquetaFacturacion('otracosa'));
        $this->assertSame('', OrdenServicio::etiquetaFacturacion(null));
        $this->assertSame('', OrdenServicio::etiquetaFacturacion(''));

        // El valor GUARDADO sigue sin tildes (es el valor de la columna).
        $this->assertSame(['garantia', 'reparacion'], OrdenServicio::FACTURACION);
    }

    /** Los dos explican la condicion con EL MISMO texto. */
    public function test_la_condicion_se_explica_igual_en_los_dos_formularios(): void
    {
        $esperado = 'Garantía: equipos con garantía vigente (trae la boleta o factura). '
            .'Reparación: fuera de garantía (tiene costo).';

        $formularios = $this->ambosFormularios();

        foreach ($formularios as $donde => $html) {
            // El de UNA máquina dice "equipo" en singular; se compara la parte
            // que no cambia y el cierre, que sí debe ser idéntico.
            $this->assertStringContainsString('con garantía vigente (trae la boleta o factura)', $html,
                "[{$donde}] la condición no se explica con el documento que hay que traer.");
            $this->assertStringContainsString('fuera de garantía (tiene costo)', $html,
                "[{$donde}] la condición no dice que la reparación se cobra.");
        }

        // El de varias máquinas usa el texto completo en plural.
        $this->assertStringContainsString($esperado, $formularios['cantidad']);
    }

    // --- Ayuda del N° de serie ---

    /**
     * La ayuda («Ver ejemplo del N° de serie») esta en los DOS. Antes vivia solo
     * en el ingreso por unidad: el cliente que trae 10 dispensadores, que es el
     * que mas la necesita, no la tenia.
     */
    public function test_la_ayuda_del_numero_de_serie_esta_en_los_dos_formularios(): void
    {
        foreach ($this->ambosFormularios() as $donde => $html) {
            $this->assertStringContainsString('Ver ejemplo del N° de serie', $html,
                "[{$donde}] falta la ayuda del N° de serie.");
            $this->assertStringContainsString('¿Dónde está el N° de serie?', $html,
                "[{$donde}] falta el modal con la foto de ejemplo.");
        }
    }

    /**
     * En el ingreso por CANTIDAD la ayuda aparece UNA sola vez: una por máquina
     * serían 10 modales con la misma foto embebida en base64.
     */
    public function test_la_ayuda_del_numero_de_serie_no_se_repite_en_el_ingreso_por_cantidad(): void
    {
        $html = $this->ambosFormularios()['cantidad'];

        $this->assertSame(1, substr_count($html, 'Ver ejemplo del N° de serie'));
        // Y escucha el selector del lote (#tipo_default): esta pantalla no tiene
        // NINGÚN #tipo_equipo, así que si el componente lo nombrara, la ayuda
        // quedaría con su condición leyendo un elemento inexistente.
        $this->assertStringContainsString('tipo_default', $html);
        $this->assertStringNotContainsString('tipo_equipo', $html);
    }

    /** El componente acepta el id del selector que gobierna su visibilidad. */
    public function test_la_ayuda_del_numero_de_serie_escucha_el_selector_que_se_le_pasa(): void
    {
        $porDefecto = view('components.ayuda-serie')->render();
        $delLote = view('components.ayuda-serie', ['tipoSelector' => 'tipo_default'])->render();

        $this->assertStringContainsString('tipo_equipo', $porDefecto);
        $this->assertStringContainsString('tipo_default', $delLote);
        $this->assertStringNotContainsString('tipo_equipo', $delLote);
    }

    // --- La pregunta por la falla ---

    /**
     * Los dos preguntan por la falla Y EL ESTADO. El de una maquina pedia solo
     * "¿Qué le pasa al equipo?": lo que le falta al equipo (caja, llave, una
     * pieza) es justo lo que se discute despues en la entrega.
     */
    public function test_los_dos_formularios_preguntan_por_la_falla_y_el_estado(): void
    {
        foreach ($this->ambosFormularios() as $donde => $html) {
            $this->assertStringContainsString('Falla y estado del equipo', $html,
                "[{$donde}] no se pide el estado del equipo, solo la falla.");
        }
    }

    // --- Ayudas que faltaban en el ingreso por cantidad ---

    /** Los dos dicen PARA QUE se pide el telefono. */
    public function test_los_dos_formularios_dicen_para_que_es_el_telefono(): void
    {
        foreach ($this->ambosFormularios() as $donde => $html) {
            $this->assertStringContainsString('Para avisarte cuando tu', $html,
                "[{$donde}] el teléfono se pide sin decir para qué.");
        }
    }

    /**
     * El ingreso por cantidad explica QUE TIPOS llevan N° de serie. Mostraba el
     * asterisco aparecer y desaparecer segun el tipo, sin decir nunca por que.
     */
    public function test_el_ingreso_por_cantidad_explica_que_tipos_llevan_serie(): void
    {
        $html = $this->ambosFormularios()['cantidad'];

        $this->assertStringContainsString('necesitan N° de serie', $html);
        // Los tipos que la exigen son los del modelo, no una lista escrita a mano.
        $this->assertSame(['dispensador', 'lavadora'], OrdenServicio::SERIE_OBLIGATORIA_TIPOS);
    }

    /** Y dice para que sirve el equipo (marca/modelo), que aca es obligatorio. */
    public function test_el_ingreso_por_cantidad_explica_para_que_pide_el_modelo(): void
    {
        $this->assertStringContainsString(
            'nos sirve para distinguir una máquina de otra',
            $this->ambosFormularios()['cantidad'],
        );
    }

    // --- Campos obligatorios marcados como tales ---

    /**
     * La condicion del ingreso por unidad es `required` en el HTML. Sin eso el
     * navegador dejaba enviar en blanco y el rechazo llegaba del servidor, con el
     * formulario entero recargado (el de cantidad si lo tenia).
     */
    public function test_la_condicion_del_ingreso_por_unidad_es_required(): void
    {
        foreach ($this->ambosFormularios() as $donde => $html) {
            $this->assertSame(1, preg_match('/<select[^>]*name="facturacion"[^>]*>/', $html, $m),
                "[{$donde}] no se encontró el selector de condición.");
            $this->assertStringContainsString('required', $m[0],
                "[{$donde}] el selector de condición no es required.");
        }
    }

    /**
     * Los tres campos del documento de garantia van marcados con asterisco: son
     * `x-bind:required` cuando la condicion es garantia, asi que el navegador
     * bloqueaba el envio por un campo que se veia opcional.
     */
    public function test_el_documento_de_garantia_va_marcado_como_obligatorio(): void
    {
        $html = $this->ambosFormularios()['unidad'];

        $this->assertStringContainsString('Documento *', $html);
        $this->assertStringContainsString('N° del documento *', $html);
        $this->assertStringContainsString('Fecha de compra *', $html);
    }
}
