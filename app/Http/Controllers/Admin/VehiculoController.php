<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vehiculo;
use App\Services\Logistica\FlotaExcel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Flota de vehículos (módulo LOGÍSTICA, pedido del dueño 04-08-2026).
 *
 * Reemplaza la planilla «Control vehiculos». Lo que la planilla no puede hacer
 * y es el punto del módulo: el semáforo de vencimientos se calcula solo (nadie
 * pinta celdas a mano) y el aviso sale sin que alguien abra el archivo (ver
 * App\Console\Commands\VehiculosAvisarVencimientos).
 */
class VehiculoController extends Controller
{
    /**
     * Listado de la flota con el semáforo de documentos.
     *
     * El filtro por estado documental se resuelve EN PHP y no en SQL a
     * propósito: "vencido" es el peor de 5 fechas con una regla por tipo de
     * vehículo (el semirremolque no rinde emisiones), y expresar eso en un
     * WHERE lo duplicaría fuera del modelo. La flota son decenas de filas
     * —17 hoy—, así que traerlas todas cuesta una query y ~0 ms.
     * Si algún día fueran miles, el orden correcto es mover el semáforo a
     * columnas materializadas, no a un WHERE copiado.
     */
    public function index(Request $request): View
    {
        [$vehiculos, $filtros] = $this->filtrada($request);

        // El resumen se calcula sobre la flota ACTIVA completa (sin los filtros
        // de pantalla): es el estado de la flota, no el de la vista. Si el
        // conteo cambiara al filtrar, dejaría de servir como tablero.
        $resumen = $this->resumen(Vehiculo::activos()->get());

        return view('admin.vehiculos.index', [
            'vehiculos' => $vehiculos,
            'resumen' => $resumen,
            'estado' => $filtros['estado'],
            'doc' => $filtros['doc'],
            'base' => $filtros['base'],
            'q' => $filtros['q'],
            'bases' => Vehiculo::query()->whereNotNull('base')->distinct()->orderBy('base')->pluck('base'),
        ]);
    }

