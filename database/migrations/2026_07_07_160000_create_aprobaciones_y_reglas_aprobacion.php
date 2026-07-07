<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M14 — Motor de aprobaciones digitales (PLAN-M14 §1.2).
 *
 * Aditiva y MySQL 5.7-safe: sin ENUM nativo (`estado` es string con constantes
 * de clase), strings indexados ≤191 (defaultStringLength cubre el morph), sin
 * JSON nativo (`datos` es TEXT con cast array). `reglas_aprobacion` define QUÉ
 * requiere aprobación y quién aprueba; `aprobaciones` es el histórico completo
 * (también de las auto-aprobadas: la biblia exige registro aunque no haya
 * habido humano). "Escalada" NO es un estado: es nivel + timestamp + reescritura
 * del rol vigente — la solicitud sigue `pendiente`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reglas_aprobacion', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_accion', 64)->unique(); // catálogo Aprobacion::TIPOS_ACCION
            $table->string('descripcion', 191);
            $table->boolean('activa')->default(true); // inactiva = nunca matchea (todo auto-aprueba)
            // CLAVE de `configuraciones` cuyo valor entero es el umbral (así el
            // admin lo edita en /admin/configuracion sin UI de reglas). NULL =
            // la regla matchea siempre.
            $table->string('umbral_config', 64)->nullable();
            $table->string('rol_aprobador', 64); // rol spatie del nivel 0
            $table->string('rol_escalamiento', 64)->nullable(); // NULL = no escala
            $table->timestamps();
        });

        Schema::create('aprobaciones', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_accion', 64)->index();
            $table->foreignId('regla_id')->nullable()
                ->constrained('reglas_aprobacion')->nullOnDelete();
            $table->nullableMorphs('aprobable'); // objetivo (v1: ProduccionReporte)
            $table->foreignId('solicitante_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('estado', 32)->default('pendiente'); // pendiente | aprobada | rechazada | auto_aprobada
            // Magnitud evaluada contra el umbral (CLP o unidades según el tipo);
            // denormalizada para reportes (el payload `datos` no es consultable).
            $table->unsignedBigInteger('monto')->nullable();
            $table->string('motivo', 255); // del solicitante
            $table->string('descripcion', 255); // texto humano para la bandeja
            $table->text('datos')->nullable(); // payload diferido {nuevo, anterior, objetivo_updated_at}
            $table->string('rol_aprobador', 64); // rol VIGENTE (se reescribe al escalar)
            $table->unsignedTinyInteger('nivel_escalamiento')->default(0);
            $table->timestamp('escalada_at')->nullable();
            $table->foreignId('resuelto_por')->nullable()
                ->constrained('users')->nullOnDelete(); // = solicitante en auto-aprobadas
            $table->timestamp('resuelta_at')->nullable();
            $table->string('resultado_motivo', 255)->nullable(); // rechazo humano o conflicto automático
            $table->timestamps();

            $table->index(['estado', 'rol_aprobador']); // bandeja del aprobador
            $table->index(['estado', 'created_at']); // barrido del escalamiento
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aprobaciones');
        Schema::dropIfExists('reglas_aprobacion');
    }
};
