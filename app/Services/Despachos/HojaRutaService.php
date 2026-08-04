<?php

namespace App\Services\Despachos;

use App\Models\Despacho;
use App\Models\DocumentoVenta;
use App\Models\HojaDeRuta;
use App\Models\HojaRutaParada;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Dominio de la hoja de ruta digital (P-DSP-08, PLAN-DESPACHOS-V2).
 *
 * La hoja reemplaza el Excel de Ricardo: una salida de un vehículo (R2),
 * armada por zona (R21) ELIGIENDO documentos del espejo Bsale (R1: el tipeo
 * muere), con folio correlativo desde 1000 (R25) y la cadena de 3 llaves
 * secuenciales (R11) — ventas autoriza PAGOS, despacho autoriza RUTA, bodega
 * autoriza CARGA y registra la salida. Toda transición estampa quién y
 * cuándo AUTOMÁTICAMENTE (R5: cero campos manuales de hora).
 */
class HojaRutaService
{
    public function __construct(private DespachoService $despachos) {}

    /**
     * Crea la hoja en borrador con su folio correlativo.
     *
     * El folio se calcula max(folio, 999)+1 CON lockForUpdate dentro de la
     * transacción; la red real contra la carrera es el unique de BD (dos
     * transacciones que igual calculen el mismo número chocan ahí — patrón
     * del unique de documento_venta_id). El vehículo llega como id del
     * catálogo M18 y se CONGELA como texto (vehiculo/patente): si mañana el
     * vehículo se renombra o se borra, la hoja histórica no cambia — mismo
     * criterio que traslados_servicio con emisor_nombre.
     *
     * @param  array{sucursal_id:int, zona_id:int, vehiculo_id:int, conductor_id:int, peoneta_nombre?:string|null}  $datos
     */
    public function crear(array $datos): HojaDeRuta
    {
        $vehiculo = Vehiculo::findOrFail($datos['vehiculo_id']);

        return DB::transaction(function () use ($datos, $vehiculo) {
            $folio = max(
                (int) HojaDeRuta::query()->lockForUpdate()->max('folio'),
                HojaDeRuta::FOLIO_PISO,
            ) + 1;

            return HojaDeRuta::create([
                'folio' => $folio,
                'sucursal_id' => $datos['sucursal_id'],
                'zona_id' => $datos['zona_id'],
                'vehiculo_id' => $vehiculo->id,
                'vehiculo' => $vehiculo->nombre,
                'patente' => $vehiculo->ppu,
                'conductor_id' => $datos['conductor_id'],
                'peoneta_nombre' => $datos['peoneta_nombre'] ?? null,
                'estado' => HojaDeRuta::BORRADOR,
            ]);
        });
    }

