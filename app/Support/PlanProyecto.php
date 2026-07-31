<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Fuente única de la página «Plan del proyecto» (/plan): la carta Gantt
 * transicional que se consulta mientras la app se construye.
 *
 * Doctrina de "estado único": el AVANCE OFICIAL no vive aquí ni en la BD —
 * se PARSEA del tracker §10 de docs/RUTA-MAESTRA.md, el archivo que ya se
 * actualiza en cada push (regla de oro del proyecto). Como `git push origin
 * main` = deploy automático, la página queda al día sola con cada commit;
 * nadie mantiene una segunda copia que pueda driftear (la enfermedad que ya
 * sufrieron la biblia y el propio tracker, ver RUTA §11/R-003).
 *
 * Lo que SÍ vive aquí como datos (misma filosofía que MenuPrincipal): las
 * FECHAS de cada módulo para las barras del Gantt (el tracker no las tiene)
 * y los HITOS re-baselinados. Ajustarlas = editar este archivo = commit =
 * deploy — coherente con "el plan oficial se edita commiteando".
 *
 * Candados en PlanProyectoTest: el parser corre contra el archivo REAL en
 * cada push (la CI se pone roja si el formato de la tabla cambia, ANTES de
 * romper la página en producción) y MODULOS↔tracker deben ser biyectivos
 * (un módulo nuevo en el tracker obliga a agregar su línea de fechas aquí,
 * en el mismo push — así entró M17 al tracker sin que nadie lo notara).
 */
class PlanProyecto
{
    /** Los 3 estados visuales de la página (derivados del %, no editables). */
    public const ESTADOS = ['no_iniciada', 'en_curso', 'finalizada'];

    /** Ventana temporal del Gantt (proyecto re-baselinado, RUTA §2/§11). */
    public const INICIO_PROYECTO = '2026-05-01';

    public const FIN_PROYECTO = '2027-02-28';

    /**
     * Fechas de las barras del Gantt, por ítem del tracker §10. `inicio` =
     * cuándo partió (real) o cuándo debería partir (estimado); `fin` = cuándo
     * debería estar al 100 % (los cerrados llevan su fecha real). Semillas
     * derivadas de la biblia §6 remapeada a los hitos re-baselinados de RUTA
     * §2 + las fechas reales de cierre de RUTA §4; el dueño las ajusta
     * editando este array.
     */
    public const MODULOS = [
        'M01' => ['fase' => 'F1', 'label' => 'M01 Core', 'inicio' => '2026-06-01', 'fin' => '2026-06-10'],
        'M02' => ['fase' => 'F1', 'label' => 'M02 Catálogo+precios', 'inicio' => '2026-06-08', 'fin' => '2026-10-09'],
        'M03' => ['fase' => 'F1', 'label' => 'M03 Clientes', 'inicio' => '2026-06-15', 'fin' => '2026-12-05'],
        'M04' => ['fase' => 'F2', 'label' => 'M04 Inventario', 'inicio' => '2026-09-01', 'fin' => '2026-10-15'],
        'M05' => ['fase' => 'F2', 'label' => 'M05 Ciclo factura', 'inicio' => '2026-07-14', 'fin' => '2026-11-30'],
        'M07' => ['fase' => 'F2', 'label' => 'M07 QR retiro', 'inicio' => '2026-07-14', 'fin' => '2026-09-30'],
        'M08' => ['fase' => 'F2', 'label' => 'M08 Despacho+PWA', 'inicio' => '2026-07-14', 'fin' => '2026-12-05'],
        'M11' => ['fase' => 'F2', 'label' => 'M11 Producción', 'inicio' => '2026-06-20', 'fin' => '2026-12-05'],
        'M12' => ['fase' => 'F2', 'label' => 'M12 Servicio técnico', 'inicio' => '2026-06-10', 'fin' => '2026-11-15'],
        'M13' => ['fase' => 'F2', 'label' => 'M13 Devoluciones', 'inicio' => '2026-10-01', 'fin' => '2026-10-25'],
        'M14' => ['fase' => 'F1', 'label' => 'M14 Aprobaciones', 'inicio' => '2026-07-02', 'fin' => '2026-07-17'],
        'M15' => ['fase' => 'F1', 'label' => 'M15 Notificaciones', 'inicio' => '2026-07-02', 'fin' => '2026-07-08'],
        'M16' => ['fase' => 'F1', 'label' => 'M16 BI', 'inicio' => '2026-07-09', 'fin' => '2026-12-15'],
        'M17' => ['fase' => 'F2', 'label' => 'M17 Servicio en terreno', 'inicio' => '2026-07-15', 'fin' => '2026-07-24'],
        'F3' => ['fase' => 'F3', 'label' => 'F3 Piloto Mirador', 'inicio' => '2026-12-07', 'fin' => '2027-01-11'],
        'F4' => ['fase' => 'F4', 'label' => 'F4 Rollout Abate', 'inicio' => '2027-01-12', 'fin' => '2027-02-09'],
        'F5' => ['fase' => 'F5', 'label' => 'F5 Coquimbo + cierre', 'inicio' => '2027-02-10', 'fin' => '2027-02-28'],
    ];

