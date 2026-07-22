<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AgendaTrabajoAviso;
use App\Models\AgendaTrabajo;
use App\Models\Cliente;
use App\Models\ServicioTerreno;
use App\Models\User;
use App\Rules\RutChileno;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Agenda del técnico industrial (servicio en terreno): el jefe o los
 * vendedores agendan mantenciones/reparaciones/instalaciones (plantas de
 * osmosis, llenadoras, lavadoras) con el cliente y lo que hay que hacer;
 * el técnico ve su mes (lista agrupada por día) y marca lo realizado.
 */
class AgendaTrabajoController extends Controller
{
    /**
     * Vista ÚNIFICADA de la agenda: calendario del mes a la IZQUIERDA (grilla con
     * el conteo de trabajos por día) y el DÍA SELECCIONADO a la DERECHA. Al entrar
     * queda en HOY; al tocar un día se selecciona (?dia=). La derecha muestra los
     * trabajos de ese día como FORMULARIOS editables (el técnico agenda/modifica
     * ahí mismo) — solo un día por vez, para que la página cargue liviana.
     */
    public function index(Request $request): View
    {
        $v = $request->validate([
            'anio' => ['nullable', 'integer', 'between:2020,2100'],
            'mes' => ['nullable', 'integer', 'between:1,12'],
            'dia' => ['nullable', 'date'],
        ]);

        $anio = isset($v['anio']) ? (int) $v['anio'] : \App\Support\FechaNegocio::ahora()->year;
        $mes = isset($v['mes']) ? (int) $v['mes'] : \App\Support\FechaNegocio::ahora()->month;
        $cursor = Carbon::create($anio, $mes, 1);
        $prev = $cursor->copy()->subMonth();
        $next = $cursor->copy()->addMonth();

        // Día seleccionado: ?dia= válido y dentro del mes; si no, HOY (si cae en el
        // mes mostrado) o el día 1. Es el día cuyos trabajos se editan a la derecha.
        $hoy = \App\Support\FechaNegocio::ahora()->startOfDay();
        $diaSel = isset($v['dia']) ? Carbon::parse($v['dia']) : null;
        if (! $diaSel || $diaSel->year !== $anio || $diaSel->month !== $mes) {
            $diaSel = ($hoy->year === $anio && $hoy->month === $mes) ? $hoy->copy() : $cursor->copy();
        }

        // Trabajos que SE SOLAPAN con el mes (incluye viajes de varios días que
        // empiezan antes o terminan después). Se comparan fecha y fecha_fin sin
        // funciones de fecha crudas (portable MySQL 5.7 / SQLite).
        $inicioMes = $cursor->copy()->startOfMonth()->toDateString();
        $finMes = $cursor->copy()->endOfMonth()->toDateString();
        $trabajos = AgendaTrabajo::query()
            ->with(['servicio', 'tecnico', 'repuestos', 'cliente'])
            ->whereNotNull('fecha')
            ->whereDate('fecha', '<=', $finMes)
            ->where(function (Builder $q) use ($inicioMes) {
                $q->where(fn (Builder $w) => $w->whereNotNull('fecha_fin')->whereDate('fecha_fin', '>=', $inicioMes))
                    ->orWhere(fn (Builder $w) => $w->whereNull('fecha_fin')->whereDate('fecha', '>=', $inicioMes));
            })
            ->orderBy('fecha')->orderBy('id')
            ->get();

        // Cada trabajo aparece en TODOS los días que abarca dentro del mes (un
        // viaje del 7 al 10 sale en 7, 8, 9 y 10) para que quien mire la agenda
        // vea que el técnico está ocupado esos días.
        $jobsPorDia = collect();
        foreach ($trabajos as $t) {
            $desde = $t->fecha->copy();
            $hasta = ($t->fecha_fin ?? $t->fecha)->copy();
            for ($dia = $desde; $dia->lte($hasta); $dia->addDay()) {
                if ($dia->month !== $mes || $dia->year !== $anio) {
                    continue;
                }
                $iso = $dia->toDateString();
                $jobsPorDia[$iso] = ($jobsPorDia->get($iso) ?? collect())->push($t);
            }
        }
        $jobsPorDia = $jobsPorDia
            ->map(fn ($c) => $c->sortBy(fn (AgendaTrabajo $t) => $t->hora_corta ?? '99:99')->values())
            ->sortKeys();

        // Grilla de semanas completas (lunes a domingo) que cubren el mes.
        $grid = [];
        $d = $cursor->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $fin = $cursor->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        for (; $d->lte($fin); $d->addDay()) {
            $grid[] = $d->copy();
        }

        return view('admin.agenda-terreno.index', array_merge($this->formData(), [
            'trabajos' => $trabajos,
            'jobsPorDia' => $jobsPorDia,          // conteos por día para el calendario
            'grid' => $grid,
            'diaSel' => $diaSel,                  // día activo (se edita a la derecha)
            'trabajosDia' => $jobsPorDia->get($diaSel->toDateString()) ?? collect(),
            'puedeAgendar' => $request->user()->can('agendar servicio terreno'),
            // Solicitudes del cliente (QR) esperando coordinación (sin fecha).
            'porCoordinar' => $porCoordinar = AgendaTrabajo::porCoordinar()->with('servicio')->get(),
            // RUTs de esas solicitudes que YA están en el catálogo → marca "✓ en
            // catálogo" (una sola consulta para todas). Cubre solicitudes viejas
            // sin cliente_id enlazado.
            'rutsEnCatalogo' => Cliente::whereIn('rut', $porCoordinar->pluck('cliente_rut')->filter()->unique()->all())
                ->pluck('rut')->all(),
            'anio' => $anio,
            'mes' => $mes,
            'mesLabel' => ucfirst($cursor->translatedFormat('F Y')),
            'anterior' => ['anio' => $prev->year, 'mes' => $prev->month],
            'siguiente' => ['anio' => $next->year, 'mes' => $next->month],
        ]));
    }

