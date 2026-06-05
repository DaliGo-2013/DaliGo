<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConfiguracionController extends Controller
{
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
        return view('admin.configuracion.edit', ['configuracion' => $configuracion]);
    }

    public function update(Request $request, Configuracion $configuracion): RedirectResponse
    {
        $valor = $this->validateValor($request, $configuracion);

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

        $rules = match ($configuracion->tipo) {
            Configuracion::TIPO_INTEGER => ['required', 'integer'],
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
}
