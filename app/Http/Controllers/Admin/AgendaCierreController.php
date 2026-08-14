<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgendaCierre;
use App\Support\FechaNegocio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * CUÁNDO LA AGENDA DEL TÉCNICO ESTÁ CERRADA: feriados, vacaciones y medias jornadas.
 *
 * Pedido del dueño (13-08-2026): «habría que dejar una opción que diga ocupado por si el
 * técnico está de vacaciones… y lo mismo por si un día trabaja hasta las dos de la tarde…
 * obviamente todo esto alimentado por el jefe de ventas, que va a ser el que lleve adelante
 * la agenda del técnico industrial».
 *
 * QUÉ HACE Y QUÉ NO: cierra el día para el FORMULARIO PÚBLICO — el cliente deja de poder
 * pedir esa fecha y el cartel se lo dice al elegirla. NO impide agendar por dentro: si surge
 * una urgencia en un feriado, quien agenda lo agenda igual. Cerrar los dos lados convertiría
 * una ayuda en una traba, y la agenda interna ya tiene sus propios controles.
 *
 * LOS FERIADOS NO SE EDITAN DESDE ACÁ. Los siembra `FeriadosChileSeeder` en cada deploy con
 * el calendario legal, así que borrar uno a mano duraría hasta el próximo despliegue: sería
 * un botón que promete algo que no puede cumplir.
 */
class AgendaCierreController extends Controller
{
    // El permiso se exige en las RUTAS (`permission:gestionar cierres agenda`), no acá:
    // Laravel 12 sacó `$this->middleware()` del controlador base. Es el mismo esquema que
    // usa el resto del panel, así que el gate está donde alguien lo va a buscar.

    public function index(): View
    {
        // Desde HOY hacia adelante: lo que ya pasó no cambia ninguna decisión y llenaría la
        // pantalla con los feriados de todo el año anterior.
        $desde = FechaNegocio::hoy();

        return view('admin.agenda-terreno.cierres', [
            'cierres' => AgendaCierre::with('autor')
                ->whereDate('fecha_hasta', '>=', $desde)
                ->orderBy('fecha_desde')
                ->get(),
            'hoy' => $desde,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'fecha_desde' => ['required', 'date'],
            // Un cierre de un día se carga con la misma fecha en las dos puntas; el
            // formulario copia `desde` cuando `hasta` viene vacío.
            'fecha_hasta' => ['nullable', 'date'],
            'tipo' => ['required', 'in:'.implode(',', array_keys(AgendaCierre::TIPOS))],
            'hora_hasta' => ['nullable', 'date_format:H:i', 'required_if:tipo,'.AgendaCierre::TIPO_MEDIA_JORNADA],
            'motivo' => ['required', 'string', 'max:191'],
        ], [
            'hora_hasta.required_if' => 'Para una media jornada hay que decir hasta qué hora atiende.',
            'motivo.required' => 'El motivo es para ustedes, no para el cliente — pero sin él, dentro de un mes nadie recuerda por qué se cerró ese día.',
        ]);

        // `?? null` y no `$datos['fecha_hasta']` a secas: un campo `nullable` que no viene en
        // la petición NO existe en el array validado, y leerlo directo revienta con 500. Pasa
        // apenas alguien envía el formulario sin tocar ese campo.
        //
        // Un rango al revés es un tipeo, no un rango: se ordena en vez de rechazarlo.
        [$desde, $hasta] = AgendaCierre::ordenar(
            $datos['fecha_desde'],
            ($datos['fecha_hasta'] ?? null) ?: $datos['fecha_desde'],
        );

        AgendaCierre::create([
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'tipo' => $datos['tipo'],
            // La hora solo tiene sentido en una media jornada: guardarla en un día cerrado
            // dejaría un dato que contradice al tipo.
            'hora_hasta' => $datos['tipo'] === AgendaCierre::TIPO_MEDIA_JORNADA ? ($datos['hora_hasta'] ?? null) : null,
            'motivo' => $datos['motivo'],
            'origen' => AgendaCierre::ORIGEN_MANUAL,
            'creado_por' => $request->user()->id,
        ]);

        return back()->with('status', 'Listo: la agenda quedó cerrada esos días para las solicitudes del cliente.');
    }

    public function destroy(AgendaCierre $cierre): RedirectResponse
    {
        // Ver el encabezado: un feriado borrado vuelve en el próximo deploy.
        abort_if($cierre->origen === AgendaCierre::ORIGEN_FERIADO, 403,
            'Los feriados vienen del calendario legal y se recargan en cada despliegue.');

        $cierre->delete();

        return back()->with('status', 'Se quitó el cierre: esos días vuelven a estar disponibles.');
    }

    /** Fecha mínima que ofrece el formulario: hoy (cerrar el pasado no hace nada). */
    public static function minimo(): string
    {
        return Carbon::parse(FechaNegocio::hoy())->toDateString();
    }
}