    /**
     * La antigua vista "calendario" se fusionó dentro de index (calendario a la
     * izquierda + lista a la derecha). Se conserva la ruta por compatibilidad y
     * redirige a la vista única preservando el mes consultado.
     */
    public function calendario(Request $request): RedirectResponse
    {
        return redirect()->route('admin.agenda-terreno.index', $request->only(['anio', 'mes']));
    }

    public function create(): View
    {
        return view('admin.agenda-terreno.create', array_merge($this->formData(), [
            'clienteCatalogo' => null,
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $this->bloquearSiOcupado($request, $data);
        $this->sincronizarCatalogo($request, $data);
        $data['creado_por'] = $request->user()->name;

        $trabajo = AgendaTrabajo::create($data);

        // Nace agendado con fecha y correo → confírmaselo al cliente de una.
        if ($trabajo->estado === 'agendado' && filled($trabajo->cliente_email) && $trabajo->fecha) {
            $this->avisarClienteDeCita($trabajo, 'agendada');
        }

        return redirect()->route('admin.agenda-terreno.index', ['anio' => $trabajo->fecha->year, 'mes' => $trabajo->fecha->month, 'dia' => $trabajo->fecha->toDateString()])
            ->with('status', "Trabajo agendado para el {$trabajo->fecha->format('d-m-Y')} ({$trabajo->tipo_label}, {$trabajo->cliente_nombre}).");
    }

    public function edit(AgendaTrabajo $trabajo): View
    {
        return view('admin.agenda-terreno.edit', array_merge(
            [
                'trabajo' => $trabajo->load(['servicio', 'tecnico']),
                // Ficha del catálogo que calza con este trabajo: la enlazada o, si
                // no la hay (solicitud vieja), la que matchee por RUT. Alimenta el
                // recuadro "Cliente conocido" + botón "Usar datos guardados".
                'clienteCatalogo' => $trabajo->cliente ?: Cliente::buscarPorRut($trabajo->cliente_rut),
            ],
            // Incluye el servicio ACTUAL del trabajo aunque esté inactivo: si no
            // estuviera en el select, guardar cualquier edición lo desvincularía
            // en silencio (x-model resetea a la opción vacía).
            $this->formData($trabajo)
        ));
    }

    public function update(Request $request, AgendaTrabajo $trabajo): RedirectResponse
    {
        $data = $this->validateData($request, editando: true);
        $this->bloquearSiOcupado($request, $data, $trabajo->id);
        $this->sincronizarCatalogo($request, $data);

        // Estado ANTES de guardar, para decidir si hay que avisar al cliente.
        $antes = [
            'estado' => $trabajo->estado,
            'fecha' => $trabajo->fecha?->toDateString(),
            'hora' => $trabajo->hora_corta,
            'tecnico_id' => $trabajo->tecnico_id,
        ];

        $trabajo->update($data);

        $this->avisarClienteSiCorresponde($trabajo->refresh(), $antes);

        // Una solicitud puede seguir sin fecha: se vuelve al mes actual.
        $destino = $trabajo->fecha ?? \App\Support\FechaNegocio::ahora();
        $params = ['anio' => $destino->year, 'mes' => $destino->month];
        if ($trabajo->fecha) {
            $params['dia'] = $trabajo->fecha->toDateString();
        }

        return redirect()->route('admin.agenda-terreno.index', $params)
            ->with('status', 'Trabajo actualizado.');
    }

    public function destroy(AgendaTrabajo $trabajo): RedirectResponse
    {
        $trabajo->delete();

        return back()->with('status', 'Trabajo eliminado de la agenda.');
    }

    /**
     * Cambia SOLO el estado (el técnico marca realizado desde la lista, sin
     * entrar al formulario). Quien solo VE la agenda (técnico industrial) puede
     * únicamente cerrar: agendado → realizado; cancelar o reabrir exige el
     * permiso de agendar (jefe/vendedores).
     */
    public function estado(Request $request, AgendaTrabajo $trabajo): RedirectResponse
    {
        $data = $request->validate([
            'estado' => ['required', Rule::in(AgendaTrabajo::ESTADOS)],
            'notas_tecnico' => ['nullable', 'string'],
            // Repuestos usados: el técnico los registra al cerrar (Realizado).
            'repuestos' => ['nullable', 'array'],
            'repuestos.*.nombre' => ['nullable', 'string', 'max:191'],
            'repuestos.*.cantidad' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ]);

        if (! $request->user()->can('agendar servicio terreno')
            && ! ($trabajo->estado === 'agendado' && $data['estado'] === 'realizado')) {
            abort(403, 'Solo puedes marcar como realizado un trabajo agendado.');
        }

        $update = ['estado' => $data['estado']];
        if (array_key_exists('notas_tecnico', $data)) {
            $update['notas_tecnico'] = $data['notas_tecnico'];
        }
        $trabajo->update($update);

        // Repuestos SOLO al marcar realizado y si vienen en la petición: se
        // reemplazan los del trabajo (filas con nombre vacío se descartan).
        if ($data['estado'] === 'realizado' && $request->has('repuestos')) {
            $trabajo->repuestos()->delete();
            foreach ($data['repuestos'] ?? [] as $r) {
                if (! empty($r['nombre'])) {
                    $trabajo->repuestos()->create([
                        'nombre' => $r['nombre'],
                        'cantidad' => $r['cantidad'] ?? 1,
                    ]);
                }
            }
        }

        return back()->with('status', "Trabajo de {$trabajo->cliente_nombre} marcado como {$data['estado']}.");
    }

    /**
     * Autocompletado del cliente por RUT o razón social (JSON). Mismo contrato
     * que los buscadores de ST; permiso propio de la agenda (los vendedores no
     * tienen 'manage servicio tecnico').
     */
    public function buscarCliente(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }
        $rutQ = preg_replace('/[.\s]/', '', $q);

        $clientes = Cliente::query()
            ->where(fn (Builder $w) => $w
                ->where('razon_social', 'like', "%{$q}%")
                ->orWhere('rut', 'like', "%{$rutQ}%"))
            ->orderBy('razon_social')
            ->limit(15)
            ->get(['id', 'rut', 'razon_social', 'telefono', 'email', 'direccion', 'ciudad']);

        return response()->json($clientes->map(fn (Cliente $c) => [
            'id' => $c->id,
            'rut' => $c->rut,
            'razon_social' => $c->razon_social,
            'telefono' => $c->telefono,
            'email' => $c->email,
            'direccion' => $c->direccion,
            'ciudad' => $c->ciudad,
            'label' => ($c->rut ? $c->rut.' — ' : '').$c->razon_social,
        ]));
    }

    // --- Helpers --------------------------------------------------------

    /**
     * Enlaza el trabajo con la ficha del catálogo por RUT y, si quien coordina lo
     * pidió, guarda un cliente nuevo o actualiza una ficha LOCAL:
     *  - RUT ya en catálogo → enlaza (cliente_id) y, con `actualizar_catalogo` y
     *    solo si la ficha es LOCAL (no de Bsale), refresca sus datos de contacto.
     *  - RUT nuevo + `guardar_en_catalogo` → crea la ficha y la enlaza.
     * OJO: NO se tocan fichas de Bsale (email/telefono/direccion/ciudad los pisa
     * la sync horaria; su fuente de verdad es Bsale). Muta $data['cliente_id'].
     */
    private function sincronizarCatalogo(Request $request, array &$data): void
    {
        $rut = $data['cliente_rut'] ?? null;
        if (blank($rut)) {
            return;
        }

        $existente = Cliente::where('rut', $rut)->first();

        if ($existente) {
            $data['cliente_id'] = $existente->id; // asegura el enlace

            if ($request->boolean('actualizar_catalogo') && ! $existente->es_de_bsale) {
                $existente->update($this->datosContactoCliente($data));
            }

            return;
        }

        if ($request->boolean('guardar_en_catalogo')) {
            try {
                $cliente = Cliente::create(array_merge(
                    ['rut' => $rut],
                    $this->datosContactoCliente($data),
                ));
                $data['cliente_id'] = $cliente->id;
            } catch (\Illuminate\Database\QueryException $e) {
                // Carrera: si otro proceso ya creó ese RUT, enlaza al existente.
                $data['cliente_id'] = Cliente::where('rut', $rut)->value('id') ?? ($data['cliente_id'] ?? null);
            }
        }
    }

    /**
     * Campos de contacto de la ficha tomados de lo que quedó en el trabajo (razón
     * social = nombre del cliente). Compartido por crear/actualizar catálogo.
     *
     * @return array<string, string|null>
     */
    private function datosContactoCliente(array $data): array
    {
        return [
            'razon_social' => $data['cliente_nombre'],
            'email' => $data['cliente_email'] ?? null,
            'telefono' => $data['cliente_telefono'] ?? null,
            'direccion' => $data['direccion'] ?? null,
            'ciudad' => $data['ciudad'] ?? null,
        ];
    }

    /**
     * Decide, comparando el estado ANTES/DESPUÉS de editar, si hay que avisar al
     * cliente y con qué motivo: recién coordinada (agendada), fecha/hora/técnico
     * cambiados (reprogramada) o cancelada (anulada). Sin correo del cliente no
     * se hace nada.
     */
    private function avisarClienteSiCorresponde(AgendaTrabajo $trabajo, array $antes): void
    {
        if (blank($trabajo->cliente_email)) {
            return;
        }

        if ($trabajo->estado === 'agendado' && $trabajo->fecha) {
            if ($antes['estado'] !== 'agendado') {
                $this->avisarClienteDeCita($trabajo, 'agendada');
            } elseif ($antes['fecha'] !== $trabajo->fecha?->toDateString()
                || $antes['hora'] !== $trabajo->hora_corta
                || $antes['tecnico_id'] !== $trabajo->tecnico_id) {
                $this->avisarClienteDeCita($trabajo, 'reprogramada');
            }

            return;
        }

        if ($trabajo->estado === 'cancelado' && $antes['estado'] === 'agendado') {
            $this->avisarClienteDeCita($trabajo, 'anulada');
        }
    }

    /**
     * Manda el correo al cliente (agendada/reprogramada abren el link de
     * confirmación reseteando la respuesta previa; anulada no). Secundario: un
     * fallo de correo no debe tumbar el guardado del trabajo.
     */
    private function avisarClienteDeCita(AgendaTrabajo $trabajo, string $motivo): void
    {
        try {
            if ($motivo !== 'anulada') {
                $trabajo->prepararConfirmacionCliente();
            }
            Mail::to($trabajo->cliente_email)->send(new AgendaTrabajoAviso($trabajo, $motivo));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function validateData(Request $request, bool $editando = false): array
    {
        // Normalizar el RUT (opcional aquí) a la forma canónica.
        $rutInput = trim((string) $request->input('cliente_rut'));
        $fecha = $request->input('fecha') ?: null;
        $hora = $request->input('hora') ?: null;
        $request->merge([
            'cliente_rut' => $rutInput === '' ? null : (Cliente::normalizarRut($rutInput) ?? $rutInput),
            // Cliente NUEVO (no elegido de la lista): el hidden puede traer 0 →
            // se trata como null para que `exists` no rechace el agendamiento.
            'cliente_id' => $request->input('cliente_id') ?: null,
            // fecha_fin/hora_fin solo tienen sentido junto a su inicio.
            'fecha_fin' => $fecha ? ($request->input('fecha_fin') ?: null) : null,
            'hora_fin' => $hora ? ($request->input('hora_fin') ?: null) : null,
        ]);

        return $request->validate([
            'tipo' => ['required', Rule::in(AgendaTrabajo::TIPOS)],
            // Una SOLICITUD del cliente aún no tiene fecha real (se pone al
            // coordinar); en cualquier otro estado la fecha es obligatoria.
            'fecha' => [Rule::requiredIf(fn () => $request->input('estado') !== 'solicitado'), 'nullable', 'date'],
            // Fin del rango (viaje de varios días): opcional, no antes del inicio.
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha'],
            // Hora opcional (la vista calendario la prellena con el slot elegido).
            'hora' => ['nullable', 'date_format:H:i'],
            // Hora de término (trabajo de día completo): opcional, no antes del inicio.
            'hora_fin' => ['nullable', 'date_format:H:i', 'after_or_equal:hora'],
            'fecha_preferida' => ['nullable', 'date'],
            'estado' => $editando
                ? ['required', Rule::in(AgendaTrabajo::ESTADOS)]
                : ['nullable', Rule::in(AgendaTrabajo::ESTADOS)],
            'servicio_terreno_id' => ['nullable', 'integer', Rule::exists('servicios_terreno', 'id')],
            'cliente_id' => ['nullable', 'integer', Rule::exists('clientes', 'id')],
            // Datos del cliente OBLIGATORIOS (parean con el formulario público
            // del QR): sin ellos el técnico no puede llegar ni coordinar.
            'cliente_nombre' => ['required', 'string', 'min:3', 'max:191'],
            'cliente_rut' => ['required', 'string', 'max:20', new RutChileno],
            'cliente_telefono' => ['required', 'string', 'max:30'],
            'cliente_email' => ['required', 'email', 'max:191'],
            'direccion' => ['required', 'string', 'max:191'],
            'ciudad' => ['required', 'string', 'max:191'],
            'tecnico_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'descripcion' => ['required', 'string'],
            'notas_tecnico' => ['nullable', 'string'],
        ]);
    }

    /**
     * Bloquea agendar/editar cuando el técnico ya está ocupado (o de viaje) en
     * esos días: si el rango [fecha, fecha_fin] se solapa con otro trabajo
     * comprometido, se rechaza — SALVO que quien agenda sea admin (solo el admin
     * puede pisar días ocupados). Las solicitudes sin fecha (QR «por coordinar»)
     * no bloquean.
     */
    private function bloquearSiOcupado(Request $request, array $data, ?int $exceptId = null): void
    {
        $fecha = $data['fecha'] ?? null;
        if (! $fecha || ($data['estado'] ?? null) === 'solicitado') {
            return;
        }
        if ($request->user()->hasRole('admin')) {
            return; // el admin puede agendar sobre días ocupados
        }

        $hasta = $data['fecha_fin'] ?? $fecha;
        $conflictos = AgendaTrabajo::conflictos((string) $fecha, (string) $hasta, $exceptId);

        if ($conflictos->isNotEmpty()) {
            $c = $conflictos->first();
            $donde = $c->ciudad ? " en {$c->ciudad}" : '';
            throw ValidationException::withMessages([
                'fecha' => "El técnico ya está ocupado esos días ({$c->rango_fechas_label}{$donde}). Pídele a un administrador que lo agende si es imprescindible.",
            ]);
        }
    }

    /**
     * Combos del formulario: servicios del catálogo (activos + el actual del
     * trabajo aunque esté inactivo, para no desvincularlo al editar) y técnicos
     * industriales (rol) para asignar. `serviciosJs` es el mapa que consume el
     * Alpine del detalle en vivo (construido UNA vez para create y edit).
     */
    private function formData(?AgendaTrabajo $trabajo = null): array
    {
        $servicios = ServicioTerreno::activos()->get();
        if ($trabajo?->servicio && ! $servicios->contains('id', $trabajo->servicio_terreno_id)) {
            $servicios->push($trabajo->servicio);
        }

        return [
            'tipos' => AgendaTrabajo::TIPOS,
            'estados' => AgendaTrabajo::ESTADOS,
            'servicios' => $servicios,
            'serviciosJs' => $servicios->keyBy('id')->map(fn (ServicioTerreno $s) => [
                'valor_uf' => $s->valor_uf_fmt,
                'duracion' => $s->duracion,
                'incluye' => $s->incluye,
                'observaciones' => $s->observaciones,
            ]),
            'tecnicos' => User::role('tecnico_industrial')->orderBy('name')->get(),
        ];
    }
}
