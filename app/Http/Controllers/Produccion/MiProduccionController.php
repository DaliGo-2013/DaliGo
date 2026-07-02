<?php

namespace App\Http\Controllers\Produccion;

use App\Http\Controllers\Controller;
use App\Models\Maquina;
use App\Models\ProduccionRegistro;
use App\Models\ProduccionReporte;
use App\Models\TipoBotellon;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
        $hoy = now()->toDateString();

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
    public function registroStore(Request $request, ProduccionReporte $reporte): RedirectResponse
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
            'maquina_id' => [$maquinas->isEmpty() ? 'nullable' : 'required', Rule::in($maquinas->pluck('id'))],
            'tipo_botellon_id' => [$tipos->isEmpty() ? 'nullable' : 'required', Rule::in($tipos->pluck('id'))],
            // max como guardia anti-dedazo (un cero de mas ensucia el kardex).
            'primera' => ['required', 'integer', 'min:0', 'max:100000'],
            'segunda' => ['required', 'integer', 'min:0', 'max:100000'],
            'malo' => ['required', 'integer', 'min:0', 'max:100000'],
            'danada' => ['required', 'integer', 'min:0', 'max:100000'],
            'motivo_segunda' => ['nullable', Rule::in(ProduccionRegistro::MOTIVOS_DEFECTO)],
            'motivo_malo' => ['nullable', Rule::in(ProduccionRegistro::MOTIVOS_DEFECTO)],
        ], [
            '*.max' => 'La cantidad es demasiado grande; revisa el número ingresado.',
            'maquina_id.required' => 'Selecciona la máquina en la que trabajaste.',
            'maquina_id.in' => 'Selecciona una máquina válida.',
            'tipo_botellon_id.required' => 'Selecciona el tipo de botellón.',
            'tipo_botellon_id.in' => 'Selecciona un tipo de botellón válido.',
            'motivo_segunda.in' => 'Selecciona un motivo válido para las de segunda.',
            'motivo_malo.in' => 'Selecciona un motivo válido para las malas.',
        ]);

        if (($validated['primera'] + $validated['segunda'] + $validated['malo'] + $validated['danada']) <= 0) {
            return back()->withInput()
                ->withErrors(['primera' => 'Ingresa al menos una cantidad antes de agregar.']);
        }

        // Si hay defectuosas, exigir su motivo (el select solo aparece con
        // cantidad > 0, asi que esto cubre el envio sin elegir).
        if ($validated['segunda'] > 0 && blank($validated['motivo_segunda'])) {
            return back()->withInput()
                ->withErrors(['motivo_segunda' => 'Indica el motivo de las de segunda.']);
        }
        if ($validated['malo'] > 0 && blank($validated['motivo_malo'])) {
            return back()->withInput()
                ->withErrors(['motivo_malo' => 'Indica el motivo de las malas.']);
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
            $reporte->registros()->create($validated);
            $reporte->recalcularDesdeRegistros();
        });

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
            if ($reporte->diferencia !== 0 && blank($validated['motivo'] ?? null)) {
                return back()->withInput()
                    ->withErrors(['motivo' => 'Indica el motivo de la diferencia con lo asignado.']);
            }
        }

        $reporte->fill($validated);

        if ($enviar) {
            $reporte->estado = ProduccionReporte::ENVIADO;
            $reporte->enviado_at = now();
            // El motivo de una devolucion anterior ya se atendio al re-enviar.
            $reporte->devuelto_motivo = null;
        }

        $reporte->save();

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
            'registros' => fn ($query) => $query->latest('id'),
            'registros.maquina',
            'registros.tipoBotellon',
        ]);

        $maquinas = Maquina::paraSoplador($user);
        $tipos = TipoBotellon::activos()->orderBy('nombre')->get();

        // Preseleccion pegajosa: la maquina/tipo de la ultima tanda del reporte.
        $ultimo = $reporte?->registros->first();

        // Reportes devueltos pendientes (de otros dias o turnos) que el
        // soplador no veria de otra forma.
        $devueltos = ProduccionReporte::where('soplador_id', $user->id)
            ->where('estado', ProduccionReporte::DEVUELTO)
            ->when($reporte, fn ($query) => $query->where('id', '!=', $reporte->id))
            ->orderByDesc('fecha')
            ->get();

        return view('produccion.mi-reporte', [
            'reporte' => $reporte,
            'maquinas' => $maquinas,
            'tipos' => $tipos,
            'maquinaPreseleccionada' => (int) old('maquina_id', $ultimo?->maquina_id),
            'tipoPreseleccionado' => (int) old('tipo_botellon_id', $ultimo?->tipo_botellon_id),
            'devueltos' => $devueltos,
        ]);
    }

    /**
     * Tras agregar/eliminar una tanda, quedarse en el reporte (la pantalla de
     * llenado), no en la lista de producciones del dia.
     */
    private function rutaDelReporte(ProduccionReporte $reporte): string
    {
        return route('produccion.mi.show', $reporte);
    }
}
