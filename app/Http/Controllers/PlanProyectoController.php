<?php

namespace App\Http\Controllers;

use App\Models\PlanExtra;
use App\Services\Plan\CartaGanttExcel;
use App\Support\PlanProyecto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Página «Plan del proyecto» (/plan): carta Gantt transicional mientras la
 * app se construye. El plan oficial se LEE del repo (PlanProyecto parsea
 * RUTA-MAESTRA §10 — push a main = deploy = página al día); lo único que se
 * escribe desde aquí son los "trabajos extras en paralelo" (PlanExtra),
 * gateados por 'gestionar plan proyecto'. Ver = 'ver plan proyecto'.
 */
class PlanProyectoController extends Controller
{
    public function index(): View
    {
        $tracker = PlanProyecto::tracker();

        return view('plan.index', [
            'gantt' => PlanProyecto::gantt(),
            'meses' => PlanProyecto::meses(),
            'hoyPct' => PlanProyecto::hoyPct(),
            'avanceGlobal' => $tracker['pct_global'],
            'totalPeso' => $tracker['total']['peso'] ?? null,
            'hitos' => PlanProyecto::hitos(),
            'decisiones' => PlanProyecto::decisiones(),
            'extras' => PlanExtra::orderByDesc('created_at')->get(),
            // filemtime = cuándo el deploy (git pull) refrescó el tracker en
            // este servidor. Es un timestamp con hora → enChile() al mostrar.
            'planActualizado' => Carbon::createFromTimestamp(
                (int) filemtime(base_path('docs/RUTA-MAESTRA.md'))
            ),
        ]);
    }

    /**
     * Descarga la carta Gantt como .xlsx, GENERADA en el momento desde la misma
     * fuente que la pagina (nada que mantener a mano: cada descarga sale al dia
     * del repo). El semaforo del archivo es el de la reunion de avance
     * (rojo/amarillo/verde/gris — ver CartaGanttExcel::semaforo).
     */
    public function excel(): Response
    {
        $excel = new CartaGanttExcel;

        return response($excel->generar(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.CartaGanttExcel::nombreArchivo().'"',
            // Se genera fresco en cada descarga: que ningun proxy lo retenga.
            'Cache-Control' => 'no-store',
        ]);
    }

    public function extraStore(Request $request): RedirectResponse
    {
        PlanExtra::create($this->validateData($request));

        return redirect()->route('plan.index')->with('status', 'Trabajo extra agregado.');
    }

    public function extraUpdate(Request $request, PlanExtra $extra): RedirectResponse
    {
        $extra->update($this->validateData($request));

        return redirect()->route('plan.index')->with('status', 'Trabajo extra actualizado.');
    }

    public function extraDestroy(PlanExtra $extra): RedirectResponse
    {
        $extra->delete();

        return redirect()->route('plan.index')->with('status', 'Trabajo extra eliminado.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'titulo' => ['required', 'string', 'max:191'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'estado' => ['required', Rule::in(PlanExtra::ESTADOS)],
            'avance' => ['required', 'integer', 'between:0,100'],
            'responsable' => ['nullable', 'string', 'max:191'],
        ]);
    }
}
