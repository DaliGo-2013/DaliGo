<?php

namespace App\Services\Produccion;

use App\Models\Molde;
use App\Models\MoldeMantencion;
use App\Models\ProduccionParada;
use App\Models\ProduccionReporte;
use App\Models\User;
use App\Services\Notificaciones\NotificacionDispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * El ciclo de vida del molde (P-M11-12): contador que se alimenta solo al
 * APROBAR un reporte, umbral de mantención con aviso M15 «una vez por
 * cruce» (guard timestamp, patrón aviso_stock_nuevo de M04-F2), correctiva
 * automática desde una parada «Molde dañado», y el registro de mantención
 * que resetea el contador y re-arma el aviso.
 *
 * registrarCiclos() corre DENTRO de la transacción de aprobar(), bajo el
 * MISMO guard del backflush (movimientos()->exists()): devolver jamás
 * resta y re-aprobar jamás re-suma. Los avisos M15 se despachan DESPUÉS
 * del commit (primero el estado, después el correo — doctrina M13).
 */
class Moldes
{
    /** El motivo de parada que gatilla la correctiva automática. */
    public const MOTIVO_CORRECTIVA = 'Molde dañado';

    /**
     * Suma los ciclos del reporte a sus moldes y detecta umbrales cruzados
     * y correctivas por parada. Reparte POR TANDA (un reporte puede mezclar
     * tipos): ciclos de la tanda = unidades / (cavidades_activas ?? 1) —
     * el mismo divisor del rendimiento del OEE.
     *
     * El receptor de cada tanda es el molde ACTIVO de su tipo; con 2+
     * activos decide `reporte.molde_id` (elegido por el jefe al aprobar);
     * sin candidato, la tanda no suma (inferencia honesta: mejor no contar
     * que contarle al molde equivocado).
     *
     * @return array<int, array{evento: string, molde: Molde}> avisos por
     *         despachar DESPUÉS del commit (via despachar()).
     */
    public function registrarCiclos(ProduccionReporte $reporte): array
    {
        $reporte->loadMissing(['registros.tipoBotellon', 'paradas']);

        $tipoIds = $reporte->registros->pluck('tipo_botellon_id')->filter()->unique()->values();
        if ($tipoIds->isEmpty()) {
            return [];
        }

        $activosPorTipo = Molde::activos()->whereIn('tipo_botellon_id', $tipoIds)
            ->get()->groupBy('tipo_botellon_id');

        $divisor = max(1, (int) ($reporte->cavidades_activas ?? 1));
        $ciclosPorMolde = [];   // molde_id => unidades acumuladas
        $moldes = [];           // molde_id => Molde

        foreach ($reporte->registros as $registro) {
            $unidades = (int) $registro->primera + (int) $registro->segunda + (int) $registro->malo + (int) $registro->danada;
            $molde = $this->receptorDeTanda($registro->tipo_botellon_id, $reporte->molde_id, $activosPorTipo);

            if ($unidades === 0 || $molde === null) {
                continue;
            }

            $ciclosPorMolde[$molde->id] = ($ciclosPorMolde[$molde->id] ?? 0) + $unidades;
            $moldes[$molde->id] = $molde;
        }

        $hayMoldeDanado = $reporte->paradas->contains('motivo', self::MOTIVO_CORRECTIVA);
        $avisos = [];

        foreach ($ciclosPorMolde as $moldeId => $unidades) {
            // Lock del molde: dos aprobaciones simultáneas de reportes
            // distintos suman al mismo contador sin pisarse.
            $molde = Molde::whereKey($moldeId)->lockForUpdate()->first();
            $molde->update(['ciclos_acumulados' => $molde->ciclos_acumulados + (int) round($unidades / $divisor)]);

            // Umbral cruzado → UNA vez (guard timestamp; la mantención lo re-arma).
            if ($molde->umbralCruzado() && $molde->aviso_umbral_at === null) {
                $molde->update(['aviso_umbral_at' => now()]);
                $avisos[] = ['evento' => 'molde.umbral_mantencion', 'molde' => $molde];
            }

            // Parada «Molde dañado» → correctiva PENDIENTE, una por (molde,
            // reporte): el firstOrCreate contra el reporte es el guard.
            if ($hayMoldeDanado) {
                $correctiva = MoldeMantencion::firstOrCreate(
                    ['molde_id' => $molde->id, 'reporte_id' => $reporte->id, 'tipo' => MoldeMantencion::TIPO_CORRECTIVA],
                    ['ciclos_al_momento' => $molde->ciclos_acumulados, 'nota' => 'Creada automáticamente por parada «Molde dañado»'],
                );
                if ($correctiva->wasRecentlyCreated) {
                    $avisos[] = ['evento' => 'molde.correctiva_pendiente', 'molde' => $molde];
                }
            }
        }

        return $avisos;
    }

