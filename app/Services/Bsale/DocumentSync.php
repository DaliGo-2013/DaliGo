<?php

namespace App\Services\Bsale;

use App\Models\Bodega;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\DocumentoVenta;
use App\Models\Producto;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sincroniza los documentos de venta de Bsale hacia el espejo local
 * `documentos_venta` (+ detalles). Solo lectura en Bsale; upsert por
 * bsale_document_id, SIN fase de delete de documentos.
 *
 * Ventana por `emissiondaterange` (backfill completo PROHIBIDO: producción
 * tiene ~676k documentos) anclada en un WATERMARK persistente que solo avanza
 * cuando el tramo quedó completo: un doc fallido o una corrida abortada NO
 * mueven el ancla, así el tramo se re-barre en la corrida siguiente (nada se
 * pierde en silencio). Cada corrida procesa a lo más DIAS_VENTANA_MAX días
 * (catch-up por tramos, nunca un backfill gigante por cron).
 *
 * LÍMITE CONOCIDO (aceptado en v1, plan §Riesgo #2): la ventana filtra por
 * FECHA DE EMISIÓN. Un doc anulado o creado retroactivamente cuya emisión ya
 * salió del resolape no se re-lee: cancellation_status solo es confiable
 * dentro de ~DIAS_RESOLAPE. El consumidor (despachos) debe re-verificar el
 * documento puntual (documents/{id}.json) antes de actuar; el espejo fiel
 * llega con webhooks (D-005).
 *
 * Shape verificado contra producción (P-DSP-00): details viene como sobre
 * anidado {items}; client puede NO venir (boletas); estados son INT.
 */
class DocumentSync
{
    /**
     * Clave de configuración con la fecha de arranque del espejo (Y-m-d).
     * Es el PISO: la ventana nunca retrocede más atrás de esta fecha.
     */
    public const CONFIG_DESDE = 'documentos_sync_desde';

    /**
     * Clave interna: hasta dónde el espejo quedó COMPLETO (ISO-8601). Solo la
     * escribe esta sync; no editar a mano. Si una corrida falla o deja docs
     * omitidos, no avanza (o retrocede al fallido más antiguo) para re-barrer.
     */
    public const CONFIG_WATERMARK = 'documentos_sync_watermark';

    /** Días hacia atrás en el primer run si no hay fecha configurada. */
    public const DIAS_DEFAULT = 7;

    /** Días de resolape al avanzar (reatrapa docs tardíos y anulaciones del borde). */
    public const DIAS_RESOLAPE = 1;

    /**
     * Tope de la ventana por corrida: un piso antiguo (o mucho downtime) se
     * pone al día por tramos de este tamaño, una corrida horaria a la vez —
     * jamás los ~676k históricos de un tirón en el hosting compartido.
     */
    public const DIAS_VENTANA_MAX = 30;

    public function __construct(private BsaleClient $client) {}

    /**
     * @return array{creados:int,actualizados:int,detalles:int,omitidos:int,errores:array<int,array>}
     */
    public function run(): array
    {
        $stats = ['creados' => 0, 'actualizados' => 0, 'detalles' => 0, 'omitidos' => 0, 'errores' => []];

        [$desde, $hasta] = $this->ventana();

        // Mapas de match locales (patrón de las syncs existentes: un pluck, no N+1).
        $clientes = Cliente::whereNotNull('bsale_client_id')->pluck('id', 'bsale_client_id');
        $bodegas = Bodega::whereNotNull('bsale_office_id')->pluck('id', 'bsale_office_id');
        $productos = Producto::whereNotNull('bsale_variant_id')
            ->get(['id', 'nombre', 'bsale_variant_id'])
            ->keyBy('bsale_variant_id');

        // Epoch de emisión del doc fallido más antiguo de ESTA corrida: si algo
        // falla, el watermark retrocede hasta ahí para reintentarlo después.
        $fallidoMasAntiguo = null;

        // Carga masiva espejo: sin audit por fila (igual que las otras 4 syncs).
        DocumentoVenta::withoutAuditing(function () use (&$stats, &$fallidoMasAntiguo, $desde, $hasta, $clientes, $bodegas, $productos) {
            $query = [
                'expand' => '[details,client,office]',
                'emissiondaterange' => '['.$desde->timestamp.','.$hasta->timestamp.']',
            ];

            foreach ($this->client->each('documents.json', $query) as $doc) {
                try {
                    $this->upsertOne($doc, $stats, $clientes, $bodegas, $productos);
                } catch (Throwable $e) {
                    $stats['omitidos']++;
                    $stats['errores'][] = [
                        'document_id' => $doc['id'] ?? null,
                        'folio' => $doc['number'] ?? null,
                        'error' => $e->getMessage(),
                    ];

                    $epoch = $doc['emissionDate'] ?? null;
                    if (is_numeric($epoch) && (int) $epoch > 0) {
                        $fallidoMasAntiguo = $fallidoMasAntiguo === null
                            ? (int) $epoch
                            : min($fallidoMasAntiguo, (int) $epoch);
                    } else {
                        // Fallido sin fecha: no se puede acotar → no avanzar nada.
                        $fallidoMasAntiguo = $fallidoMasAntiguo ?? $desde->timestamp;
                    }
                }
            }
        });

        // El watermark solo avanza sobre tramo COMPLETO. Si run() aborta antes
        // (p.ej. una página de Bsale falló tras los reintentos), no se toca y
        // la corrida siguiente repite la MISMA ventana: sin huecos silenciosos.
        $this->guardarWatermark(
            $stats['errores'] === []
                ? $hasta
                : Carbon::createFromTimestamp(min($fallidoMasAntiguo, $hasta->timestamp)),
        );

        Log::info(sprintf(
            'bsale:sync-documents → ventana %s..%s · %d creados, %d actualizados, %d detalles, %d omitidos, %d errores.',
            $desde->toDateString(), $hasta->toDateString(),
            $stats['creados'], $stats['actualizados'], $stats['detalles'], $stats['omitidos'], count($stats['errores']),
        ));

        return $stats;
    }

    /**
     * Ventana [desde, hasta] de la corrida.
     *
     * - Ancla = watermark persistente (o, si aún no existe, el último
     *   emitido_at espejado); menos DIAS_RESOLAPE, sin retroceder del piso.
     * - Espejo virgen sin ancla: piso configurado o últimos DIAS_DEFAULT días.
     * - `hasta` se capa a DIAS_VENTANA_MAX desde el `desde`: el catch-up de un
     *   piso antiguo avanza por tramos, corrida a corrida.
     *
     * @return array{0:Carbon,1:Carbon}
     */
    private function ventana(): array
    {
        $ahora = now();
        $piso = $this->pisoConfigurado();

        $ancla = $this->watermark() ?? $this->ultimoEmitido();

        if ($ancla === null) {
            $desde = $piso ?? $ahora->copy()->subDays(self::DIAS_DEFAULT);
        } else {
            $desde = $ancla->copy()->subDays(self::DIAS_RESOLAPE);
            if ($piso !== null && $desde->lt($piso)) {
                $desde = $piso->copy();
            }
        }

        $hasta = $desde->copy()->addDays(self::DIAS_VENTANA_MAX);
        if ($hasta->gt($ahora)) {
            $hasta = $ahora;
        }

        return [$desde, $hasta];
    }

    /**
     * Piso Y-m-d de Configuración. Un valor malformado NO puede tumbar la sync
     * horaria: se loguea y se ignora (aplica el default conservador).
     */
    private function pisoConfigurado(): ?Carbon
    {
        $valor = Configuracion::get(self::CONFIG_DESDE);
        if (! filled($valor)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', trim((string) $valor))->startOfDay();
        } catch (InvalidFormatException) {
            Log::warning("bsale:sync-documents → '".self::CONFIG_DESDE."' malformada ('{$valor}', se espera Y-m-d); se ignora.");

            return null;
        }
    }

    private function watermark(): ?Carbon
    {
        $valor = Configuracion::get(self::CONFIG_WATERMARK);

        try {
            return filled($valor) ? Carbon::parse($valor) : null;
        } catch (InvalidFormatException) {
            return null; // watermark corrupto: cae al último emitido_at
        }
    }

    private function guardarWatermark(Carbon $hasta): void
    {
        // La fila puede no existir aún (BD anterior al seeder nuevo): crearla.
        Configuracion::firstOrCreate(
            ['clave' => self::CONFIG_WATERMARK],
            [
                'valor' => null,
                'tipo' => Configuracion::TIPO_STRING,
                'grupo' => 'despachos',
                'descripcion' => 'Interno (lo escribe bsale:sync-documents): hasta dónde el espejo de documentos quedó completo. No editar a mano.',
            ],
        );

        Configuracion::set(self::CONFIG_WATERMARK, $hasta->toIso8601String());
    }

    private function ultimoEmitido(): ?Carbon
    {
        $ultimo = DocumentoVenta::max('emitido_at');

        return $ultimo === null ? null : Carbon::parse($ultimo);
    }

    /**
     * Header + detalles en UNA transacción: un doc jamás queda espejado a
     * medias (header sin líneas) — si algo falla, se reintenta completo en la
     * corrida siguiente vía watermark.
     *
     * @param  \Illuminate\Support\Collection<int|string, int>  $clientes
     * @param  \Illuminate\Support\Collection<int|string, int>  $bodegas
     * @param  \Illuminate\Support\Collection<int|string, Producto>  $productos
     */
    private function upsertOne(array $doc, array &$stats, $clientes, $bodegas, $productos): void
    {
        $documentId = isset($doc['id']) ? (int) $doc['id'] : null;
        if ($documentId === null) {
            throw new \RuntimeException('Documento sin id.');
        }

        // client puede NO venir (boletas, hallazgo #2): match solo cuando venga.
        $bsaleClientId = isset($doc['client']['id']) ? (int) $doc['client']['id'] : null;
        $bsaleOfficeId = isset($doc['office']['id']) ? (int) $doc['office']['id'] : null;

        $campos = [
            'bsale_document_id' => $documentId,
            'folio' => isset($doc['number']) ? (int) $doc['number'] : null,
            'bsale_document_type_id' => isset($doc['document_type']['id']) ? (int) $doc['document_type']['id'] : null,
            'emitido_at' => $this->fecha($doc['emissionDate'] ?? null),
            'neto' => (float) ($doc['netAmount'] ?? 0),
            'iva' => (float) ($doc['taxAmount'] ?? 0),
            'total' => (float) ($doc['totalAmount'] ?? 0),
            'state' => isset($doc['state']) ? (int) $doc['state'] : null,
            'commercial_state' => isset($doc['commercialState']) ? (int) $doc['commercialState'] : null,
            'cancellation_status' => isset($doc['cancellationStatus']) ? (int) $doc['cancellationStatus'] : null,
            'cancellation_at' => $this->fecha($doc['cancellationDate'] ?? null),
            'informed_sii' => isset($doc['informedSii']) ? (int) $doc['informedSii'] : null,
            'url_pdf' => $this->limpiar($doc['urlPdf'] ?? null),
            'url_public' => $this->limpiar($doc['urlPublicView'] ?? null),
            'token' => $this->limpiar($doc['token'] ?? null, 64),
            'cliente_id' => $bsaleClientId !== null ? ($clientes[$bsaleClientId] ?? null) : null,
            'bodega_id' => $bsaleOfficeId !== null ? ($bodegas[$bsaleOfficeId] ?? null) : null,
        ];

        // El fetch del plan B (detalles paginados) va ANTES de abrir la
        // transacción: nada de HTTP con la transacción abierta.
        $lineas = $this->lineasDe($doc);

        DB::transaction(function () use (&$stats, $documentId, $campos, $lineas, $productos) {
            $row = DocumentoVenta::where('bsale_document_id', $documentId)->first();

            if ($row !== null) {
                $row->fill($campos)->save();
                $stats['actualizados']++;
            } else {
                $row = DocumentoVenta::create($campos);
                $stats['creados']++;
            }

            $this->syncDetalles($row, $lineas, $stats, $productos);
        });
    }

    /**
     * Líneas del documento. En el GET con expand vienen como sobre anidado
     * {count, items} (verificado en P-DSP-00); si el sobre declara MÁS items
     * de los que trae (documento largo, paginado), se recorre el endpoint
     * dedicado documents/{id}/details.json (plan B previsto). Devuelve null si
     * la respuesta no trajo detalles utilizables (se tolera: no tocar nada).
     *
     * @return array<int,array>|null
     */
    private function lineasDe(array $doc): ?array
    {
        $sobre = $doc['details'] ?? null;
        if (! is_array($sobre)) {
            return null;
        }

        $items = $sobre['items'] ?? [];
        $declarados = (int) ($sobre['count'] ?? count($items));

        if ($declarados > count($items)) {
            $items = iterator_to_array(
                $this->client->each('documents/'.(int) $doc['id'].'/details.json'),
                false,
            );
        }

        return $items;
    }

    /**
     * @param  array<int,array>|null  $lineas
     * @param  \Illuminate\Support\Collection<int|string, Producto>  $productos
     */
    private function syncDetalles(DocumentoVenta $row, ?array $lineas, array &$stats, $productos): void
    {
        if ($lineas === null) {
            return; // sin detalles en la respuesta: se tolera (no borrar nada)
        }

        $vistos = [];

        foreach ($lineas as $d) {
            $detailId = isset($d['id']) ? (int) $d['id'] : null;
            if ($detailId === null) {
                continue; // línea sin id: no hay identidad para el upsert
            }

            $variantId = isset($d['variant']['id']) ? (int) $d['variant']['id'] : null;
            $producto = $variantId !== null ? ($productos[$variantId] ?? null) : null;

            // Fallback de descripción (hallazgo #3): producto espejado, luego
            // lo que el nodo variant permita, si no null.
            $descripcion = $producto?->nombre
                ?? $this->limpiar($d['variant']['description'] ?? null)
                ?? $this->limpiar($d['variant']['code'] ?? null);

            $row->detalles()->updateOrCreate(
                ['bsale_detail_id' => $detailId],
                [
                    'producto_id' => $producto?->id,
                    'descripcion' => $descripcion,
                    'cantidad' => (float) ($d['quantity'] ?? 0),
                    'precio_neto' => (float) ($d['netUnitValueRaw'] ?? $d['netUnitValue'] ?? 0),
                    'descuento' => (float) ($d['totalDiscount'] ?? 0),
                ],
            );

            $vistos[] = $detailId;
            $stats['detalles']++;
        }

        // Espejo de líneas eliminadas dentro del doc, con el guard de la
        // bitácora [2026-06-12]: si hubo líneas pero NINGUNA con id, no borrar
        // (whereNotIn con [] compila 1=1 y barrería todo el detalle).
        if ($lineas !== [] && $vistos !== []) {
            $row->detalles()->whereNotIn('bsale_detail_id', $vistos)->delete();
        }
    }

    private function fecha(mixed $epoch): ?Carbon
    {
        if (! is_numeric($epoch) || (int) $epoch <= 0) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $epoch);
    }

    private function limpiar(mixed $valor, int $max = 191): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : mb_substr($valor, 0, $max);
    }
}
