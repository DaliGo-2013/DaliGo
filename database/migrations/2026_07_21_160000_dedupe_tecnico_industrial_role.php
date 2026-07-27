<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Quita el rol "Tecnico Industrial" DUPLICADO (hallazgo del dueño 2026-07-21):
 * en producción quedaron dos filas que se muestran igual con Str::headline
 * porque una es `tecnico_industrial` (canónica, del seeder) y otra un variante
 * creado a mano (ej. `tecnico industrial` con espacio, o distinta capitalización).
 *
 * Consolida al canónico `tecnico_industrial`: reasigna los usuarios del/los
 * duplicado(s) (insertOrIgnore, no duplica el pivote) y borra el duplicado con
 * sus permisos. Nadie queda sin rol. Idempotente: si no hay duplicado (BD fresca
 * / tests / ya limpio) es no-op. Mismo patrón que reconcile_business_roles.
 */
return new class extends Migration
{
    public function up(): void
    {
        $norm = fn ($n) => strtolower(str_replace([' ', '-'], '_', trim((string) $n)));

        $coincidencias = DB::table('roles')->where('guard_name', 'web')->get(['id', 'name'])
            ->filter(fn ($r) => $norm($r->name) === 'tecnico_industrial')
            ->values();

        if ($coincidencias->count() < 2) {
            return; // sin duplicado
        }

        // Canónico: el que ya se llama exactamente 'tecnico_industrial'; si no
        // existe, el de menor id (y se le pone el nombre correcto).
        $canonico = $coincidencias->firstWhere('name', 'tecnico_industrial')
            ?? $coincidencias->sortBy('id')->first();

        if ($canonico->name !== 'tecnico_industrial') {
            DB::table('roles')->where('id', $canonico->id)->update(['name' => 'tecnico_industrial']);
        }

        foreach ($coincidencias->where('id', '!=', $canonico->id) as $dup) {
            foreach (DB::table('model_has_roles')->where('role_id', $dup->id)->get() as $asignacion) {
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $canonico->id,
                    'model_type' => $asignacion->model_type,
                    'model_id' => $asignacion->model_id,
                ]);
            }

            DB::table('model_has_roles')->where('role_id', $dup->id)->delete();
            DB::table('role_has_permissions')->where('role_id', $dup->id)->delete();
            DB::table('roles')->where('id', $dup->id)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** No-op: reconciliación puntual de datos, deploy forward-only. */
    public function down(): void
    {
        //
    }
};