    /**
     * Genera las paradas ELIGIENDO documentos (R1: Ricardo no tipea nada).
     *
     * Solo sobre una hoja en borrador: después de la llave de pagos, agregar
     * un destino es la edición auditada de P-DSP-10 (R6). Por cada documento,
     * en el orden elegido:
     * - reusa su despacho si existe, está sin hoja y su ciclo no cerró, o
     * - lo crea vía DespachoService::crearDesdeDocumento (que re-verifica la
     *   vigencia contra Bsale FAIL-CLOSED y hereda zona/conductor de la hoja).
     *
     * Dos fases a propósito: primero se resuelven TODOS los despachos (cada
     * creación es atómica por sí misma y una que falle no deja paradas a
     * medias), recién después una transacción corta crea TODAS las paradas.
     * Si la fase 1 se corta a mitad, los despachos ya creados quedan — son
     * válidos por sí solos (aparecen en el panel) y una nueva generación los
     * reusa.
     *
     * @param  array<int, int>  $documentoIds  ids en el orden pactado
     * @param  array<int, string>  $cobros  documento_id => estado_cobro (R4)
     *
     * @throws ValidationException
     */
    public function generarParadas(HojaDeRuta $hoja, array $documentoIds, array $cobros = []): HojaDeRuta
    {
        if ($hoja->estado !== HojaDeRuta::BORRADOR) {
            throw ValidationException::withMessages([
                'estado' => "La hoja {$hoja->folio} ya no está en borrador: sus paradas no se editan por aquí.",
            ]);
        }

        // Fase 1: resolver los despachos (validación + creación, sin paradas).
        $despachos = [];
        foreach ($documentoIds as $documentoId) {
            $documento = DocumentoVenta::query()->vigentes()->find($documentoId);

            if (! $documento) {
                throw ValidationException::withMessages([
                    'documentos' => 'Uno de los documentos elegidos no existe o está anulado.',
                ]);
            }

            $despachos[$documentoId] = $this->resolverDespacho($documento, $hoja);
        }

        // Fase 2: las paradas, todas o ninguna.
        DB::transaction(function () use ($hoja, $despachos, $cobros) {
            $orden = (int) $hoja->paradas()->max('orden');

            foreach ($despachos as $documentoId => $despacho) {
                HojaRutaParada::create([
                    'hoja_de_ruta_id' => $hoja->id,
                    'despacho_id' => $despacho->id,
                    'orden' => ++$orden,
                    'estado_cobro' => $this->cobroValido($cobros[$documentoId] ?? null),
                ]);
            }
        });

        return $hoja->refresh();
    }

    /**
     * Reordena las paradas de un borrador (R3: el orden pactado). Regenera
     * la secuencia COMPLETA — por eso la tabla no lleva unique de orden (un
     * swap A↔B se validaría fila a fila y chocaría a mitad del update).
     *
     * @param  array<int, int>  $paradaIds  ids de parada en el orden nuevo
     */
    public function reordenar(HojaDeRuta $hoja, array $paradaIds): HojaDeRuta
    {
        if (! in_array($hoja->estado, [HojaDeRuta::BORRADOR, HojaDeRuta::PAGOS_OK, HojaDeRuta::RUTA_AUTORIZADA, HojaDeRuta::CARGADA], true)) {
            throw ValidationException::withMessages([
                'estado' => "La hoja {$hoja->folio} ya salió a ruta: el orden no se toca.",
            ]);
        }

        $propias = $hoja->paradas()->pluck('id');

        if ($propias->count() !== count($paradaIds) || $propias->diff($paradaIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'orden' => 'El orden recibido no calza con las paradas de la hoja.',
            ]);
        }

        DB::transaction(function () use ($hoja, $paradaIds) {
            // Pasada previa a valores fuera de rango: evita el choque
            // transitorio si algún día el índice vuelve a ser unique.
            $hoja->paradas()->increment('orden', 10000);

            foreach (array_values($paradaIds) as $i => $paradaId) {
                HojaRutaParada::whereKey($paradaId)->update(['orden' => $i + 1]);
            }
        });

