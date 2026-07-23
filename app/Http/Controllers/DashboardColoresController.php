<?php

namespace App\Http\Controllers;

use App\Support\AccesosDashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Color de las cards de accesos del Inicio (M16, D-013): preferencia PERSONAL
 * — cada usuario guarda su propio mapa card→color y nunca afecta a otros
 * perfiles. Se consume por fetch desde el modo "Personalizar" del dashboard.
 */
class DashboardColoresController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'colores' => ['required', 'array'],
            'colores.*' => ['required', 'string', Rule::in(AccesosDashboard::COLORES)],
        ]);

        $desconocidas = array_diff(array_keys($data['colores']), array_keys(AccesosDashboard::cards()));
        if ($desconocidas !== []) {
            throw ValidationException::withMessages(['colores' => 'Card desconocida.']);
        }

        $user = $request->user();
        $user->dashboard_colores = $data['colores'];
        $user->save();

        return response()->json(['ok' => true]);
    }
}
