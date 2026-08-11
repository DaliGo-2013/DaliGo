<?php

namespace App\Services\Produccion;

use App\Models\Configuracion;
use App\Models\ProduccionCorte;
use App\Models\ProduccionReporte;
use App\Models\User;
use App\Services\Notificaciones\NotificacionDispatcher;
use App\Support\FechaNegocio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Corte SIC de produccion (P-M11-21): cada 2 horas proyecta linealmente la
 * produccion del turno vs la meta asignada y, bajo umbral, avisa al jefe por
 * M15. La informacion PERSIGUE al jefe en vez de esperarlo (PLAN-M11-FINAL
 * §4-F2, patron SIC del benchmark).
 *
 * La UNIDAD del corte es el REPORTE (asignacion activa del dia, no enviada):
 * la meta solo existe ahi (`asignadas`); una meta POR MAQUINA no existe en el
 * esquema (las asignaciones son por soplador). Las maquinas del turno viajan
 * como contexto en el aviso.
 *
 * Todo el reloj es de NEGOCIO (FechaNegocio, hora chilena): el scheduler corre
 * en UTC y este service decide por condicion, no por cadencia (patron de la
 * casa). El slot persistido es UTC (Chile tiene DST: una "hora bonita" chilena
 * seria ambigua el dia del cambio).
 */
class CorteSic
{
    /** % proyectado bajo el cual la meta se considera en riesgo (config). */
    public const UMBRAL_DEFAULT = 85;

    /**
     * Horarios de turno por defecto — HIPOTESIS editable en Configuracion
     * (patron D-003: la respuesta del dueño es un ajuste de datos, no codigo).
     * El turno noche cruza medianoche a proposito (inicio > fin).
     */
    public const TURNOS_DEFAULT = [
        'dia' => ['inicio' => '08:00', 'fin' => '20:00'],
        'noche' => ['inicio' => '20:00', 'fin' => '08:00'],
    ];

    /**
     * Minutos minimos de turno transcurrido para proyectar: con menos, la
     * proyeccion lineal es ruido (y este guard es el que evita dividir por
     * cero en el primer corte del turno).
     */
    public const MINUTOS_MINIMOS = 60;

    // Semaforo del panel vivo (variantes = paleta ESTRICTA de 4, ver variante()).
    public const SEMAFORO_AL_DIA = 'al_dia';

    public const SEMAFORO_EN_RIESGO = 'en_riesgo';

    public const SEMAFORO_CRITICO = 'critico';

    public function __construct(private NotificacionDispatcher $dispatcher)
    {
    }

    /**
     * Turnos ACTIVOS en este instante chileno, con sus minutos transcurridos,
     * duracion total y la FECHA del reporte objetivo. Intervalos semiabiertos
     * [inicio, fin) en minutos de pared; un turno que cruza medianoche y aun
     * no termina arranco AYER de negocio — esa es la fecha con la que el jefe
     * asigno (el panel vivo usa este MISMO helper: a las 00:30 debe mostrar el
     * reporte de ayer-noche, no una pantalla vacia).
     *
     * @return array<string, array{minutos_transcurridos: int, minutos_turno: int, fecha: string}>
     */
    public function turnosActivos(?Carbon $ahora = null): array
    {
        $ahora ??= FechaNegocio::ahora();
        $ahoraMin = $ahora->hour * 60 + $ahora->minute;

        $turnos = Configuracion::get('produccion_turnos', self::TURNOS_DEFAULT);

        if (! is_array($turnos) || $turnos === []) {
            Log::warning('produccion_turnos invalido en Configuracion; se usa la hipotesis por defecto.');
            $turnos = self::TURNOS_DEFAULT;
        }

        $activos = [];

        foreach ($turnos as $turno => $horario) {
            $inicio = $this->minutosDe($horario['inicio'] ?? null);
            $fin = $this->minutosDe($horario['fin'] ?? null);

            // Un turno malformado se salta con aviso: jamas tumba el cron.
            if ($inicio === null || $fin === null || $inicio === $fin) {
                Log::warning('Turno de produccion malformado en produccion_turnos; se salta.', ['turno' => $turno]);

                continue;
            }

            $cruzaMedianoche = $inicio > $fin;
            $activo = $cruzaMedianoche
                ? ($ahoraMin >= $inicio || $ahoraMin < $fin)
                : ($ahoraMin >= $inicio && $ahoraMin < $fin);

            if (! $activo) {
                continue;
            }

            $activos[(string) $turno] = [
                'minutos_transcurridos' => (($ahoraMin - $inicio) + 1440) % 1440,
                'minutos_turno' => (($fin - $inicio) + 1440) % 1440,
                'fecha' => ($cruzaMedianoche && $ahoraMin < $fin)
                    ? $ahora->copy()->subDay()->toDateString()
                    : $ahora->toDateString(),
            ];
        }

        return $activos;
    }

