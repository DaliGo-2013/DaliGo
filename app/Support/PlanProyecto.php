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
     * Datos curados por ítem del tracker §10 — como las fechas de los hitos:
     * viven en el repo, editar = commit = deploy.
     * - `inicio`/`fin`: la ventana de la barra del Gantt (cuándo partió o
     *   debería partir; cuándo debería estar al 100 % — los cerrados llevan
     *   su fecha real). Semillas de la biblia §6 remapeada a los hitos
     *   re-baselinados de RUTA §2 + fechas reales de cierre de RUTA §4.
     * - `hecho`/`falta`: los bullets del panel de detalle (click en la fila).
     *   Son la capa NARRATIVA que el tracker no tiene; el % y el fundamento
     *   siguen saliendo AUTO del tracker. Al cerrar o abrir un frente de un
     *   módulo, actualizar sus bullets en el mismo push que el tracker.
     */
    public const MODULOS = [
        'M01' => ['fase' => 'F1', 'label' => 'M01 Core', 'inicio' => '2026-06-01', 'fin' => '2026-06-10',
            'hecho' => ['Autenticación completa (dominio @impdali.cl, sin registro público)', 'Usuarios y roles con permisos editables desde la UI', 'Sucursales (multi-sucursal, 4 sembradas)', 'Configuración del sistema', 'Auditoría de cambios (quién/qué/cuándo)'],
            'falta' => []],
        'M02' => ['fase' => 'F1', 'label' => 'M02 Catálogo+precios', 'inicio' => '2026-06-08', 'fin' => '2026-10-09',
            'hecho' => ['Sync de catálogo desde Bsale (~2.850 productos, cron horario)', 'Sync de listas de precios (lista oficial GENERAL como fuente única)', 'Import/export CSV de peso y dimensiones'],
            'falta' => ['Webhooks de Bsale (hoy solo polling horario)', 'Enlace con el módulo de inventario (M04)']],
        'M03' => ['fase' => 'F1', 'label' => 'M03 Clientes', 'inicio' => '2026-06-15', 'fin' => '2026-12-05',
            'hecho' => ['CRUD de clientes + sync de ~47.800 desde Bsale', 'Vendedor por cartera (gestión por vendedor)'],
            'falta' => ['Boleta rápida (es parte de M05)', 'Historial de compras (post-M05)']],
        'M04' => ['fase' => 'F2', 'label' => 'M04 Inventario', 'inicio' => '2026-09-01', 'fin' => '2026-10-15',
            'hecho' => ['Espejo de bodegas y stock desde Bsale (solo lectura, sync horaria)'],
            'falta' => ['Módulo real: mapping bodega↔sucursal (espera la decisión D-003)', 'Reservas por vendedor', 'Transferencias entre bodegas']],
        'M05' => ['fase' => 'F2', 'label' => 'M05 Ciclo factura', 'inicio' => '2026-07-14', 'fin' => '2026-11-30',
            'hecho' => ['Andamiaje DTE completo y probado: puerto emisor Bsale, servicios, candados anti-duplicado'],
            'falta' => ['Emitir un documento real: mapas de configuración vacíos, emisión deshabilitada, sin ruta de emisión ni comando de prueba', 'Boleta rápida', 'Autorización de Gerencia para la primera emisión']],
        'M07' => ['fase' => 'F2', 'label' => 'M07 QR retiro', 'inicio' => '2026-07-14', 'fin' => '2026-09-30',
            'hecho' => ['QR firmado de retiro en producción', 'Validación en el puesto de bodega', 'Doble-retiro cerrado (lock + candado)'],
            'falta' => ['Caso «retirar carga ajena» (decisión de producto pendiente)', 'QA de bodega con papel impreso']],
        'M08' => ['fase' => 'F2', 'label' => 'M08 Despacho+PWA', 'inicio' => '2026-07-14', 'fin' => '2026-12-05',
            'hecho' => ['Lado bodega en producción: cola de despacho, escaneo, entrega con firma/foto/parcial'],
            'falta' => ['PWA del conductor (codificada en rama sin mergear; se rehace desde main)', 'Prueba en campo con un conductor real']],
        'M11' => ['fase' => 'F2', 'label' => 'M11 Producción', 'inicio' => '2026-06-20', 'fin' => '2026-12-05',
            'hecho' => ['Ciclo completo: asignar → tandas del soplador → enviar → aprobar', 'Kardex local al aprobar (consumo de preforma + producción + merma)', 'Cola offline del soplador (PWA, sin señal en planta)', 'Panel del jefe con drill-downs e historial'],
            'falta' => ['Descuento de preforma contra el stock real', 'Meta del día', 'Gráficos de productividad (GP)']],
        'M12' => ['fase' => 'F2', 'label' => 'M12 Servicio técnico', 'inicio' => '2026-06-10', 'fin' => '2026-11-15',
            'hecho' => ['Taller completo (recepción → diagnóstico → cotización → reparación → entrega)', 'Portal público QR para que el cliente ingrese su equipo', 'Cotización al cliente por correo con respuesta ACEPTO/NO ACEPTO', 'Ingreso por lote del conductor en ruta', 'Informes de dispensadores e industrial', 'Aviso al cliente y a ventas al cerrar sin solución'],
            'falta' => ['Alertas de mantención 3/6/12 meses', 'Sugerencia automática de repuestos', 'Cobro (depende de M05)']],
        'M13' => ['fase' => 'F2', 'label' => 'M13 Devoluciones', 'inicio' => '2026-10-01', 'fin' => '2026-10-25',
            'hecho' => [],
            'falta' => ['Todo el módulo (especificado en la biblia, sin código)']],
        'M14' => ['fase' => 'F1', 'label' => 'M14 Aprobaciones', 'inicio' => '2026-07-02', 'fin' => '2026-07-17',
            'hecho' => ['Motor polimórfico de aprobaciones (auto-aprueba si ninguna regla matchea)', 'Bandeja móvil del aprobador + escalamiento automático', 'Historial admin con filtros', 'QA del dueño 8/8 en producción'],
            'falta' => ['Cablear más acciones al motor (hoy solo el ajuste de producción)']],
        'M15' => ['fase' => 'F1', 'label' => 'M15 Notificaciones', 'inicio' => '2026-07-02', 'fin' => '2026-07-08',
            'hecho' => ['Motor multi-canal con plantillas editables y reintentos', 'Correo operativo con entregabilidad verificada (SPF/DKIM/DMARC)', 'Campanita in-app + bandeja personal + preferencias por usuario', 'Panel admin de notificaciones'],
            'falta' => ['Canal WhatsApp real (hoy es un stub; decisión D-007 aplazada)']],
        'M16' => ['fase' => 'F1', 'label' => 'M16 BI', 'inicio' => '2026-07-09', 'fin' => '2026-12-15',
            'hecho' => ['Tablero «Pulso del día»: excepciones + producción + taller', 'Accesos personalizables por usuario (íconos y colores)', 'Esta página /plan'],
            'falta' => ['BI de reportes de venta/facturación (depende de M05)']],
        'M17' => ['fase' => 'F2', 'label' => 'M17 Servicio en terreno', 'inicio' => '2026-07-15', 'fin' => '2026-07-24',
            'hecho' => ['Agenda de terreno (calendario + lista, multi-día, franjas de 2h)', 'Confirmación del cliente a la cita por correo', 'Registro de instalaciones', 'Conductores y servicios de terreno'],
            'falta' => ['Cierre fino del ciclo en terreno (cobro y reportes, con M05)']],
        'F3' => ['fase' => 'F3', 'label' => 'F3 Piloto Mirador', 'inicio' => '2026-12-07', 'fin' => '2027-01-11',
            'hecho' => [],
            'falta' => ['Hardening (carga, seguridad, respaldos)', 'Migración de datos + peso/dimensiones por SKU', 'Capacitación (Pedro, Ricardo, sopladores)', 'Marcha blanca de diciembre']],
        'F4' => ['fase' => 'F4', 'label' => 'F4 Rollout Abate', 'inicio' => '2027-01-12', 'fin' => '2027-02-09',
            'hecho' => [],
            'falta' => ['Configuración y migración de Abate Molina', 'Capacitación y go-live']],
        'F5' => ['fase' => 'F5', 'label' => 'F5 Coquimbo + cierre', 'inicio' => '2027-02-10', 'fin' => '2027-02-28',
            'hecho' => [],
            'falta' => ['Configuración de Coquimbo (producción de botellones)', 'Deuda técnica y documentación final', 'Traspaso a soporte + retrospectiva']],
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
     * Los BLOQUES EXTRA del repo: unidades de RUTA-MAESTRA con nombre CON
     * GUIÓN (E-NAV, E-TZ, E-PLAN…) — trabajo fuera del plan oficial que ya
     * viaja agrupado «en bloques con sentido» y con pasos [x]/[ ] marcados
     * en cada push. Las unidades oficiales son numeradas (E1…E13) y mapean
     * a módulos que ya están en el Gantt, así que quedan fuera. Pedido del
     * dueño 31-07: auto-ingresar estos bloques al apartado de extras.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function bloquesExtra(): array
    {
        $ruta = base_path('docs/RUTA-MAESTRA.md');

        return Cache::remember(
            'dg.plan.bloques.'.filemtime($ruta),
            self::TTL_PARSE,
            fn () => self::parsearBloquesExtra((string) file_get_contents($ruta))
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
                'hecho' => $modulo['hecho'] ?? [],
                'falta' => $modulo['falta'] ?? [],
                'desde' => $desde,
                'hasta' => $hasta,
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

    /**
     * Parser de los bloques extra. Un bloque = sección `### E-XXX · Título`
     * cuyo key lleva GUIÓN y que contiene al menos un paso `- [x]`/`- [ ]`
     * a columna 0 (los sub-bullets indentados no son pasos). Doble guarda:
     * las R-00N de §11 no tienen pasos, y `E1 · M15` no lleva guión;
     * cinturón: un título con token M\d\d se descarta igual.
     */
    private static function parsearBloquesExtra(string $md): array
    {
        $bloques = [];
        // Particionar por encabezados ###: el chunk 2k+1 es el título, el
        // 2k+2 su cuerpo hasta el próximo encabezado (## o ###).
        $partes = preg_split('/^### (.+)$/m', $md, -1, PREG_SPLIT_DELIM_CAPTURE);

        for ($i = 1; $i < count($partes); $i += 2) {
            $encabezado = $partes[$i];
            $cuerpo = $partes[$i + 1] ?? '';
            // El cuerpo termina donde arranca la próxima sección ## (el split
            // solo corta en ###).
            if (($fin = strpos($cuerpo, "\n## ")) !== false) {
                $cuerpo = substr($cuerpo, 0, $fin);
            }

            if (! preg_match('/^(E-[A-Z0-9]+) · (.+)$/u', $encabezado, $m)) {
                continue;
            }
            if (preg_match('/\bM\d{2}\b/', $encabezado)) {
                continue; // cinturón: un E-xx que nombre un módulo oficial no es extra
            }

            [$hechos, $pendientes] = [[], []];
            foreach (preg_split('/\R/', $cuerpo) as $linea) {
                if (! preg_match('/^- \[( |x)\] (.+)$/u', $linea, $p)) {
                    continue;
                }
                $texto = self::resumirPaso($p[2]);
                $p[1] === 'x' ? $hechos[] = $texto : $pendientes[] = $texto;
            }

            if ($hechos === [] && $pendientes === []) {
                continue; // sección sin pasos (ej. R-00N si algún día llevara E-)
            }

            $total = count($hechos) + count($pendientes);
            $bloques[$m[1]] = [
                'key' => $m[1],
                'titulo' => self::limpiarTituloBloque($m[2]),
                'pasos_hechos' => $hechos,
                'pasos_pendientes' => $pendientes,
                'hechos' => count($hechos),
                'total' => $total,
                'pct' => (int) round(count($hechos) / $total * 100),
                // Estado desde los CONTEOS, no del pct (un 99.6% redondea a
                // 100 sin estar terminado).
                'estado' => $pendientes === [] ? 'finalizada'
                    : ($hechos === [] ? 'no_iniciada' : 'en_curso'),
            ];
        }

        return $bloques;
    }

    /** Título del bloque legible: sin backticks, cortado antes del ruido operativo. */
    private static function limpiarTituloBloque(string $titulo): string
    {
        $titulo = str_replace('`', '', $titulo);
        foreach (['(rama', '(plan:', '— ✅'] as $corte) {
            if (($pos = strpos($titulo, $corte)) !== false) {
                $titulo = substr($titulo, 0, $pos);
            }
        }

        return rtrim(trim($titulo), '—- ');
    }

    /** Resumen de un paso para el panel: etiqueta P-XXX-nn + arranque del texto. */
    private static function resumirPaso(string $paso): string
    {
        $paso = str_replace(['**', '`'], '', $paso);
        if (mb_strlen($paso) > 140) {
            $corte = mb_substr($paso, 0, 140);
            // Retroceder al último espacio para no partir una palabra.
            if (($esp = mb_strrpos($corte, ' ')) !== false && $esp > 80) {
                $corte = mb_substr($corte, 0, $esp);
            }
            $paso = rtrim($corte, ' ·,;:').'…';
        }

        return $paso;
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