    /**
     * La flota como Excel (.xlsx), pedido del dueño 04-08-2026.
     *
     * Respeta los MISMOS filtros de la pantalla —es «descargar lo que estoy
     * viendo»— y el archivo escribe adentro cuál filtro se aplicó, así que un
     * Excel de 10 filas nunca se confunde con la flota entera.
     */
    public function excel(Request $request, FlotaExcel $excel): Response
    {
        [$vehiculos, $filtros] = $this->filtrada($request);

        return response($excel->generar($vehiculos, $this->descripcionFiltros($filtros)), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.FlotaExcel::nombreArchivo().'"',
            // El archivo se arma con los datos del momento: que no quede cacheado
            // ni en el navegador ni en un proxy, o «al día» deja de ser cierto.
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * Los filtros de la pantalla aplicados. Punto ÚNICO: el listado y el Excel
     * tienen que filtrar igual — si cada uno armara su query, la descarga
     * empezaría a diferir de lo que se ve, que es el defecto clásico de este
     * tipo de botón.
     *
     * El filtro por estado documental se resuelve EN PHP y no en SQL a
     * propósito: "vencido" es el peor de 5 fechas con una regla por tipo de
     * vehículo (el semirremolque no rinde emisiones), y expresar eso en un
     * WHERE lo duplicaría fuera del modelo. La flota son decenas de filas
     * —17 hoy—, así que traerlas todas cuesta una query y ~0 ms. Si algún día
     * fueran miles, el orden correcto es mover el semáforo a columnas
     * materializadas, no a un WHERE copiado.
     *
     * @return array{0: Collection<int, Vehiculo>, 1: array<string, string>}
     */
    private function filtrada(Request $request): array
    {
        $filtros = [
            'estado' => $request->string('estado')->toString(),
            'doc' => $request->string('doc')->toString(),
            'base' => $request->string('base')->toString(),
            'q' => $request->string('q')->toString(),
        ];

        $vehiculos = Vehiculo::query()
            ->buscar($filtros['q'])
            ->when(array_key_exists($filtros['estado'], Vehiculo::ESTADOS), fn ($query) => $query->where('estado', $filtros['estado']))
            ->when($filtros['base'] !== '', fn ($query) => $query->where('base', $filtros['base']))
            ->orderBy('estado')            // activos primero ('activo' < 'baja' < 'vendido' alfabéticamente)
            ->orderBy('ppu')
            ->get();

        if (in_array($filtros['doc'], [Vehiculo::DOC_VENCIDO, Vehiculo::DOC_POR_VENCER, Vehiculo::DOC_SIN_REGISTRO], true)) {
            $vehiculos = $vehiculos->filter(fn (Vehiculo $v) => $v->estado_documental === $filtros['doc'])->values();
        }

        return [$vehiculos, $filtros];
    }

    /**
     * Los filtros en palabras, para escribirlos DENTRO del Excel.
     *
     * @param  array<string, string>  $filtros
     */
    private function descripcionFiltros(array $filtros): string
    {
        $partes = [];

        if ($filtros['q'] !== '') {
            $partes[] = 'búsqueda «'.$filtros['q'].'»';
        }
        if (isset(Vehiculo::ESTADOS[$filtros['estado']])) {
            $partes[] = 'estado '.mb_strtolower(Vehiculo::ESTADOS[$filtros['estado']]);
        }
        if ($filtros['base'] !== '') {
            $partes[] = 'base '.$filtros['base'];
        }
        if (in_array($filtros['doc'], [Vehiculo::DOC_VENCIDO, Vehiculo::DOC_POR_VENCER, Vehiculo::DOC_SIN_REGISTRO], true)) {
            $partes[] = 'documentos: '.mb_strtolower(Vehiculo::estadoDocumentalLabel($filtros['doc']));
        }

        return implode(' · ', $partes);
    }

    public function show(Vehiculo $vehiculo): View
    {
        return view('admin.vehiculos.show', ['vehiculo' => $vehiculo]);
    }

    public function create(): View
    {
        return view('admin.vehiculos.create', ['vehiculo' => new Vehiculo(['estado' => Vehiculo::ESTADO_ACTIVO])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $vehiculo = Vehiculo::create($this->datosValidados($request));

        return redirect()->route('admin.vehiculos.show', $vehiculo)
            ->with('status', "Vehículo {$vehiculo->ppu} creado.");
    }

    public function edit(Vehiculo $vehiculo): View
    {
        return view('admin.vehiculos.edit', ['vehiculo' => $vehiculo]);
    }

    public function update(Request $request, Vehiculo $vehiculo): RedirectResponse
    {
        $vehiculo->update($this->datosValidados($request, $vehiculo));

        return redirect()->route('admin.vehiculos.show', $vehiculo)
            ->with('status', "Vehículo {$vehiculo->ppu} actualizado.");
    }

    /**
     * Elimina un vehículo cargado por error. La salida NORMAL de la flota es
     * dar de baja (estado vendido / baja), que conserva la historia: eliminar
     * es solo para una fila mal cargada.
     */
    public function destroy(Vehiculo $vehiculo): RedirectResponse
    {
        $ppu = $vehiculo->ppu;
        $vehiculo->delete();

        return redirect()->route('admin.vehiculos.index')
            ->with('status', "Vehículo {$ppu} eliminado.");
    }

    /**
     * Conteo por estado documental de la flota activa.
     *
     * @param  Collection<int, Vehiculo>  $activos
     * @return array<string, int>
     */
    private function resumen(Collection $activos): array
    {
        $resumen = [
            'total' => $activos->count(),
            Vehiculo::DOC_VENCIDO => 0,
            Vehiculo::DOC_POR_VENCER => 0,
            Vehiculo::DOC_AL_DIA => 0,
            Vehiculo::DOC_SIN_REGISTRO => 0,
        ];

        foreach ($activos as $vehiculo) {
            $estado = $vehiculo->estado_documental;
            if (isset($resumen[$estado])) {
                $resumen[$estado]++;
            }
        }

        return $resumen;
    }

    /**
     * Valida y normaliza el formulario.
     *
     * @return array<string, mixed>
     */
    private function datosValidados(Request $request, ?Vehiculo $vehiculo = null): array
    {
        // La patente se guarda SIEMPRE en mayúsculas y sin espacios: en la
        // planilla convivían "TJGW-15" y "TJGW15", y con eso el unique no
        // sirve (entran dos filas del mismo camión).
        $request->merge([
            'ppu' => strtoupper(trim(str_replace(' ', '', (string) $request->input('ppu')))),
        ]);

        $datos = $request->validate([
            'ppu' => ['required', 'string', 'max:12', Rule::unique('vehiculos', 'ppu')->ignore($vehiculo)],
            'alias' => ['nullable', 'string', 'max:191'],
            'marca' => ['nullable', 'string', 'max:60'],
            'modelo' => ['nullable', 'string', 'max:120'],
            'anio' => ['nullable', 'integer', 'min:1980', 'max:'.(now()->year + 1)],
            'tipo' => ['required', Rule::in(array_keys(Vehiculo::TIPOS))],
            'combustible' => ['nullable', Rule::in(array_keys(Vehiculo::COMBUSTIBLES))],
            'vin' => ['nullable', 'string', 'max:40'],
            'numero_motor' => ['nullable', 'string', 'max:40'],
            'cilindrada' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'pbv_kg' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'capacidad_carga_kg' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'presion_psi' => ['nullable', 'integer', 'min:0', 'max:500'],
            'base' => ['nullable', 'string', 'max:40'],
            'conductor_nombre' => ['nullable', 'string', 'max:191'],
            'estado' => ['required', Rule::in(array_keys(Vehiculo::ESTADOS))],
            // Sacar un vehículo de la flota EXIGE decir por qué. Es el mismo
            // criterio del traslado: sin responsable ni motivo, el registro no
            // prueba nada (en la planilla el motivo vivía escrito a mano en la
            // columna del chofer).
            'baja_motivo' => ['nullable', 'string', 'max:191', Rule::requiredIf(
                fn () => $request->input('estado') !== Vehiculo::ESTADO_ACTIVO
            )],
            'baja_at' => ['nullable', 'date'],
            'rt_vence' => ['nullable', 'date'],
            'emisiones_vence' => ['nullable', 'date'],
            'permiso_circulacion_vence' => ['nullable', 'date'],
            'soap_vence' => ['nullable', 'date'],
            'extintor_vence' => ['nullable', 'date'],
            'extintor_capacidad_kg' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ], [
            'ppu.unique' => 'Ya existe un vehículo con esa patente.',
            'baja_motivo.required' => 'Escribe por qué sale de la flota (venta, pérdida total, etc.).',
        ]);

        // Volver a "activo" limpia los datos de la baja: si no, un vehículo
        // reincorporado arrastraría para siempre un "Venta febrero 2023".
        if ($datos['estado'] === Vehiculo::ESTADO_ACTIVO) {
            $datos['baja_motivo'] = null;
            $datos['baja_at'] = null;
        }

        return $datos;
    }
}