    /**
     * Proyeccion lineal del turno como % de la meta, en enteros (sin floats).
     * Usa `producido` (1a+2a, la metrica vendible de armarResumen) — usar el
     * total premiaria la merma. Clamp a 999: con 60 min corridos y un turno de
     * 720 el factor es 12x y un smallint no aguanta porcentajes absurdos.
     */
    public function proyeccionPct(int $producido, int $asignadas, int $minutosTranscurridos, int $minutosTurno): int
    {
        if ($asignadas <= 0 || $minutosTranscurridos <= 0) {
            return 0;
        }

        return (int) min(999, round($producido * $minutosTurno / $minutosTranscurridos / $asignadas * 100));
    }

    /**
     * Semaforo del panel vivo. Neutral SOLO cuando no hay proyeccion que leer
     * (turno recien arrancado o reporte cerrado): 0 producido con horas
     * corridas ES riesgo (si el panel dijera "al dia" mientras la campanita
     * grita, uno de los dos miente).
     */
    public function semaforo(?int $proyeccion, int $umbral, int $racha): string
    {
        if ($proyeccion === null) {
            return self::SEMAFORO_AL_DIA;
        }

        if ($proyeccion >= $umbral) {
            return self::SEMAFORO_AL_DIA;
        }

        return $racha >= 2 ? self::SEMAFORO_CRITICO : self::SEMAFORO_EN_RIESGO;
    }

    /**
     * Variante de <x-badge> del semaforo. Paleta ESTRICTA de 4 (CLAUDE.md):
     * el verde no existe en esta app — al dia = neutro (reposo), en riesgo =
     * naranjo de marca (requiere atencion), critico = rojo (negativo
     * declarado: dos cortes seguidos sin recuperarse). Mismo criterio que
     * Vehiculo::variante() y su candado de paleta.
     */
    public static function variante(string $semaforo): string
    {
        return match ($semaforo) {
            self::SEMAFORO_CRITICO => 'danger',
            self::SEMAFORO_EN_RIESGO => 'brand',
            default => 'neutral',
        };
    }

    /** Umbral vigente (config editable; piso 1 para no dividir el sentido). */
    public function umbral(): int
    {
        return max(1, (int) Configuracion::get('produccion_umbral_proyeccion', self::UMBRAL_DEFAULT));
    }

