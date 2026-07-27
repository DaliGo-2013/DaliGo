<?php

namespace App\Services\Despachos;

use App\Models\Despacho;
use App\Models\DocumentoVenta;
use App\Services\Bsale\BsaleClient;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Dominio del despacho (DESPACHOS-v1). Capa Service (GUIA-DALIGO): los
 * controladores validan el request; las reglas del negocio viven aquí.
 */
class DespachoService
{
    public function __construct(private BsaleClient $bsale) {}

    /**
     * Crea el despacho de un documento espejado.
     *
     * ANTES de crear, re-verifica el documento PUNTUAL contra Bsale
     * (documents/{id}.json): el cancellation_status del espejo solo es fresco
     * ~1 día tras la emisión (límite de la ventana de DocumentSync) y un DTE
     * anulado NO se despacha. FAIL-CLOSED: si Bsale no responde O responde
     * sin un cancellationStatus legible, se rechaza (sin verificación no hay
     * despacho — es anti-fraude, no conveniencia). Lo re-leído refresca de
     * paso el espejo local.
     *
     * "Un despacho por documento" (v1) es ESTRUCTURAL: unique en BD +
     * lock corto al crear (la verificación HTTP va FUERA de la transacción,
     * review P-DSP-03 — un doble-submit no puede fabricar dos QR válidos).
     *
     * @param  array{zona_id?:int|null, transportista?:string|null, conductor_id?:int|null}  $datos
     *
     * @throws ValidationException si el documento está anulado, ya tiene
     *                             despacho, o no se pudo verificar.
     */
    public function crearDesdeDocumento(DocumentoVenta $documento, array $datos = []): Despacho
    {
        // Cara amable del invariante (mensaje inmediato sin gastar la HTTP).
        $this->exigirSinDespacho($documento);

        $this->verificarVigenciaEnBsale($documento);

        try {
            // Bloque corto: lock sobre el documento + re-check + create. El
            // unique de despachos.documento_venta_id respalda en BD lo que la
            // carrera pudiera colar igual (patrón bitácora 2026-06-30).
            return DB::transaction(function () use ($documento, $datos) {
                $anclado = DocumentoVenta::whereKey($documento->id)->lockForUpdate()->firstOrFail();
                $this->exigirSinDespacho($anclado);

                return Despacho::create([
                    'documento_venta_id' => $anclado->id,
                    // Zona explícita del form o la EFECTIVA del cliente
                    // (precedencia cliente-explícito > vendedor, P-DSP-02).
                    'zona_id' => $datos['zona_id'] ?? $anclado->cliente?->zonaEfectiva()?->id,
                    'transportista' => $datos['transportista'] ?? null,
                    'conductor_id' => $datos['conductor_id'] ?? null,
                    'estado' => Despacho::PREPARADO,
                ]);
            });
        } catch (QueryException $e) {
            if ($this->esViolacionDeUnique($e)) {
                throw ValidationException::withMessages([
                    'documento_venta_id' => "El documento folio {$documento->folio} ya tiene un despacho.",
                ]);
            }
            throw $e;
        }
    }

    private function exigirSinDespacho(DocumentoVenta $documento): void
    {
        $existente = $documento->despachos()->first();
        if ($existente !== null) {
            throw ValidationException::withMessages([
                'documento_venta_id' => "El documento folio {$documento->folio} ya tiene un despacho (código {$existente->codigo}).",
            ]);
        }
    }

    /**
     * Re-lee el documento puntual en Bsale y exige verlo VIGENTE de forma
     * explícita (cancellationStatus === 0). Un 200 sin el campo legible es
     * "no verificado" y se rechaza igual que una caída (fail-closed).
     * Actualiza el espejo local con el estado fresco, sea cual sea.
     */
    private function verificarVigenciaEnBsale(DocumentoVenta $documento): void
    {
        try {
            $doc = $this->bsale->get('documents/'.$documento->bsale_document_id.'.json');
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'documento_venta_id' => 'No se pudo verificar el documento contra Bsale (folio '.$documento->folio.'): sin verificación no se crea el despacho. Reintenta en unos minutos.',
            ]);
        }

        $cancellation = isset($doc['cancellationStatus']) && is_numeric($doc['cancellationStatus'])
            ? (int) $doc['cancellationStatus']
            : null;

        // Refrescar el espejo con lo recién leído (evita re-stale).
        $cancelladoAt = isset($doc['cancellationDate']) && is_numeric($doc['cancellationDate']) && (int) $doc['cancellationDate'] > 0
            ? Carbon::createFromTimestamp((int) $doc['cancellationDate'])
            : null;
        $documento->fill(array_filter([
            'cancellation_status' => $cancellation,
            'cancellation_at' => $cancelladoAt,
            'commercial_state' => isset($doc['commercialState']) ? (int) $doc['commercialState'] : null,
            'state' => isset($doc['state']) ? (int) $doc['state'] : null,
        ], fn ($v) => $v !== null))->save();

        if ($cancellation === null) {
            // 200 sin cancellationStatus legible: indeterminado ≠ vigente.
            throw ValidationException::withMessages([
                'documento_venta_id' => 'Bsale no confirmó la vigencia del documento (folio '.$documento->folio.'): sin verificación no se crea el despacho.',
            ]);
        }

        if ($cancellation !== 0) {
            throw ValidationException::withMessages([
                'documento_venta_id' => "El documento folio {$documento->folio} está ANULADO en Bsale: no se puede despachar.",
            ]);
        }
    }

    private function esViolacionDeUnique(QueryException $e): bool
    {
        $code = (string) ($e->errorInfo[1] ?? '');

        return $code === '1062' || $code === '19'
            || str_contains($e->getMessage(), 'UNIQUE')
            || str_contains($e->getMessage(), 'Duplicate');
    }
}
