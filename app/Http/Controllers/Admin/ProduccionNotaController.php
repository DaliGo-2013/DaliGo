<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProduccionNota;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Notas del jefe para los sopladores (P-M11-22): CRUD chico bajo el permiso
 * existente de produccion. La nota se PINTA en mi-reporte del destinatario
 * mientras este vigente; no dispara notificaciones (sin M15 a proposito).
 */
class ProduccionNotaController extends Controller
{
    public function index(): View
    {
        $notas = ProduccionNota::with(['autor', 'soplador'])
            ->orderByDesc('id')
            ->get();

        return view('admin.produccion.notas.index', ['notas' => $notas]);
    }

    public function create(): View
    {
        return view('admin.produccion.notas.create', ['sopladores' => $this->sopladores()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['autor_id'] = Auth::id();

        ProduccionNota::create($data);

        return redirect()->route('admin.produccion.notas.index')
            ->with('status', 'Nota publicada.');
    }

    public function edit(ProduccionNota $nota): View
    {
        return view('admin.produccion.notas.edit', [
            'nota' => $nota,
            'sopladores' => $this->sopladores(),
        ]);
    }

    public function update(Request $request, ProduccionNota $nota): RedirectResponse
    {
        $nota->update($this->validateData($request));

        return redirect()->route('admin.produccion.notas.index')
            ->with('status', 'Nota actualizada.');
    }

    public function destroy(ProduccionNota $nota): RedirectResponse
    {
        // Borrado directo: una nota no tiene hijos ni historia que preservar
        // (la traza queda en auditoria).
        $nota->delete();

        return back()->with('status', 'Nota eliminada.');
    }

    /** Los sopladores activos, para el select «Para quién» (vacio = todos). */
    private function sopladores()
    {
        return User::permission('report production')->orderBy('name')->get(['id', 'name']);
    }

    private function validateData(Request $request): array
    {
        // El select manda '' cuando se elige «Para todos»: normalizar a null
        // antes de validar (leccion 2026-06-26 de los selects no obligatorios).
        $request->merge([
            'soplador_id' => $request->filled('soplador_id') ? $request->input('soplador_id') : null,
        ]);

        return $request->validate([
            'texto' => ['required', 'string', 'max:191'],
            'soplador_id' => ['nullable', Rule::exists('users', 'id')],
            'vigente_desde' => ['nullable', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after_or_equal:vigente_desde'],
        ], [
            'texto.required' => 'Escribe la nota.',
            'texto.max' => 'La nota no puede superar los 191 caracteres.',
            'vigente_hasta.after_or_equal' => 'El fin de vigencia no puede ser antes del inicio.',
        ]);
    }
}
