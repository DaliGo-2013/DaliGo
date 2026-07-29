<?php

namespace App\Services\Dte;

use App\Models\DteEmitido;
use App\Support\FechaNegocio;
use Illuminate\Database\QueryException;

/**
 * Emite un documento tributario y lo deja registrado en `dte_emitidos` (M05 · B3).
 *
 * Es la pieza que usan los controladores y comandos. El emisor concreto (Bsale
 * hoy) llega por inyección del puerto EmisorDte, así que esta clase no sabe con
 * quién habla.
 *
 * ORDEN DE LAS OPERACIONES — no es cosmético. La fila se RESERVA antes de llamar
 * al emisor, no después:
 *
 *   1. Si ya hay fila para este `sales_id` y no quedó en ERROR, se devuelve esa
 *      y no se emite nada. Un F5 o un doble clic no puede duplicar.
 *   2. Si no hay, se inserta en estado PENDIENTE. El índice único de
 *      `dte_emitidos.sales_id` hace de barrera FÍSICA: si dos peticiones
 *      simultáneas llegan hasta acá, la segunda choca con el índice y se
 *      convierte en el caso 1 en vez de mandar un segundo POST.
 *   3. Recién entonces se emite.
 *
 * Si el orden fuera al revés (emitir y después guardar), dos clics rápidos
 * mandarían dos POST antes de que ninguno alcanzara a escribir, y el resultado
 * serían dos documentos tributarios con folio propio. Eso no se borra: se
 * corrige con nota de crédito, con el cliente esperando.
 *
 * Una falla de emisión deja la fila en ERROR y se puede reintentar con el MISMO
 * salesId, porque en ese caso el documento no existe en el emisor.
 */
class EmisionDte
{
    public function __construct(private EmisorDte $emisor) {}

    /**
     * Emite (o devuelve lo ya emitido para este salesId).
     *
     * @throws EmisionException si la emisión falla. La fila queda en ERROR.
     */
    public function emitir(DocumentoTributario $documento): DteEmitido
    {
        $registro = $this->reservar($documento);

        // Ya emitido antes: no se vuelve a llamar al emisor.
        if ($registro->estado_sii !== EstadoSii::PENDIENTE && $registro->estado_sii !== EstadoSii::ERROR) {
            return $registro;
        }

        try {
            $resultado = $this->emisor->emitir($documento);
        } catch (EmisionException $e) {
            $registro->update([
                'estado_sii' => EstadoSii::ERROR,
                'mensaje_sii' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $this->guardarResultado($registro, $resultado);
    }

    /**
     * Vuelve a preguntar por el veredicto del SII y actualiza el registro. Lo
     * usa el cron que persigue los que quedaron PENDIENTE o ENVIADO.
     *
     * @throws EmisionException
     */
    public function reconsultar(DteEmitido $registro): DteEmitido
    {
        if (blank($registro->documento_externo_id)) {
            return $registro;
        }

        $resultado = $this->emisor->consultarEstado($registro->documento_externo_id);

        return $this->guardarResultado($registro, $resultado);
    }

    /**
     * Reserva la fila del documento. Devuelve la existente si ya estaba.
     *
     * El catch de QueryException no es defensivo por si acaso: es el camino que
     * recorre la segunda de dos peticiones simultáneas cuando choca con el
     * índice único. Que llegue acá significa que la barrera funcionó.
     */
    private function reservar(DocumentoTributario $documento): DteEmitido
    {
        if ($existente = DteEmitido::where('sales_id', $documento->salesId)->first()) {
            return $existente;
        }

        try {
            return DteEmitido::create([
                'emisor' => $this->emisor->nombre(),
                'tipo_dte' => $documento->tipoDte,
                'sales_id' => $documento->salesId,
                'receptor_rut' => $documento->receptorRut,
                'receptor_nombre' => $documento->receptorNombre,
                'neto' => $documento->neto(),
                'iva' => $documento->iva(),
                'total' => $documento->totalEfectivo(),
                'estado_sii' => EstadoSii::PENDIENTE,
                'orden_servicio_id' => $documento->origen['orden_servicio_id'] ?? null,
                'sucursal_id' => $documento->origen['sucursal_id'] ?? null,
                'emitido_por' => $documento->origen['emitido_por'] ?? null,
            ]);
        } catch (QueryException $e) {
            $existente = DteEmitido::where('sales_id', $documento->salesId)->first();

            if ($existente) {
                return $existente;
            }

            throw $e;
        }
    }

    /**
     * Copia el resultado del emisor a la fila local.
     *
     * Los montos se toman del EMISOR y no de lo que pedimos: si Bsale calculó un
     * total distinto, el que vale para el SII es el suyo, y guardar el nuestro
     * escondería el descuadre en vez de mostrarlo.
     */
    private function guardarResultado(DteEmitido $registro, ResultadoEmision $resultado): DteEmitido
    {
        $registro->update(array_filter([
            'folio' => $resultado->folio,
            'documento_externo_id' => $resultado->documentoExternoId,
            'estado_sii' => $resultado->estado,
            'mensaje_sii' => $resultado->mensaje,
            'url_xml' => $resultado->urlXml,
            'url_pdf' => $resultado->urlPdf,
            'neto' => $resultado->neto ?: null,
            'iva' => $resultado->iva ?: null,
            'total' => $resultado->total ?: null,
            'emitido_at' => $registro->emitido_at ?? FechaNegocio::ahora(),
        ], fn ($valor) => $valor !== null));

        return $registro->refresh();
    }
}
