<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\ProduccionReporte;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Página de Inicio: punto de partida según los permisos del usuario.
 * Cada bloque (CTA del operario, indicadores, accesos rápidos) se arma solo
 * con lo que el usuario puede ver; un member sin permisos ve solo el saludo.
 * Se gatea por PERMISO (no por nombre de rol), igual que rutas y navegación.
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        // Indicadores accionables: cada tarjeta enlaza a la pantalla donde se
        // resuelve. 'alerta' destaca el número cuando > 0 amerita acción.
        $indicadores = [];

        if ($user->can('manage production')) {
            $indicadores[] = [
                'label' => 'Reportes por revisar',
                'valor' => ProduccionReporte::pendientes()->count(),
                'href' => route('admin.produccion.index'),
                'alerta' => true,
            ];
        }

        if ($user->can('manage productos')) {
            $activos = Producto::where('activo', true)->count();
            $completos = Producto::where('activo', true)
                ->whereNotNull('peso_kg')->whereNotNull('alto_cm')
                ->whereNotNull('ancho_cm')->whereNotNull('largo_cm')
                ->count();

            $indicadores[] = [
                'label' => 'Productos sin medidas',
                'valor' => $activos - $completos,
                'href' => route('admin.productos.index', ['medidas' => 'incompletas']),
                'alerta' => true,
            ];
        }

        if ($user->can('manage clientes')) {
            $indicadores[] = [
                'label' => 'Clientes',
                'valor' => Cliente::count(),
                'href' => route('admin.clientes.index'),
                'alerta' => false,
            ];
        }

        if ($user->can('view users')) {
            $indicadores[] = [
                'label' => 'Usuarios',
                'valor' => User::count(),
                'href' => route('admin.users.index'),
                'alerta' => false,
            ];
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
            'indicadores' => $indicadores,
            'accesos' => $accesos,
        ]);
    }
}
