<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sucursal;
use App\Models\User;
use App\Rules\ImpdaliEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Listado de cuentas.
     */
    public function index(): View
    {
        $users = User::with('roles', 'sucursal')->orderBy('name')->get();

        return view('admin.users.index', ['users' => $users]);
    }

    /**
     * Formulario para crear una cuenta.
     */
    public function create(): View
    {
        return view('admin.users.create', [
            'roles' => Role::orderBy('name')->pluck('name'),
            'sucursales' => Sucursal::where('activa', true)->orderBy('nombre')->get(),
        ]);
    }

    /**
     * Crea una cuenta (solo dominio @impdali.cl) y le asigna un rol y sucursal.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', new ImpdaliEmail, 'unique:'.User::class],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
            'sucursal_id' => ['nullable', 'integer', Rule::exists('sucursales', 'id')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'sucursal_id' => $validated['sucursal_id'] ?? null,
        ]);

        // Cuenta interna creada por un admin: se marca verificada.
        $user->markEmailAsVerified();
        $user->assignRole($validated['role']);

        return redirect()->route('admin.users.index')
            ->with('status', "Cuenta creada para {$user->email}.");
    }

    /**
     * Formulario para cambiar el rol y la sucursal de una cuenta.
     */
    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user->load('roles'),
            'roles' => Role::orderBy('name')->pluck('name'),
            // Solo sucursales activas, pero conservando la del usuario aunque este inactiva
            // (para no borrar su asignacion sin querer al guardar).
            'sucursales' => Sucursal::where('activa', true)
                ->orWhere('id', $user->sucursal_id)
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    /**
     * Actualiza el rol y la sucursal de una cuenta.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
            'sucursal_id' => ['nullable', 'integer', Rule::exists('sucursales', 'id')],
        ]);

        if ($this->wouldRemoveLastAdmin($user, $validated['role'])) {
            return back()->with('status', 'No puedes quitar el rol admin: es el ultimo administrador.');
        }

        $user->update(['sucursal_id' => $validated['sucursal_id'] ?? null]);
        $user->syncRoles([$validated['role']]);

        return redirect()->route('admin.users.index')
            ->with('status', "Rol de {$user->email} actualizado a {$validated['role']}.");
    }

    /**
     * Elimina una cuenta (no permite auto-eliminarse ni borrar al ultimo admin).
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('status', 'No puedes eliminar tu propia cuenta.');
        }

        if ($this->wouldRemoveLastAdmin($user)) {
            return back()->with('status', 'No puedes eliminar al ultimo administrador.');
        }

        $user->delete();

        return back()->with('status', "Cuenta de {$user->email} eliminada.");
    }

    /**
     * Determina si la accion dejaria al sistema sin ningun administrador.
     * Al editar, $newRole es el rol que quedaria; al eliminar, es null.
     */
    private function wouldRemoveLastAdmin(User $user, ?string $newRole = null): bool
    {
        if (! $user->hasRole('admin')) {
            return false;
        }

        if ($newRole === 'admin') {
            return false;
        }

        return User::role('admin')->count() <= 1;
    }
}
