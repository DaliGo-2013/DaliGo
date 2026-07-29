<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrdenServicio;
use App\Services\Dte\CandadoDeEmision;
use App\Services\Dte\DocumentoDesdeOrdenServicio;
use App\Services\Dte\DocumentoTributario;
use App\Services\Dte\EmisionException;
use App\Services\Dte\EmisorDte;
use App\Services\Dte\EmisorPrevisualizable;
use App\Services\Dte\FormaPago;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pantalla del documento tributario de una orden de Servicio Técnico (M05 · B8).
 *
 * Hoy es un ENSAYO EN SECO: arma el documento completo y lo muestra, pero no
 * emite. No es una limitación de esta pantalla — es el candado
 * (App\Services\Dte\CandadoDeEmision), que exige autorización explícita para
 * crear documentos tributarios reales. La pantalla lo dice en vez de esconderlo.
 *
 * El orden de la pantalla imita el de Bsale (buscador arriba, líneas al medio con
 * Cantidad · Detalle · $/unidad · % desc. · Subtotal, cliente abajo a la
 * izquierda, tipo de documento junto al total, Confirmar abajo a la derecha) por
 * pedido de Gerencia: quien hoy factura en Bsale tiene que encontrar las cosas
 * donde las busca. Lo que NO se copia son los colores ni los componentes: eso
 * sigue las reglas de diseño de DaliGo.
 */
class DocumentoTributarioController extends Controller
{
    public function __construct(
        private DocumentoDesdeOrdenServicio $armador,
        private EmisorDte $emisor,
    ) {}

    /**
     * Muestra el documento que se emitiría para esta orden.
     */
    public function show(Request $request, OrdenServicio $orden): View
    {
        // Visibilidad por vendedor: no dejar abrir por URL una orden fuera de la
        // cartera propia (mismo criterio que el resto de la ficha).
        abort_unless($orden->esVisiblePara($request->user()), 403);

        $filtros = $request->validate([
            'tipo_dte' => ['nullable', Rule::in([DocumentoTributario::BOLETA, DocumentoTributario::FACTURA_AFECTA])],
            'forma_pago' => ['nullable', Rule::in(FormaPago::TODAS)],
        ]);

        $tipoDte = (int) ($filtros['tipo_dte'] ?? DocumentoTributario::BOLETA);
        $formaPago = $filtros['forma_pago'] ?? FormaPago::EFECTIVO;

        // Dos fallas DISTINTAS, y mezclarlas era un error: el armado del documento
        // puede fallar por reglas de negocio (garantía, total $0, tipo exento) y ahí
        // no hay documento que mostrar; el armado del MENSAJE para el emisor puede
        // fallar porque falta un id de Bsale en config/dte.php, y en ese caso el
        // documento sí existe y hay que poder verlo — es justamente el propósito de
        // esta pantalla. Si se tratan igual, la pantalla queda inútil hasta que
        // alguien complete la configuración.
        $documento = null;
        $problema = null;
        $payload = null;
        $faltaConfigurar = null;

        try {
            $documento = $this->armador->armar($orden, $tipoDte, $formaPago, $request->user());
        } catch (EmisionException $e) {
            $problema = $e->getMessage();
        }

        if ($documento) {
            try {
                $payload = $this->payloadDe($documento);
            } catch (EmisionException $e) {
                $faltaConfigurar = $e->getMessage();
            }
        }

        return view('admin.servicio-tecnico.documento', [
            'orden' => $orden->load('repuestos'),
            'documento' => $documento,
            'problema' => $problema,
            'payload' => $payload,
            // Qué falta en config/dte.php para poder emitir. Es la lista de tareas
            // del catastro, mostrada donde se necesita.
            'faltaConfigurar' => $faltaConfigurar,
            'tipoDte' => $tipoDte,
            'formaPago' => $formaPago,
            // Estado del candado: por qué no se puede emitir (null = se puede).
            'bloqueo' => CandadoDeEmision::motivoDelBloqueo(),
            'emisor' => $this->emisor->nombre(),
            'ambiente' => CandadoDeEmision::ambiente(),
            'yaEmitido' => $orden->dtesEmitidos()->latest('id')->first(),
        ]);
    }

    /**
     * El cuerpo que se le mandaría al emisor, si este emisor sabe mostrarlo.
     * Null cuando no ofrece previsualización (ver EmisorPrevisualizable).
     *
     * @return array<string, mixed>|null
     */
    private function payloadDe(DocumentoTributario $documento): ?array
    {
        if (! $this->emisor instanceof EmisorPrevisualizable) {
            return null;
        }

        // Si falta un mapeo de config (oficina, medio de pago) esto lanza, y lo
        // atrapa show() para mostrarlo como problema — que es precisamente lo que
        // hay que resolver antes de la primera emisión.
        return $this->emisor->previsualizar($documento);
    }
}
