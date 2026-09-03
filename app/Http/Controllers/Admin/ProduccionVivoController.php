<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProduccionCorte;
use App\Models\ProduccionReporte;
use App\Services\Produccion\CorteSic;
use App\Support\FechaNegocio;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Panel «Hoy en vivo» del jefe de produccion (P-M11-21): por reporte del
 * turno activo — avance vs meta, proyeccion lineal, semaforo y paradas
 * abiertas con su duracion corriendo (server-side). Se refresca solo por
 * POLLING de la firma (patron de la cola de bodega): un monitor encendido
 * todo el dia pide un JSON chico y solo recarga si el contenido CAMBIO.
 *
 * Controller propio a proposito: ProduccionController es archivo caliente de
 * frontera con el stream A (aprobar/backflush) y ya es grande.
 */
class ProduccionVivoController extends Controller
{
    public function __construct(private CorteSic $corte)
    {
    }

    public function vivo(): View
    {
        [$filas, $porMaquina, $hayTurnoActivo] = $this->armarPanel();

        return view('admin.produccion.vivo', [
            'filas' => $filas,
            'porMaquina' => $porMaquina,
            'hayTurnoActivo' => $hayTurnoActivo,
            'umbral' => $this->corte->umbral(),
            // La MISMA funcion que el endpoint del poll: si divergieran, el
            // monitor recargaria en loop (o nunca) — candado de la cola.
            'firma' => $this->firma($filas, $porMaquina),
        ]);
    }

    /** JSON chico para el poll: total + firma del contenido. */
    public function conteo(): JsonResponse
    {
        [$filas, $porMaquina] = $this->armarPanel();

        return response()->json([
            'total' => count($filas),
            'firma' => $this->firma($filas, $porMaquina),
        ]);
    }

    /**
     * Huella del CONTENIDO del panel. Incluye los minutos corriendo de las
     * paradas abiertas A PROPOSITO: con una parada abierta la firma cambia
     * cada minuto y el poll recarga (=<60 s), lo que mantiene honesto el
     * «lleva X min» calculado en el servidor; sin paradas abiertas la firma
     * es estable y el monitor no recarga nada.
     */
    private function firma(array $filas, array $porMaquina): string
    {
        return md5(json_encode([$filas, $porMaquina]));
    }

