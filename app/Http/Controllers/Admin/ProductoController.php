<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProductoController extends Controller
{
    /**
     * Columnas que el IMPORT puede escribir. Los campos que manda Bsale por la
     * sincronizacion (barcode, bsale_*) NO son importables: un CSV podria
     * re-enlazar un producto al variant_id equivocado y la sync le reescribiria
     * la identidad. Esos van solo en el export, como referencia.
     */
    private const IMPORTABLE = [
        'sku', 'nombre', 'descripcion', 'categoria', 'marca',
        'peso_kg', 'alto_cm', 'ancho_cm', 'largo_cm', 'activo',
    ];

    /**
     * Orden canonico de columnas del CSV de export/plantilla: las importables
     * primero, las de referencia (solo-export) al final.
     */
    private const CSV_HEADERS = [
        'sku', 'nombre', 'descripcion', 'categoria', 'marca',
        'peso_kg', 'alto_cm', 'ancho_cm', 'largo_cm', 'activo',
        'barcode', 'bsale_variant_id', 'bsale_product_id',
    ];

    private const NUMERICAS = ['peso_kg', 'alto_cm', 'ancho_cm', 'largo_cm'];

    /**
     * Categorías internas SUGERIDAS: siempre disponibles para corregir (aparecen
     * en el filtro y el datalist) aunque todavía no las use ningún producto.
     */
    private const PRESETS_CATEGORIA_INTERNA = ['Repuestos industriales'];

    public function index(Request $request): View
    {
        $productos = $this->filteredQuery($request)
            ->orderBy('nombre')
            ->paginate(25)
            ->withQueryString();

        $activos = Producto::where('activo', true)->count();
        $activosCompletos = Producto::where('activo', true)
            ->whereNotNull('peso_kg')->whereNotNull('alto_cm')
            ->whereNotNull('ancho_cm')->whereNotNull('largo_cm')
            ->count();

        return view('admin.productos.index', array_merge([
            'productos' => $productos,
            'filtros' => $request->only(['q', 'categoria', 'corregidos', 'marca', 'activo', 'medidas']),
            'activos' => $activos,
            'activosCompletos' => $activosCompletos,
        ], $this->formData()));
    }

    public function create(): View
    {
        return view('admin.productos.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $producto = Producto::create($this->validateData($request));

        return redirect()->route('admin.productos.index')
            ->with('status', "Producto {$producto->sku} creado.");
    }

    public function edit(Producto $producto): View
    {
        // Precios y stock espejados desde Bsale (solo lectura), si el producto esta enlazado.
        $precios = $producto->bsale_variant_id
            ? $producto->precios()->with('lista')->get()->sortBy(fn ($p) => $p->lista->nombre)->values()
            : collect();

        $stocks = $producto->bsale_variant_id
            ? $producto->stocks()->with('bodega')->get()->sortBy(fn ($s) => $s->bodega->nombre)->values()
            : collect();

        return view('admin.productos.edit', array_merge([
            'producto' => $producto,
            'precios' => $precios,
            'stocks' => $stocks,
        ], $this->formData()));
    }

    public function update(Request $request, Producto $producto): RedirectResponse
    {
        $data = $this->validateData($request, $producto);

        // El campo "Categoría" del formulario edita la CORRECCIÓN duradera de
        // DaliGo (categoria_interna), NO la de Bsale (esa la reescribe el sync).
        // Si queda igual a la de Bsale o vacía, no hay corrección (override null).
        $efectiva = trim((string) ($data['categoria'] ?? ''));
        unset($data['categoria']);
        $data['categoria_interna'] = ($efectiva === '' || $efectiva === (string) $producto->categoria) ? null : $efectiva;

        $producto->update($data);

        return redirect()->route('admin.productos.index')
            ->with('status', "Producto {$producto->sku} actualizado.");
    }

    public function destroy(Producto $producto): RedirectResponse
    {
        $producto->delete();

        return back()->with('status', "Producto {$producto->sku} eliminado.");
    }

    // --- CSV ------------------------------------------------------------

    public function importForm(): View
    {
        return view('admin.productos.importar');
    }

    /**
     * Importa productos desde CSV con SEMANTICA DE PARCHE: solo se tocan las
     * columnas presentes en el archivo (una columna ausente no se modifica; una
     * celda vacia borra el valor). Upsert por SKU, idempotente. Tolera el CSV de
     * Excel-CL (separador ; o ,, decimales con coma, BOM, Windows-1252). Filas
     * invalidas se saltan y se reportan. Sin auditoria por fila (resumen al log).
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'max:5120'], // 5 MB
        ]);

        $file = $request->file('archivo');
        if (! in_array(strtolower($file->getClientOriginalExtension()), ['csv', 'txt'], true)) {
            return back()->withErrors(['archivo' => 'El archivo debe ser .csv o .txt.']);
        }

        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return back()->withErrors(['archivo' => 'No se pudo leer el archivo.']);
        }

        $headerLine = fgets($handle);
        if ($headerLine === false) {
            fclose($handle);

            return back()->withErrors(['archivo' => 'El archivo está vacío.']);
        }

        $headerLine = preg_replace('/^\xEF\xBB\xBF/', '', $headerLine); // quitar BOM
        $delimiter = substr_count($headerLine, ';') >= substr_count($headerLine, ',') ? ';' : ',';

        $map = array_flip(array_map(
            fn ($h) => $this->normalizeKey($h),
            str_getcsv(rtrim($headerLine, "\r\n"), $delimiter),
        ));

        if (! isset($map['sku'])) {
            fclose($handle);

            return back()->withErrors(['archivo' => 'El archivo debe incluir la columna "sku".']);
        }

        // Solo columnas importables presentes en el archivo; el resto se ignora.
        $presentes = array_values(array_intersect(self::IMPORTABLE, array_keys($map)));

        $stats = ['creados' => 0, 'actualizados' => 0, 'sin_cambios' => 0, 'vaciados' => 0, 'errores' => []];
        $fila = 1; // la cabecera es la linea 1

        Producto::withoutAuditing(function () use ($handle, $delimiter, $map, $presentes, &$stats, &$fila) {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $fila++;

                if ($row === [null] || count(array_filter($row, fn ($c) => trim((string) $c) !== '')) === 0) {
                    continue; // fila en blanco
                }

                $error = $this->importRow($row, $map, $presentes, $stats);
                if ($error !== null) {
                    $stats['errores'][] = ['fila' => $fila, 'error' => $error];
                }
            }
        });

        fclose($handle);

        Log::info(sprintf(
            'Import catálogo por usuario %d: %d creados, %d actualizados, %d sin cambios, %d campos vaciados, %d errores.',
            $request->user()->id, $stats['creados'], $stats['actualizados'],
            $stats['sin_cambios'], $stats['vaciados'], count($stats['errores']),
        ));

        return redirect()->route('admin.productos.import.form')->with('importResult', $stats);
    }

    /**
     * Procesa una fila del CSV. Devuelve el mensaje de error (la fila se salta)
     * o null si se aplico bien. Solo escribe las columnas presentes en el archivo.
     */
    private function importRow(array $row, array $map, array $presentes, array &$stats): ?string
    {
        // Celda fisicamente ausente (fila mas corta que el header) = celda vacia.
        $data = [];
        foreach ($presentes as $col) {
            $idx = $map[$col];
            $val = array_key_exists($idx, $row) ? $this->toUtf8(trim((string) $row[$idx])) : '';
            $data[$col] = ($val === '') ? null : $val;
        }

        // 'activo' solo aplica si viene con valor; token no reconocido = error.
        if (array_key_exists('activo', $data)) {
            if ($data['activo'] === null) {
                unset($data['activo']); // celda vacia: no tocar
            } else {
                $bool = $this->parseBoolStrict($data['activo']);
                if ($bool === null) {
                    return "Valor de \"activo\" no reconocido: \"{$data['activo']}\" (usa 1/0, si/no).";
                }
                $data['activo'] = $bool;
            }
        }

        foreach (self::NUMERICAS as $col) {
            if (array_key_exists($col, $data)) {
                $data[$col] = $this->normalizeDecimal($data[$col]);
            }
        }

        // Validar solo las columnas presentes. 'nombre' presente no puede ir vacio
        // (la columna es NOT NULL; vaciarla seria un error de digitacion).
        // max: acorde a decimal(10,3)/(10,2) para no gatillar "Out of range" en MySQL.
        $reglas = [
            'sku' => ['required', 'string', 'max:64'],
            'nombre' => ['required', 'string', 'max:191'],
            'descripcion' => ['nullable', 'string'],
            'categoria' => ['nullable', 'string', 'max:191'],
            'marca' => ['nullable', 'string', 'max:191'],
            'peso_kg' => ['nullable', 'numeric', 'min:0', 'max:9999999.999'],
            'alto_cm' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'ancho_cm' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'largo_cm' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
        $validator = Validator::make($data, array_intersect_key($reglas, $data + ['sku' => true]));

        if ($validator->fails()) {
            return $validator->errors()->first();
        }

        $sku = $data['sku'];
        unset($data['sku']);

        try {
            $existing = Producto::where('sku', $sku)->first();

            if ($existing === null) {
                if (! isset($data['nombre'])) {
                    return "Producto nuevo \"{$sku}\": requiere la columna \"nombre\".";
                }

                Producto::create(['sku' => $sku] + $data);
                $stats['creados']++;

                return null;
            }

            // Rastro contra perdida silenciosa: campos que pasan de valor a vacio.
            foreach ($data as $col => $val) {
                if ($val === null && $existing->{$col} !== null) {
                    $stats['vaciados']++;
                }
            }

            $existing->fill($data);
            if ($existing->isDirty()) {
                $existing->save();
                $stats['actualizados']++;
            } else {
                $stats['sin_cambios']++;
            }

            return null;
        } catch (Throwable $e) {
            return 'Error al guardar: '.mb_substr($e->getMessage(), 0, 150);
        }
    }

    /**
     * Exporta el catalogo filtrado a CSV (mismas reglas de filtro que el index:
     * "filtro previo a exportar"). ; + BOM para que el Excel chileno lo abra bien.
     * barcode y bsale_* van al final como referencia (el import los ignora).
     */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->filteredQuery($request)->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8
            fputcsv($out, self::CSV_HEADERS, ';');

            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $p) {
                    fputcsv($out, [
                        $p->sku, $p->nombre, $p->descripcion, $p->categoria, $p->marca,
                        $p->peso_kg, $p->alto_cm, $p->ancho_cm, $p->largo_cm,
                        $p->activo ? '1' : '0',
                        $p->barcode, $p->bsale_variant_id, $p->bsale_product_id,
                    ], ';');
                }
            });

            fclose($out);
        }, 'catalogo_'.now()->format('Ymd_His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Plantilla CSV vacia (solo cabecera) para que el usuario sepa el formato.
     */
    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::CSV_HEADERS, ';');
            fclose($out);
        }, 'catalogo_plantilla.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Plantilla de TRABAJO para cargar peso/dimensiones: los SKUs pendientes
     * (medidas incompletas por defecto; respeta los demas filtros del index) con
     * columnas de referencia NO importables (producto/categoria_ref/codigo_barras:
     * el import las ignora) + las 4 medidas a llenar. Reimportarla solo escribe
     * sku + medidas: cero riesgo de pisar nombre/categoria.
     */
    public function plantillaMedidas(Request $request): StreamedResponse
    {
        if (! $request->has('medidas')) {
            $request->merge(['medidas' => 'incompletas']);
        }

        $query = $this->filteredQuery($request)->orderBy('categoria')->orderBy('nombre');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['sku', 'producto', 'categoria_ref', 'codigo_barras', 'peso_kg', 'alto_cm', 'ancho_cm', 'largo_cm'], ';');

            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $p) {
                    fputcsv($out, [
                        $p->sku, $p->nombre, $p->categoria, $p->barcode,
                        $p->peso_kg, $p->alto_cm, $p->ancho_cm, $p->largo_cm,
                    ], ';');
                }
            });

            fclose($out);
        }, 'medidas_pendientes_'.now()->format('Ymd_His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // --- Helpers --------------------------------------------------------

    /**
     * Query del catalogo con los filtros del request aplicados. Compartida por
     * index (paginado), export y plantilla-medidas para que respeten el filtro.
     */
    private function filteredQuery(Request $request): Builder
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:191'],
            'categoria' => ['nullable', 'string', 'max:191'],
            'corregidos' => ['nullable', 'in:1,0'],
            'marca' => ['nullable', 'string', 'max:191'],
            'activo' => ['nullable', 'in:0,1'],
            'medidas' => ['nullable', 'in:incompletas,completas'],
        ]);

        $query = Producto::query()
            ->when($f['q'] ?? null, fn (Builder $qb, $q) => $qb->where(
                fn (Builder $w) => $w->where('sku', 'like', "%{$q}%")->orWhere('nombre', 'like', "%{$q}%"),
            ))
            // "Categoría" filtra por la EFECTIVA: la corregida en DaliGo manda;
            // si no hay corrección, la de Bsale. COALESCE es portable 5.7/SQLite.
            ->when($f['categoria'] ?? null, fn (Builder $qb, $v) => $qb->whereRaw('COALESCE(categoria_interna, categoria) = ?', [$v]))
            // Solo corregidos (1) / solo sin corregir (0).
            ->when(($f['corregidos'] ?? null) === '1', fn (Builder $qb) => $qb->whereNotNull('categoria_interna'))
            ->when(($f['corregidos'] ?? null) === '0', fn (Builder $qb) => $qb->whereNull('categoria_interna'))
            ->when($f['marca'] ?? null, fn (Builder $qb, $v) => $qb->where('marca', $v))
            // OJO: el OR va agrupado en closure para no romper el AND con los demas filtros.
            ->when(($f['medidas'] ?? null) === 'incompletas', fn (Builder $qb) => $qb->where(
                fn (Builder $w) => $w->whereNull('peso_kg')->orWhereNull('alto_cm')
                    ->orWhereNull('ancho_cm')->orWhereNull('largo_cm'),
            ))
            ->when(($f['medidas'] ?? null) === 'completas', fn (Builder $qb) => $qb
                ->whereNotNull('peso_kg')->whereNotNull('alto_cm')
                ->whereNotNull('ancho_cm')->whereNotNull('largo_cm'));

        if (isset($f['activo']) && $f['activo'] !== '' && $f['activo'] !== null) {
            $query->where('activo', $f['activo'] === '1');
        }

        return $query;
    }

    private function validateData(Request $request, ?Producto $producto = null): array
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:64', Rule::unique('productos', 'sku')->ignore($producto)],
            'nombre' => ['required', 'string', 'max:191'],
            'descripcion' => ['nullable', 'string'],
            'categoria' => ['nullable', 'string', 'max:191'],
            'marca' => ['nullable', 'string', 'max:191'],
            'peso_kg' => ['nullable', 'numeric', 'min:0', 'max:9999999.999'],
            'alto_cm' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'ancho_cm' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'largo_cm' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            // bsale_variant_id / bsale_product_id NO se validan ni persisten aqui:
            // el form no los expone y la sync es la unica duena del enlace (un POST
            // manipulado podria duplicar un variant_id y romper el espejo de precios).
            'atributos' => ['nullable', 'string'],
        ]);

        $validated['activo'] = $request->boolean('activo');
        $validated['atributos'] = $this->parseAtributos($request->input('atributos'));

        return $validated;
    }

    /**
     * Datos compartidos por los formularios y filtros: valores distintos de
     * categoria/marca para los datalists y selects.
     */
    private function formData(): array
    {
        return [
            // Categorías EFECTIVAS distintas (corregida en DaliGo si existe, si no
            // la de Bsale) + las sugeridas (siempre disponibles): alimentan el
            // filtro y el datalist de corrección.
            'categorias' => collect(self::PRESETS_CATEGORIA_INTERNA)
                ->merge(Producto::query()
                    ->selectRaw('COALESCE(categoria_interna, categoria) as cat')
                    ->whereRaw('COALESCE(categoria_interna, categoria) IS NOT NULL')
                    ->distinct()->pluck('cat'))
                ->unique()->sort()->values(),
            'marcas' => Producto::whereNotNull('marca')->distinct()->orderBy('marca')->pluck('marca'),
        ];
    }

    /**
     * CORRECCIÓN MASIVA de categoría: el dueño selecciona productos (checkboxes)
     * y les fija la categoría que DEBERÍAN tener (ej. separar lo industrial). Esa
     * corrección se guarda en `categoria_interna` y MANDA sobre la de Bsale (ver
     * `categoria_efectiva`); NO toca `categoria` (esa la manda Bsale) y el sync
     * nunca la pisa, así que es duradera. "Quitar" devuelve el producto a su
     * categoría de Bsale. Update masivo (sin auditoría por fila, como el CSV).
     */
    public function clasificacionInterna(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'accion' => ['required', 'in:asignar,quitar'],
            'categoria_interna' => [Rule::requiredIf(fn () => $request->input('accion') === 'asignar'), 'nullable', 'string', 'max:191'],
        ]);

        $valor = $request->input('accion') === 'asignar'
            ? trim((string) $request->input('categoria_interna'))
            : null;

        $n = Producto::whereIn('id', $request->input('ids'))->update(['categoria_interna' => $valor]);

        $msg = $valor !== null && $valor !== ''
            ? "{$n} producto(s) corregidos a la categoría «{$valor}»."
            : "{$n} producto(s): se quitó la corrección (vuelven a su categoría de Bsale).";

        return back()->with('status', $msg);
    }

    private function parseAtributos(?string $raw): ?array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw ValidationException::withMessages(['atributos' => 'Debe ser un JSON válido (objeto).']);
        }

        return $decoded;
    }

    private function normalizeKey(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $this->toUtf8(trim($header)));
        $header = Str::lower(Str::ascii($header));

        return preg_replace('/[^a-z0-9_]/', '', str_replace(' ', '_', $header));
    }

    private function toUtf8(string $value): string
    {
        return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }

    /**
     * Normaliza un decimal de CSV. Si trae coma, la coma es el separador decimal
     * (Excel-CL): se quitan los puntos de miles y la coma pasa a punto. Si no trae
     * coma, se deja igual (formato US/dot).
     */
    private function normalizeDecimal(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (str_contains($value, ',')) {
            $value = str_replace(',', '.', str_replace('.', '', $value));
        }

        return $value;
    }

    /**
     * Interpreta un booleano del CSV con whitelist estricta. Token desconocido
     * devuelve null (la fila se reporta como error: evita desactivar productos
     * en silencio por un typo).
     */
    private function parseBoolStrict(string $value): ?bool
    {
        $v = Str::lower(trim($value));

        if (in_array($v, ['1', 'si', 'sí', 'true', 'verdadero', 'activo'], true)) {
            return true;
        }

        if (in_array($v, ['0', 'no', 'false', 'falso', 'inactivo'], true)) {
            return false;
        }

        return null;
    }
}
