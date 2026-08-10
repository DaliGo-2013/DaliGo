<?php

namespace App\Services\Produccion;

use App\Models\Configuracion;
use App\Models\Maquina;
use App\Models\ProduccionParada;
use App\Models\ProduccionRegistro;
use App\Models\Receta;
use Illuminate\Support\Collection;

/**
 * OEE por máquina (P-M11-11, PLAN-M11-FINAL F2): Disponibilidad ×
 * Rendimiento × Calidad sobre un rango de fechas, + Pareto de paradas.
 *
 * Reglas (dictado v42 + doctrina OEE):
 *  - DISPONIBILIDAD: tiempo planificado = turnos trabajados × duración del
 *    turno (`produccion_minutos_turno`, Configuración — la duración NO
 *    existía como dato en el código, hipótesis [B] editable) − paradas NO
 *    planificadas. Las PLANIFICADAS (mantención, cambio de molde) viven
 *    DENTRO del tiempo planificado y no descuentan. La duración de cada
 *    parada la da `ProduccionParada::duracion_minutos` (módulo 1440 de
 *    Max-2: 23:40→06:30 = 410 min) — solo LECTURA, frontera respetada.
 *    Paradas sin cerrar (fin NULL) no aportan duración.
 *  - RENDIMIENTO: tiempo ideal del output real / tiempo disponible, donde
 *    el ideal por unidad = `recetas.ciclo_ideal_seg` del botellón ÷ las
 *    cavidades activas del reporte (NULL = todas → factor 1). Algún tipo
 *    del período SIN ciclo cargado → el factor se DECLARA como faltante
 *    (jamás un 100 % falso). Si el cálculo pasa de 100 %, es señal de un
 *    ciclo ideal mal cargado: se muestra el aviso, nunca la cifra.
 *  - CALIDAD: buenos / (buenos + merma), desde las tandas.
 *  - OEE = D × R × Q solo cuando los tres factores existen.
 *
 * Los informes agregan TODAS las tandas del rango (mismo criterio que los
 * informes de producción existentes, que no filtran por estado del reporte).
 */
class Oee
{
    /** Minutos del turno (día o noche) según Configuración; 720 = 12 h. */
    public static function minutosTurno(): int
    {
        return max(1, (int) Configuracion::get('produccion_minutos_turno', 720));
    }

