<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\Receta;
use App\Models\TipoBotellon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Recetas de botellón (P-M11-10): qué componentes consume UNA unidad del
 * producto terminado. CRUD mínimo estilo D-003 (clasificación de bodegas):
 * el seeder siembra la hipótesis «por confirmar» y guardar desde la UI
 * confirma. Permiso: `manage production` (el del módulo; cero permisos
 * nuevos). El soplador jamás llega acá — y la pantalla solo habla de
 * CANTIDADES, nunca de costos (regla del PLAN §1.3).
 */
class RecetaController extends Controller
{
    public function index(): View
    {
        // Botellones con receta posible: los enlazados desde los tipos MÁS
        // cualquiera que ya tenga filas (por si un tipo se desenlazó después).
        $ids = TipoBotellon::whereNotNull('producto_id')->pluck('producto_id')
            ->merge(Receta::pluck('producto_id'))->unique()->values();

        return view('admin.recetas.index', [
            'botellones' => Producto::whereIn('id', $ids)->orderBy('nombre')->get(),
            'recetas' => Receta::with('componente')->whereIn('producto_id', $ids)->get()->groupBy('producto_id'),
        ]);
    }

    public function edit(Producto $producto): View
    {
        $filas = Receta::paraProducto($producto->id);

        return view('admin.recetas.edit', [
            'producto' => $producto,
            'preforma' => $filas[Receta::ROL_PREFORMA] ?? null,
            'tapa' => $filas[Receta::ROL_TAPA] ?? null,
            'tapas' => $this->tapasParaSelector(),
        ]);
    }

    public function update(Request $request, Producto $producto): RedirectResponse
    {
        $data = $request->validate([
            'cantidad_preforma' => ['required', 'numeric', 'min:0.0001', 'max:1000'],
            'cantidad_tapa' => ['nullable', 'required_with:componente_tapa', 'numeric', 'min:0.0001', 'max:1000'],
            // MISMO scope que el selector (regla M-3): activo y sin dañadas.
            'componente_tapa' => ['nullable', 'integer', Rule::exists('productos', 'id')->where('activo', true)->where($this->sinDanadas())],
        ], [], [
            'cantidad_preforma' => 'cantidad de preformas',
            'cantidad_tapa' => 'cantidad de tapas',
            'componente_tapa' => 'tapa',
        ]);

        // Guardar = confirmar (D-003). La fila preforma NO gestiona componente
        // desde la UI: el producto del movimiento es la preforma de la
        // asignación del turno; la receta solo aporta la cantidad.
        Receta::updateOrCreate(
            ['producto_id' => $producto->id, 'rol' => Receta::ROL_PREFORMA],
            ['cantidad' => $data['cantidad_preforma'], 'confirmada' => true],
        );

        if ($data['cantidad_tapa'] ?? null) {
            Receta::updateOrCreate(
                ['producto_id' => $producto->id, 'rol' => Receta::ROL_TAPA],
                ['cantidad' => $data['cantidad_tapa'], 'componente_id' => $data['componente_tapa'] ?? null, 'confirmada' => true],
            );
        } else {
            // Sin cantidad de tapa = este botellón no lleva tapa: la fila se va
            // (borrarla ES desactivarla; CRUD mínimo, sin flag `activa`).
            Receta::where('producto_id', $producto->id)->where('rol', Receta::ROL_TAPA)->delete();
        }

        return redirect()->route('admin.recetas.index')
            ->with('status', "Receta de {$producto->nombre} confirmada.");
    }

    /**
     * Tapas elegibles: activos cuya categoría menciona "tapa"; si el catálogo
     * aún no las categoriza, todos los activos (mismo idioma que
     * preformasParaSelector en ProduccionController). Siempre sin dañadas.
     */
    private function tapasParaSelector()
    {
        $tapas = Producto::query()->where('activo', true)
            ->where('categoria', 'like', '%tapa%')
            ->where($this->sinDanadas())
            ->orderBy('nombre')
            ->get(['id', 'sku', 'nombre']);

        if ($tapas->isNotEmpty()) {
            return $tapas;
        }

        return Producto::query()->where('activo', true)
            ->where($this->sinDanadas())
            ->orderBy('nombre')
            ->get(['id', 'sku', 'nombre']);
    }

    private function sinDanadas(): \Closure
    {
        return function ($query) {
            $query->where('nombre', 'not like', '%dañada%')
                ->where('nombre', 'not like', '%DAÑADA%');
        };
    }
}
