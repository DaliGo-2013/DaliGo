<?php

namespace App\Http\Controllers;

use App\Models\Aprobacion;
use App\Models\Notificacion;
use App\Models\OrdenServicio;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionReporte;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Página de Inicio (M16-v1 «Pulso del día», PLAN-M16-V1 opción A):
 *  ① Excepciones — SOLO lo que se desvía, con su edad y su destino (andon:
 *    lo normal se ve quieto). Si no hay nada: «Operación al día».
 *  ② Pulso — cómo viene el día/la semana: producción como medida directa
 *    (producido vs asignado + merma con su referencia + serie 7 días) y
 *    taller con antigüedad y flujo semanal.
 *  ③ Zócalo — los accesos directos, compactos, al final.
 * Se gatea por PERMISO (no por rol); un member sin permisos ve solo el saludo.
 * Solo lectura: queries agregadas, sin N+1; fechas SIEMPRE con whereDate y
 * límites calculados en PHP (portable SQLite/MySQL 5.7 — sin DATEDIFF).
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        // Día de NEGOCIO (hora chilena), no día UTC: P-TZ-01 — de noche el
        // "hoy" UTC ya es mañana y el tablero se quedaba en ceros.
        $hoy = \App\Support\FechaNegocio::hoy();

        // ── ① Excepciones: cada ítem = señal + edad del más viejo + destino ──
        $excepciones = [];
        $puedeVerExcepciones = false;

        if ($user->can('manage production')) {
            $puedeVerExcepciones = true;

            $porAprobar = ProduccionReporte::pendientes()->count();
            if ($porAprobar > 0) {
                // El más viejo por enviado_at (fallback fecha para históricos).
                $masViejo = ProduccionReporte::pendientes()->min('enviado_at')
                    ?? ProduccionReporte::pendientes()->min('fecha');
                $excepciones[] = [
                    'label' => 'Reportes por aprobar',
                    'cantidad' => $porAprobar,
                    'edad' => $this->edad($masViejo),
                    'href' => route('admin.produccion.index'),
                ];
            }

            $devueltos = ProduccionReporte::where('estado', ProduccionReporte::DEVUELTO)->count();
            if ($devueltos > 0) {
                $excepciones[] = [
                    'label' => 'Reportes devueltos sin corregir',
                    'cantidad' => $devueltos,
                    'edad' => null,
                    'href' => route('admin.produccion.index'),
                ];
            }

            $atrasados = ProduccionAsignacion::whereDate('fecha', $hoy)
                ->where(function ($q) {
                    $q->doesntHave('reporte')
                        ->orWhereHas('reporte', fn ($r) => $r->where('estado', ProduccionReporte::BORRADOR));
                })
                ->count();
            if ($atrasados > 0) {
                $excepciones[] = [
                    'label' => 'Asignaciones de hoy sin reporte',
                    'cantidad' => $atrasados,
                    'edad' => null,
                    'href' => route('admin.produccion.index'),
                ];
            }
        }

        if ($user->can('confirmar servicio tecnico')
            && $user->canAny(['view servicio tecnico', 'manage servicio tecnico'])) {
            $puedeVerExcepciones = true;

            $porConfirmar = OrdenServicio::porConfirmar()->count();
            if ($porConfirmar > 0) {
                $excepciones[] = [
                    'label' => 'Recepciones por confirmar',
                    'cantidad' => $porConfirmar,
                    'edad' => $this->edad(OrdenServicio::porConfirmar()->min('created_at')),
                    'href' => route('admin.servicio-tecnico.index'),
                ];
            }
        }

        if ($user->can('aprobar solicitudes')) {
            $puedeVerExcepciones = true;

            // Espejo de la bandeja: el número = lo que verá al hacer click.
            $bandeja = fn () => Aprobacion::where('estado', Aprobacion::ESTADO_PENDIENTE)
                ->when(! $user->hasRole('admin'), fn ($q) => $q->whereIn('rol_aprobador', $user->getRoleNames()));
            $pendientes = $bandeja()->count();
            if ($pendientes > 0) {
                $excepciones[] = [
                    'label' => 'Aprobaciones pendientes',
                    'cantidad' => $pendientes,
                    'edad' => $this->edad($bandeja()->min('created_at')),
                    'href' => route('aprobaciones.index'),
                ];
            }
        }

        if ($user->can('view notificaciones')) {
            $puedeVerExcepciones = true;

            // Solo las TERMINALES (agotaron reintentos): las que aún reintentan
            // se resuelven solas y no ameritan interrumpir a nadie.
            $fallidas = Notificacion::where('estado', Notificacion::FALLIDA)
                ->whereNull('programada_para')
                ->count();
            if ($fallidas > 0) {
                $excepciones[] = [
                    'label' => 'Notificaciones caídas (sin reintento)',
                    'cantidad' => $fallidas,
                    'edad' => null,
                    'href' => route('admin.notificaciones.index', ['estado' => Notificacion::FALLIDA]),
                ];
            }
        }

        // ── ② Pulso: producción (medida directa + referencia + serie 7d) ──
        $pulsoProduccion = null;

        if ($user->can('manage production')) {
            $sumas = ProduccionReporte::delDia($hoy)
                ->selectRaw('COALESCE(SUM(primera),0) as p1, COALESCE(SUM(segunda),0) as p2, COALESCE(SUM(malo),0) as mal, COALESCE(SUM(danada),0) as dan')
                ->first();
            $resumen = ProduccionReporte::armarResumen(
                (int) $sumas->p1, (int) $sumas->p2, (int) $sumas->mal, (int) $sumas->dan,
                (int) ProduccionAsignacion::whereDate('fecha', $hoy)->sum('asignadas'),
            );

            // Serie de 7 días (incluye hoy) para las mini-barras, con ceros.
            $desde7 = \App\Support\FechaNegocio::ahora()->subDays(6)->toDateString();
            $porDia = ProduccionReporte::seriePorDia($desde7, $hoy);
            $serie = [];
            for ($cursor = Carbon::parse($desde7); $cursor->toDateString() <= $hoy; $cursor->addDay()) {
                $fila = $porDia->get($cursor->toDateString());
                $serie[] = [
                    'fecha' => $cursor->toDateString(),
                    'producido' => $fila ? (int) $fila->p1 + (int) $fila->p2 : 0,
                ];
            }
            $max = max(1, max(array_column($serie, 'producido')));
            foreach ($serie as &$dia) {
                $dia['pct'] = (int) round($dia['producido'] / $max * 100);
            }
            unset($dia);

            // Referencia de merma: los 7 días ANTERIORES a hoy (hoy no puede
            // ser su propia vara). Sin datos previos queda null (sin referencia).
            $prev = ProduccionReporte::seriePorDia(
                \App\Support\FechaNegocio::ahora()->subDays(7)->toDateString(),
                \App\Support\FechaNegocio::ahora()->subDay()->toDateString(),
            );
            $prevP1 = (int) $prev->sum('p1');
            $prevTotal = $prevP1 + (int) $prev->sum('p2') + (int) $prev->sum('mal') + (int) $prev->sum('dan');
            $mermaProm7 = $prevTotal > 0
                ? (int) round(((int) $prev->sum('mal') + (int) $prev->sum('dan')) / $prevTotal * 100)
                : null;

            $pulsoProduccion = $resumen + [
                'mermaProm7' => $mermaProm7,
                'serie' => $serie,
                'href' => route('admin.produccion.index'),
            ];
        }

        // ── ② Pulso: taller (antigüedad de lo activo + flujo semanal) ──
        $pulsoTaller = null;

        if ($user->can('manage servicio tecnico')) {
            // Buckets de antigüedad con límites en PHP + whereDate (portable):
            // 0-7 / 8-30 / 30+ días desde el ingreso, solo órdenes activas.
            $d7 = \App\Support\FechaNegocio::ahora()->subDays(7)->toDateString();
            $d30 = \App\Support\FechaNegocio::ahora()->subDays(30)->toDateString();
            $activas = fn () => OrdenServicio::pendientesTecnico();

            $aging = [
                'd0_7' => $activas()->whereDate('fecha_ingreso', '>=', $d7)->count(),
                'd8_30' => $activas()->whereDate('fecha_ingreso', '<', $d7)->whereDate('fecha_ingreso', '>=', $d30)->count(),
                'd30' => $activas()->whereDate('fecha_ingreso', '<', $d30)->count(),
            ];

            // Flujo de los últimos 7 días: entradas por fecha_ingreso; salidas
            // por fecha_entrega (histórico puede venir NULL — se subestima, no
            // se inventa).
            $pulsoTaller = [
                'activos' => array_sum($aging),
                'aging' => $aging,
                'entradasSemana' => OrdenServicio::whereDate('fecha_ingreso', '>=', $d7)->count(),
                'salidasSemana' => OrdenServicio::whereDate('fecha_entrega', '>=', $d7)->count(),
                'href' => route('admin.servicio-tecnico.index'),
            ];
        }

        // ── ③ Zócalo: accesos directos compactos (mismos grupos que el nav) ──
        $accesos = collect([
            'Comercial' => [
                ['label' => 'Catálogo', 'desc' => 'Productos: peso y dimensiones para despacho', 'href' => route('admin.productos.index'), 'permiso' => 'manage productos'],
                ['label' => 'Precios', 'desc' => 'Listas de precios (espejo de Bsale)', 'href' => route('admin.listas-precios.index'), 'permiso' => 'manage productos'],
                ['label' => 'Clientes', 'desc' => 'Ficha local sincronizada con Bsale', 'href' => route('admin.clientes.index'), 'permiso' => 'manage clientes'],
            ],
            'Operación' => [
                ['label' => 'Inventario', 'desc' => 'Stock por bodega (espejo de Bsale)', 'href' => route('admin.bodegas.index'), 'permiso' => 'manage productos'],
                ['label' => 'Producción', 'desc' => 'Asignar y revisar reportes de soplado', 'href' => route('admin.produccion.index'), 'permiso' => 'manage production'],
            ],
            'Servicio Técnico' => [
                ['label' => 'Servicio Técnico', 'desc' => 'Ingreso de máquinas y lavadoras al taller', 'href' => route('admin.servicio-tecnico.index'), 'permiso' => 'manage servicio tecnico'],
            ],
            'Administración' => [
                ['label' => 'Usuarios', 'desc' => 'Cuentas y roles del equipo', 'href' => route('admin.users.index'), 'permiso' => 'view users'],
                ['label' => 'Roles', 'desc' => 'Permisos por rol', 'href' => route('admin.roles.index'), 'permiso' => 'manage roles'],
                ['label' => 'Sucursales', 'desc' => 'Mirador, Coquimbo, Abate Molina, Buzeta', 'href' => route('admin.sucursales.index'), 'permiso' => 'manage sucursales'],
                ['label' => 'Configuración', 'desc' => 'Parámetros globales de la app', 'href' => route('admin.configuracion.index'), 'permiso' => 'manage settings'],
                ['label' => 'Auditoría', 'desc' => 'Quién cambió qué y cuándo', 'href' => route('admin.audits.index'), 'permiso' => 'view audit'],
                ['label' => 'Notificaciones', 'desc' => 'Envíos, reintentos y fallas', 'href' => route('admin.notificaciones.index'), 'permiso' => 'view notificaciones'],
                ['label' => 'Aprobaciones', 'desc' => 'Historial del motor de aprobaciones', 'href' => route('admin.aprobaciones.index'), 'permiso' => 'view aprobaciones'],
            ],
        ])
            ->map(fn (array $items) => array_values(array_filter($items, fn ($i) => $user->can($i['permiso']))))
            ->filter(fn (array $items) => $items !== []);

        return view('dashboard', [
            'excepciones' => $excepciones,
            'puedeVerExcepciones' => $puedeVerExcepciones,
            'pulsoProduccion' => $pulsoProduccion,
            'pulsoTaller' => $pulsoTaller,
            'accesos' => $accesos,
        ]);
    }

    /**
     * Edad legible del ítem más viejo de una cola ("hace 2 días" / "hace 5 h"),
     * calculada en PHP (portable). Null si la cola no tiene timestamp.
     */
    private function edad(mixed $timestamp): ?string
    {
        if ($timestamp === null) {
            return null;
        }

        $horas = (int) Carbon::parse($timestamp)->diffInHours(now());

        if ($horas >= 48) {
            return 'hace '.intdiv($horas, 24).' días';
        }
        if ($horas >= 24) {
            return 'hace 1 día';
        }
        if ($horas >= 1) {
            return "hace {$horas} h";
        }

        return 'hace minutos';
    }
}