    /**
     * Los tres factores + insumos de una máquina en el rango.
     *
     * @return array{
     *   slots: int, minutosTurno: int, tiempoPlanificado: int,
     *   minutosNoPlanificadas: int, minutosPlanificadas: int,
     *   disponibilidad: float|null, rendimiento: float|null, calidad: float|null,
     *   oee: float|null, sinCiclo: array<int, string>, cicloSospechoso: bool,
     *   producido: int, merma: int, mermaPct: float|null, scrap: int, scrapPct: float|null
     * }
     */
    public function paraMaquina(Maquina $maquina, string $desde, string $hasta): array
    {
        $minutosTurno = static::minutosTurno();

        // Tandas de la máquina en el rango, con el contexto del reporte
        // (fecha/turno para los slots; cavidades para el rendimiento).
        $registros = ProduccionRegistro::query()
            ->with('tipoBotellon')
            ->join('produccion_reportes', 'produccion_reportes.id', '=', 'produccion_registros.reporte_id')
            ->whereDate('produccion_reportes.fecha', '>=', $desde)
            ->whereDate('produccion_reportes.fecha', '<=', $hasta)
            ->where('produccion_registros.maquina_id', $maquina->id)
            ->get([
                'produccion_registros.*',
                'produccion_reportes.fecha AS reporte_fecha',
                'produccion_reportes.turno AS reporte_turno',
                'produccion_reportes.cavidades_activas AS reporte_cavidades',
            ]);

        $paradas = $this->paradasDelRango($desde, $hasta, $maquina->id);

        // Turnos TRABAJADOS por la máquina: aparece en tandas o en paradas.
        $slots = $registros->map(fn ($r) => $r->reporte_fecha.'|'.$r->reporte_turno)
            ->merge($paradas->map(fn ($p) => $p->reporte_fecha.'|'.$p->reporte_turno))
            ->unique()->count();

        // --- Disponibilidad ---
        $minutosNoPlanificadas = (int) $paradas
            ->where('clase', ProduccionParada::CLASE_NO_PLANIFICADA)
            ->sum(fn ($p) => $p->duracion_minutos ?? 0);
        $minutosPlanificadas = (int) $paradas
            ->where('clase', ProduccionParada::CLASE_PLANIFICADA)
            ->sum(fn ($p) => $p->duracion_minutos ?? 0);

        $tiempoPlanificado = $slots * $minutosTurno;
        $tiempoDisponible = max(0, $tiempoPlanificado - $minutosNoPlanificadas);
        $disponibilidad = $tiempoPlanificado > 0
            ? round($tiempoDisponible / $tiempoPlanificado * 100, 1)
            : null;

        // --- Rendimiento ---
        $ciclos = Receta::where('rol', Receta::ROL_PREFORMA)
            ->whereIn('producto_id', $registros->map(fn ($r) => $r->tipoBotellon?->producto_id)->filter()->unique()->values())
            ->pluck('ciclo_ideal_seg', 'producto_id');

        $tiempoIdealSeg = 0.0;
        $sinCiclo = [];

        foreach ($registros as $registro) {
            $unidades = (int) $registro->primera + (int) $registro->segunda + (int) $registro->malo + (int) $registro->danada;
            if ($unidades === 0) {
                continue;
            }

            $productoId = $registro->tipoBotellon?->producto_id;
            $ciclo = $productoId ? $ciclos->get($productoId) : null;

            if ($ciclo === null) {
                $sinCiclo[] = $registro->tipoBotellon?->nombre ?? 'Sin tipo';

                continue;
            }

            $tiempoIdealSeg += $unidades * (int) $ciclo / max(1, (int) ($registro->reporte_cavidades ?? 1));
        }

        $sinCiclo = array_values(array_unique($sinCiclo));
        $cicloSospechoso = false;
        $rendimiento = null;

        if ($sinCiclo === [] && $tiempoDisponible > 0 && $tiempoIdealSeg > 0) {
            $pct = round($tiempoIdealSeg / 60 / $tiempoDisponible * 100, 1);
            if ($pct > 100) {
                // Señal de ciclo ideal mal cargado: se avisa, no se muestra
                // una cifra imposible (candado 3 del dictado).
                $cicloSospechoso = true;
            } else {
                $rendimiento = $pct;
            }
        }

        // --- Calidad (+ scrap de arranque separado) ---
        $producido = (int) $registros->sum(fn ($r) => (int) $r->primera + (int) $r->segunda);
        $merma = (int) $registros->sum(fn ($r) => (int) $r->malo + (int) $r->danada);
        $scrap = (int) $registros
            ->where('motivo_malo', 'Scrap de arranque')
            ->sum(fn ($r) => (int) $r->malo);

        $totalUnidades = $producido + $merma;
        $calidad = $totalUnidades > 0 ? round($producido / $totalUnidades * 100, 1) : null;
        $mermaPct = $totalUnidades > 0 ? round($merma / $totalUnidades * 100, 1) : null;
        $scrapPct = $merma > 0 ? round($scrap / $merma * 100, 1) : null;

        $oee = ($disponibilidad !== null && $rendimiento !== null && $calidad !== null)
            ? round($disponibilidad * $rendimiento * $calidad / 10000, 1)
            : null;

        return [
            'slots' => $slots,
            'minutosTurno' => $minutosTurno,
            'tiempoPlanificado' => $tiempoPlanificado,
            'minutosNoPlanificadas' => $minutosNoPlanificadas,
            'minutosPlanificadas' => $minutosPlanificadas,
            'disponibilidad' => $disponibilidad,
            'rendimiento' => $rendimiento,
            'calidad' => $calidad,
            'oee' => $oee,
            'sinCiclo' => $sinCiclo,
            'cicloSospechoso' => $cicloSospechoso,
            'producido' => $producido,
            'merma' => $merma,
            'mermaPct' => $mermaPct,
            'scrap' => $scrap,
            'scrapPct' => $scrapPct,
        ];
    }