        return $hoja->refresh();
    }

    /** Llave 1 (R11): jefe de ventas — los pagos de la ruta están OK (R4). */
    public function autorizarPagos(HojaDeRuta $hoja, User $usuario): HojaDeRuta
    {
        return $this->transicionar($hoja, HojaDeRuta::PAGOS_OK, 'pagos_ok', $usuario);
    }

    /** Llave 2 (R11): jefe de despacho — la ruta y su orden están pactados (R3). */
    public function autorizarRuta(HojaDeRuta $hoja, User $usuario): HojaDeRuta
    {
        return $this->transicionar($hoja, HojaDeRuta::RUTA_AUTORIZADA, 'ruta_autorizada', $usuario);
    }

    /** Llave 3 (R11): jefe de bodega — la carga subió al camión. */
    public function autorizarCarga(HojaDeRuta $hoja, User $usuario): HojaDeRuta
    {
        return $this->transicionar($hoja, HojaDeRuta::CARGADA, 'cargada', $usuario);
    }

    /**
     * La salida del camión (la registra bodega, que lo ve partir): la hoja
     * pasa a en_ruta y sus despachos RETIRADOS avanzan a EN_RUTA en la misma
     * transacción — este es el punto que ESCRIBE el estado que la PWA y los
     * scopes ya leían. en_ruta_at es la hora de salida que la guía
     * electrónica del 1-nov-2026 va a exigir (R5). No se bloquea la salida
     * por paradas sin escanear: si el camión partió, partió — la pregunta de
     * si debe bloquearse va a la ronda 2 con Luis.
     */
    public function salirARuta(HojaDeRuta $hoja, User $usuario): HojaDeRuta
    {
        return $this->transicionar($hoja, HojaDeRuta::EN_RUTA, 'en_ruta', $usuario, function (HojaDeRuta $anclada) {
            Despacho::whereIn('id', $anclada->paradas()->pluck('despacho_id'))
                ->where('estado', Despacho::RETIRADO)
                ->update(['estado' => Despacho::EN_RUTA]);
        });
    }

    /**
     * La transición, SIEMPRE bajo lock: re-lee la hoja con la fila bloqueada,
     * exige que el destino sea EXACTAMENTE el paso siguiente del mapa
     * (TRANSICIONES — secuencial estricta, sin saltos) y estampa quién y
     * cuándo. El permiso lo gatea la ruta (una por llave, D-014); acá se
     * protege la SECUENCIA, que es lo que un permiso no puede garantizar.
     *
     * El lock no es asertable en la suite (SQLiteGrammar::compileLock()
     * devuelve ''); su cobertura honesta vive en LockParaMySqlTest. Lo que sí
     * cubren los feature tests es la RE-LECTURA: con instancia stale, el
     * segundo intento se rechaza.
     */
    private function transicionar(HojaDeRuta $hoja, string $destino, string $columna, User $usuario, ?\Closure $dentro = null): HojaDeRuta
    {
        return DB::transaction(function () use ($hoja, $destino, $columna, $usuario, $dentro) {
            $anclada = HojaDeRuta::whereKey($hoja->id)->lockForUpdate()->firstOrFail();

            if (! $anclada->puedeTransicionarA($destino)) {
                throw ValidationException::withMessages([
                    'estado' => "La hoja {$anclada->folio} está {$anclada->estado}: no puede pasar a {$destino}.",
                ]);
            }

            $anclada->update([
                'estado' => $destino,
                "{$columna}_at" => now(),
                "{$columna}_por" => $usuario->id,
            ]);

            if ($dentro) {
                $dentro($anclada);
            }

            return $anclada;
        });
    }

    /** Reusa el despacho del documento o lo crea heredando zona/conductor de la hoja. */
    private function resolverDespacho(DocumentoVenta $documento, HojaDeRuta $hoja): Despacho
    {
        $existente = $documento->despachos()->first();

        if (! $existente) {
            return $this->despachos->crearDesdeDocumento($documento, [
                'zona_id' => $hoja->zona_id,
                'conductor_id' => $hoja->conductor_id,
            ]);
        }

        if ($existente->parada()->exists()) {
            throw ValidationException::withMessages([
                'documentos' => "El documento folio {$documento->folio} ya está en otra hoja de ruta.",
            ]);
        }

        if (in_array($existente->estado, [Despacho::ENTREGADO, Despacho::ENTREGA_PARCIAL], true)) {
            throw ValidationException::withMessages([
                'documentos' => "El despacho del folio {$documento->folio} ya se entregó: no entra a una hoja nueva.",
            ]);
        }

        return $existente;
    }

    /** Normaliza el estado de cobro al catálogo; default fail-safe: se cobra. */
    private function cobroValido(?string $cobro): string
    {
        return in_array($cobro, HojaRutaParada::ESTADOS_COBRO, true)
            ? $cobro
            : HojaRutaParada::COBRO_EN_ENTREGA;
    }
}
