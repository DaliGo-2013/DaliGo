<?php

namespace App\Services\Despachos;

use App\Models\Despacho;
use App\Models\DocumentoVenta;
use App\Models\EscaneoDespacho;
use App\Models\HojaRutaParada;
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
     * check-then-act sin lock sobre la fila ancla).
     *
     * QUÉ CUBRE LA SUITE Y QUÉ NO (corregido tras el gate del 28-07 — la versión
     * anterior de este comentario afirmaba de más):
     * - `RetiroQrTest` cubre la **RE-LECTURA**: con una instancia stale, el 2º
     *   intento se rechaza. Quitando la re-lectura, ese test se pone rojo.
     * - **El lock NO es asertable en esta suite.** `SQLiteGrammar::compileLock()`
     *   devuelve `''` de forma incondicional, así que bajo SQLite
     *   `->lockForUpdate()` emite SQL byte-idéntico a omitirlo: ningún test de
     *   feature puede distinguirlo, y un assert de `DB::listen` buscando
     *   "for update" saldría rojo sobre código correcto. La cobertura honesta del
     *   lock es a nivel de grammar: ver `tests/Unit/LockParaMySqlTest.php`, que
     *   compila el mismo builder con `MySqlGrammar` y exige el sufijo.
     * - En producción (MySQL 5.7) el lock SÍ se emite; es la mitad que protege
     *   contra dos procesos PHP concurrentes, donde la re-lectura sola no basta.
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
     * Mismo patrón que el retiro: cerrar dos veces el mismo despacho desde dos
     * pestañas no debe pisar la hora ni el saldo del primero. Igual que allá, lo
     * que la suite cubre es la **re-lectura** con la fila tomada; el
     * `lockForUpdate` en sí no es asertable bajo SQLite (ver la nota extendida en
     * `validarRetiro` y `tests/Unit/LockParaMySqlTest.php`).
     *
     * @param  array<string,mixed>  $extra  columnas adicionales que entran al MISMO
     *                                       update, dentro del lock (P-DSP-05: el
     *                                       uuid de idempotencia y la hora del
     *                                       dispositivo deben quedar atómicos con el
     *                                       cambio de estado). Default [] = camino
     *                                       del jefe byte-equivalente al de antes.
     * @param  \Closure|null  $dentro  gancho ejecutado con la fila ANCLADA, dentro
     *                                 de la misma transacción (P-DSP-09: escribir el
     *                                 cobro y el resultado en la PARADA debe quedar
     *                                 atómico con el cambio de estado del despacho —
     *                                 mismo patrón que transicionar() en
     *                                 HojaRutaService). Default null = sin efecto.
     *
     * @throws ValidationException si el despacho no salió de bodega o falta el saldo.
     */
    public function registrarEntrega(Despacho $despacho, bool $parcial, ?string $observacion = null, array $extra = [], ?\Closure $dentro = null): Despacho
    {
        if ($parcial && blank($observacion)) {
            throw ValidationException::withMessages([
                'entrega_observacion' => 'Indica qué quedó pendiente: una entrega parcial sin saldo no se puede reclamar después.',
            ]);
        }

        return DB::transaction(function () use ($despacho, $parcial, $observacion, $extra, $dentro) {
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

            $anclado->update($extra + [
                'estado' => $parcial ? Despacho::ENTREGA_PARCIAL : Despacho::ENTREGADO,
                'entregado_at' => now(),
                'entrega_observacion' => blank($observacion) ? null : Str::limit($observacion, 188),
            ]);

            if ($dentro) {
                $dentro($anclado);
            }

            return $anclado;
        });
    }

    /**
     * CONFIRMACIÓN DEL CONDUCTOR (P-DSP-05): la entrega que llega desde la PWA,
     * directa o drenada por la cola offline — por eso es IDEMPOTENTE por
     * `entrega_uuid`: la cola puede reintentar el mismo envío tras un corte y el
     * segundo intento debe responder éxito SIN tocar nada.
     *
     * Tres capas, patrón de LoteServicioController::store:
     * 1. Pre-check por uuid (la cara amable: responde sin gastar transacción).
     * 2. El update con el uuid dentro del lock de registrarEntrega().
     * 3. El UNIQUE de despachos.entrega_uuid + catch de QueryException (la red:
     *    dos drenados en paralelo pasan ambos el pre-check; el segundo choca con
     *    la BD y se le responde igual que al duplicado amable).
     *
     * P-DSP-09: además del despacho, la confirmación escribe la PARADA de la
     * hoja de ruta (si existe) EN LA MISMA transacción: `resultado=entregada`
     * y — solo si la parada es `cobrar_en_entrega` — el método y el monto que
     * el chofer cobró en la puerta (R4+R7). El receptor (R13) va al despacho
     * por $extra. El registro de la rendición NO es de este lote.
     *
     * @param  array{entrega_uuid:string, capturado_at:string, parcial?:bool, entrega_observacion?:string|null, receptor_nombre?:string, receptor_rut?:string, receptor_relacion?:string, cobro_metodo?:string|null, cobro_monto?:int|null}  $datos
     * @return array{despacho: Despacho, yaExistia: bool}
     *
     * @throws ValidationException si el despacho no admite entrega o falta el saldo.
     */
    public function confirmarEntregaConductor(Despacho $despacho, array $datos): array
    {
        $uuid = $datos['entrega_uuid'];

        // Cara amable del invariante: ¿este envío ya se procesó?
        $existente = Despacho::where('entrega_uuid', $uuid)->first();
        if ($existente !== null) {
            return ['despacho' => $existente, 'yaExistia' => true];
        }

        try {
            $entregado = $this->registrarEntrega(
                $despacho,
                (bool) ($datos['parcial'] ?? false),
                $datos['entrega_observacion'] ?? null,
                [
                    'entrega_uuid' => $uuid,
                    // Hora del DISPOSITIVO (offline-safe): cuándo firmó el
                    // cliente de verdad, aunque el envío drene horas después.
                    // entregado_at (hora del server) sigue siendo la verdad de
                    // auditoría — registrarEntrega la sella igual.
                    'capturado_at' => $datos['capturado_at'],
                    // Receptor en la puerta (R13): quién recibió, atómico con
                    // el cambio de estado. El controller ya los exigió.
                    'receptor_nombre' => $datos['receptor_nombre'] ?? null,
                    'receptor_rut' => $datos['receptor_rut'] ?? null,
                    'receptor_relacion' => $datos['receptor_relacion'] ?? null,
                ],
                function (Despacho $anclado) use ($datos) {
                    $parada = $anclado->parada;
                    if (! $parada) {
                        return;   // despacho suelto: nada que cerrar en la hoja
                    }

                    $cambios = ['resultado' => HojaRutaParada::RESULTADO_ENTREGADA];

                    // El cobro solo existe donde la hoja lo pidió; en una
                    // parada pagada/crédito lo recibido se IGNORA (el
                    // controller tampoco lo exige — doble capa).
                    if ($parada->estado_cobro === HojaRutaParada::COBRO_EN_ENTREGA) {
                        $cambios['cobro_metodo'] = $datos['cobro_metodo'] ?? null;
                        $cambios['cobro_monto'] = $datos['cobro_monto'] ?? null;
                    }

                    $parada->update($cambios);
                },
            );

            return ['despacho' => $entregado, 'yaExistia' => false];
        } catch (QueryException $e) {
            // La carrera real: otro drenado escribió el mismo uuid entre el
            // pre-check y nuestro update. Para la cola es el mismo éxito.
            if ($this->esViolacionDeUnique($e)) {
                $existente = Despacho::where('entrega_uuid', $uuid)->first();
                if ($existente !== null) {
                    return ['despacho' => $existente, 'yaExistia' => true];
                }
            }
            throw $e;
        }
    }

    /**
     * RECHAZO EN PUERTA (P-DSP-09, R15): el conductor NO pudo entregar. Solo
     * existe para despachos EN una hoja (el resultado vive en la parada); la
     * carga física vuelve a bodega y el jefe de despacho decide qué pasa —
     * el aviso M15 sale de aquí. NO crea la devolución M13 (decisión del
     * dueño pendiente, pregunta del parte).
     *
     * Idempotente SIN columna uuid: el rechazo no crea filas — un reintento
     * de la cola encuentra la parada ya rechazada y responde duplicado sin
     * pisar el motivo original. El re-check corre bajo el lock del despacho
     * (misma fila ancla que la entrega: entrega y rechazo compiten entre sí).
     *
     * @param  array{motivo:string, capturado_at?:string|null}  $datos
     * @return array{despacho: Despacho, yaExistia: bool}
     *
     * @throws ValidationException si el despacho no tiene parada o su ciclo ya cerró.
     */
    public function rechazarEntregaConductor(Despacho $despacho, array $datos): array
    {
        $parada = $despacho->parada;

        if (! $parada) {
            throw ValidationException::withMessages([
                'despacho' => 'Este despacho no pertenece a una hoja de ruta: el rechazo en puerta se registra sobre la parada.',
            ]);
        }

        // Cara amable: ¿ya se rechazó? (reintento de la cola)
        if ($parada->resultado === HojaRutaParada::RESULTADO_RECHAZADA) {
            return ['despacho' => $despacho, 'yaExistia' => true];
        }

        $resultado = DB::transaction(function () use ($despacho, $datos) {
            $anclado = Despacho::whereKey($despacho->id)->lockForUpdate()->firstOrFail();
            $parada = $anclado->parada()->first();

            // Re-check con la fila tomada: otro drenado pudo rechazarla (o
            // entregarla) entre el pre-check y este lock.
            if ($parada->resultado === HojaRutaParada::RESULTADO_RECHAZADA) {
                return ['despacho' => $anclado, 'yaExistia' => true];
            }

            if ($parada->resultado !== null || ! $anclado->admiteEntrega()) {
                throw ValidationException::withMessages([
                    'estado' => "El despacho {$anclado->codigo} ya está resuelto: no se puede rechazar.",
                ]);
            }

            $parada->update([
                'resultado' => HojaRutaParada::RESULTADO_RECHAZADA,
                'rechazo_motivo' => Str::limit($datos['motivo'], 188),
            ]);

            return ['despacho' => $anclado, 'yaExistia' => false];
        });

        // El aviso sale DESPUÉS del commit (patrón M13): si el correo falla,
        // el rechazo ya está registrado — se reporta, no se revienta.
        if (! $resultado['yaExistia']) {
            $this->avisarParadaRechazada($resultado['despacho']);
        }

        return $resultado;
    }

    /**
     * Aviso M15 al equipo de despacho: una parada volvió rechazada (R15). Por
     * ROL (patrón M13): jefe_despacho decide, jefe_logistica arma las hojas,
     * admin supervisa. Campanita al tiro; correo con la latencia de la grilla.
     */
    private function avisarParadaRechazada(Despacho $despacho): void
    {
        try {
            $parada = $despacho->parada()->with('hoja.conductor')->first();
            $hoja = $parada?->hoja;
            if (! $hoja) {
                return;
            }

            $dispatcher = app(\App\Services\Notificaciones\NotificacionDispatcher::class);
            $datos = [
                'folio_hoja' => (string) $hoja->folio,
                'folio_documento' => (string) ($despacho->documento?->folio ?? '—'),
                'cliente' => $despacho->documento?->cliente?->razon_social ?? '—',
                'motivo' => $parada->rechazo_motivo ?? '—',
                'conductor' => $hoja->conductor?->name ?? '—',
                'url' => route('admin.hojas-ruta.show', $hoja),
            ];

            \App\Support\AudienciasNotificacion::destinatarios('despacho.parada_rechazada')
                ->each(fn (User $u) => $dispatcher->despachar('despacho.parada_rechazada', $hoja, $u, $datos));
        } catch (Throwable $e) {
            report($e);
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