    /**
     * Despacha los avisos acumulados por registrarCiclos() — llamar DESPUÉS
     * del commit. Destinatarios: quienes gestionan producción; try/catch por
     * destinatario (un correo malo no deshace una aprobación — patrón F1/SIC).
     */
    public function despachar(array $avisos): void
    {
        if ($avisos === []) {
            return;
        }

        try {
            $destinatarios = User::permission('manage production')->get()->unique('id')->values();
            $dispatcher = app(NotificacionDispatcher::class);
        } catch (\Throwable $e) {
            Log::warning('Avisos de molde no despachados (setup)', ['error' => $e->getMessage()]);

            return;
        }

        foreach ($avisos as $aviso) {
            $molde = $aviso['molde'];
            $datos = [
                'molde' => $molde->nombre,
                'tipo_botellon' => $molde->tipoBotellon?->nombre ?? '—',
                'ciclos' => number_format($molde->ciclos_acumulados, 0, ',', '.'),
                'umbral' => $molde->umbral_mantencion !== null ? number_format($molde->umbral_mantencion, 0, ',', '.') : '—',
                'url' => route('admin.moldes.show', $molde),
            ];

            foreach ($destinatarios as $destinatario) {
                try {
                    $dispatcher->despachar($aviso['evento'], $molde, $destinatario, $datos);
                } catch (\Throwable $e) {
                    Log::warning('Aviso de molde no despachado', [
                        'molde' => $molde->id,
                        'evento' => $aviso['evento'],
                        'user_id' => $destinatario->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Registra una mantención desde la ficha: completa la correctiva
     * pendiente si el tipo coincide (en vez de duplicar historial) o crea
     * una fila ya realizada; resetea el contador y RE-ARMA el aviso de
     * umbral. El estado del molde no se toca (lo gestiona la ficha).
     */
    public function registrarMantencion(Molde $molde, User $usuario, string $tipo, ?string $nota): MoldeMantencion
    {
        return DB::transaction(function () use ($molde, $usuario, $tipo, $nota) {
            $fresco = Molde::whereKey($molde->id)->lockForUpdate()->firstOrFail();

            $pendiente = $tipo === MoldeMantencion::TIPO_CORRECTIVA
                ? $fresco->mantenciones()->where('tipo', MoldeMantencion::TIPO_CORRECTIVA)->whereNull('realizada_at')->first()
                : null;

            $datos = [
                'user_id' => $usuario->id,
                'user_nombre' => $usuario->name,
                'nota' => $nota ?: ($pendiente->nota ?? null),
                'realizada_at' => now(),
            ];

            $mantencion = $pendiente !== null
                ? tap($pendiente)->update($datos)
                : $fresco->mantenciones()->create($datos + [
                    'tipo' => $tipo,
                    'ciclos_al_momento' => $fresco->ciclos_acumulados,
                ]);

            $fresco->update(['ciclos_acumulados' => 0, 'aviso_umbral_at' => null]);

            return $mantencion;
        });
    }

    /**
     * Moldes candidatos cuando la inferencia por tipo es AMBIGUA en este
     * reporte (algún tipo con 2+ moldes activos): el jefe elige al aprobar.
     */
    public function candidatosAmbiguos(ProduccionReporte $reporte): Collection
    {
        $reporte->loadMissing('registros');

        $tipoIds = $reporte->registros->pluck('tipo_botellon_id')->filter()->unique()->values();
        if ($tipoIds->isEmpty()) {
            return collect();
        }

        return Molde::activos()->whereIn('tipo_botellon_id', $tipoIds)
            ->orderBy('nombre')
            ->get()
            ->groupBy('tipo_botellon_id')
            ->filter(fn (Collection $candidatos) => $candidatos->count() > 1)
            ->flatten()
            ->values();
    }

    /** El molde que recibe los ciclos de una tanda (o null: inferencia honesta). */
    private function receptorDeTanda(?int $tipoId, ?int $moldeElegidoId, Collection $activosPorTipo): ?Molde
    {
        if ($tipoId === null) {
            return null;
        }

        $candidatos = $activosPorTipo->get($tipoId, collect());

        if ($candidatos->count() === 1) {
            return $candidatos->first();
        }

        if ($candidatos->count() > 1 && $moldeElegidoId !== null) {
            return $candidatos->firstWhere('id', $moldeElegidoId);
        }

        return null;
    }
}
