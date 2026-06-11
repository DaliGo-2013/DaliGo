<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bodegas y stock espejados desde Bsale (M04). Solo lectura.
 */
class BodegaController extends Controller
{
    public function index(): View
    {
        $bodegas = Bodega::withCount('stocks')
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
            'bodega' => $bodega->loadCount('stocks'),
            'stocks' => $stocks,
            'filtros' => $request->only(['q', 'con_stock']),
        ]);
    }
}
