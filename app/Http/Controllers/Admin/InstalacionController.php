<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Instalacion;
use App\Models\Producto;
use App\Rules\RutChileno;
use App\Services\ServicioTecnico\InstalacionesExcel;
use App\Support\FechaNegocio;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Registro de instalaciones en terreno del técnico industrial (Carlos Tablante).
 * Plasma su Excel "INSTALACION DE MAQUINAS": listado editable de instalaciones/
 * puestas en marcha con datos comerciales. Lo usan el técnico industrial, jefes
 * de venta y admin (permiso 'gestionar instalaciones').
 */
class InstalacionController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->only(['q', 'categoria', 'anio', 'mes']);

        return view('admin.instalaciones.index', [
            'instalaciones' => $this->filtradas($request),
            'filtros' => $filtros,
            // Cards de navegacion del historial (Año → Mes) sobre el listado, mismo
            // patron que el listado de Servicio Tecnico: el registro es historico y
            // crece sin cota, asi que se entra por periodo en vez de scrollear.
            // Reemplazan al antiguo desplegable de "Año" (dos formas de filtrar lo
            // mismo se contradicen entre si).
            'historial' => $this->resumenHistorial($request->filled('anio') ? (int) $request->input('anio') : null),
            'categorias' => Instalacion::CATEGORIAS,
        ]);
    }

    public function create(): View
    {
        return view('admin.instalaciones.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['creado_por'] = $request->user()->name;

        $instalacion = Instalacion::create($data);

        return redirect()->route('admin.instalaciones.index')
            ->with('status', "Instalación registrada: {$instalacion->cliente_nombre} ({$instalacion->fecha->format('d-m-Y')}).");
    }

    public function edit(Instalacion $instalacion): View
    {
        return view('admin.instalaciones.edit', array_merge(['instalacion' => $instalacion], $this->formData()));
    }

    public function update(Request $request, Instalacion $instalacion): RedirectResponse
    {
        $instalacion->update($this->validateData($request));

        return redirect()->route('admin.instalaciones.index')
            ->with('status', "Instalación de {$instalacion->cliente_nombre} actualizada.");
    }

    public function destroy(Instalacion $instalacion): RedirectResponse
    {
        $instalacion->delete();

        return back()->with('status', 'Instalación eliminada del registro.');
    }

    /**
     * Autocompletado del cliente por RUT o razón social (JSON). Mismo contrato
     * que la agenda de terreno; al elegir se rellenan nombre/rut/comuna (editables).
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
            ->get(['id', 'rut', 'razon_social', 'ciudad']);

        return response()->json($clientes->map(fn (Cliente $c) => [
            'id' => $c->id,
            'rut' => $c->rut,
            'razon_social' => $c->razon_social,
            'ciudad' => $c->ciudad,
            'label' => ($c->rut ? $c->rut.' — ' : '').$c->razon_social,
        ]));
    }

    /**
     * El registro como Excel para compartir. Pedido del tecnico industrial
     * (13-08-2026): el detalle mes por mes de sus trabajos, porque con eso le
     * pagan las horas extras — de ahi que el archivo traiga una hoja que suma los
     * DIAS por mes y no solo la lista.
     *
     * Baja lo MISMO que se esta viendo (reusa consulta(), con sus filtros de
     * busqueda, categoria y periodo) pero COMPLETO: el listado pagina de 25 en 25
     * y un respaldo de pago cortado en la primera pagina seria una liquidacion
     * incompleta.
     */
    public function excel(Request $request): Response
    {
        $instalaciones = $this->consulta($request)
            ->orderBy('fecha')->orderBy('id')
            ->get();

        $periodo = $this->periodoLabel($request);

        return response((new InstalacionesExcel)->generar($instalaciones, $periodo), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.InstalacionesExcel::nombreArchivo($periodo).'"',
        ]);
    }

    // --- Helpers --------------------------------------------------------

    /**
     * Rotulo del periodo que se esta viendo, para el nombre del archivo y su
     * linea de resumen: «Julio 2026», «Año 2026» o todo el registro.
     */
    private function periodoLabel(Request $request): string
    {
        $anio = $request->filled('anio') ? (int) $request->input('anio') : null;
        $mes = $request->filled('mes') ? (int) $request->input('mes') : null;

        if ($anio === null) {
            return 'Registro completo';
        }

        return $mes
            ? ucfirst(Carbon::create($anio, $mes, 1)->locale('es')->translatedFormat('F Y'))
            : 'Año '.$anio;
    }

    /**
     * @return LengthAwarePaginator<Instalacion>
     */
    private function filtradas(Request $request): LengthAwarePaginator
    {
        return $this->consulta($request)
            ->latest('fecha')->latest('id')
            ->paginate(25)
            ->withQueryString();
    }

    /**
     * El listado con sus filtros (busqueda, categoria, periodo) SIN ordenar ni
     * paginar. Vive aparte de filtradas() porque el Excel exporta EXACTAMENTE lo
     * que se esta viendo: con el criterio escrito dos veces, el archivo diria una
     * cosa distinta que la pantalla el dia que uno de los dos cambie.
     *
     * @return Builder<Instalacion>
     */
    private function consulta(Request $request): Builder
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:191'],
            'categoria' => ['nullable', Rule::in(Instalacion::CATEGORIAS)],
            // Periodo del historial (cards Año → Mes del listado).
            'anio' => ['nullable', 'integer', 'between:2020,2100'],
            'mes' => ['nullable', 'integer', 'between:1,12'],
        ]);

        $q = trim((string) ($f['q'] ?? ''));

        return Instalacion::query()
            ->when($q !== '', fn (Builder $b) => $b->where(fn (Builder $w) => $w
                ->where('cliente_nombre', 'like', "%{$q}%")
                ->orWhere('cliente_rut', 'like', "%{$q}%")
                ->orWhere('producto', 'like', "%{$q}%")
                ->orWhere('n_factura', 'like', "%{$q}%")
                ->orWhere('vendedor', 'like', "%{$q}%")))
            ->when($f['categoria'] ?? null, fn (Builder $b, $v) => $b->where('categoria', $v))
            // Periodo Año/Mes: rango de fechas con whereDate en ambos bordes, igual
            // que el listado de Servicio Tecnico. Portable (MySQL 5.7 / SQLite) y usa
            // el indice, a diferencia del whereYear() que habia antes: envolver la
            // columna en una funcion impide usar el indice de `fecha`.
            ->when($f['anio'] ?? $f['mes'] ?? null, function (Builder $b) use ($f) {
                $anio = (int) ($f['anio'] ?? FechaNegocio::ahora()->year);
                $mes = isset($f['mes']) ? (int) $f['mes'] : null;
                $desde = Carbon::create($anio, $mes ?? 1, 1);
                $hasta = $mes ? $desde->copy()->endOfMonth() : $desde->copy()->endOfYear();
                $b->whereDate('fecha', '>=', $desde->toDateString())
                    ->whereDate('fecha', '<=', $hasta->toDateString());
            });
    }

    /**
     * Resumen para las cards de navegacion del historial (Año → Mes).
     *
     * Agregado en SQL y no en PHP: `fecha` se guarda 'YYYY-MM-DD' y SUBSTR es
     * identico en MySQL y SQLite (a diferencia de YEAR()/EXTRACT, que no son
     * portables), asi que los conteos salen de una consulta por nivel en vez de
     * traer todas las instalaciones de la historia a memoria en cada carga —
     * mismo criterio y misma razon que en el listado de Servicio Tecnico.
     *
     * El desglose es por CATEGORIA porque son mutuamente excluyentes (una
     * instalacion es lavadora, llenadora o planta). Los booleanos
     * instalacion/puesta_en_marcha no servirian: un registro puede ser ambos y
     * los numeros no sumarian el total.
     *
     * @return array{anios: \Illuminate\Support\Collection<int, array>, meses: array<int, int>|null}
     */
    private function resumenHistorial(?int $anioActivo): array
    {
        // Un SUM condicional por categoria. El nombre de la columna sale de la
        // constante del modelo (no de la request) y el valor va como binding.
        $select = 'SUBSTR(fecha, 1, 4) as anio, COUNT(*) as total';
        $bindings = [];
        foreach (Instalacion::CATEGORIAS as $categoria) {
            $select .= ", SUM(CASE WHEN categoria = ? THEN 1 ELSE 0 END) as cat_{$categoria}";
            $bindings[] = $categoria;
        }

        $anios = Instalacion::query()
            ->whereNotNull('fecha')
            ->selectRaw($select, $bindings)
            ->groupBy('anio')
            ->get()
            ->mapWithKeys(fn ($fila) => [
                (int) $fila->anio => [
                    'total' => (int) $fila->total,
                    // Solo las categorias con registros: una card con "0 planta" es ruido.
                    'categorias' => collect(Instalacion::CATEGORIAS)
                        ->mapWithKeys(fn (string $c) => [$c => (int) ($fila->{"cat_{$c}"} ?? 0)])
                        ->filter()
                        ->all(),
                ],
            ])
            ->sortKeysDesc();

        $meses = null;
        if ($anioActivo !== null) {
            $porMes = Instalacion::query()
                ->whereNotNull('fecha')
                ->whereRaw('SUBSTR(fecha, 1, 4) = ?', [(string) $anioActivo])
                ->selectRaw('SUBSTR(fecha, 6, 2) as mes, COUNT(*) as total')
                ->groupBy('mes')
                ->pluck('total', 'mes');

            $meses = collect(range(1, 12))
                ->mapWithKeys(fn (int $m) => [$m => (int) ($porMes[str_pad((string) $m, 2, '0', STR_PAD_LEFT)] ?? 0)])
                ->all();
        }

        return ['anios' => $anios, 'meses' => $meses];
    }

    private function validateData(Request $request): array
    {
        // RUT obligatorio: se normaliza a la forma canónica.
        $rutInput = trim((string) $request->input('cliente_rut'));
        $request->merge([
            'cliente_rut' => $rutInput === '' ? null : (Cliente::normalizarRut($rutInput) ?? $rutInput),
            'cliente_id' => $request->input('cliente_id') ?: null,
        ]);

        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'cliente_id' => ['nullable', 'integer', Rule::exists('clientes', 'id')],
            'cliente_nombre' => ['required', 'string', 'min:2', 'max:191'],
            'cliente_rut' => ['required', 'string', 'max:20', new RutChileno],
            'comuna_region' => ['required', 'string', 'max:191'],
            'categoria' => ['required', Rule::in(Instalacion::CATEGORIAS)],
            'producto' => ['required', 'string', 'max:191'],
            'dias' => ['required', 'integer', 'min:0', 'max:365'],
            'vendedor' => ['required', 'string', 'max:191'],
            'n_factura' => ['nullable', 'string', 'max:50'],
            'fecha_factura' => ['nullable', 'date'],
            'forma_pago' => ['nullable', Rule::in(Instalacion::FORMAS_PAGO)],
            'fecha_pago' => ['nullable', 'date'],
        ]);

        // Checkboxes: SI/NO del Excel.
        $data['instalacion'] = $request->boolean('instalacion');
        $data['puesta_en_marcha'] = $request->boolean('puesta_en_marcha');

        return $data;
    }

    private function formData(): array
    {
        return [
            'categorias' => Instalacion::CATEGORIAS,
            'formasPago' => Instalacion::FORMAS_PAGO,
            'vendedores' => Instalacion::VENDEDORES_SUGERIDOS,
            'productos' => $this->sugerenciasProducto(),
        ];
    }

    /**
     * Nombres del catálogo maestro (Producto) para sugerir en el campo "Producto
     * instalado". El campo sigue siendo texto libre (datalist): estas son solo
     * sugerencias, no una lista cerrada. Se listan los productos activos, sin
     * repetir nombre y ordenados; con tope de seguridad porque el catálogo real
     * (espejado de Bsale) tiene miles de SKU y un datalist gigante infla el HTML.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function sugerenciasProducto(): \Illuminate\Support\Collection
    {
        return Producto::query()
            ->where('activo', true)
            ->whereNotNull('nombre')
            ->orderBy('nombre')
            ->distinct()
            ->limit(1000)
            ->pluck('nombre')
            ->filter(fn ($n) => filled($n))
            ->values();
    }
}
