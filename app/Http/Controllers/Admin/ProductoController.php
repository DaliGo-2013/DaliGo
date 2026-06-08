<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductoController extends Controller
{
    /**
     * Orden canonico de columnas del CSV (export e import lo comparten).
     */
    private const CSV_HEADERS = [
        'sku', 'nombre', 'descripcion', 'categoria', 'marca',
        'peso_kg', 'alto_cm', 'ancho_cm', 'largo_cm',
        'activo', 'bsale_variant_id', 'bsale_product_id',
    ];

    public function index(Request $request): View
    {
        $productos = $this->filteredQuery($request)
            ->orderBy('nombre')
            ->paginate(25)
            ->withQueryString();

        return view('admin.productos.index', array_merge([
            'productos' => $productos,
            'filtros' => $request->only(['q', 'categoria', 'marca', 'activo']),
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
        return view('admin.productos.edit', array_merge(['producto' => $producto], $this->formData()));
    }

    public function update(Request $request, Producto $producto): RedirectResponse
    {
        $producto->update($this->validateData($request, $producto));

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
     * Importa productos desde CSV (upsert por SKU, idempotente). Tolera el CSV de
     * Excel-CL: separador ; o , (autodetectado), decimales con coma, BOM y
     * Windows-1252. Filas invalidas se saltan y se reportan. La auditoria por fila
     * se desactiva (seria ruido en cargas masivas); se deja un resumen en el log.
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

        if (! isset($map['sku'], $map['nombre'])) {
            fclose($handle);

            return back()->withErrors(['archivo' => 'El archivo debe incluir las columnas "sku" y "nombre".']);
        }

        $numericas = ['peso_kg', 'alto_cm', 'ancho_cm', 'largo_cm'];
        $creados = 0;
        $actualizados = 0;
        $errores = [];
        $fila = 1; // la cabecera es la linea 1

        Producto::withoutAuditing(function () use ($handle, $delimiter, $map, $numericas, &$creados, &$actualizados, &$errores, &$fila) {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $fila++;

                if ($row === [null] || count(array_filter($row, fn ($c) => trim((string) $c) !== '')) === 0) {
                    continue; // fila en blanco
                }

                $data = [];
                foreach (self::CSV_HEADERS as $col) {
                    $idx = $map[$col] ?? null;
                    $val = ($idx !== null && array_key_exists($idx, $row)) ? $this->toUtf8(trim((string) $row[$idx])) : null;
                    $data[$col] = ($val === '' || $val === null) ? null : $val;
                }

                foreach ($numericas as $col) {
                    $data[$col] = $this->normalizeDecimal($data[$col]);
                }
                $data['activo'] = $this->parseBool($data['activo']);

                $validator = Validator::make($data, [
                    'sku' => ['required', 'string', 'max:64'],
                    'nombre' => ['required', 'string', 'max:191'],
                    'categoria' => ['nullable', 'string', 'max:191'],
                    'marca' => ['nullable', 'string', 'max:191'],
                    'peso_kg' => ['nullable', 'numeric', 'min:0'],
                    'alto_cm' => ['nullable', 'numeric', 'min:0'],
                    'ancho_cm' => ['nullable', 'numeric', 'min:0'],
                    'largo_cm' => ['nullable', 'numeric', 'min:0'],
                    'bsale_variant_id' => ['nullable', 'integer', 'min:0'],
                    'bsale_product_id' => ['nullable', 'integer', 'min:0'],
                ]);

                if ($validator->fails()) {
                    $errores[] = ['fila' => $fila, 'error' => $validator->errors()->first()];

                    continue;
                }

                $producto = Producto::updateOrCreate(
                    ['sku' => $data['sku']],
                    Arr::except($data, ['sku']),
                );
                $producto->wasRecentlyCreated ? $creados++ : $actualizados++;
            }
        });

        fclose($handle);

        Log::info("Import catálogo por usuario {$request->user()->id}: {$creados} creados, {$actualizados} actualizados, ".count($errores).' errores.');

        return redirect()->route('admin.productos.import.form')
            ->with('importResult', [
                'creados' => $creados,
                'actualizados' => $actualizados,
                'errores' => $errores,
            ]);
    }

    /**
     * Exporta el catalogo filtrado a CSV (mismas reglas de filtro que el index:
     * "filtro previo a exportar"). ; + BOM para que el Excel chileno lo abra bien.
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
                        $p->activo ? '1' : '0', $p->bsale_variant_id, $p->bsale_product_id,
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

    // --- Helpers --------------------------------------------------------

    /**
     * Query del catalogo con los filtros del request aplicados. Compartida por
     * index (paginado) y export (streaming) para que el export respete el filtro.
     */
    private function filteredQuery(Request $request): Builder
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:191'],
            'categoria' => ['nullable', 'string', 'max:191'],
            'marca' => ['nullable', 'string', 'max:191'],
            'activo' => ['nullable', 'in:0,1'],
        ]);

        $query = Producto::query()
            ->when($f['q'] ?? null, fn (Builder $qb, $q) => $qb->where(
                fn (Builder $w) => $w->where('sku', 'like', "%{$q}%")->orWhere('nombre', 'like', "%{$q}%"),
            ))
            ->when($f['categoria'] ?? null, fn (Builder $qb, $v) => $qb->where('categoria', $v))
            ->when($f['marca'] ?? null, fn (Builder $qb, $v) => $qb->where('marca', $v));

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
            'peso_kg' => ['nullable', 'numeric', 'min:0'],
            'alto_cm' => ['nullable', 'numeric', 'min:0'],
            'ancho_cm' => ['nullable', 'numeric', 'min:0'],
            'largo_cm' => ['nullable', 'numeric', 'min:0'],
            'bsale_variant_id' => ['nullable', 'integer', 'min:0'],
            'bsale_product_id' => ['nullable', 'integer', 'min:0'],
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
            'categorias' => Producto::whereNotNull('categoria')->distinct()->orderBy('categoria')->pluck('categoria'),
            'marcas' => Producto::whereNotNull('marca')->distinct()->orderBy('marca')->pluck('marca'),
        ];
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

    private function parseBool(?string $value): bool
    {
        if ($value === null) {
            return true; // por defecto, activo
        }

        return in_array(Str::lower(trim($value)), ['1', 'si', 'sí', 'true', 'verdadero', 'activo'], true);
    }
}
