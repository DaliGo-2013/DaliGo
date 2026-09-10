<?php

namespace App\Http\Controllers;

use App\Models\PlanExtra;
use App\Models\PlanHito;
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
 * escribe desde aquí son los "trabajos extras en paralelo" (PlanExtra) y,
 * desde P-PLAN-06, los HITOS (PlanHito — el gerente los corre o agrega sin
 * esperar un deploy), gateados por 'gestionar plan proyecto'. Ver = 'ver plan
 * proyecto'.
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
            'hoyFecha' => PlanProyecto::hoyFecha(),
            'avanceGlobal' => $tracker['pct_global'],
            'totalPeso' => $tracker['total']['peso'] ?? null,
            'hitos' => PlanProyecto::hitos(),
            'decisiones' => PlanProyecto::decisiones(),
            'bloquesExtra' => PlanProyecto::bloquesExtra(),
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

    // --- Hitos (P-PLAN-06) -------------------------------------------------

    public function hitoStore(Request $request): RedirectResponse
    {
        PlanHito::create($this->validateHito($request));

        return redirect()->route('plan.index')->with('status', 'Hito agregado.');
    }

    public function hitoUpdate(Request $request, PlanHito $hito): RedirectResponse
    {
        $hito->update($this->validateHito($request, $hito));

        return redirect()->route('plan.index')->with('status', 'Hito actualizado.');
    }

    public function hitoDestroy(PlanHito $hito): RedirectResponse
    {
        $hito->delete();

        return redirect()->route('plan.index')->with('status', 'Hito eliminado.');
    }

    /**
     * El checkbox «cumplido» llega ausente cuando está desmarcado: se lee con
     * boolean() y no con `?? 0` sobre un dato que se conserva (gotcha
     * [2026-08-20] — acá SÍ es un formulario que siempre dibuja el control).
     */
    private function validateHito(Request $request, ?PlanHito $hito = null): array
    {
        $datos = $request->validate([
            'clave' => ['required', 'string', 'max:12', Rule::unique('plan_hitos', 'clave')->ignore($hito?->id)],
            'etiqueta' => ['required', 'string', 'max:191'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'cumplido' => ['nullable', 'boolean'],
        ]);
        $datos['cumplido'] = $request->boolean('cumplido');

        return $datos;
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
