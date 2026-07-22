<?php

namespace App\Services\Aprobaciones;

use App\Models\Aprobacion;
use App\Models\Configuracion;
use App\Models\ProduccionReporte;
use App\Models\ReglaAprobacion;
use App\Models\User;
use App\Services\Aprobaciones\Acciones\AjusteReporteProduccion;
use App\Services\Notificaciones\NotificacionDispatcher;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Motor de aprobaciones (M14, PLAN-M14 §1.1). Punto unico de entrada del
 * consumidor: solicitar(). Evalua la regla del tipo:
 *
 * - NO matchea (sin regla activa / bajo umbral / el solicitante YA tiene el
 *   rol aprobador) → crea la fila AUTO_APROBADA y aplica el handler INLINE,
 *   en la misma transaccion del mismo request ("Hector 5→1-2 pasos": cero
 *   friccion, con registro historico igual).
 * - matchea → crea PENDIENTE para el rol de la regla y notifica (via M15).
 *
 * aprobar()/rechazar() corren con lockForUpdate + re-check de estado dentro
 * del lock (anti doble-tap, idioma de ProduccionController::aprobar). La
 * aplicacion de la accion y el flip a `aprobada` son ATOMICOS: o queda
 * aprobada Y aplicada, o (ante ConflictoAccionException del handler) queda
 * RECHAZADA automatica con motivo claro — jamas payload obsoleto.
 */
class Aprobaciones
{
    /**
     * tipo_accion => handler de la accion diferida. Los consumidores futuros
     * (M04/M05/M07/M13) agregan aqui su par al integrarse, junto con su tipo
     * en Aprobacion::TIPOS_ACCION y su regla en ReglasAprobacionSeeder.
     */
    public const HANDLERS = [
        Aprobacion::ACCION_AJUSTE_REPORTE => AjusteReporteProduccion::class,
    ];

    /**
     * @param  array<string, mixed>  $datos  payload diferido: {nuevo, anterior, objetivo_updated_at}
     * @param  int|null  $monto  magnitud contra el umbral; NULL bajo regla con
     *                           umbral = PENDIENTE (contrato conservador: sin
     *                           magnitud no se puede probar que esta bajo el umbral)
     */
    public function solicitar(
        string $tipoAccion,
        Model $aprobable,
        User $solicitante,
        string $motivo,
        array $datos,
        ?int $monto = null,
        ?string $descripcion = null,
    ): Aprobacion {
        if (! array_key_exists($tipoAccion, self::HANDLERS)) {
            throw new InvalidArgumentException(
                "Tipo de acción sin handler: [{$tipoAccion}]. Agrégalo a Aprobaciones::HANDLERS y Aprobacion::TIPOS_ACCION.",
            );
        }

        $regla = ReglaAprobacion::activas()->where('tipo_accion', $tipoAccion)->first();
        $requiereHumano = $regla !== null && $this->reglaMatchea($regla, $solicitante, $monto);

        $base = [
            'tipo_accion' => $tipoAccion,
            'regla_id' => $regla?->id,
            'aprobable_type' => $aprobable->getMorphClass(),
            'aprobable_id' => $aprobable->getKey(),
            'solicitante_id' => $solicitante->id,
            'monto' => $monto,
            'motivo' => $motivo,
            'descripcion' => $descripcion ?? Aprobacion::TIPOS_ACCION[$tipoAccion],
            'datos' => $datos,
        ];

        $aprobacion = DB::transaction(function () use ($base, $regla, $solicitante, $requiereHumano) {
            if ($requiereHumano) {
                return Aprobacion::create($base + [
                    'estado' => Aprobacion::ESTADO_PENDIENTE,
                    'rol_aprobador' => $regla->rol_aprobador,
                ]);
            }

            $aprobacion = Aprobacion::create($base + [
                'estado' => Aprobacion::ESTADO_AUTO_APROBADA,
                // Sin humano en el camino: el rol vigente queda como referencia
                // (el de la regla si existe; si no, el flujo nunca fue visible).
                'rol_aprobador' => $regla?->rol_aprobador ?? 'admin',
                'resuelto_por' => $solicitante->id,
                'resuelta_at' => now(),
            ]);

            $this->handler($aprobacion)->aplicar($aprobacion);

            return $aprobacion;
        });

        // Solo las pendientes notifican (las auto-aprobadas serian puro ruido).
        if ($aprobacion->esPendiente()) {
            $this->notificarRol('aprobacion.solicitada', $aprobacion, $aprobacion->rol_aprobador);
        }

        return $aprobacion;
    }

