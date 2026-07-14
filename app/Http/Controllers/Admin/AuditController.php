<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\ListaPrecio;
use App\Models\Maquina;
use App\Models\PreferenciaCanal;
use App\Models\Producto;
use App\Models\ProduccionReporte;
use App\Models\Sucursal;
use App\Models\TipoBotellon;
use App\Models\User;
use App\Models\Zona;
use Illuminate\Http\Request;
use Illuminate\View\View;
use OwenIt\Auditing\Models\Audit;

class AuditController extends Controller
{
    /**
     * Modelos auditados: FQCN => etiqueta legible. Fuente del filtro y de las etiquetas.
     */
    public const MODELOS = [
        User::class => 'Usuario',
        Sucursal::class => 'Sucursal',
        Configuracion::class => 'Configuración',
        Producto::class => 'Producto',
        Cliente::class => 'Cliente',
        ListaPrecio::class => 'Lista de precios',
        Bodega::class => 'Bodega',
        ProduccionReporte::class => 'Reporte de producción',
        Maquina::class => 'Máquina',
        TipoBotellon::class => 'Tipo de botellón',
        PreferenciaCanal::class => 'Preferencia de canal',
        Zona::class => 'Zona',
    ];

    /**
     * Eventos => verbo en español.
     */
    public const EVENTOS = [
        'created' => 'creó',
        'updated' => 'actualizó',
        'deleted' => 'eliminó',
        'roleChanged' => 'cambió rol',
    ];

    /**
     * Historial de auditoría (solo lectura), más reciente primero, filtrable.
     */
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'auditable_type' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::MODELOS))],
        ]);

        $audits = Audit::with('user')
            ->when($filtros['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filtros['auditable_type'] ?? null, fn ($q, $type) => $q->where('auditable_type', $type))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.audits.index', [
            'audits' => $audits,
            'usuarios' => User::orderBy('name')->get(['id', 'name']),
            'modelos' => self::MODELOS,
            'eventos' => self::EVENTOS,
            'filtros' => $filtros,
        ]);
    }
}
