<?php

namespace App\Services\Devoluciones;

use App\Models\Aprobacion;
use App\Models\Cliente;
use App\Models\Devolucion;
use App\Models\DevolucionItem;
use App\Models\DevolucionMovimiento;
use App\Models\User;
use App\Services\Aprobaciones\Aprobaciones;
use App\Services\Notificaciones\NotificacionDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Devoluciones (M13, flujo A-12): registrar (público) → recibir (bodega) →
 * evaluar (categorización + reglas por tipo y origen) → resolver (reembolso
 * vía M14 / reingreso al kardex LOCAL / rechazo).
 *
 * Contratos que este servicio respeta:
 * - El kardex local JAMÁS escribe `stocks`/`bodegas` (espejo de Bsale).
 * - El reembolso pasa SIEMPRE por Aprobaciones::solicitar() con `monto` no
 *   nulo (un null bajo regla con umbral cae a pendiente siempre) — y es el
 *   MOTOR quien aplica el efecto, nunca este servicio.
 * - Los avisos van FUERA de las transacciones y nunca tumban el flujo
 *   (patrón AgendaTrabajo: try/catch + report).
 */
class Devoluciones
{
    // Quién se entera de una devolución declarada vive en AudienciasNotificacion
    // (editable por el dueño en Configuración → Avisos).

    /**
     * Registra la devolución que un cliente declara desde el link público.
     * Solo la transacción de BD: las fotos (filesystem, no transaccional) y
     * el aviso los maneja el llamador DESPUÉS del commit.
     *
     * @param  array<string, mixed>  $datos
     */
    public function registrar(array $datos): Devolucion
    {
        return DB::transaction(function () use ($datos) {
            // Enlace BLANDO al espejo M03: si el RUT matchea, se enlaza la
            // ficha; jamás se crea ni se edita (la sync de Bsale es la dueña).
            $rut = Cliente::normalizarRut($datos['cliente_rut'] ?? null);
            $cliente = $rut !== null ? Cliente::buscarPorRut($rut) : null;

            $devolucion = Devolucion::create([
                'folio' => Devolucion::generarFolioUnico(),
                'token' => Str::random(64),
                'estado' => Devolucion::SOLICITADA,
                'canal' => $datos['canal'],
                'cliente_id' => $cliente?->id,
                'cliente_rut' => $rut,
                'cliente_nombre' => $datos['cliente_nombre'],
                'cliente_email' => $datos['cliente_email'],
                'cliente_telefono' => $datos['cliente_telefono'] ?? null,
                'folio_referencia' => $datos['folio_referencia'] ?? null,
                'motivo' => $datos['motivo'],
                'sucursal_id' => $datos['sucursal_id'],
                'ip' => $datos['ip'] ?? null,
                'user_agent' => $datos['user_agent'] ?? null,
            ]);

            $devolucion->items()->create([
                'descripcion' => $datos['producto'],
                'cantidad' => (int) $datos['cantidad'],
            ]);

            return $devolucion;
        });
    }

