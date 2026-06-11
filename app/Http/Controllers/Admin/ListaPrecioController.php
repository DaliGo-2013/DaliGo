<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ListaPrecio;
use App\Models\Precio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Listas de precios espejadas desde Bsale (M02.2). Solo lectura de valores
 * (se editan en Bsale); lo unico editable aqui es el campo local `canal`.
 */
class ListaPrecioController extends Controller
{
    public function index(): View
    {
        $listas = ListaPrecio::withCount('precios')
            ->orderByDesc('activa')
            ->orderBy('nombre')
            ->get();

        return view('admin.listas-precios.index', ['listas' => $listas]);
    }

    public function show(Request $request, ListaPrecio $listaPrecio): View
    {
        $f = $request->validate(['q' => ['nullable', 'string', 'max:191']]);

        // Join solo para filtrar/ordenar por el producto; las filas son precios.
        $precios = Precio::where('lista_precio_id', $listaPrecio->id)
            ->join('productos', 'productos.id', '=', 'precios.producto_id')
            ->when($f['q'] ?? null, fn ($qb, $q) => $qb->where(function ($w) use ($q) {
                $w->where('productos.nombre', 'like', "%{$q}%")
                    ->orWhere('productos.sku', 'like', "%{$q}%");
            }))
            ->orderBy('productos.nombre')
            ->select('precios.*')
            ->with('producto')
            ->paginate(25)
            ->withQueryString();

        return view('admin.listas-precios.show', [
            'lista' => $listaPrecio->loadCount('precios'),
            'precios' => $precios,
            'filtros' => $request->only(['q']),
        ]);
    }

    /**
     * Solo el campo local `canal` (convencion DaliGo); el resto es espejo Bsale.
     */
    public function update(Request $request, ListaPrecio $listaPrecio): RedirectResponse
    {
        $validated = $request->validate([
            'canal' => ['nullable', 'string', 'max:50'],
        ]);

        $listaPrecio->update(['canal' => $validated['canal'] ?? null]);

        return back()->with('status', "Canal de {$listaPrecio->nombre} actualizado.");
    }
}