    /**
     * Hitos re-baselinados (RUTA §2 + lectura ejecutiva §10). `cumplido` se
     * marca a mano al cerrarse (commit = deploy, igual que las fechas).
     */
    public const HITOS = [
        ['key' => "H1'", 'label' => 'Decisiones Sprint 0 cerradas', 'fecha' => '2026-07-31', 'cumplido' => false],
        ['key' => 'H2', 'label' => 'Login operativo', 'fecha' => '2026-06-05', 'cumplido' => true],
        ['key' => "H3'", 'label' => 'Transversales completos', 'fecha' => '2026-10-09', 'cumplido' => false],
        ['key' => "H4'", 'label' => 'Núcleo operativo listo', 'fecha' => '2026-12-05', 'cumplido' => false],
        ['key' => "H5'", 'label' => 'Go-live Mirador sin papel', 'fecha' => '2027-01-11', 'cumplido' => false],
        ['key' => "H6'", 'label' => 'Abate en producción', 'fecha' => '2027-02-09', 'cumplido' => false],
        ['key' => "H7'", 'label' => 'Coquimbo iniciado + cierre', 'fecha' => '2027-02-28', 'cumplido' => false],
    ];

    /** Meses cortos en español, sin depender del locale de la app. */
    private const MESES_CORTOS = [1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    /** TTL del caché de parseo: el archivo solo cambia con un deploy, y la
     *  clave lleva el filemtime → un deploy invalida al instante. */
    private const TTL_PARSE = 3600;

    /** Estado visual derivado del % del tracker — los 3 colores de la página. */
    public static function estadoDe(int $pct): string
    {
        return match (true) {
            $pct <= 0 => 'no_iniciada',
            $pct >= 100 => 'finalizada',
            default => 'en_curso',
        };
    }

    /**
     * El tracker §10 de RUTA-MAESTRA, parseado.
     *
     * @return array{filas: array<string, array<string, mixed>>, total: array{peso: int, aporta: float}|null, pct_global: int}
     */
    public static function tracker(): array
    {
        $ruta = base_path('docs/RUTA-MAESTRA.md');

        return Cache::remember(
            'dg.plan.tracker.'.filemtime($ruta),
            self::TTL_PARSE,
            fn () => self::parsearTracker((string) file_get_contents($ruta))
        );
    }

    /**
     * El semáforo de decisiones (§2 de docs/DECISIONES.md), parseado.
     *
     * @return array<int, array<string, string>>
     */
    public static function decisiones(): array
    {
        $ruta = base_path('docs/DECISIONES.md');

        return Cache::remember(
            'dg.plan.decisiones.'.filemtime($ruta),
            self::TTL_PARSE,
            fn () => self::parsearDecisiones((string) file_get_contents($ruta))
        );
    }

    /**
     * Filas del Gantt listas para la vista: MODULOS (fechas) × tracker (%).
     * `left`/`width` en % del span total del proyecto — la vista los pinta
     * con style inline (Tailwind purga anchos dinámicos, mismo idioma que
     * las mini-barras de _tendencia).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function gantt(): array
    {
        $tracker = self::tracker();
        $inicio = Carbon::parse(self::INICIO_PROYECTO);
        $dias = max((int) $inicio->diffInDays(Carbon::parse(self::FIN_PROYECTO)), 1);

        $filas = [];
        foreach (self::MODULOS as $key => $modulo) {
            $fila = $tracker['filas'][$key] ?? null;
            $pct = $fila['pct'] ?? 0;
            $desde = Carbon::parse($modulo['inicio']);
            $hasta = Carbon::parse($modulo['fin']);

            $filas[$key] = [
                'key' => $key,
                'label' => $fila['item'] ?? $modulo['label'],
                'fase' => $modulo['fase'],
                'peso' => $fila['peso'] ?? null,
                'pct' => $pct,
                'estado' => self::estadoDe($pct),
                'fundamento' => $fila['fundamento'] ?? '',
                'left' => round((int) $inicio->diffInDays($desde) / $dias * 100, 2),
                // Piso de 1.5% para que una unidad corta (M15: 6 días) no
                // desaparezca del dibujo.
                'width' => max(round((int) $desde->diffInDays($hasta) / $dias * 100, 2), 1.5),
            ];
        }

        return $filas;
    }

    /**
     * Etiquetas de mes para el eje del Gantt, con su posición en %.
     *
     * @return array<int, array{label: string, left: float}>
     */
    public static function meses(): array
    {
        $inicio = Carbon::parse(self::INICIO_PROYECTO);
        $fin = Carbon::parse(self::FIN_PROYECTO);
        $dias = max((int) $inicio->diffInDays($fin), 1);

        $meses = [];
        for ($mes = $inicio->copy()->startOfMonth(); $mes->lte($fin); $mes = $mes->copy()->addMonth()) {
            $meses[] = [
                'label' => self::MESES_CORTOS[$mes->month],
                'left' => round(max((int) $inicio->diffInDays($mes, false), 0) / $dias * 100, 2),
            ];
        }

        return $meses;
    }

    /** Posición de "hoy" (día de NEGOCIO, jamás now() UTC) en % del span; null si quedó fuera. */
    public static function hoyPct(): ?float
    {
        $inicio = Carbon::parse(self::INICIO_PROYECTO);
        $dias = max((int) $inicio->diffInDays(Carbon::parse(self::FIN_PROYECTO)), 1);
        $hoy = (int) $inicio->diffInDays(Carbon::parse(FechaNegocio::hoy()), false);

        if ($hoy < 0 || $hoy > $dias) {
            return null;
        }

        return round($hoy / $dias * 100, 2);
    }

    /**
     * Hitos con su countdown contra el día de negocio: cumplido / atrasado /
     * pendiente (+ días de distancia).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function hitos(): array
    {
        $hoy = Carbon::parse(FechaNegocio::hoy());

        return array_map(function (array $hito) use ($hoy) {
            $fecha = Carbon::parse($hito['fecha']);
            $dias = (int) $hoy->diffInDays($fecha, false);

            return array_merge($hito, [
                'carbon' => $fecha,
                'dias' => $dias,
                'estado' => $hito['cumplido'] ? 'cumplido' : ($dias < 0 ? 'atrasado' : 'pendiente'),
            ]);
        }, self::HITOS);
    }

    /**
     * Parser de la tabla §10 (| Ítem | Peso | % | Aporta | Fundamento |).
     * Deliberadamente tolerante: negritas (**) fuera, texto extra tras el
     * número ("15 % (espejo)") tolerado, y el % global se CALCULA de
     * aporta/peso en vez de leer el "≈ NN %" del texto (robusto al redondeo).
     */
    private static function parsearTracker(string $md): array
    {
        $seccion = self::seccion($md, '## 10.');

        $filas = [];
        $total = null;
        foreach (preg_split('/\R/', $seccion) as $linea) {
            $celdas = self::celdas($linea);
            if ($celdas === null || count($celdas) < 5) {
                continue;
            }
            [$item, $peso, $pct, $aporta, $fundamento] = $celdas;

            if ($item === 'TOTAL') {
                $total = ['peso' => (int) $peso, 'aporta' => (float) $aporta];

                continue;
            }
            // Solo las filas de ítems reales (M01…M17, F3…F5); cabecera y
            // separador no matchean y caen solos.
            if (! preg_match('/^(M\d{2}|F\d)\b/u', $item, $m)) {
                continue;
            }

            $filas[$m[1]] = [
                'item' => $item,
                'peso' => (int) $peso,
                'pct' => (int) $pct,
                'aporta' => (float) $aporta,
                'fundamento' => $fundamento === '—' ? '' : $fundamento,
            ];
        }

        return [
            'filas' => $filas,
            'total' => $total,
            'pct_global' => ($total && $total['peso'] > 0)
                ? (int) round($total['aporta'] / $total['peso'] * 100)
                : 0,
        ];
    }

    /** Parser del índice/semáforo §2 (| ID | Título | Estado | Decisor | Bloquea | Límite |). */
    private static function parsearDecisiones(string $md): array
    {
        $seccion = self::seccion($md, '## 2.');

        $decisiones = [];
        foreach (preg_split('/\R/', $seccion) as $linea) {
            $celdas = self::celdas($linea);
            if ($celdas === null || count($celdas) < 6) {
                continue;
            }
            [$id, $titulo, $estadoRaw, $decisor, $bloquea, $limite] = $celdas;

            if (! preg_match('/^D-\d{3}$/', $id)) {
                continue;
            }

            $decisiones[] = [
                'id' => $id,
                'titulo' => $titulo,
                // El orden importa: DESCARTADA antes que TOMADA (no colisionan
                // hoy, pero el match es por substring).
                'estado' => match (true) {
                    str_contains($estadoRaw, 'DESCARTADA') => 'descartada',
                    str_contains($estadoRaw, 'APLAZADA') => 'aplazada',
                    str_contains($estadoRaw, 'ABIERTA') => 'abierta',
                    str_contains($estadoRaw, 'TOMADA'), str_contains($estadoRaw, 'CERRADA') => 'tomada',
                    default => 'abierta',
                },
                // El texto crudo sin el emoji del semáforo (para el tooltip).
                'detalle' => trim((string) preg_replace('/^[^\p{L}\p{N}]+/u', '', $estadoRaw)),
                'decisor' => $decisor,
                'bloquea' => $bloquea === '—' ? '' : $bloquea,
                'limite' => $limite === '—' ? '' : $limite,
            ];
        }

        return $decisiones;
    }

    /** Aísla una sección del markdown: desde su encabezado hasta el próximo '## '. */
    private static function seccion(string $md, string $encabezado): string
    {
        $inicio = strpos($md, $encabezado);
        if ($inicio === false) {
            return '';
        }
        $seccion = substr($md, $inicio);
        $fin = strpos($seccion, "\n## ", strlen($encabezado));

        return $fin === false ? $seccion : substr($seccion, 0, $fin);
    }

    /** Celdas de una fila de tabla markdown, sin negritas; null si no es fila. */
    private static function celdas(string $linea): ?array
    {
        $linea = trim($linea);
        if (! str_starts_with($linea, '|')) {
            return null;
        }

        return array_map(
            fn (string $celda) => trim(str_replace('**', '', $celda)),
            explode('|', trim($linea, '|'))
        );
    }
}
