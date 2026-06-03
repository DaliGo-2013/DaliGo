<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
        $users = User::with('roles')->orderBy('name')->get();

        return view('admin.users.index', ['users' => $users]);
    }

    /**
     * Formulario para crear una cuenta.
     */
    public function create(): View
    {
        return view('admin.users.create', [
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    /**
     * Crea una cuenta (solo dominio @impdali.cl) y le asigna un rol.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', new ImpdaliEmail, 'unique:'.User::class],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Cuenta interna creada por un admin: se marca verificada.
        $user->markEmailAsVerified();
        $user->assignRole($validated['role']);

        return redirect()->route('admin.users.index')
            ->with('status', "Cuenta creada para {$user->email}.");
    }

    /**
     * Elimina una cuenta (no permite auto-eliminarse).
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('status', 'No puedes eliminar tu propia cuenta.');
        }

        $user->delete();

        return back()->with('status', "Cuenta de {$user->email} eliminada.");
    }
}
