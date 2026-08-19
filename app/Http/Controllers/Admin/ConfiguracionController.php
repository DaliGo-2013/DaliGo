<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ConfiguracionController extends Controller
{
    /**
     * Rango [min, max] por clave para enteros cuyo valor fuera de rango deja
     * una pantalla sin sentido (PLAN-PARAMETRICOS, exigencia del nivel 1:
     * «un 0 o un negativo no puede romper la operación»). El consumidor
     * además clampa al leer, por si el valor entró por fuera de esta UI.
     * 31 = un mes de mirada, tope sano (dictado DASH-1).
     */
    private const RANGOS = [
        'dashboard_dias_serie_produccion' => [2, 31],
        'dashboard_dias_referencia_merma' => [2, 31],
        'dashboard_corte_taller_reciente' => [2, 60],
        'dashboard_corte_taller_antiguo' => [7, 180],
    ];

    /**
     * Pares de claves que deben mantenerse ESTRICTAMENTE ordenados
     * (menor < mayor). DASH-2: los dos cortes de antigüedad del taller — un
     * par invertido o igual deja los tramos del Inicio sin sentido. La
     * validación corre al guardar CUALQUIERA de las dos puntas y el mensaje
     * nombra a la otra clave. El consumidor además fuerza el orden al leer
     * (clamp), por si un par roto entra por fuera de esta UI.
     */
    private const PARES_ORDENADOS = [
        ['dashboard_corte_taller_reciente', 'dashboard_corte_taller_antiguo'],
    ];

    /**
     * Claves JSON que son LISTAS SIMPLES de strings (COM-1): el dueño las
     * edita UNA POR LÍNEA (nada de corchetes ni comillas de programador) y
     * acá se convierten a array normalizado antes de guardarse como JSON.
     * Tercer mecanismo declarativo por clave, hermano de RANGOS y
     * PARES_ORDENADOS. Las claves JSON de OBJETO (plantillas, feriados)
     * siguen con su textarea JSON.
     */
    private const LISTAS_SIMPLES = [
        'clientes_segmentos',
        'catalogo_categorias_sugeridas',
    ];

    /** Tope sano de elementos de una lista simple (nadie clasifica con 50+ opciones). */
    private const MAX_ELEMENTOS_LISTA = 50;

    /**
     * Listado de parametros agrupados por `grupo`.
     */
    public function index(): View
    {
        $grupos = Configuracion::orderBy('grupo')->orderBy('clave')->get()->groupBy('grupo');

        return view('admin.configuracion.index', ['grupos' => $grupos]);
    }

    public function edit(Configuracion $configuracion): View
    {
        return view('admin.configuracion.edit', [
            'configuracion' => $configuracion,
            'esLista' => in_array($configuracion->clave, self::LISTAS_SIMPLES, true),
        ]);
    }

    public function update(Request $request, Configuracion $configuracion): RedirectResponse
    {
        $valor = $this->validateValor($request, $configuracion);
        $this->validarParOrdenado($configuracion, $valor);
        $this->validarSegmentosEnUso($configuracion, $valor);

        Configuracion::set($configuracion->clave, $valor);

        return redirect()->route('admin.configuracion.index')
            ->with('status', "Configuración «{$configuracion->clave}» actualizada.");
    }

    /**
     * Valida el valor enviado segun el `tipo` del ajuste y devuelve el valor PHP
     * a guardar. set() lo re-serializa al formato de almacenamiento.
     */
    private function validateValor(Request $request, Configuracion $configuracion): mixed
    {
        // Booleano: el checkbox puede no enviarse => boolean() normaliza la ausencia.
        if ($configuracion->tipo === Configuracion::TIPO_BOOLEAN) {
            return $request->boolean('valor');
        }

        // Lista simple (COM-1): el textarea trae UN valor por línea. Se
        // normaliza acá (trim, sin vacíos, sin duplicados case-insensitive)
        // y se devuelve como array — set() lo serializa a JSON.
        if (in_array($configuracion->clave, self::LISTAS_SIMPLES, true)) {
            return $this->parseListaSimple($request);
        }

        $rango = self::RANGOS[$configuracion->clave] ?? null;

        $rules = match ($configuracion->tipo) {
            Configuracion::TIPO_INTEGER => $rango
                ? ['required', 'integer', 'min:'.$rango[0], 'max:'.$rango[1]]
                : ['required', 'integer'],
            Configuracion::TIPO_DECIMAL => ['required', 'numeric'],
            Configuracion::TIPO_JSON => ['required', 'string', function ($attribute, $value, $fail) {
                json_decode($value);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $fail('El valor debe ser JSON válido.');
                }
            }],
            default => ['nullable', 'string'],
        };

        return $request->validate(['valor' => $rules])['valor'];
    }

    /**
     * Parsea el textarea de una LISTA SIMPLE (un valor por línea) a un array
     * normalizado: trim, fuera los vacíos y los duplicados (case-insensitive,
     * conservando la primera forma escrita). Rechaza la lista vacía, un
     * elemento más largo que el esquema (191) y el tope de elementos.
     *
     * @return array<int, string>
     */
    private function parseListaSimple(Request $request): array
    {
        $crudo = (string) $request->validate(['valor' => ['required', 'string']])['valor'];

        $items = [];
        foreach (preg_split('/\R/', $crudo) ?: [] as $linea) {
            $linea = trim($linea);
            if ($linea === '') {
                continue;
            }
            if (mb_strlen($linea) > 191) {
                throw ValidationException::withMessages([
                    'valor' => 'Cada valor debe tener 191 caracteres o menos: revisa «'.mb_substr($linea, 0, 40).'…».',
                ]);
            }
            $k = mb_strtolower($linea);
            if (! array_key_exists($k, $items)) {
                $items[$k] = $linea;
            }
        }

        if ($items === []) {
            throw ValidationException::withMessages(['valor' => 'La lista no puede quedar vacía: escribe al menos un valor (uno por línea).']);
        }

        if (count($items) > self::MAX_ELEMENTOS_LISTA) {
            throw ValidationException::withMessages(['valor' => 'Máximo '.self::MAX_ELEMENTOS_LISTA.' valores en la lista.']);
        }

        return array_values($items);
    }

    /**
     * La regla de seguridad de COM-1 (dictada por el dueño en el veredicto
     * del mapa): AGREGAR un segmento es libre; QUITAR uno que tenga clientes
     * asignados se rechaza nombrando cuántos lo usan — si no, esos clientes
     * quedan con un segmento que el filtro ya no ofrece. Validación por clave
     * CON LÓGICA (el hermano con código de RANGOS/PARES_ORDENADOS).
     */
    private function validarSegmentosEnUso(Configuracion $configuracion, mixed $valor): void
    {
        if ($configuracion->clave !== 'clientes_segmentos' || ! is_array($valor)) {
            return;
        }

        $nuevos = array_map('mb_strtolower', $valor);

        foreach (\App\Models\Cliente::segmentos() as $vigente) {
            if (in_array(mb_strtolower($vigente), $nuevos, true)) {
                continue;
            }

            $enUso = \App\Models\Cliente::where('segmento', $vigente)->count();
            if ($enUso > 0) {
                throw ValidationException::withMessages([
                    'valor' => "No puedes quitar «{$vigente}»: {$enUso} cliente(s) lo tienen asignado. Reasígnalos primero desde el listado de Clientes.",
                ]);
            }
        }
    }

    /**
     * Rechaza el guardado si el valor rompe el orden de su par (ver
     * PARES_ORDENADOS). Si la otra punta no está sembrada, no hay par que
     * cruzar y el rango simple de RANGOS es la única vara.
     */
    private function validarParOrdenado(Configuracion $configuracion, mixed $valor): void
    {
        foreach (self::PARES_ORDENADOS as [$claveMenor, $claveMayor]) {
            if ($configuracion->clave !== $claveMenor && $configuracion->clave !== $claveMayor) {
                continue;
            }

            $otraClave = $configuracion->clave === $claveMenor ? $claveMayor : $claveMenor;
            $otro = Configuracion::get($otraClave);
            if ($otro === null) {
                continue;
            }

            $menor = (int) ($configuracion->clave === $claveMenor ? $valor : $otro);
            $mayor = (int) ($configuracion->clave === $claveMayor ? $valor : $otro);

            if ($menor >= $mayor) {
                $rotulo = Str::headline($otraClave);
                throw ValidationException::withMessages([
                    'valor' => $configuracion->clave === $claveMenor
                        ? "Debe quedar por debajo de «{$rotulo}» (hoy {$mayor})."
                        : "Debe quedar por encima de «{$rotulo}» (hoy {$menor}).",
                ]);
            }
        }
    }
}