    /**
     * OEE de todas las máquinas con actividad en el rango (para la vista
     * comparativa del panel), cada fila con su máquina y su target.
     */
    public function porMaquina(string $desde, string $hasta): Collection
    {
        $conTandas = ProduccionRegistro::query()
            ->join('produccion_reportes', 'produccion_reportes.id', '=', 'produccion_registros.reporte_id')
            ->whereDate('produccion_reportes.fecha', '>=', $desde)
            ->whereDate('produccion_reportes.fecha', '<=', $hasta)
            ->whereNotNull('produccion_registros.maquina_id')
            ->distinct()->pluck('produccion_registros.maquina_id');

        $conParadas = $this->paradasDelRango($desde, $hasta)->pluck('maquina_id')->filter();

        return Maquina::with('sucursal')
            ->whereIn('id', $conTandas->merge($conParadas)->unique()->values())
            ->orderBy('nombre')
            ->get()
            ->map(fn (Maquina $maquina) => ['maquina' => $maquina] + $this->paraMaquina($maquina, $desde, $hasta));
    }

    /**
     * Pareto de paradas del rango (opcionalmente de una máquina): por motivo,
     * minutos acumulados + eventos + % y % acumulado, ordenado por minutos.
     * Solo paradas CERRADAS (una abierta no tiene duración todavía); la suma
     * de las filas ES la suma de las paradas del período (candado 4).
     *
     * @return array{motivos: array<int, array{motivo: string, clase: string, minutos: int, eventos: int, pct: float, pctAcum: float}>, totalMinutos: int, totalEventos: int}
     */
    public function pareto(string $desde, string $hasta, ?int $maquinaId = null): array
    {
        $paradas = $this->paradasDelRango($desde, $hasta, $maquinaId);

        $motivos = $paradas
            ->groupBy('motivo')
            ->map(fn (Collection $grupo, string $motivo) => [
                'motivo' => $motivo,
                'clase' => ProduccionParada::claseDe($motivo),
                'minutos' => (int) $grupo->sum(fn ($p) => $p->duracion_minutos ?? 0),
                'eventos' => $grupo->count(),
            ])
            ->sortByDesc('minutos')
            ->values();

        $totalMinutos = (int) $motivos->sum('minutos');
        $acumulado = 0;

        $motivos = $motivos->map(function (array $fila) use ($totalMinutos, &$acumulado) {
            $acumulado += $fila['minutos'];
            $fila['pct'] = $totalMinutos > 0 ? round($fila['minutos'] / $totalMinutos * 100, 1) : 0.0;
            $fila['pctAcum'] = $totalMinutos > 0 ? round($acumulado / $totalMinutos * 100, 1) : 0.0;

            return $fila;
        })->all();

        return [
            'motivos' => $motivos,
            'totalMinutos' => $totalMinutos,
            'totalEventos' => $paradas->count(),
        ];
    }

    /**
     * Paradas CERRADAS del rango (por la fecha del reporte), hidratadas como
     * ProduccionParada (el accessor duracion_minutos de Max-2 sigue operando)
     * con fecha/turno del reporte a bordo.
     */
    private function paradasDelRango(string $desde, string $hasta, ?int $maquinaId = null): Collection
    {
        return ProduccionParada::query()
            ->join('produccion_reportes', 'produccion_reportes.id', '=', 'produccion_paradas.reporte_id')
            ->whereDate('produccion_reportes.fecha', '>=', $desde)
            ->whereDate('produccion_reportes.fecha', '<=', $hasta)
            ->when($maquinaId !== null, fn ($q) => $q->where('produccion_paradas.maquina_id', $maquinaId))
            ->whereNotNull('produccion_paradas.fin')
            ->get([
                'produccion_paradas.*',
                'produccion_reportes.fecha AS reporte_fecha',
                'produccion_reportes.turno AS reporte_turno',
            ]);
    }
}