    /**
     * Filas del panel: reportes de los turnos ACTIVOS ahora (mismo helper de
     * fechas que el corte SIC: a las 00:30 el turno noche arranco AYER de
     * negocio y el panel debe mostrarlo, no una pantalla vacia).
     *
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>, 2: bool}
     */
    private function armarPanel(): array
    {
        $turnos = $this->corte->turnosActivos();

        if ($turnos === []) {
            return [[], [], false];
        }

        $umbral = $this->corte->umbral();
        $ahoraPared = FechaNegocio::ahora()->format('H:i');
        // La racha ACTUAL incluye el ultimo corte registrado (rachaDe cuenta
        // los estrictamente anteriores al instante dado).
        $desdeAhora = Carbon::now('UTC')->addSecond();

        $reportes = ProduccionReporte::query()
            ->where(function ($query) use ($turnos) {
                foreach ($turnos as $turno => $info) {
                    $query->orWhere(fn ($q) => $q->whereDate('fecha', $info['fecha'])->where('turno', $turno));
                }
            })
            ->with([
                'soplador',
                'paradas' => fn ($q) => $q->whereNull('fin'),
                'paradas.maquina',
                'registros' => fn ($q) => $q->latest('id'),
                'registros.maquina',
            ])
            ->orderBy('id')
            ->get();

        $filas = [];

        foreach ($reportes as $reporte) {
            $info = $turnos[$reporte->turno] ?? null;
            $abierto = $reporte->editablePorSoplador(); // borrador | devuelto
            $conProyeccion = $abierto
                && $info !== null
                && $info['minutos_transcurridos'] >= CorteSic::MINUTOS_MINIMOS
                && $reporte->asignadas > 0;

            $proyeccion = $conProyeccion
                ? $this->corte->proyeccionPct(
                    $reporte->producido,
                    $reporte->asignadas,
                    $info['minutos_transcurridos'],
                    $info['minutos_turno'],
                )
                : null;

            $racha = ($proyeccion !== null && $proyeccion < $umbral)
                ? ProduccionCorte::rachaDe($reporte->id, $desdeAhora)
                : 0;

            $semaforo = $this->corte->semaforo($proyeccion, $umbral, $racha);

            $resumen = ProduccionReporte::armarResumen(
                $reporte->primera,
                $reporte->segunda,
                $reporte->malo,
                $reporte->danada,
                $reporte->asignadas,
            );

            $ultimaTanda = $reporte->registros->first();

            $filas[] = [
                'id' => $reporte->id,
                'soplador' => $reporte->soplador?->name ?? '—',
                'turno' => $reporte->turno,
                'estado' => $reporte->estado,
                'abierto' => $abierto,
                'producido' => $resumen['producido'],
                'meta' => $resumen['asignadas'],
                'avance' => $resumen['avance'],
                'proyeccion' => $proyeccion,
                'semaforo' => $semaforo,
                'variante' => CorteSic::variante($semaforo),
                'maquinas' => $reporte->registros
                    ->map(fn ($registro) => $registro->maquina?->nombre)
                    ->filter()->unique()->implode(' · '),
                'ultima_tanda' => $ultimaTanda?->created_at->enChile()->format('H:i'),
                'paradas' => $reporte->paradas
                    ->map(fn ($parada) => [
                        'motivo' => $parada->motivo,
                        // La colación no tiene máquina (detiene al operario): que no salga con hueco.
                        'maquina' => $parada->maquina?->nombre ?? 'Operario',
                        'inicio' => $parada->inicio_corta,
                        'lleva' => \App\Models\ProduccionParada::labelDe($parada->duracionMinutosHasta($ahoraPared)),
                    ])
                    ->values()
                    ->all(),
            ];
        }

        return [$filas, $this->porMaquina($turnos), true];
    }

    /**
     * Lo que SI existe por maquina (la meta no: las asignaciones son por
     * soplador): produccion del turno segun tandas + paradas abiertas.
     *
     * @param  array<string, array{minutos_transcurridos: int, minutos_turno: int, fecha: string}>  $turnos
     * @return list<array<string, mixed>>
     */
    private function porMaquina(array $turnos): array
    {
        $reporteIds = ProduccionReporte::query()
            ->where(function ($query) use ($turnos) {
                foreach ($turnos as $turno => $info) {
                    $query->orWhere(fn ($q) => $q->whereDate('fecha', $info['fecha'])->where('turno', $turno));
                }
            })
            ->pluck('id');

        if ($reporteIds->isEmpty()) {
            return [];
        }

        $produccion = \App\Models\ProduccionRegistro::query()
            ->whereIn('reporte_id', $reporteIds)
            ->leftJoin('maquinas', 'maquinas.id', '=', 'produccion_registros.maquina_id')
            ->groupBy('produccion_registros.maquina_id', 'maquinas.nombre')
            ->selectRaw('produccion_registros.maquina_id AS maquina_id, maquinas.nombre AS maquina, SUM(produccion_registros.primera + produccion_registros.segunda) AS producido, SUM(produccion_registros.malo + produccion_registros.danada) AS merma')
            ->orderByRaw('maquinas.nombre IS NULL, maquinas.nombre')
            ->get();

        $paradasAbiertas = \App\Models\ProduccionParada::query()
            ->whereIn('reporte_id', $reporteIds)
            ->whereNull('fin')
            ->selectRaw('maquina_id, COUNT(*) AS abiertas')
            ->groupBy('maquina_id')
            ->pluck('abiertas', 'maquina_id');

        return $produccion
            ->map(fn ($fila) => [
                'maquina' => $fila->maquina ?? 'Sin máquina',
                'producido' => (int) $fila->producido,
                'merma' => (int) $fila->merma,
                'paradas_abiertas' => (int) ($paradasAbiertas[$fila->maquina_id] ?? 0),
            ])
            ->values()
            ->all();
    }
}
