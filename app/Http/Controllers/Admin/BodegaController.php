<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Models\Stock;
use App\Models\Sucursal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Bodegas y stock espejados desde Bsale (M04). El stock sigue siendo solo
 * lectura (Bsale es master); la CLASIFICACION local (sucursal, proposito,
 * operacion, alias — M04-F1) se edita aca. Guardar la ficha CONFIRMA la
 * clasificacion: el acto de confirmacion es que un humano la miro y guardo.
 */
class BodegaController extends Controller
{
    public function index(): View
    {
        $bodegas = Bodega::withCount('stocks')
            ->with('sucursal')
            ->orderByDesc('activa')
            ->orderBy('nombre')
            ->get();

        return view('admin.bodegas.index', ['bodegas' => $bodegas]);
    }

    public function show(Request $request, Bodega $bodega): View
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:191'],
            'con_stock' => ['nullable', 'in:1'],
        ]);

        $stocks = Stock::where('bodega_id', $bodega->id)
            ->join('productos', 'productos.id', '=', 'stocks.producto_id')
            ->when($f['q'] ?? null, fn ($qb, $q) => $qb->where(function ($w) use ($q) {
                $w->where('productos.nombre', 'like', "%{$q}%")
                    ->orWhere('productos.sku', 'like', "%{$q}%");
            }))
            ->when($f['con_stock'] ?? null, fn ($qb) => $qb->where('stocks.stock_disponible', '>', 0))
            ->orderByDesc('stocks.stock_disponible')
            ->orderBy('productos.nombre')
            ->select('stocks.*')
            ->with('producto')
            ->paginate(25)
            ->withQueryString();

        return view('admin.bodegas.show', [
            'bodega' => $bodega->loadCount('stocks')->load('sucursal'),
            'stocks' => $stocks,
            'filtros' => $request->only(['q', 'con_stock']),
        ]);
    }

    public function edit(Bodega $bodega): View
    {
        return view('admin.bodegas.edit', [
            'bodega' => $bodega,
            'sucursales' => Sucursal::orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, Bodega $bodega): RedirectResponse
    {
        $validated = $request->validate([
            // NULL = transversal (MERMAS, RESERVA): la opcion existe a proposito.
            'sucursal_id' => ['nullable', Rule::exists('sucursales', 'id')],
            // Confirmar sin proposito no es clasificar: required.
            'proposito' => ['required', Rule::in(array_keys(Bodega::PROPOSITOS))],
            'alias' => ['nullable', 'string', 'max:191'],
        ]);

        $bodega->update($validated + [
            'en_operacion' => $request->boolean('en_operacion'),
            // Guardar = confirmar (un humano miro la ficha). `estado_baja` NO
            // se edita aca: lo escribe el wizard de baja (F2).
            'clasificacion_confirmada' => true,
        ]);

        return redirect()->route('admin.bodegas.show', $bodega)
            ->with('status', "Bodega {$bodega->nombre} clasificada.");
    }
}
