<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jefe directo de un usuario (auto-referencia users.jefe_id -> users.id).
     * Modela la jerarquía comercial (jefatura -> sus vendedores) que hoy no
     * existía. Su único uso por ahora es la VISIBILIDAD en Servicio Técnico:
     * una jefatura ve las órdenes de su cartera + la de los vendedores a su
     * cargo. Nullable (la mayoría no tiene jefe); ON DELETE SET NULL para no
     * romper cuentas si se elimina al jefe. Idempotente.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'jefe_id')) {
                $table->foreignId('jefe_id')->nullable()->after('sucursal_id')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'jefe_id')) {
                $table->dropConstrainedForeignId('jefe_id');
            }
        });
    }
};
