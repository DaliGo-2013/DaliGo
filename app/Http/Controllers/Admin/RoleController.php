<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Roles del sistema: no se pueden renombrar ni eliminar desde la UI.
     */
    private const BASE_ROLES = ['admin', 'member'];

    /**
     * Listado de roles con sus permisos y cantidad de usuarios.
     */
    public function index(): View
    {
        $roles = Role::withCount('users')->with('permissions')->orderBy('name')->get();

        return view('admin.roles.index', [
            'roles' => $roles,
            'baseRoles' => self::BASE_ROLES,
        ]);
    }

    /**
     * Formulario para crear un rol.
     */
    public function create(): View
    {
        return view('admin.roles.create', [
            'permissions' => Permission::orderBy('name')->get(),
        ]);
    }

    /**
     * Crea un rol con los permisos seleccionados.
     */
    public function store(Request $request): RedirectResponse
    {
        // Normalizar a minusculas ANTES de validar: el unique de MySQL 5.7 es
        // case-insensitive (utf8mb4_unicode_ci) pero el de SQLite (tests) no;
        // ademas mantiene la convencion ASCII-minuscula de los roles del negocio.
        $request->merge(['name' => mb_strtolower(trim((string) $request->input('name')))]);

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9 _-]+$/i',
                Rule::unique('roles', 'name')->where('guard_name', 'web'),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ]);

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);
        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('admin.roles.index')
            ->with('status', "Rol '{$role->name}' creado.");
    }

    /**
     * Formulario para editar un rol y sus permisos.
     */
    public function edit(Role $role): View
    {
        return view('admin.roles.edit', [
            'role' => $role,
            'permissions' => Permission::orderBy('name')->get(),
            'assigned' => $role->permissions->pluck('name')->all(),
            'isBase' => $this->isBaseRole($role),
        ]);
    }

    /**
     * Actualiza los permisos (y el nombre, si no es un rol base) de un rol.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        $isBase = $this->isBaseRole($role);

        if (! $isBase && $request->has('name')) {
            // Misma normalizacion que en store() (ver comentario alla).
            $request->merge(['name' => mb_strtolower(trim((string) $request->input('name')))]);
        }

        $rules = [
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ];

        if (! $isBase) {
            $rules['name'] = [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9 _-]+$/i',
                Rule::unique('roles', 'name')->ignore($role->id)->where('guard_name', 'web'),
            ];
        }

        $validated = $request->validate($rules);
        $permissions = $validated['permissions'] ?? [];

        // El rol admin nunca debe perder la capacidad de gestionar roles (anti auto-bloqueo).
        if ($role->name === 'admin' && ! in_array('manage roles', $permissions, true)) {
            $permissions[] = 'manage roles';
        }

        if (! $isBase) {
            $role->update(['name' => $validated['name']]);
        }

        $role->syncPermissions($permissions);

        return redirect()->route('admin.roles.index')
            ->with('status', "Rol '{$role->name}' actualizado.");
    }

    /**
     * Elimina un rol (no permite borrar roles base ni roles con usuarios asignados).
     */
    public function destroy(Role $role): RedirectResponse
    {
        if ($this->isBaseRole($role)) {
            return back()->with('status', "El rol '{$role->name}' es del sistema y no puede eliminarse.");
        }

        if ($role->users()->exists()) {
            return back()->with('status', "No se puede eliminar '{$role->name}': tiene usuarios asignados.");
        }

        $role->delete();

        return back()->with('status', "Rol '{$role->name}' eliminado.");
    }

    private function isBaseRole(Role $role): bool
    {
        return in_array($role->name, self::BASE_ROLES, true);
    }
}
