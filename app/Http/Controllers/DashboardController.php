<?php

namespace App\Http\Controllers;

use App\Models\Aprobacion;
use App\Models\Cliente;
use App\Models\Notificacion;
use App\Models\OrdenServicio;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionReporte;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Página de Inicio: punto de partida según los permisos del usuario y
 * tablero ejecutivo (M16-v0) — cards de LECTURA agrupadas por módulo.
 * Cada bloque (CTA del operario, indicadores, accesos rápidos) se arma solo
 * con lo que el usuario puede ver; un member sin permisos ve solo el saludo.
 * Se gatea por PERMISO (no por nombre de rol), igual que rutas y navegación.
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        // Indicadores accionables agrupados por módulo: cada tarjeta enlaza a
        // la pantalla donde se resuelve. 'alerta' destaca el número cuando
        // > 0 amerita acción. Solo lectura: queries agregadas, sin N+1.
        $secciones = [];

        if ($user->can('manage production')) {
            $hoy = now()->toDateString();
            // Una sola pasada SUM sobre los reportes de hoy (scope delDia =
            // whereDate) + el asignado del día; mismas fórmulas que el panel
            // del jefe (armarResumen), con guard de división por cero.
            $sumas = ProduccionReporte::delDia($hoy)
                ->selectRaw('COALESCE(SUM(primera),0) as p1, COALESCE(SUM(segunda),0) as p2, COALESCE(SUM(malo),0) as mal, COALESCE(SUM(danada),0) as dan')
                ->first();
            $producido = (int) $sumas->p1 + (int) $sumas->p2;
            $merma = (int) $sumas->mal + (int) $sumas->dan;
            $total = $producido + $merma;
            $asignadas = (int) ProduccionAsignacion::whereDate('fecha', $hoy)->sum('asignadas');

            $secciones[] = ['label' => 'Producción · hoy', 'cards' => [
                ['label' => 'Producido hoy', 'valor' => $producido, 'href' => route('admin.produccion.dia', ['fecha' => $hoy]), 'alerta' => false],
                ['label' => 'Avance de hoy (%)', 'valor' => $asignadas > 0 ? (int) round($producido / $asignadas * 100) : 0, 'href' => route('admin.produccion.index'), 'alerta' => false],
                ['label' => 'Merma de hoy (%)', 'valor' => $total > 0 ? (int) round($merma / $total * 100) : 0, 'href' => route('admin.produccion.index'), 'alerta' => false],
                ['label' => 'Reportes por revisar', 'valor' => ProduccionReporte::pendientes()->count(), 'href' => route('admin.produccion.index'), 'alerta' => true],
            ]];
        }

        $servicioTecnico = [];

        if ($user->can('manage servicio tecnico')) {
            // Un solo COUNT agrupado por estado (sin N+1). Cada card enlaza al
            // listado ya filtrado por ese estado (donde se ve tipo/equipo).
            $porEstado = OrdenServicio::selectRaw('estado, COUNT(*) as c')
                ->groupBy('estado')->pluck('c', 'estado');
            $enTaller = (int) $porEstado->except(['entregado'])->sum();
            $link = fn (?string $estado = null) => route('admin.servicio-tecnico.index', $estado ? ['estado' => $estado] : []);

            $servicioTecnico[] = ['label' => 'Equipos en taller', 'valor' => $enTaller, 'href' => $link(), 'alerta' => true];
            // Los 4 estados clave del paso a paso. "Cotización" = esperando la
            // respuesta del cliente (lo más urgente) → se destaca.
            $servicioTecnico[] = ['label' => 'Cotización (espera cliente)', 'valor' => (int) ($porEstado['cotizacion'] ?? 0), 'href' => $link('cotizacion'), 'alerta' => true];
            $servicioTecnico[] = ['label' => 'Reparado', 'valor' => (int) ($porEstado['reparado'] ?? 0), 'href' => $link('reparado'), 'alerta' => false];
            $servicioTecnico[] = ['label' => 'Entregado', 'valor' => (int) ($porEstado['entregado'] ?? 0), 'href' => $link('entregado'), 'alerta' => false];
            $servicioTecnico[] = ['label' => 'Sin solución', 'valor' => (int) ($porEstado['sin_solucion'] ?? 0), 'href' => $link('sin_solucion'), 'alerta' => false];
        }

        if ($user->can('confirmar servicio tecnico')) {
            $servicioTecnico[] = [
                'label' => 'Recepciones por confirmar',
                'valor' => OrdenServicio::porConfirmar()->count(),
                'href' => route('admin.servicio-tecnico.index'),
                'alerta' => true,
            ];
        }

        if ($servicioTecnico !== []) {
            $secciones[] = ['label' => 'Servicio Técnico', 'cards' => $servicioTecnico];
        }

        if ($user->can('aprobar solicitudes')) {
            // Espejo exacto de la bandeja (AprobacionController::index): el
            // número de la card = lo que el aprobador verá al hacer click.
            $pendientes = Aprobacion::where('estado', Aprobacion::ESTADO_PENDIENTE)
                ->when(! $user->hasRole('admin'), fn ($q) => $q->whereIn('rol_aprobador', $user->getRoleNames()))
                ->count();

            $secciones[] = ['label' => 'Aprobaciones', 'cards' => [
                ['label' => 'Aprobaciones pendientes', 'valor' => $pendientes, 'href' => route('aprobaciones.index'), 'alerta' => true],
            ]];
        }

        $administracion = [];

        if ($user->can('manage productos')) {
            $activos = Producto::where('activo', true)->count();
            $completos = Producto::where('activo', true)
                ->whereNotNull('peso_kg')->whereNotNull('alto_cm')
                ->whereNotNull('ancho_cm')->whereNotNull('largo_cm')
                ->count();

            $administracion[] = [
                'label' => 'Productos sin medidas',
                'valor' => $activos - $completos,
                'href' => route('admin.productos.index', ['medidas' => 'incompletas']),
                'alerta' => true,
            ];
        }

        if ($user->can('manage clientes')) {
            $administracion[] = [
                'label' => 'Clientes',
                'valor' => Cliente::count(),
                'href' => route('admin.clientes.index'),
                'alerta' => false,
            ];
        }

        if ($user->can('view users')) {
            $administracion[] = [
                'label' => 'Usuarios',
                'valor' => User::count(),
                'href' => route('admin.users.index'),
                'alerta' => false,
            ];
        }

        if ($user->can('view notificaciones')) {
            $administracion[] = [
                'label' => 'Notificaciones fallidas',
                'valor' => Notificacion::where('estado', Notificacion::FALLIDA)->count(),
                'href' => route('admin.notificaciones.index', ['estado' => Notificacion::FALLIDA]),
                'alerta' => true,
            ];
        }

        if ($administracion !== []) {
            $secciones[] = ['label' => 'Administración', 'cards' => $administracion];
        }

        // Accesos rápidos: mismos grupos y permisos que la navegación.
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
            ],
        ])
            ->map(fn (array $items) => array_values(array_filter($items, fn ($i) => $user->can($i['permiso']))))
            ->filter(fn (array $items) => $items !== []);

        return view('dashboard', [
            'secciones' => $secciones,
            // Plano (todas las cards en orden): contrato de DashboardTest.
            'indicadores' => collect($secciones)->flatMap(fn (array $s) => $s['cards'])->values()->all(),
            'accesos' => $accesos,
        ]);
    }
}
