<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AgendaTrabajoAviso;
use App\Models\AgendaTrabajo;
use App\Models\AgendaTrabajoRepuesto;
use App\Models\Aprobacion;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\ReglaAprobacion;
use App\Models\ServicioTerreno;
use App\Models\User;
use App\Rules\RutChileno;
use App\Services\Aprobaciones\Aprobaciones;
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

        // ¿ESTA CITA NECESITA EL VISTO BUENO DEL JEFE DE VENTAS? Si sí, el trabajo NACE «en
        // espera» (ver `pedirAutorizacion`) y no ocupa la agenda hasta que la autorice.
        if ($this->necesitaAutorizacion($request, $data)) {
            return $this->pedirAutorizacion($request, $data);
        }

        $trabajo = AgendaTrabajo::create($data);

        // Nace agendado con fecha y correo → confírmaselo al cliente de una.
        if ($trabajo->estado === 'agendado' && filled($trabajo->cliente_email) && $trabajo->fecha) {
            $this->avisarClienteDeCita($trabajo, 'agendada');
        }

        // Y AVÍSALE AL TÉCNICO, que es quien tiene que ir. El `$antes` es un
        // trabajo que no existía, así que el estado previo es null y el modelo lo
        // lee como «esto es nuevo».
        $trabajo->avisarAlTecnicoSiCorresponde(['estado' => null, 'fecha' => null, 'hora' => null, 'tecnico_id' => null]);

        return redirect()->route('admin.agenda-terreno.index', ['anio' => $trabajo->fecha->year, 'mes' => $trabajo->fecha->month, 'dia' => $trabajo->fecha->toDateString()])
            ->with('status', "Trabajo agendado para el {$trabajo->fecha->format('d-m-Y')} ({$trabajo->tipo_label}, {$trabajo->cliente_nombre}).");
    }

    /**
     * ¿Esta cita la tiene que autorizar el jefe de ventas?
     *
     * Pedido del dueño (13-08-2026): «cuando un vendedor fije una cita con un cliente por
     * mantención, reparación o instalación le tiene que llegar una notificación al jefe de
     * ventas para autorizar eso».
     *
     * TRES CONDICIONES, y las tres importan:
     *
     *  1. Que sea una CITA: estado 'agendado' con fecha. Guardar una solicitud sin fecha no
     *     compromete al técnico y no hay nada que autorizar.
     *  2. Que sea uno de los TRES TIPOS que él nombró. La visita técnica queda afuera: es la
     *     que pide el cliente por el QR y el vendedor solo la coordina.
     *  3. Que el que agenda NO sea quien autoriza. Acá solo se pregunta si existe una regla
     *     activa que lo obligue; la exención del jefe de ventas (y de admin) la resuelve el
     *     motor, que es donde vive esa lógica para los tres consumidores.
     *
     * @param  array<string, mixed>  $data
     */
    private function necesitaAutorizacion(Request $request, array $data): bool
    {
        // El default de la TABLA es 'agendado', así que un `estado` ausente no significa «sin
        // estado»: significa agendado. Leerlo como null dejaba pasar sin autorización a
        // cualquier petición que no mandara el campo — y el control quedaba decorativo.
        $estado = $data['estado'] ?? 'agendado';

        if ($estado !== 'agendado' || blank($data['fecha'] ?? null)) {
            return false;
        }

        if (! in_array($data['tipo'] ?? null, AgendaTrabajo::TIPOS_QUE_AUTORIZA_JEFATURA, true)) {
            return false;
        }

        // Si el usuario porta el rol aprobador, el motor auto-aprueba: se agenda derecho y sin
        // dar una vuelta que termina en el mismo lugar (y sin ensuciar la bandeja con una
        // solicitud que nadie tiene que mirar).
        $regla = ReglaAprobacion::activas()
            ->where('tipo_accion', Aprobacion::ACCION_AGENDA_CITA)
            ->first();

        return $regla !== null && ! $request->user()->hasRole($regla->rol_aprobador);
    }

    /**
     * Crea la cita EN ESPERA y le pide autorización al jefe de ventas.
     *
     * La cita queda con estado 'solicitado' y la fecha pedida en `fecha_preferida` /
     * `hora_preferida` — decisión del dueño: «queda en espera», no ocupa la agenda. No se
     * inventó un estado nuevo porque 'solicitado' + fecha preferida ya significaba eso, y es
     * lo que muestra el bloque «Por coordinar». Ver `Acciones\CitaTerreno` para el detalle.
     *
     * @param  array<string, mixed>  $data
     */
    private function pedirAutorizacion(Request $request, array $data): RedirectResponse
    {
        // Lo que el vendedor pidió viaja en el payload de la aprobación; el trabajo se guarda
        // sin fecha real para que no cuente como ocupado en ningún cálculo.
        $pedido = [
            'fecha' => $data['fecha'],
            'fecha_fin' => $data['fecha_fin'] ?? null,
            'hora' => $data['hora'] ?? null,
            'hora_fin' => $data['hora_fin'] ?? null,
            'tecnico_id' => $data['tecnico_id'] ?? null,
        ];

        $trabajo = AgendaTrabajo::create(array_merge($data, [
            'estado' => 'solicitado',
            'fecha' => null,
            'fecha_fin' => null,
            'hora' => null,
            'hora_fin' => null,
            'fecha_preferida' => $data['fecha'],
            'hora_preferida' => $data['hora'] ?? null,
        ]));

        $fecha = \Illuminate\Support\Carbon::parse($data['fecha']);

        app(Aprobaciones::class)->solicitar(
            tipoAccion: Aprobacion::ACCION_AGENDA_CITA,
            aprobable: $trabajo,
            solicitante: $request->user(),
            motivo: "{$trabajo->tipo_label} para {$trabajo->cliente_nombre} el {$fecha->format('d-m-Y')}"
                .($pedido['hora'] ? " a las {$pedido['hora']}" : ''),
            datos: [
                'nuevo' => $pedido,
                // El snapshot es el contrato del motor: si el trabajo cambia entre la solicitud
                // y la resolución, el handler rechaza en vez de aplicar un payload viejo.
                'objetivo_updated_at' => $trabajo->updated_at?->toJSON(),
            ],
            descripcion: "Cita de terreno · {$trabajo->tipo_label} · {$trabajo->cliente_nombre}",
        );

        return redirect()->route('admin.agenda-terreno.index', ['anio' => $fecha->year, 'mes' => $fecha->month])
            ->with('status', "La cita quedó ESPERANDO la autorización del jefe de ventas ({$trabajo->tipo_label}, {$trabajo->cliente_nombre}, {$fecha->format('d-m-Y')}). Le llegó el aviso; cuando la autorice queda agendada y se le avisa al cliente.");
    }

    /**
     * Lo mismo, pero editando una cita que YA existe (el camino «Coordinar»).
     *
     * Se guarda todo MENOS lo que compromete al técnico: los datos del cliente, la descripción
     * y el servicio se actualizan igual —son correcciones, no compromisos— y la fecha, la hora
     * y el técnico viajan a la aprobación.
     *
     * Si ya hay una solicitud esperando para esta cita, NO se crea otra: el jefe vería dos
     * pedidos de lo mismo y aprobaría uno sin saber qué pasa con el otro.
     *
     * @param  array<string, mixed>  $data
     */
    private function pedirAutorizacionAlEditar(Request $request, AgendaTrabajo $trabajo, array $data): RedirectResponse
    {
        $fecha = \Illuminate\Support\Carbon::parse($data['fecha']);

        if ($trabajo->esperandoAutorizacion()) {
            return back()->with('status', "Esta cita YA está esperando la autorización del jefe de ventas ({$trabajo->fecha_preferida?->format('d-m-Y')}). Cuando la resuelva vas a poder cambiarla.");
        }

        $pedido = [
            'fecha' => $data['fecha'],
            'fecha_fin' => $data['fecha_fin'] ?? null,
            'hora' => $data['hora'] ?? null,
            'hora_fin' => $data['hora_fin'] ?? null,
            'tecnico_id' => $data['tecnico_id'] ?? null,
        ];

        // Se aparta lo que compromete al técnico y se guarda el resto.
        $trabajo->update(array_merge($data, [
            'estado' => 'solicitado',
            'fecha' => null,
            'fecha_fin' => null,
            'hora' => null,
            'hora_fin' => null,
            'fecha_preferida' => $data['fecha'],
            'hora_preferida' => $data['hora'] ?? null,
        ]));

        app(Aprobaciones::class)->solicitar(
            tipoAccion: Aprobacion::ACCION_AGENDA_CITA,
            aprobable: $trabajo->refresh(),
            solicitante: $request->user(),
            motivo: "{$trabajo->tipo_label} para {$trabajo->cliente_nombre} el {$fecha->format('d-m-Y')}"
                .($pedido['hora'] ? " a las {$pedido['hora']}" : ''),
            datos: [
                'nuevo' => $pedido,
                'objetivo_updated_at' => $trabajo->updated_at?->toJSON(),
            ],
            descripcion: "Cita de terreno · {$trabajo->tipo_label} · {$trabajo->cliente_nombre}",
        );

        return redirect()->route('admin.agenda-terreno.index', ['anio' => $fecha->year, 'mes' => $fecha->month])
            ->with('status', "Se guardaron los datos, y la FECHA quedó esperando la autorización del jefe de ventas ({$fecha->format('d-m-Y')}). Cuando la autorice queda agendada y se le avisa al cliente.");
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

        // ═══ «CONFIRMAR Y AVISAR AL CLIENTE» ═══
        //
        // Dueño 21-08-2026: «al cliquear coordinar, al final del apartado aparezca enviar o
        // confirmar, y que ahí se mande al cliente el detalle de que la visita está
        // confirmada; hasta ahora no entiendo que aparezca algo que cierre esa
        // confirmación». Y tenía razón: el aviso salía igual, pero solo si el jefe sabía que
        // confirmar era «cambiar el estado a Agendado y guardar». La cara NO del mismo flujo
        // («Rechazar y avisar», con su motivo) sí tenía su botón desde el principio.
        //
        // El botón fija el estado ACÁ y no en el select: así el camino de la autorización de
        // jefatura —que corre justo abajo y mira `estado`+`fecha`— ve la misma intención, y
        // un vendedor confirmando una mantención sigue pasando por el jefe.
        $confirmando = $request->boolean('confirmar');
        if ($confirmando) {
            if (blank($data['fecha'] ?? null)) {
                throw ValidationException::withMessages([
                    'fecha' => 'Pon la fecha de la visita antes de confirmársela al cliente.',
                ]);
            }
            $data['estado'] = 'agendado';
        }

        // ═══ EL AGUJERO QUE ESTE CHEQUEO TAPA ═══
        //
        // Sin esto, la autorización era decorativa: el vendedor creaba la cita (que quedaba
        // esperando, en «Por coordinar»), apretaba «Coordinar», ponía la fecha y la guardaba
        // AGENDADA por este camino — sin que el jefe viera nada. Cualquier control que se pueda
        // saltar por la pantalla de al lado no es un control.
        //
        // Acá la edición SÍ se guarda (datos del cliente, descripción, servicio…): lo único
        // que se aparta es la parte que compromete al técnico —fecha, hora y técnico—, que
        // viaja a la aprobación y se aplica cuando el jefe la autoriza.
        if ($this->necesitaAutorizacion($request, $data)) {
            return $this->pedirAutorizacionAlEditar($request, $trabajo, $data);
        }

        // Estado ANTES de guardar, para decidir si hay que avisar al cliente.
        $antes = [
            'estado' => $trabajo->estado,
            'fecha' => $trabajo->fecha?->toDateString(),
            'hora' => $trabajo->hora_corta,
            'tecnico_id' => $trabajo->tecnico_id,
        ];

        $trabajo->update($data);

        $aviso = $this->avisarClienteSiCorresponde($trabajo->refresh(), $antes);
        // El MISMO snapshot sirve para el técnico: los tres cambios que le avisamos
        // al cliente (agendada / movida / anulada) son los tres que le cambian el
        // día al técnico.
        $trabajo->avisarAlTecnicoSiCorresponde($antes);

        // Una solicitud puede seguir sin fecha: se vuelve al mes actual.
        $destino = $trabajo->fecha ?? \App\Support\FechaNegocio::ahora();
        $params = ['anio' => $destino->year, 'mes' => $destino->month];
        if ($trabajo->fecha) {
            $params['dia'] = $trabajo->fecha->toDateString();
        }

        // El mensaje DICE QUE PASO CON EL CLIENTE. «Trabajo actualizado» no cierra nada: el
        // jefe de ventas necesita saber si el correo salió, a qué dirección, o por qué no.
        $status = match (true) {
            $aviso === 'enviado' => "Visita confirmada. Se le avisó a {$trabajo->cliente_email} por correo.",
            $aviso === 'fallo' => 'Visita confirmada, pero el correo NO salió: hay que llamar al cliente.',
            $confirmando => 'Visita confirmada, pero la solicitud no tiene correo del cliente: hay que llamarlo.',
            default => 'Trabajo actualizado.',
        };

        return redirect()->route('admin.agenda-terreno.index', $params)
            ->with('status', $status);
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
            // Repuestos usados: el técnico los registra al cerrar el trabajo.
            // SIN precio a propósito (no maneja precios) y sin efecto en stock: el
            // descuento sale de la factura del vendedor. Ver AgendaTrabajoRepuesto.
            'repuestos' => ['nullable', 'array'],
            'repuestos.*.nombre' => ['nullable', 'string', 'max:191'],
            'repuestos.*.sku' => ['nullable', 'string', 'max:191'],
            'repuestos.*.cantidad' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ]);

        $esCierreDeTerreno = $trabajo->estado === 'agendado'
            && in_array($data['estado'], AgendaTrabajo::ESTADOS_CIERRE, true);

        // El técnico industrial solo VE su agenda: lo único que puede hacer es
        // CERRAR un trabajo agendado, de las dos formas (hecho / no se pudo).
        if (! $request->user()->can('agendar servicio terreno') && ! $esCierreDeTerreno) {
            abort(403, 'Solo puedes cerrar un trabajo agendado (realizado o no realizado).');
        }

        // Al cerrar hay que CONTAR qué pasó, y es obligatorio a propósito (dueño
        // 14-08): el detalle paso a paso es lo que viaja en el aviso a ventas, y
        // un cierre sin explicación deja al vendedor llamando al técnico para
        // preguntarle. Se valida acá y no en las reglas de arriba porque depende
        // de la transición, no del campo.
        if ($esCierreDeTerreno && blank($data['notas_tecnico'] ?? null)) {
            throw ValidationException::withMessages([
                'notas_tecnico' => $data['estado'] === 'realizado'
                    ? 'Cuenta qué hiciste, paso a paso: es lo que le llega a ventas.'
                    : 'Cuenta por qué no se pudo hacer: es lo que le llega a ventas.',
            ]);
        }

        // Estado previo: lo necesita el aviso al técnico de más abajo (una cita que
        // se cancela DESPUÉS de estar agendada es la que no tiene que ir a hacer).
        $estadoAntes = $trabajo->estado;
        $tecnicoAntes = $trabajo->tecnico_id;

        $update = ['estado' => $data['estado']];
        if (array_key_exists('notas_tecnico', $data)) {
            $update['notas_tecnico'] = $data['notas_tecnico'];
        }
        $trabajo->update($update);

        // Repuestos: se guardan al CERRAR, de las dos formas. Un «no realizado»
        // también consume repuestos (el técnico abrió el equipo, cambió el filtro y
        // se quedó sin la membrana), y si solo se guardaran en el «realizado» ese
        // consumo no quedaría en ninguna parte. Se reemplazan los del trabajo; las
        // filas con nombre vacío se descartan.
        if ($esCierreDeTerreno && $request->has('repuestos')) {
            $trabajo->repuestos()->delete();
            foreach ($data['repuestos'] ?? [] as $r) {
                if (! empty($r['nombre'])) {
                    $trabajo->repuestos()->create([
                        'nombre' => $r['nombre'],
                        // Solo si vino del catálogo; en blanco = escrito a mano.
                        'sku' => blank($r['sku'] ?? null) ? null : $r['sku'],
                        'cantidad' => $r['cantidad'] ?? 1,
                    ]);
                }
            }
        }

        // Aviso a ventas POR LA ZONA (dueño 14-08): jefe de ventas + el vendedor
        // del cliente. Va después de guardar y no revierte el cierre si falla —
        // el trabajo ya se hizo (o no), y eso es lo que tiene que quedar.
        if ($esCierreDeTerreno) {
            $trabajo->refresh()->avisarCierre(
                $data['estado'] === 'realizado' ? 'terreno.realizado' : 'terreno.no_realizado'
            );
        }

        // Si le CANCELARON un trabajo que ya estaba agendado, el técnico tiene que
        // saberlo antes de manejar hasta allá. El propio técnico cerrando su
        // trabajo no se auto-avisa: el modelo solo mira agendado → cancelado.
        $trabajo->refresh()->avisarAlTecnicoSiCorresponde([
            'estado' => $estadoAntes,
            'fecha' => $trabajo->fecha?->toDateString(),
            'hora' => $trabajo->hora_corta,
            'tecnico_id' => $tecnicoAntes,
        ]);

        return back()->with('status', "Trabajo de {$trabajo->cliente_nombre} marcado como {$trabajo->estado_label}.");
    }

    /**
     * Rechaza una solicitud/trabajo con un MOTIVO (técnico de vacaciones, equipo
     * de otra marca, atraso de pagos, etc.): la marca 'cancelado', guarda el
     * motivo, avisa al CLIENTE por correo (variante 'anulada' con el motivo) y al
     * EQUIPO por M15. Es la cara "no" del flujo de coordinación (misma vía que el
     * "sí"). Los avisos son secundarios: un fallo no revierte el rechazo.
     */
    public function rechazar(Request $request, AgendaTrabajo $trabajo): RedirectResponse
    {
        $data = $request->validate([
            'motivo' => ['required', Rule::in(array_keys(AgendaTrabajo::MOTIVOS_CANCELACION))],
            'motivo_otro' => ['nullable', 'required_if:motivo,otro', 'string', 'max:191'],
        ]);

        $texto = $data['motivo'] === 'otro'
            ? trim((string) $data['motivo_otro'])
            : AgendaTrabajo::MOTIVOS_CANCELACION[$data['motivo']];

        $trabajo->update(['estado' => 'cancelado', 'motivo_cancelacion' => $texto]);

        // Se guarda si el aviso al cliente SALIÓ de verdad: sin esto, tanto el aviso
        // interno como el mensaje de esta pantalla afirmaban que se le avisó incluso
        // cuando el correo falló o el cliente no tenía correo — y nadie lo llamaba.
        $avisadoAlCliente = false;

        if (filled($trabajo->cliente_email)) {
            try {
                Mail::to($trabajo->cliente_email)->send(new AgendaTrabajoAviso($trabajo, 'anulada'));
                $avisadoAlCliente = true;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        try {
            $trabajo->avisarRechazoInterno($request->user()?->name, $avisadoAlCliente);
        } catch (\Throwable $e) {
            report($e);
        }

        $aviso = $avisadoAlCliente
            ? 'se le avisó al cliente por correo'
            : 'NO se pudo avisar al cliente por correo: contáctalo por teléfono';

        return redirect()->route('admin.agenda-terreno.index')
            ->with('status', "Solicitud de {$trabajo->cliente_nombre} rechazada ({$texto}); {$aviso}.");
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

    /**
     * Autocompletado de repuestos para el cierre del trabajo en terreno.
     *
     * ES UN ENDPOINT APARTE del de servicio técnico A PROPÓSITO, y no por el
     * permiso (que también): el del taller devuelve `precio`, y el técnico
     * industrial NO maneja precios (dueño 14-08-2026). Reusarlo dejaría el precio
     * viajando al navegador aunque ninguna pantalla lo pinte —visible en la
     * pestaña de red— y bastaría un `x-text` de más para que apareciera. Acá el
     * precio no se consulta, así que no hay nada que filtrar en la vista.
     *
     * Dos fuentes, como en el taller: el catálogo (que es el que trae el CÓDIGO,
     * lo único que el vendedor necesita para facturar sin preguntar) y el
     * historial de lo ya usado en terreno (nombres, sin código: cubre el repuesto
     * que se escribe a mano una y otra vez).
     */
    public function buscarRepuesto(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $catalogo = Producto::query()
            ->where(fn (Builder $w) => $w
                ->where('sku', 'like', "%{$q}%")
                ->orWhere('nombre', 'like', "%{$q}%"))
            ->orderBy('sku')
            ->limit(10)
            ->get(['id', 'sku', 'nombre'])
            ->map(fn (Producto $p) => ['nombre' => $p->nombre, 'sku' => $p->sku]);

        $historial = AgendaTrabajoRepuesto::query()
            ->where('nombre', 'like', "%{$q}%")
            ->whereNull('sku')
            ->distinct()
            ->orderBy('nombre')
            ->limit(10)
            ->pluck('nombre')
            ->map(fn (string $nombre) => ['nombre' => $nombre, 'sku' => null]);

        return response()->json(
            $catalogo->concat($historial)->unique('nombre')->take(12)->values()
        );
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
    /**
     * @return 'enviado'|'fallo'|null  qué pasó con el correo al cliente (null = no correspondía)
     */
    private function avisarClienteSiCorresponde(AgendaTrabajo $trabajo, array $antes): ?string
    {
        if (blank($trabajo->cliente_email)) {
            return null;
        }

        if ($trabajo->estado === 'agendado' && $trabajo->fecha) {
            if ($antes['estado'] !== 'agendado') {
                return $this->avisarClienteDeCita($trabajo, 'agendada');
            }

            if ($antes['fecha'] !== $trabajo->fecha?->toDateString()
                || $antes['hora'] !== $trabajo->hora_corta
                || $antes['tecnico_id'] !== $trabajo->tecnico_id) {
                return $this->avisarClienteDeCita($trabajo, 'reprogramada');
            }

            return null;
        }

        // CANCELAR UNA SOLICITUD TAMBIÉN AVISA. Antes se exigía que viniera de 'agendado', así
        // que una solicitud del QR cancelada acá se apagaba en silencio: el cliente que la
        // pidió no se enteraba nunca. Y el cartel de la pantalla ya prometía el aviso.
        if ($trabajo->estado === 'cancelado' && $antes['estado'] !== 'cancelado') {
            return $this->avisarClienteDeCita($trabajo, 'anulada');
        }

        return null;
    }

    /**
     * Manda el correo al cliente (agendada/reprogramada abren el link de
     * confirmación reseteando la respuesta previa; anulada no). Secundario: un
     * fallo de correo no debe tumbar el guardado del trabajo.
     */
    /**
     * El aviso vive en el MODELO (`AgendaTrabajo::avisarAlCliente`): hay dos caminos que
     * llegan a avisar —este, y el jefe de ventas autorizando una cita días después— y con la
     * lógica acá adentro el camino diferido salía sin avisarle a nadie.
     */
    private function avisarClienteDeCita(AgendaTrabajo $trabajo, string $motivo): string
    {
        return $trabajo->avisarAlCliente($motivo) ? 'enviado' : 'fallo';
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
            // Disponibilidad del cliente (cuándo puede/no): la escribe en el QR y
            // quien coordina la puede ajustar tras hablar con él.
            'disponibilidad' => ['nullable', 'string', 'max:1000'],
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