    /**
     * Aviso interno post-commit. Nunca tumba el flujo público (el cliente ya
     * tiene su folio): cada fallo se reporta y se sigue.
     */
    public function avisarSolicitada(Devolucion $devolucion): void
    {
        try {
            $dispatcher = app(NotificacionDispatcher::class);
            $datos = $this->datosPlantilla($devolucion) + [
                'url' => route('admin.devoluciones.show', $devolucion->id),
            ];

            \App\Support\AudienciasNotificacion::destinatarios('devolucion.solicitada')
                ->each(fn (User $u) => $dispatcher->despachar('devolucion.solicitada', $devolucion, $u, $datos));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Bodega recibe físicamente la devolución. Lock + guard de estado (dos
     * pestañas abiertas no la reciben dos veces). Las fotos de bodega las
     * guarda el llamador después; el aviso al cliente sale al final.
     */
    public function recibir(Devolucion $devolucion, User $user): Devolucion
    {
        $fresca = DB::transaction(function () use ($devolucion, $user) {
            $fresca = Devolucion::whereKey($devolucion->id)->lockForUpdate()->firstOrFail();

            if ($fresca->estado !== Devolucion::SOLICITADA) {
                throw new InvalidArgumentException("La devolución {$fresca->folio} ya fue recibida.");
            }

            $fresca->update([
                'estado' => Devolucion::RECIBIDA,
                'recibida_at' => now(),
                'recibida_por' => $user->id,
            ]);

            return $fresca;
        });

        $this->avisarCliente($fresca, 'devolucion.recibida');

        return $fresca;
    }

    /**
     * Categorización (P-M13-02) con las reglas automáticas por tipo y origen:
     * - causa `transporte` EXIGE transportista + N° de seguimiento (es lo que
     *   hace posible el reclamo; el conductor propio es opcional).
     * - el estado del producto por ítem decide su destino: `apto` reingresa,
     *   `danado` va a merma (se aplica al resolver).
     *
     * @param  array<string, mixed>  $datos  {causa, transportista?, seguimiento?, conductor_id?, items: [id => estado_producto]}
     */
    public function evaluar(Devolucion $devolucion, User $user, array $datos): Devolucion
    {
        return DB::transaction(function () use ($devolucion, $datos) {
            $fresca = Devolucion::whereKey($devolucion->id)->lockForUpdate()->firstOrFail();

            if (! in_array($fresca->estado, [Devolucion::RECIBIDA, Devolucion::EVALUADA], true)) {
                throw new InvalidArgumentException("La devolución {$fresca->folio} no está en estado de evaluarse ({$fresca->estado}).");
            }

            if ($datos['causa'] === 'transporte'
                && (blank($datos['transportista'] ?? null) || blank($datos['seguimiento'] ?? null))) {
                throw new InvalidArgumentException('Un daño de transporte exige transportista y N° de seguimiento (sin eso no hay reclamo posible).');
            }

            $fresca->update([
                'estado' => Devolucion::EVALUADA,
                'causa' => $datos['causa'],
                'transportista' => $datos['transportista'] ?? null,
                'seguimiento' => $datos['seguimiento'] ?? null,
                'conductor_id' => $datos['conductor_id'] ?? null,
            ]);

            foreach ($datos['items'] ?? [] as $itemId => $estadoProducto) {
                $fresca->items()->whereKey($itemId)->update(['estado_producto' => $estadoProducto]);
            }

            return $fresca;
        });
    }

    /**
     * Resolución final. Tres salidas:
     * - `reembolso`: SIEMPRE por el motor M14 — bajo el umbral se auto-aprueba
     *   y el handler aplica INLINE; sobre él queda pendiente y la devolución
     *   NO cambia hasta que la aprueben. Devuelve la Aprobacion.
     * - `reingreso`: exige al menos un ítem apto; escribe los movimientos del
     *   kardex LOCAL (apto → reingreso, danado → merma) y marca REINGRESADA.
     * - `rechazo`: marca RECHAZADA con motivo.
     */
    public function resolver(Devolucion $devolucion, User $user, string $salida, array $datos): Aprobacion|Devolucion
    {
        if ($salida === 'reembolso') {
            return $this->resolverReembolso($devolucion, $user, $datos);
        }

        $fresca = DB::transaction(function () use ($devolucion, $user, $salida, $datos) {
            $fresca = Devolucion::with('items')->whereKey($devolucion->id)->lockForUpdate()->firstOrFail();

            if ($fresca->estado !== Devolucion::EVALUADA) {
                throw new InvalidArgumentException("La devolución {$fresca->folio} debe estar evaluada antes de resolverse ({$fresca->estado}).");
            }

            if ($salida === 'reingreso') {
                $this->registrarMovimientos($fresca);
                $estado = Devolucion::REINGRESADA;
            } elseif ($salida === 'rechazo') {
                $estado = Devolucion::RECHAZADA;
            } else {
                throw new InvalidArgumentException("Salida desconocida: [{$salida}].");
            }

            $fresca->update([
                'estado' => $estado,
                'resolucion_motivo' => $datos['resolucion_motivo'] ?? null,
                'resuelta_at' => now(),
                'resuelta_por' => $user->id,
            ]);

            return $fresca;
        });

        $this->avisarCliente($fresca, 'devolucion.resuelta');

        return $fresca;
    }

    /**
     * Aviso al CLIENTE (destinatario externo → solo mail, lo decide el
     * dispatcher). Público a propósito: también lo llama el handler M14
     * cuando un reembolso diferido por umbral finalmente se aprueba.
     */
    public function avisarCliente(Devolucion $devolucion, string $evento): void
    {
        try {
            app(NotificacionDispatcher::class)->despachar(
                $evento,
                $devolucion,
                $devolucion->cliente_email,
                $this->datosPlantilla($devolucion),
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function resolverReembolso(Devolucion $devolucion, User $user, array $datos): Aprobacion
    {
        $fresca = Devolucion::whereKey($devolucion->id)->firstOrFail();

        if ($fresca->estado !== Devolucion::EVALUADA) {
            throw new InvalidArgumentException("La devolución {$fresca->folio} debe estar evaluada antes de resolverse ({$fresca->estado}).");
        }

        $monto = (int) round((float) $datos['monto_reembolso']);

        if ($monto <= 0) {
            throw new InvalidArgumentException('El monto del reembolso debe ser mayor que cero.');
        }

        // El motor decide: bajo el umbral el handler aplica INLINE en esta
        // misma request; sobre él queda pendiente (la devolución no cambia).
        // `monto` jamás va null: bajo regla con umbral, null = pendiente siempre.
        $aprobacion = app(Aprobaciones::class)->solicitar(
            tipoAccion: Aprobacion::ACCION_DEVOLUCION_REEMBOLSO,
            aprobable: $fresca,
            solicitante: $user,
            motivo: $datos['resolucion_motivo'] ?? 'Reembolso de devolución',
            datos: [
                'nuevo' => [
                    'monto_reembolso' => $monto,
                    'resolucion_motivo' => $datos['resolucion_motivo'] ?? null,
                    'resuelta_por' => $user->id,
                ],
                'anterior' => ['monto_reembolso' => 0],
                'objetivo_updated_at' => $fresca->updated_at?->toJSON(),
            ],
            monto: $monto,
            descripcion: "Reembolso devolución {$fresca->folio} de {$fresca->cliente_nombre}",
        );

        // El aviso al cliente NO sale de aquí: lo dispara el handler
        // (ReembolsoDevolucion::aplicar), que corre en AMBOS caminos — inline
        // cuando se auto-aprueba y diferido cuando el jefe aprueba después.
        // Avisar también acá duplicaría el correo del camino inline.
        return $aprobacion;
    }

    /**
     * Los movimientos del kardex LOCAL: apto → reingreso (a la bodega
     * parametrizada — D-003 sigue abierta), danado → merma. Un ítem
     * `incompleto` no genera movimiento (queda a criterio en v1).
     * NUNCA toca `stocks`/`bodegas`.
     */
    private function registrarMovimientos(Devolucion $devolucion): void
    {
        $aptos = $devolucion->items->where('estado_producto', DevolucionItem::APTO);

        if ($aptos->isEmpty()) {
            throw new InvalidArgumentException('Ningún ítem está apto para reingreso: corresponde merma (rechazo) o reembolso.');
        }

        $bodega = (string) \App\Models\Configuracion::get('devolucion_bodega_reingreso', 'CONTENEDORES');

        foreach ($devolucion->items as $item) {
            $tipo = match ($item->estado_producto) {
                DevolucionItem::APTO => DevolucionMovimiento::REINGRESO,
                DevolucionItem::DANADO => DevolucionMovimiento::MERMA,
                default => null,
            };

            if ($tipo === null) {
                continue;
            }

            $devolucion->movimientos()->create([
                'devolucion_item_id' => $item->id,
                'producto_id' => $item->producto_id,
                'cantidad' => $item->cantidad,
                'tipo' => $tipo,
                'bodega_destino' => $tipo === DevolucionMovimiento::REINGRESO ? $bodega : null,
                'observacion' => Str::limit($item->descripcion, 180),
            ]);
        }
    }

    /**
     * Placeholders de las plantillas M15. TODO placeholder con default '—':
     * el render filtra los no-escalares y un null dejaría el {placeholder}
     * literal en el texto (contrato del dispatcher).
     *
     * @return array<string, string>
     */
    private function datosPlantilla(Devolucion $devolucion): array
    {
        $item = $devolucion->items->first();

        return [
            'folio' => $devolucion->folio,
            'cliente' => $devolucion->cliente_nombre ?: '—',
            'canal' => Devolucion::CANALES[$devolucion->canal] ?? $devolucion->canal,
            'producto' => $item ? ($item->cantidad.'× '.$item->descripcion) : '—',
            'motivo' => $devolucion->motivo ?: '—',
            'resultado' => match ($devolucion->estado) {
                Devolucion::REEMBOLSADA => 'Reembolso aprobado',
                Devolucion::REINGRESADA => 'Producto recibido conforme',
                Devolucion::RECHAZADA => 'Rechazada',
                default => 'En revisión',
            },
            'detalle' => $devolucion->resolucion_motivo ?: '—',
        ];
    }
}