    /**
     * @throws AprobacionYaResueltaException si ya no esta pendiente (doble-tap)
     * @throws AuthorizationException si el aprobador no porta el rol vigente ni es admin
     */
    public function aprobar(Aprobacion $aprobacion, User $aprobador): Aprobacion
    {
        $resuelta = DB::transaction(function () use ($aprobacion, $aprobador) {
            $fresh = $this->bloquearPendiente($aprobacion, $aprobador);

            $fresh->update([
                'estado' => Aprobacion::ESTADO_APROBADA,
                'resuelto_por' => $aprobador->id,
                'resuelta_at' => now(),
            ]);

            try {
                $this->handler($fresh)->aplicar($fresh);
            } catch (ConflictoAccionException $e) {
                // Resolucion deterministica DENTRO de la misma transaccion:
                // jamas aplicar payload obsoleto → rechazo automatico claro.
                $fresh->update([
                    'estado' => Aprobacion::ESTADO_RECHAZADA,
                    'resultado_motivo' => 'Conflicto: '.$e->getMessage(),
                ]);
            }

            return $fresh;
        });

        $this->notificarSolicitante('aprobacion.resuelta', $resuelta);

        return $resuelta;
    }

    /**
     * @throws AprobacionYaResueltaException
     * @throws AuthorizationException
     */
    public function rechazar(Aprobacion $aprobacion, User $aprobador, string $motivo): Aprobacion
    {
        $resuelta = DB::transaction(function () use ($aprobacion, $aprobador, $motivo) {
            $fresh = $this->bloquearPendiente($aprobacion, $aprobador);

            $fresh->update([
                'estado' => Aprobacion::ESTADO_RECHAZADA,
                'resuelto_por' => $aprobador->id,
                'resuelta_at' => now(),
                'resultado_motivo' => $motivo,
            ]);

            return $fresh;
        });

        $this->notificarSolicitante('aprobacion.resuelta', $resuelta);

        return $resuelta;
    }

    /**
     * Barrido del escalamiento (P-M14-04; lo invoca `aprobaciones:escalar`
     * en la grilla de 15 min de I-01 — latencia real N..N+15, limite aceptado).
     * Pendientes nivel 0 mas viejas que N minutos cuya regla activa tenga
     * `rol_escalamiento`: lock + re-check por fila (mismo idioma que
     * notificaciones:reintentar), luego re-notificacion al rol NUEVO.
     *
     * @return int cuantas escalaron en esta corrida
     */
    public function escalarVencidas(): int
    {
        $minutos = (int) Configuracion::get('aprobacion_escala_minutos', 30);

        $candidatas = Aprobacion::query()
            ->where('estado', Aprobacion::ESTADO_PENDIENTE)
            ->where('nivel_escalamiento', 0)
            ->where('created_at', '<=', now()->subMinutes($minutos))
            ->whereHas('regla', fn ($q) => $q->where('activa', true)->whereNotNull('rol_escalamiento'))
            ->pluck('id');

        $escaladas = collect();

        foreach ($candidatas as $id) {
            $aprobacion = DB::transaction(function () use ($id) {
                $fresh = Aprobacion::whereKey($id)->lockForUpdate()->first();

                // Re-check con la fila bloqueada: pudo resolverse o escalar
                // en una corrida concurrente (withoutOverlapping es el
                // cinturon; esto son los tirantes).
                if ($fresh === null || ! $fresh->esPendiente() || $fresh->nivel_escalamiento > 0) {
                    return null;
                }

                $rolNuevo = $fresh->regla?->rol_escalamiento;

                if ($rolNuevo === null) {
                    return null;
                }

                $fresh->update([
                    'nivel_escalamiento' => 1,
                    'rol_aprobador' => $rolNuevo,
                    'escalada_at' => now(),
                ]);

                return $fresh;
            });

            if ($aprobacion !== null) {
                $escaladas->push($aprobacion);
            }
        }

        // Fuera de las transacciones: re-notificacion al rol nuevo.
        foreach ($escaladas as $aprobacion) {
            $this->notificarRol('aprobacion.escalada', $aprobacion, $aprobacion->rol_aprobador);
        }

        return $escaladas->count();
    }

    /**
     * Regla de matching (PLAN-M14 §1.1): requiere aprobacion humana si hay
     * regla activa Y el solicitante NO porta el rol aprobador Y (la regla no
     * tiene umbral, O no hay monto — conservador —, O monto >= umbral).
     */
    private function reglaMatchea(ReglaAprobacion $regla, User $solicitante, ?int $monto): bool
    {
        if ($solicitante->hasRole($regla->rol_aprobador)) {
            return false; // no te auto-solicitas aprobacion
        }

        $umbral = $regla->umbral();

        if ($umbral === null) {
            return true; // regla sin umbral matchea siempre
        }

        if ($monto === null) {
            return true; // contrato conservador: sin magnitud → pendiente
        }

        return $monto >= $umbral;
    }

    /**
     * Lock + re-check de estado + validacion de rol, con la fila bloqueada.
     * La clausula "o admin" es DELIBERADA (PLAN-M14 §1.3): el admin puede
     * resolver cualquier pendiente; queda auditado quien solicito y quien
     * resolvio.
     */
    private function bloquearPendiente(Aprobacion $aprobacion, User $aprobador): Aprobacion
    {
        $fresh = Aprobacion::whereKey($aprobacion->getKey())->lockForUpdate()->firstOrFail();

        if (! $fresh->esPendiente()) {
            throw new AprobacionYaResueltaException('La solicitud ya fue resuelta.');
        }

        if (! $aprobador->hasRole($fresh->rol_aprobador) && ! $aprobador->hasRole('admin')) {
            throw new AuthorizationException('No tienes el rol aprobador de esta solicitud.');
        }

        return $fresh;
    }

