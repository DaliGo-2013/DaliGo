<?php

namespace App\Http\Controllers\Produccion;

use App\Http\Controllers\Controller;
use App\Models\Maquina;
use App\Models\ProduccionMejora;
use App\Models\ProduccionParada;
use App\Models\ProduccionRegistro;
use App\Models\ProduccionReporte;
use App\Models\TipoBotellon;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MiProduccionController extends Controller
{
    /**
     * Lista de producciones del dia del soplador autenticado. Un soplador puede
     * tener varias el mismo dia; cada una se reporta por separado (mi.show).
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        // Día de NEGOCIO (P-TZ-01): el turno noche seguía viendo su producción
        // a las 22:00 Chile — el "hoy" UTC ya era mañana y la lista se vaciaba.
        $hoy = \App\Support\FechaNegocio::hoy();

        $reportes = ProduccionReporte::where('soplador_id', $user->id)
            ->whereDate('fecha', $hoy)
            ->with('asignacion.preforma')
            ->withCount('registros')
            ->orderBy('id')
            ->get();

        // Devueltos de OTROS dias (los de hoy ya salen en la lista de arriba),
        // para que un reporte por corregir de ayer no se pierda.
        $devueltos = ProduccionReporte::where('soplador_id', $user->id)
            ->where('estado', ProduccionReporte::DEVUELTO)
            ->whereDate('fecha', '!=', $hoy)
            ->orderByDesc('fecha')
            ->get();

        return view('produccion.mis-producciones', [
            'reportes' => $reportes,
            'devueltos' => $devueltos,
        ]);
    }

    /**
     * Historial propio: que produjo dia por dia. Por defecto los ultimos
     * ProduccionReporte::HISTORIAL_DIAS dias (hoy incluido); el operario puede
     * cambiar el rango con desde/hasta. Solo lectura: el detalle de un dia reusa
     * mi.show, cuyo abort_unless de propiedad ya cubre cualquier fecha.
     */
    public function historial(Request $request): View
    {
        $user = $request->user();
        // Dia de NEGOCIO (P-TZ-01): a las 22:00 de Chile el "hoy" UTC ya es
        // manana y la ventana se correria un dia.
        $hoy = \App\Support\FechaNegocio::ahora()->startOfDay();

        // 45 dias DISTINTOS incluyendo hoy: los whereDate >= / <= son inclusivos
        // en ambos bordes, asi que la cuenta es (hasta - desde + 1).
        $desde = $this->fechaDelFiltro($request, 'desde')
            ?? $hoy->copy()->subDays(ProduccionReporte::HISTORIAL_DIAS - 1);
        $hasta = $this->fechaDelFiltro($request, 'hasta') ?? $hoy->copy();

        // Rango invertido: se ORDENA, no se rechaza (una pantalla de planta no
        // muestra errores de validacion por un query string).
        if ($desde->gt($hasta)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        // Techo en hoy: sin esto un 'hasta' futuro (alcanzable desde el propio
        // date picker) empujaba el piso del clamp de abajo y dejaba la ventana
        // ENTERA en el futuro => lista vacia sin explicacion (gate R-31).
        if ($hasta->gt($hoy)) {
            $hasta = $hoy->copy();
        }

        // Ventana absurda: se recorta conservando lo mas reciente. Se compara por
        // fecha (no diffInDays: en Carbon el diff es float y con signo).
        $piso = $hasta->copy()->subDays(ProduccionReporte::HISTORIAL_DIAS_MAX - 1);
        if ($desde->lt($piso)) {
            $desde = $piso;
        }

        // whereDate en los DOS bordes, JAMAS whereBetween: la columna 'fecha' es
        // cast date y se guarda "Y-m-d 00:00:00", asi que el borde superior del
        // between se escapa (bitacora 2026-07-01 y su reincidencia del 07-02, que
        // fue en este mismo historial pero del lado del jefe).
        // El where('soplador_id') es el aislamiento: jamas se lee un id de la URL.
        $reportes = ProduccionReporte::where('soplador_id', $user->id)
            ->whereDate('fecha', '>=', $desde->toDateString())
            ->whereDate('fecha', '<=', $hasta->toDateString())
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        // Sin with(): la fila usa solo columnas denormalizadas y accessors. El
        // with(['registros...']) del historial admin aqui seria peso muerto
        // (cientos de tandas cargadas para nada en 45 dias).
        $totales = [
            'vendibles' => (int) $reportes->sum(fn (ProduccionReporte $r) => $r->producido),
            'merma' => (int) $reportes->sum(fn (ProduccionReporte $r) => $r->merma),
            'turnos' => $reportes->count(),
        ];

        return view('produccion.mi-historial', [
            'reportes' => $reportes,
            'totales' => $totales,
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'hoy' => $hoy->toDateString(),
            'esDefault' => ! $request->hasAny(['desde', 'hasta']),
        ]);
    }

    /**
     * Fecha del filtro, tolerante: solo Y-m-d REAL (lo que emite un
     * <input type="date">); cualquier otra cosa cae al default, nunca a un 500.
     *
     * Se valida con regex propia + ROUND-TRIP, no con Carbon::hasFormat ni con
     * $request->date(), porque ambos dejan pasar basura (gate R-31, 2026-07-24):
     *  - hasFormat es un match de regex y su patron de 'Y' acepta 5 digitos
     *    ('99999-01-01'), pero el 'Y' de createFromFormat consume 4 => lanzaba
     *    InvalidFormatException("The separation symbol could not be found") = 500.
     *  - un dia inexistente ('2026-02-31') pasa el regex y createFromFormat lo
     *    DESBORDA callado a 2026-03-03: el operario consultaba un rango que no
     *    pidio. El round-trip (format('Y-m-d') === $valor) lo descarta.
     *  - $request->date() hace Carbon::parse() y con basura tambien revienta.
     * El try/catch es el cinturon final: ninguna entrada de la URL debe poder
     * tumbar una pantalla de planta.
     */
    private function fechaDelFiltro(Request $request, string $campo): ?Carbon
    {
        $valor = $request->query($campo);

        if (! is_string($valor) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
            return null;
        }

        try {
            // '!' resetea la hora a 00:00 (sin el, toma la hora actual).
            $fecha = Carbon::createFromFormat('!Y-m-d', $valor, config('daligo.tz_negocio'));
        } catch (\Throwable) {
            return null;
        }

        // Round-trip: descarta los desbordes de mes/dia (2026-02-31 -> 03-03).
        return $fecha->format('Y-m-d') === $valor ? $fecha : null;
    }

    /**
     * Un reporte propio especifico (ej. uno devuelto de un dia anterior).
     */
    public function show(Request $request, ProduccionReporte $reporte): View
    {
        abort_unless($reporte->soplador_id === $request->user()->id, 403);

        return $this->vistaReporte($request->user(), $reporte);
    }

    /**
     * Agrega una tanda de produccion (maquina + tipo + cantidades) al reporte.
     * Append-only: cada tanda es una fila nueva; los totales del reporte se
     * recalculan en la misma transaccion.
     */
    public function registroStore(Request $request, ProduccionReporte $reporte): RedirectResponse|JsonResponse
    {
        abort_unless($reporte->soplador_id === $request->user()->id, 403);
        abort_unless($reporte->editablePorSoplador(), 403, 'Este reporte ya no se puede editar.');

        // Las mismas listas que ve el soplador en pantalla (sincronia por
        // construccion entre el selector y la validacion).
        $maquinas = Maquina::paraSoplador($request->user());
        $tipos = TipoBotellon::activos()->get();

        // Los select de motivo mandan '' cuando no aplican; normalizar a null
        // para que 'nullable' los deje pasar sin chocar con Rule::in.
        $request->merge([
            'motivo_segunda' => $request->filled('motivo_segunda') ? $request->input('motivo_segunda') : null,
            'motivo_malo' => $request->filled('motivo_malo') ? $request->input('motivo_malo') : null,
        ]);

        $validated = $request->validate([
            // Idempotencia de la cola offline (P-SPK-02): el cliente genera este
            // UUID por tanda; si el drenado reintenta, el mismo UUID no duplica.
            'cliente_uuid' => ['nullable', 'uuid'],
            'maquina_id' => [$maquinas->isEmpty() ? 'nullable' : 'required', Rule::in($maquinas->pluck('id'))],
            'tipo_botellon_id' => [$tipos->isEmpty() ? 'nullable' : 'required', Rule::in($tipos->pluck('id'))],
            // max como guardia anti-dedazo (un cero de mas ensucia el kardex).
            'primera' => ['required', 'integer', 'min:0', 'max:100000'],
            'segunda' => ['required', 'integer', 'min:0', 'max:100000'],
            'malo' => ['required', 'integer', 'min:0', 'max:100000'],
            'danada' => ['required', 'integer', 'min:0', 'max:100000'],
            'motivo_segunda' => ['nullable', Rule::in(ProduccionRegistro::motivosSegunda())],
            'motivo_malo' => ['nullable', Rule::in(ProduccionRegistro::motivosMalas())],
        ], [
            '*.max' => 'La cantidad es demasiado grande; revisa el número ingresado.',
            'maquina_id.required' => 'Selecciona la máquina en la que trabajaste.',
            'maquina_id.in' => 'Selecciona una máquina válida.',
            'tipo_botellon_id.required' => 'Selecciona el tipo de botellón.',
            'tipo_botellon_id.in' => 'Selecciona un tipo de botellón válido.',
            'motivo_segunda.in' => 'Selecciona un motivo válido para las de segunda.',
            'motivo_malo.in' => 'Selecciona un motivo válido para las malas.',
        ]);

        // Reglas de negocio como ValidationException (no back()->withErrors) para
        // que el drenado de la cola offline (fetch con Accept: json) reciba un
        // 422 real y las clasifique como permanentes; en web dan el mismo
        // redirect-con-errores de siempre.
        if (($validated['primera'] + $validated['segunda'] + $validated['malo'] + $validated['danada']) <= 0) {
            throw ValidationException::withMessages(['primera' => 'Ingresa al menos una cantidad antes de agregar.']);
        }
        // Si hay defectuosas, exigir su motivo (el select solo aparece con
        // cantidad > 0, asi que esto cubre el envio sin elegir).
        if ($validated['segunda'] > 0 && blank($validated['motivo_segunda'])) {
            throw ValidationException::withMessages(['motivo_segunda' => 'Indica el motivo de las de segunda.']);
        }
        if ($validated['malo'] > 0 && blank($validated['motivo_malo'])) {
            throw ValidationException::withMessages(['motivo_malo' => 'Indica el motivo de las malas.']);
        }

        // Sin cantidad no hay motivo que guardar (descarta un select tocado y
        // luego devuelto a 0).
        if ($validated['segunda'] == 0) {
            $validated['motivo_segunda'] = null;
        }
        if ($validated['malo'] == 0) {
            $validated['motivo_malo'] = null;
        }

        DB::transaction(function () use ($reporte, $validated) {
            // Lock pesimista del reporte: serializa el ciclo crear-tanda → recalcular
            // SUM ante doble POST concurrente (doble tap / reintento en el celular),
            // evitando que el total denormalizado quede corto. No-op inofensivo en SQLite.
            ProduccionReporte::whereKey($reporte->getKey())->lockForUpdate()->first();

            // Idempotencia: dentro del lock, si esta tanda (cliente_uuid) ya se
            // registro en este reporte, no crear otra. El unique compuesto
            // [reporte_id, cliente_uuid] es la red de seguridad final. UUID null
            // (camino nativo con señal) siempre crea.
            $uuid = $validated['cliente_uuid'] ?? null;
            if ($uuid && $reporte->registros()->where('cliente_uuid', $uuid)->exists()) {
                return;
            }

            $reporte->registros()->create($validated);
            $reporte->recalcularDesdeRegistros();
        });

        // El drenado de la cola offline espera JSON; el submit nativo, el redirect.
        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->to($this->rutaDelReporte($reporte))
            ->with('status', 'Producción agregada al reporte.');
    }

    /**
     * Elimina una tanda del reporte (correccion de errores del soplador).
     */
    public function registroDestroy(Request $request, ProduccionReporte $reporte, ProduccionRegistro $registro): RedirectResponse
    {
        abort_unless($reporte->soplador_id === $request->user()->id, 403);
        abort_unless($reporte->editablePorSoplador(), 403, 'Este reporte ya no se puede editar.');
        abort_unless($registro->reporte_id === $reporte->id, 404);

        DB::transaction(function () use ($reporte, $registro) {
            ProduccionReporte::whereKey($reporte->getKey())->lockForUpdate()->first();
            $registro->delete();
            $reporte->recalcularDesdeRegistros();
        });

        return redirect()->to($this->rutaDelReporte($reporte))
            ->with('status', 'Registro eliminado.');
    }

    /**
     * Registra una parada del turno: que detuvo la produccion (motivo
     * tipificado), de que recurso (maquina|operario) y entre que horas.
     * Mismo contrato que registroStore: sirve al submit nativo y al drenado
     * de la cola offline (cliente_uuid idempotente; 403/422 = permanentes).
     */
    public function paradaStore(Request $request, ProduccionReporte $reporte): RedirectResponse|JsonResponse
    {
        abort_unless($reporte->soplador_id === $request->user()->id, 403);
        abort_unless($reporte->editablePorSoplador(), 403, 'Este reporte ya no se puede editar.');

        $maquinas = Maquina::paraSoplador($request->user());

        // Campos PREFIJADOS parada_*: este form convive en el mismo Blade con
        // el de la tanda y el de envio; sin prefijo, un rechazo del servidor
        // contaminaria old('motivo')/old('maquina_id') de los otros forms (el
        // peor caso precargaba el motivo de la parada como texto de "Otro" en
        // el motivo de diferencia y terminaba mutando reporte.motivo).
        $validated = $request->validate([
            'cliente_uuid' => ['nullable', 'uuid'],
            'parada_maquina_id' => [$maquinas->isEmpty() ? 'nullable' : 'required', Rule::in($maquinas->pluck('id'))],
            'parada_motivo' => ['required', Rule::in(ProduccionParada::motivos())],
            'parada_origen' => ['required', Rule::in(ProduccionParada::ORIGENES)],
            'parada_inicio' => ['required', 'date_format:H:i'],
            // Cronologia: fin >= inicio (candado 2 del dictado). Una parada
            // que cruza la medianoche se deja ABIERTA: el cierre al enviar le
            // pone la hora real y la duracion modulo-1440 la endereza.
            'parada_fin' => ['nullable', 'date_format:H:i', 'after_or_equal:parada_inicio'],
        ], [
            'parada_maquina_id.required' => 'Selecciona la máquina de la parada.',
            'parada_maquina_id.in' => 'Selecciona una máquina válida.',
            'parada_motivo.required' => 'Selecciona qué detuvo la producción.',
            'parada_motivo.in' => 'Selecciona un motivo válido.',
            'parada_origen.required' => 'Indica qué se detuvo: la máquina o el operario.',
            'parada_origen.in' => 'Indica qué se detuvo: la máquina o el operario.',
            'parada_inicio.required' => 'Indica a qué hora empezó la parada.',
            'parada_inicio.date_format' => 'La hora de inicio no es válida.',
            'parada_fin.date_format' => 'La hora de término no es válida.',
            'parada_fin.after_or_equal' => 'El término no puede ser antes del inicio. Si la parada cruzó la medianoche, déjala abierta.',
        ]);

        DB::transaction(function () use ($reporte, $validated) {
            // Lock del reporte como serializador del check-then-act del uuid
            // (doble tap / reintento del drenado); el unique compuesto
            // [reporte_id, cliente_uuid] es la red final. Aqui no hay totales
            // que recalcular (a diferencia de las tandas).
            ProduccionReporte::whereKey($reporte->getKey())->lockForUpdate()->first();

            $uuid = $validated['cliente_uuid'] ?? null;
            if ($uuid && $reporte->paradas()->where('cliente_uuid', $uuid)->exists()) {
                return;
            }

            $reporte->paradas()->create([
                'cliente_uuid' => $uuid,
                'maquina_id' => $validated['parada_maquina_id'] ?? null,
                'motivo' => $validated['parada_motivo'],
                // La clase la deriva SIEMPRE el servidor a partir del motivo;
                // el request no puede imponerla.
                'clase' => ProduccionParada::claseDe($validated['parada_motivo']),
                'origen' => $validated['parada_origen'],
                'inicio' => $validated['parada_inicio'],
                'fin' => $validated['parada_fin'] ?? null,
            ]);
        });

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->to($this->rutaDelReporte($reporte))
            ->with('status', 'Parada registrada.');
    }

    /**
     * Elimina una parada del reporte (correccion del soplador).
     */
    public function paradaDestroy(Request $request, ProduccionReporte $reporte, ProduccionParada $parada): RedirectResponse
    {
        abort_unless($reporte->soplador_id === $request->user()->id, 403);
        abort_unless($reporte->editablePorSoplador(), 403, 'Este reporte ya no se puede editar.');
        abort_unless($parada->reporte_id === $reporte->id, 404);

        // Sin transaccion ni lock: no hay totales denormalizados que
        // recalcular (el lock de registroDestroy existe por eso).
        $parada->delete();

        return redirect()->to($this->rutaDelReporte($reporte))
            ->with('status', 'Parada eliminada.');
    }

    /**
     * Propuesta de mejora del soplador (P-M11-23, kaizen). NO cuelga de un
     * reporte a proposito: se puede proponer incluso sin asignacion del dia.
     * Mismo contrato que paradaStore para la cola offline (cliente_uuid
     * idempotente; 403/422 = permanentes). El autor SIEMPRE es el usuario
     * autenticado: un payload manipulado no propone a nombre de otro.
     */
    public function mejoraStore(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'cliente_uuid' => ['nullable', 'uuid'],
            'texto' => ['required', 'string', 'max:191'],
        ], [
            'texto.required' => 'Escribe tu propuesta antes de enviarla.',
            'texto.max' => 'La propuesta no puede superar los 191 caracteres.',
        ]);

        $user = $request->user();

        DB::transaction(function () use ($user, $validated) {
            // Lock de la fila del User como serializador del check-then-act
            // del uuid (la mejora no tiene fila padre natural; el usuario es
            // el ancla — mismo idioma que el lock del reporte en paradaStore).
            // El unique [soplador_id, cliente_uuid] es la red final.
            User::whereKey($user->id)->lockForUpdate()->first();

            $uuid = $validated['cliente_uuid'] ?? null;
            if ($uuid && ProduccionMejora::where('soplador_id', $user->id)->where('cliente_uuid', $uuid)->exists()) {
                return;
            }

            ProduccionMejora::create([
                'soplador_id' => $user->id,
                'cliente_uuid' => $uuid,
                'texto' => $validated['texto'],
                'estado' => ProduccionMejora::PENDIENTE,
            ]);
        });

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('status', 'Propuesta enviada. El jefe la verá en su panel.');
    }

    /**
     * Guarda motivo/observaciones y envia el reporte (segun el flag 'enviar').
     * Las cantidades ya no entran por aqui: viven en los registros (tandas).
     */
    public function update(Request $request, ProduccionReporte $reporte): RedirectResponse
    {
        abort_unless($reporte->soplador_id === $request->user()->id, 403);
        abort_unless($reporte->editablePorSoplador(), 403, 'Este reporte ya no se puede editar.');

        // El motivo de la diferencia llega por chips tocables; el chip "Otro"
        // viaja como centinela y el texto real en motivo_otro. Resolver a un
        // unico string antes de validar y normalizar '' -> null (un chip oculto
        // o no elegido manda ''), para que 'nullable' y la regla de motivo
        // requerido cuando hay diferencia funcionen igual que antes.
        $motivo = $request->input('motivo');
        if ($motivo === ProduccionReporte::MOTIVO_OTRO) {
            $motivo = $request->filled('motivo_otro') ? trim((string) $request->input('motivo_otro')) : null;
        }
        $request->merge(['motivo' => blank($motivo) ? null : $motivo]);

        // cavidades_activas ya NO se acepta del request (MIPROD-2, pedido del
        // dueño 21-08: el selector se retiró de la pantalla). Al no validarse,
        // fill() no la toca: el valor histórico de un reporte queda intacto.
        $validated = $request->validate([
            'motivo' => ['nullable', 'string', 'max:255'],
            'obs' => ['nullable', 'string', 'max:1000'],
        ]);

        $enviar = $request->boolean('enviar');

        if ($enviar) {
            if ($reporte->total <= 0) {
                return back()->withInput()
                    ->withErrors(['enviar' => 'Agrega al menos una tanda de producción antes de enviar.']);
            }
            // Solo cuando FALTÓ (producido < asignado): producir de más no se
            // explica — los motivos de la lista son de faltante (dueño 02-09).
            if ($reporte->falto && blank($validated['motivo'] ?? null)) {
                return back()->withInput()
                    ->withErrors(['motivo' => 'Indica por qué faltó producción respecto de lo asignado.']);
            }
        }

        $reporte->fill($validated);

        if ($enviar) {
            $reporte->estado = ProduccionReporte::ENVIADO;
            $reporte->enviado_at = now();
            // El motivo de una devolucion anterior ya se atendio al re-enviar.
            $reporte->devuelto_motivo = null;
        }

        DB::transaction(function () use ($reporte, $enviar) {
            $reporte->save();

            // Una parada sin fin no bloquea el envio: se cierra con el
            // reporte a la hora chilena del momento y queda marcada. En la
            // misma transaccion que el save: sin ella, un fallo intermedio
            // dejaria paradas "cerradas al envio" en un reporte aun borrador.
            if ($enviar) {
                $reporte->paradas()->whereNull('fin')->update([
                    'fin' => \App\Support\FechaNegocio::ahora()->format('H:i'),
                    'cerrada_al_envio' => true,
                ]);
            }
        });

        // Al enviar, volver a la lista de producciones del dia (el reporte ya
        // queda en solo lectura y puede haber otra produccion que reportar). Al
        // solo guardar, quedarse en el reporte.
        $destino = $enviar ? route('produccion.mi.index') : route('produccion.mi.show', $reporte);

        return redirect()->to($destino)->with(
            'status',
            $enviar ? 'Reporte enviado. Queda a la espera de revision.' : 'Cambios guardados.',
        );
    }

    /**
     * Arma la vista del reporte (compartida entre index y show).
     */
    private function vistaReporte(User $user, ?ProduccionReporte $reporte): View
    {
        $reporte?->load([
            'asignacion.preforma',
            'registros' => fn ($query) => $query->latest('id'),
            'registros.maquina',
            'registros.tipoBotellon',
            'paradas' => fn ($query) => $query->latest('id'),
            'paradas.maquina',
        ]);

        $maquinas = Maquina::paraSoplador($user);
        $tipos = TipoBotellon::activos()->orderBy('nombre')->get();

        // Semaforo de preformas (P-M11-22): ¿el espejo de SU sucursal alcanza
        // para la meta? Null = silencio (sin preforma/espejo/sucursal).
        $semaforoPreformas = app(\App\Services\Produccion\SemaforoPreformas::class)
            ->estadoPara($reporte, $user);

        // Notas del jefe vigentes que le hablan a ESTE soplador (las suyas +
        // las globales). Se pintan como banner, no persiguen (sin M15).
        $notasJefe = \App\Models\ProduccionNota::vigentes()
            ->paraSoplador($user->id)
            ->orderByDesc('id')
            ->get();

        // Preseleccion pegajosa: la maquina/tipo de la ultima tanda del reporte.
        $ultimo = $reporte?->registros->first();

        // Reportes devueltos pendientes (de otros dias o turnos) que el
        // soplador no veria de otra forma.
        $devueltos = ProduccionReporte::where('soplador_id', $user->id)
            ->where('estado', ProduccionReporte::DEVUELTO)
            ->when($reporte, fn ($query) => $query->where('id', '!=', $reporte->id))
            ->orderByDesc('fecha')
            ->get();

        // Historial de MIS propuestas de mejora (P-M11-23): el estado y la
        // respuesta del jefe se ven aqui — y SOLO las del autor.
        $misMejoras = ProduccionMejora::where('soplador_id', $user->id)
            ->latest()
            ->limit(10)
            ->get();

        return view('produccion.mi-reporte', [
            'misMejoras' => $misMejoras,
            'reporte' => $reporte,
            'maquinas' => $maquinas,
            'tipos' => $tipos,
            'maquinaPreseleccionada' => (int) old('maquina_id', $ultimo?->maquina_id),
            'tipoPreseleccionado' => (int) old('tipo_botellon_id', $ultimo?->tipo_botellon_id),
            'devueltos' => $devueltos,
            'semaforoPreformas' => $semaforoPreformas,
            'notasJefe' => $notasJefe,
        ]);
    }

    /**
     * Tras agregar/eliminar una tanda, quedarse en el reporte (la pantalla de
     * llenado), no en la lista de producciones del dia.
     */
    /**
     * «Salir a colación» (dueño 02-09): abre una parada PLANIFICADA de origen
     * operario, sin máquina, con la hora de negocio del servidor. Idempotente:
     * con una colación ya abierta no crea otra. Requiere señal a propósito (la
     * pantalla apaga el botón offline): una hora servida horas después por la
     * cola sería falsa. Si olvida «Volví», el envío del reporte la cierra solo.
     */
    public function colacionSalir(Request $request, ProduccionReporte $reporte): RedirectResponse|JsonResponse
    {
        abort_unless($reporte->soplador_id === $request->user()->id, 403);
        abort_unless($reporte->editablePorSoplador(), 403, 'Este reporte ya no se puede editar.');

        $hora = \App\Support\FechaNegocio::ahora()->format('H:i');

        $abierta = DB::transaction(function () use ($reporte, $hora) {
            $reporte = ProduccionReporte::lockForUpdate()->findOrFail($reporte->id);
            $abierta = $reporte->colacionAbierta();
            if ($abierta) {
                return $abierta;
            }

            return $reporte->paradas()->create([
                'motivo' => ProduccionParada::MOTIVO_COLACION,
                'clase' => ProduccionParada::claseDe(ProduccionParada::MOTIVO_COLACION),
                'origen' => 'operario',
                'maquina_id' => null,
                'inicio' => $hora,
                'fin' => null,
            ]);
        });

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'desde' => $abierta->inicio_corta]);
        }

        return redirect()->to($this->rutaDelReporte($reporte))
            ->with('status', 'En colación desde las '.$abierta->inicio_corta.'.');
    }

    /** «Volví»: cierra la colación abierta con la hora de negocio. Sin abierta, no-op. */
    public function colacionVolver(Request $request, ProduccionReporte $reporte): RedirectResponse|JsonResponse
    {
        abort_unless($reporte->soplador_id === $request->user()->id, 403);
        abort_unless($reporte->editablePorSoplador(), 403, 'Este reporte ya no se puede editar.');

        $hora = \App\Support\FechaNegocio::ahora()->format('H:i');

        $cerrada = DB::transaction(function () use ($reporte, $hora) {
            $reporte = ProduccionReporte::lockForUpdate()->findOrFail($reporte->id);
            $abierta = $reporte->colacionAbierta();
            if (! $abierta) {
                return null;
            }
            $abierta->update(['fin' => $hora]);

            return $abierta;
        });

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'cerrada' => $cerrada !== null]);
        }

        return redirect()->to($this->rutaDelReporte($reporte))
            ->with('status', $cerrada
                ? 'De vuelta. Colación de '.$cerrada->duracion_label.'.'
                : 'No había una colación abierta.');
    }

    private function rutaDelReporte(ProduccionReporte $reporte): string
    {
        return route('produccion.mi.show', $reporte);
    }
}