    /**
     * Ejecuta el corte sobre todos los turnos activos. Devuelve las filas para
     * la tabla del comando. Con $seco no escribe ni notifica.
     *
     * @return list<array{0: string, 1: string, 2: int, 3: int, 4: string, 5: string}>
     */
    public function ejecutar(bool $seco = false): array
    {
        $ahora = FechaNegocio::ahora();
        $umbral = $this->umbral();
        // startOfHour del instante real: la grilla I-01 dispara en el minuto
        // :00 exacto y withoutOverlapping SALTA (no difiere), asi que el slot
        // es determinista. UTC a proposito (DST chileno).
        $slot = Carbon::now('UTC')->startOfHour();

        $destinatarios = User::permission('manage production')->get()->unique('id')->values();

        $filas = [];

        foreach ($this->turnosActivos($ahora) as $turno => $info) {
            if ($info['minutos_transcurridos'] < self::MINUTOS_MINIMOS) {
                continue;
            }

            $reportes = ProduccionReporte::whereDate('fecha', $info['fecha'])
                ->where('turno', $turno)
                // Enviados/aprobados quedan FUERA: captura cerrada (el jefe ya
                // los tiene en su cola); devueltos siguen produciendo.
                ->whereIn('estado', [ProduccionReporte::BORRADOR, ProduccionReporte::DEVUELTO])
                ->where('asignadas', '>', 0)
                ->with([
                    'soplador',
                    'paradas' => fn ($q) => $q->whereNull('fin'),
                    'paradas.maquina',
                    'registros.maquina',
                ])
                ->get();

            foreach ($reportes as $reporte) {
                $proyeccion = $this->proyeccionPct(
                    $reporte->producido,
                    $reporte->asignadas,
                    $info['minutos_transcurridos'],
                    $info['minutos_turno'],
                );

                $bajo = $proyeccion < $umbral;
                $racha = $bajo ? ProduccionCorte::rachaDe($reporte->id, $slot) : 0;
                $avisar = $bajo && $racha < 2;
                $urgente = $bajo && $racha === 1;

                $accion = match (true) {
                    ! $bajo => 'al dia',
                    $urgente => 'aviso URGENTE',
                    $avisar => 'aviso',
                    default => 'silencio (ya avisado 2 veces)',
                };

                if (! $seco) {
                    // Reclamar el slot ANTES de notificar (patron vehiculo_avisos):
                    // una re-corrida del mismo slot encuentra la fila y NO
                    // vuelve a avisar. El unique de la tabla es la red final.
                    $corte = ProduccionCorte::firstOrCreate(
                        ['reporte_id' => $reporte->id, 'corte_slot' => $slot],
                        ['bajo_umbral' => $bajo, 'proyeccion' => max(0, $proyeccion), 'avisado' => $avisar, 'urgente' => $urgente],
                    );

                    if (! $corte->wasRecentlyCreated) {
                        $accion = 'ya cortado en este slot';
                        $avisar = false;
                    }

                    if ($avisar) {
                        $this->avisar($reporte, (string) $turno, $proyeccion, $urgente, $destinatarios);
                    }
                }

                $filas[] = [
                    $reporte->soplador?->name ?? '—',
                    (string) $turno,
                    $reporte->producido,
                    $reporte->asignadas,
                    $proyeccion.'%',
                    $accion,
                ];
            }
        }

        return $filas;
    }

    /**
     * Despacha `produccion.meta_en_riesgo` a quienes gestionan produccion.
     * Fuera de transaccion y con try/catch por destinatario: un correo mal
     * configurado no deja sin aviso a los demas ni tumba el cron.
     *
     * @param  Collection<int, User>  $destinatarios
     */
    private function avisar(ProduccionReporte $reporte, string $turno, int $proyeccion, bool $urgente, Collection $destinatarios): void
    {
        $maquinas = $reporte->registros
            ->map(fn ($registro) => $registro->maquina?->nombre)
            ->filter()
            ->unique()
            ->implode(' · ');

        $paradasAbiertas = $reporte->paradas
            ->map(function ($parada) {
                $maquina = $parada->maquina ? ' ('.$parada->maquina->nombre.')' : '';

                return '· '.$parada->motivo.$maquina.' — abierta desde las '.$parada->inicio_corta;
            })
            ->implode("\n");

        $datos = [
            // Placeholder SIEMPRE presente: '' en el primer aviso, prefijo en
            // el segundo (no existe flag urgente en M15; el patron de la casa
            // es el asunto con ⚠ MAYUSCULAS, como '⚠ VENCIDO' de vehiculos).
            'urgencia' => $urgente ? '⚠ URGENTE: ' : '',
            'soplador' => $reporte->soplador?->name ?? 'Sin soplador',
            'turno' => $turno,
            'producido' => $reporte->producido,
            'meta' => $reporte->asignadas,
            'proyeccion' => $proyeccion,
            'maquinas' => $maquinas !== '' ? $maquinas : '—',
            'paradas_abiertas' => $paradasAbiertas !== '' ? $paradasAbiertas : '—',
            'url' => route('admin.produccion.reporte.show', $reporte),
        ];

        foreach ($destinatarios as $destinatario) {
            try {
                $this->dispatcher->despachar('produccion.meta_en_riesgo', $reporte, $destinatario, $datos);
            } catch (Throwable $e) {
                Log::warning('Corte SIC: aviso de meta en riesgo no despachado', [
                    'reporte_id' => $reporte->id,
                    'user_id' => $destinatario->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /** 'HH:MM' -> minutos del dia; null si no calza el formato. */
    private function minutosDe(mixed $hora): ?int
    {
        if (! is_string($hora) || preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $hora, $m) !== 1) {
            return null;
        }

        return ((int) $m[1]) * 60 + (int) $m[2];
    }
}
