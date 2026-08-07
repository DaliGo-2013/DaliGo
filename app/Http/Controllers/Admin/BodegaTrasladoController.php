<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BodegaTraslado;
use App\Services\Inventario\BajaDeBodegas;
use App\Services\Inventario\TrasladoBodegaExcel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * La orden de traslado del wizard de baja (M04-F2): ficha, descarga Excel
 * (el documento que viaja a bodega — el traslado físico hoy se ejecuta en
 * Bsale, D-005 pendiente) y anulación mientras esté pendiente.
 */
class BodegaTrasladoController extends Controller
{
    public function show(BodegaTraslado $traslado): View
    {
        $traslado->load(['bodega', 'destino', 'solicitante', 'items']);

        return view('admin.bodegas.traslados.show', ['traslado' => $traslado]);
    }

    public function excel(BodegaTraslado $traslado, TrasladoBodegaExcel $excel): Response
    {
        $traslado->load(['bodega', 'destino', 'items']);

        return response($excel->generar($traslado), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.TrasladoBodegaExcel::nombreArchivo($traslado).'"',
            // La orden refleja la foto guardada, pero el ESTADO puede cambiar
            // (completada/anulada): que no quede cacheada.
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function anular(Request $request, BodegaTraslado $traslado, BajaDeBodegas $servicio): RedirectResponse
    {
        try {
            $servicio->anular($traslado, $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->with('status', $e->getMessage());
        }

        return redirect()->route('admin.bodegas.show', $traslado->bodega_id)
            ->with('status', "Orden de traslado anulada: {$traslado->bodega->nombre} vuelve a operación.");
    }
}
