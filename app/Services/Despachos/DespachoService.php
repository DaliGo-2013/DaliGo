<?php

namespace App\Services\Despachos;

use App\Models\Despacho;
use App\Models\DocumentoVenta;
use App\Models\EscaneoDespacho;
use App\Models\User;
use App\Services\Bsale\BsaleClient;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    /**
     * ESCANEO DEL QR EN BODEGA (P-DSP-04, el anti-fraude de M07).
     *
     * Todo intento de retiro deja fila en `escaneos_despacho` — también los
     * rechazados: la evidencia de que alguien presentó dos veces el mismo QR es
     * justamente lo que hace útil la alerta. Devuelve el resultado para que la
     * pantalla lo muestre; NO lanza excepción en el camino rechazado (no es un
     * error del operador: es un hallazgo que hay que exhibirle).
     *
     * El estado se re-lee DENTRO de la transacción con la fila BLOQUEADA
     * (`lockForUpdate`): sin eso, dos escaneos simultáneos —el doble-tap del
     * operador, o dos personas retirando la misma mercadería en dos puestos—
     * leerían ambos `preparado` y ambos marcarían el retiro como válido, que es
     * el fraude que este paso existe para impedir (patrón bitácora 2026-06-30:
     * check-then-act sin lock sobre la fila ancla). Mutación verificada: al
     * quitar este lock, RetiroQrTest se pone rojo en el test de la carrera.
     *
     * Los 3 resultados posibles del intento:
     * - `valido`          → estaba `preparado`: pasa a `retirado`, sella hora.
     * - `doble_retiro`    → ya había salido de bodega (`retirado`/`en_ruta`):
     *                       ALERTA fuerte, el estado NO se toca.
     * - `estado_invalido` → el ciclo ya cerró (`entregado`/`entrega_parcial`):
     *                       tampoco se retira, pero no es el mismo hallazgo.
     *
     * @return array{resultado:string, despacho:Despacho, escaneo:EscaneoDespacho}
     */
    public function validarRetiro(Despacho $despacho, ?User $operador = null): array
    {
        return DB::transaction(function () use ($despacho, $operador) {
            // Fila ancla bloqueada + re-lectura del estado con ella tomada.
            $anclado = Despacho::whereKey($despacho->id)->lockForUpdate()->firstOrFail();

            [$resultado, $detalle] = match (true) {
                $anclado->estado === Despacho::PREPARADO => [EscaneoDespacho::VALIDO, null],
                in_array($anclado->estado, [Despacho::RETIRADO, Despacho::EN_RUTA], true) => [
                    EscaneoDespacho::DOBLE_RETIRO,
                    'Ya retirado el '.($anclado->retirado_at?->enChile()->format('d-m-Y H:i') ?? 'sin fecha'),
                ],
                default => [
                    EscaneoDespacho::ESTADO_INVALIDO,
                    'El despacho ya está '.$anclado->estado,
                ],
            };

            if ($resultado === EscaneoDespacho::VALIDO) {
                $anclado->update([
                    'estado' => Despacho::RETIRADO,
                    'retirado_at' => now(),
                ]);
            }

            $escaneo = EscaneoDespacho::create([
                'despacho_id' => $anclado->id,
                'user_id' => $operador?->id,
                'resultado' => $resultado,
                // 191 en BD: se recorta acá para no depender de la validación
                // silenciosa de SQLite (I-07: MySQL sí rechaza el excedente).
                'detalle' => $detalle === null ? null : Str::limit($detalle, 188),
            ]);

            return ['resultado' => $resultado, 'despacho' => $anclado, 'escaneo' => $escaneo];
        });
    }

    /**
     * ENTREGA registrada desde el panel (P-DSP-04). La entrega con firma+foto
     * del conductor es P-DSP-05; esto es la contraparte de bodega para cerrar
     * un despacho hoy.
     *
     * Parcial ⇒ estado `entrega_parcial` y la observación es OBLIGATORIA: es el
     * saldo, y un parcial sin saldo visible no se puede reclamar después.
     * Mismo lock que el retiro: cerrar dos veces el mismo despacho desde dos
     * pestañas no debe pisar la hora ni el saldo del primero.
     *
     * @throws ValidationException si el despacho no salió de bodega o falta el saldo.
     */
    public function registrarEntrega(Despacho $despacho, bool $parcial, ?string $observacion = null): Despacho
    {
        if ($parcial && blank($observacion)) {
            throw ValidationException::withMessages([
                'entrega_observacion' => 'Indica qué quedó pendiente: una entrega parcial sin saldo no se puede reclamar después.',
            ]);
        }

        return DB::transaction(function () use ($despacho, $parcial, $observacion) {
            $anclado = Despacho::whereKey($despacho->id)->lockForUpdate()->firstOrFail();

            // Solo se entrega lo que SALIÓ de bodega (el retiro es el paso
            // previo del ciclo) y solo una vez.
            if (! in_array($anclado->estado, [Despacho::RETIRADO, Despacho::EN_RUTA], true)) {
                throw ValidationException::withMessages([
                    'estado' => $anclado->estado === Despacho::PREPARADO
                        ? "El despacho {$anclado->codigo} todavía no se retiró de bodega."
                        : "El despacho {$anclado->codigo} ya está {$anclado->estado}.",
                ]);
            }

            $anclado->update([
                'estado' => $parcial ? Despacho::ENTREGA_PARCIAL : Despacho::ENTREGADO,
                'entregado_at' => now(),
                'entrega_observacion' => blank($observacion) ? null : Str::limit($observacion, 188),
            ]);

            return $anclado;
        });
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
