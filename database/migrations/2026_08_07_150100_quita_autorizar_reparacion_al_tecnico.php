<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El técnico deja de coordinar plata (decisión del dueño, 07-08).
 *
 * El taller se limita a reparar y avisar: la cotización la manda el técnico, y
 * si el cliente acepta, listo — repara. El cobro pasó a ser en SALA DE VENTAS
 * cuando el cliente viene a retirar, así que el bloque «Coordina el pago y
 * autoriza la reparación» (forma de pago, comprobante de transferencia, nota)
 * no le corresponde y le agregaba ruido a su pantalla.
 *
 * Por qué una migración y no solo el seeder: `RolesAndPermissionsSeeder` usa
 * `givePermissionTo`, que SUMA permisos y nunca quita — sacar el permiso de la
 * lista no revoca nada en una base ya sembrada. Esta es la única vía para
 * producción. Idempotente (si ya no está, no hace nada) e invalida la caché de
 * permisos de spatie, que si no seguiría sirviendo el permiso viejo.
 *
 * El permiso sigue existiendo: lo conservan vendedor, jefe_ventas y admin.
 */
return new class extends Migration
{
    private const PERMISO = 'autorizar reparacion';

    private const ROL = 'tecnico';

    public function up(): void
    {
        $this->mover(quitar: true);
    }

    /**
     * Reversible: devuelve el permiso al rol (por si se decide volver a que el
     * taller coordine el pago).
     */
    public function down(): void
    {
        $this->mover(quitar: false);
    }

    private function mover(bool $quitar): void
    {
        $permisoId = DB::table('permissions')->where('name', self::PERMISO)->where('guard_name', 'web')->value('id');
        $rolId = DB::table('roles')->where('name', self::ROL)->where('guard_name', 'web')->value('id');

        if (! $permisoId || ! $rolId) {
            return; // base sin sembrar todavía (el seeder del deploy corre después).
        }

        $fila = DB::table('role_has_permissions')->where('permission_id', $permisoId)->where('role_id', $rolId);

        if ($quitar) {
            $fila->delete();
        } elseif (! $fila->exists()) {
            DB::table('role_has_permissions')->insert(['permission_id' => $permisoId, 'role_id' => $rolId]);
        }

        // spatie cachea el mapa rol→permisos: sin esto el técnico seguiría
        // pasando el gate hasta que expire (deploy.sh no corre cache:clear).
        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }
};
