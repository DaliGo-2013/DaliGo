<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El molde como entidad (P-M11-12, PLAN-M11-FINAL F3) — ficha estilo M18:
 * contador de ciclos que se alimenta solo al aprobar reportes, umbral de
 * mantención con aviso M15, e historial de mantenciones (la correctiva
 * puede nacer sola desde una parada «Molde dañado»).
 *
 * El molde NO porta `ciclo_ideal_seg`: ese dato sigue viviendo en la fila
 * ROL_PREFORMA de `recetas` (un solo lugar escribible — la ficha del molde
 * lo MUESTRA con enlace a la receta). Mover la columna acá habría dejado
 * el OEE sin ciclos el día del deploy (la tabla nace vacía y se puebla a
 * mano) y la resolución por tanda del rendimiento es por producto, no por
 * molde. Alternativa evaluada y descartada en el parte v43.
 *
 * `produccion_reportes.molde_id` es ADITIVO nullable (frontera con Max-2,
 * declarado): solo se usa cuando la inferencia «molde activo del tipo» es
 * ambigua (2+ moldes activos para el mismo tipo) y el jefe elige al aprobar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moldes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 191);
            $table->foreignId('tipo_botellon_id')->constrained('tipos_botellon')->restrictOnDelete();
            // Cavidades FISICAS del molde (cuántas unidades salen por ciclo).
            // Nullable: dato [B] que carga el dueño/Luis en la ficha.
            $table->unsignedTinyInteger('cavidades')->nullable();
            $table->unsignedBigInteger('ciclos_acumulados')->default(0);
            // Ciclos a los que toca mantención preventiva; NULL = sin umbral.
            $table->unsignedInteger('umbral_mantencion')->nullable();
            // 'activo' | 'en_mantencion' | 'retirado' (constantes de Molde).
            $table->string('estado', 32)->default('activo');
            $table->string('notas', 191)->nullable();
            // Guard «una vez por cruce de umbral» (patrón aviso_stock_nuevo
            // de M04-F2): registrar la mantención lo limpia y re-arma.
            $table->timestamp('aviso_umbral_at')->nullable();
            $table->timestamps();

            $table->unique(['tipo_botellon_id', 'nombre']);
        });

        Schema::create('molde_mantenciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('molde_id')->constrained('moldes')->cascadeOnDelete();
            // La correctiva automática nace de un reporte aprobado con parada
            // «Molde dañado»: el enlace es su guard de idempotencia y su traza.
            $table->foreignId('reporte_id')->nullable()->constrained('produccion_reportes')->nullOnDelete();
            // 'preventiva' | 'correctiva' (constantes de MoldeMantencion).
            $table->string('tipo', 32);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_nombre', 191)->nullable();
            $table->string('nota', 191)->nullable();
            // Ciclos que llevaba el molde al momento (snapshot para historial).
            $table->unsignedBigInteger('ciclos_al_momento')->default(0);
            // NULL = pendiente (la correctiva automática nace así); registrarla
            // la completa. Una preventiva registrada nace completada.
            $table->timestamp('realizada_at')->nullable();
            $table->timestamps();

            $table->index(['molde_id', 'realizada_at']);
        });

        if (! Schema::hasColumn('produccion_reportes', 'molde_id')) {
            Schema::table('produccion_reportes', function (Blueprint $table) {
                $table->foreignId('molde_id')->nullable()->after('cavidades_activas')
                    ->constrained('moldes')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('produccion_reportes', 'molde_id')) {
            Schema::table('produccion_reportes', function (Blueprint $table) {
                $table->dropConstrainedForeignId('molde_id');
            });
        }

        Schema::dropIfExists('molde_mantenciones');
        Schema::dropIfExists('moldes');
    }
};
