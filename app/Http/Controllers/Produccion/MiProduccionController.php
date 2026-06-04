<?php

namespace App\Http\Controllers\Produccion;

use App\Http\Controllers\Controller;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionReporte;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MiProduccionController extends Controller
{
    /**
     * Reporte del dia del soplador autenticado.
     */
    public function index(Request $request): View
    {
        $asignacion = ProduccionAsignacion::where('soplador_id', $request->user()->id)
            ->whereDate('fecha', now()->toDateString())
            ->latest('id')
            ->first();

        return view('produccion.mi-reporte', [
            'reporte' => $asignacion?->reporte,
        ]);
    }

    /**
     * Guarda el borrador o envia el reporte (segun el flag 'enviar').
     */
    public function update(Request $request, ProduccionReporte $reporte): RedirectResponse
    {
        abort_unless($reporte->soplador_id === $request->user()->id, 403);
        abort_unless($reporte->editablePorSoplador(), 403, 'Este reporte ya no se puede editar.');

        $validated = $request->validate([
            'primera' => ['required', 'integer', 'min:0'],
            'segunda' => ['required', 'integer', 'min:0'],
            'malo' => ['required', 'integer', 'min:0'],
            'motivo' => ['nullable', 'string', 'max:255'],
            'obs' => ['nullable', 'string', 'max:1000'],
        ]);

        $enviar = $request->boolean('enviar');
        $total = $validated['primera'] + $validated['segunda'] + $validated['malo'];
        $diferencia = $reporte->asignadas - $total;

        if ($enviar) {
            if ($total <= 0) {
                return back()->withInput()
                    ->withErrors(['primera' => 'Ingresa al menos una cantidad antes de enviar.']);
            }
            if ($diferencia !== 0 && blank($validated['motivo'] ?? null)) {
                return back()->withInput()
                    ->withErrors(['motivo' => 'Indica el motivo de la diferencia con lo asignado.']);
            }
        }

        $reporte->fill($validated);

        if ($enviar) {
            $reporte->estado = ProduccionReporte::ENVIADO;
            $reporte->enviado_at = now();
        }

        $reporte->save();

        return redirect()->route('produccion.mi.index')->with(
            'status',
            $enviar ? 'Reporte enviado. Queda a la espera de revision.' : 'Borrador guardado.',
        );
    }
}