    private function handler(Aprobacion $aprobacion): AccionAprobable
    {
        return app(self::HANDLERS[$aprobacion->tipo_accion]);
    }

    /** Notifica a cada usuario del rol (via M15; canal database siempre + mail segun preferencia). */
    private function notificarRol(string $evento, Aprobacion $aprobacion, string $rol): void
    {
        $dispatcher = app(NotificacionDispatcher::class);
        // El destinatario es un APROBADOR: su accion vive en la bandeja.
        $datos = $this->datosNotificacion($aprobacion) + ['url' => route('aprobaciones.index')];

        User::role($rol)->get()->each(function (User $user) use ($dispatcher, $evento, $aprobacion, $datos) {
            $dispatcher->despachar($evento, $aprobacion, $user, $datos);
        });
    }

    private function notificarSolicitante(string $evento, Aprobacion $aprobacion): void
    {
        $solicitante = $aprobacion->solicitante;

        if ($solicitante === null) {
            return;
        }

        app(NotificacionDispatcher::class)->despachar(
            $evento,
            $aprobacion,
            $solicitante,
            // El destinatario es el SOLICITANTE: su superficie es "Mis solicitudes".
            $this->datosNotificacion($aprobacion) + ['url' => route('aprobaciones.mias')],
        );
    }

    /** @return array<string, mixed> placeholders para las plantillas M15 */
    private function datosNotificacion(Aprobacion $aprobacion): array
    {
        return [
            'tipo' => $aprobacion->etiquetaTipo(),
            'descripcion' => $aprobacion->descripcion,
            'solicitante' => $aprobacion->solicitante?->name ?? '—',
            'motivo' => $aprobacion->motivo,
            // Legible para asuntos ("Aprobada: …"); el estado crudo va en la fila.
            'resultado' => ucfirst($aprobacion->estado),
            'resultado_motivo' => $aprobacion->resultado_motivo ?? '',
            // Siempre string: el render de plantillas filtra los no-escalares y
            // un null dejaria el placeholder {magnitud} sin reemplazar.
            'magnitud' => $aprobacion->monto !== null ? number_format($aprobacion->monto, 0, ',', '.') : '—',
            // Contexto para decidir sin entrar a la app (lote NOTIF-1, directiva
            // del dueño 22-07). TODO placeholder nuevo con default '—'.
            'objeto' => $this->describirObjeto($aprobacion),
            'cambio' => $this->describirCambio($aprobacion),
            'resuelto_por' => $aprobacion->resueltoPor?->name ?? '—',
            // Para la escalada: la REGLA conserva el rol original (tras escalar,
            // rol_aprobador de la solicitud ya es el rol nuevo).
            'rol_anterior' => $aprobacion->regla?->rol_aprobador ?? '—',
            // Timestamp absoluto con hora → enChile() (doctrina P-TZ-02).
            'pendiente_desde' => $aprobacion->created_at?->enChile()->format('d-m-Y H:i') ?? '—',
            'minutos' => (string) (int) Configuracion::get('aprobacion_escala_minutos', 30),
        ];
    }

    /** El objeto sobre el que se pide la acción, legible para un humano. */
    private function describirObjeto(Aprobacion $aprobacion): string
    {
        $objeto = $aprobacion->aprobable;

        // Los consumidores futuros (M04/M05) agregan su rama aquí al integrarse.
        return match (true) {
            $objeto instanceof ProduccionReporte => sprintf(
                'Reporte de producción %s · turno %s · %s',
                $objeto->fecha?->format('d-m-Y') ?? '—',
                $objeto->turno ?? '—',
                $objeto->soplador?->name ?? '—',
            ),
            $objeto !== null => class_basename($objeto).' #'.$objeto->getKey(),
            default => '—',
        };
    }

    /** El cambio pedido, pre-formateado ("campo: antes → después · …"). */
    private function describirCambio(Aprobacion $aprobacion): string
    {
        $anterior = $aprobacion->datos['anterior'] ?? [];
        $nuevo = $aprobacion->datos['nuevo'] ?? [];
        $anterior = is_array($anterior) ? $anterior : [];
        $nuevo = is_array($nuevo) ? $nuevo : [];

        // Solo lo que difiere (mismo criterio que el diff de la bandeja);
        // comparación laxa a propósito: '500' y 500 son el mismo valor.
        $cambios = collect($nuevo)
            ->filter(fn ($v, $campo) => is_scalar($v) && ($anterior[$campo] ?? null) != $v)
            ->map(fn ($v, $campo) => ucfirst((string) $campo).': '.(is_scalar($anterior[$campo] ?? null) ? $anterior[$campo] : '—').' → '.$v);

        return $cambios->isNotEmpty() ? $cambios->implode(' · ') : '—';
    }
}
