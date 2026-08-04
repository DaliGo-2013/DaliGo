<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M13 · Devoluciones (E6, lote 1) — la primera migración del módulo, tras el
 * visto bueno del dueño a PLAN-M13 (regla del gate previo al código).
 *
 * Cuatro tablas (PLAN-M13 §1.2; `devoluciones`/`devolucion_items` las nombra
 * la biblia §5):
 * - devoluciones: el agregado. El cliente la crea desde la ruta pública
 *   firmada; bodega la recibe y categoriza; se resuelve por reembolso (M14),
 *   reingreso (kardex local) o rechazo.
 * - devolucion_items: qué se devuelve. `producto_id` nullable a propósito
 *   (el cliente describe en texto; el enlace al espejo M02 lo pone bodega).
 * - devolucion_fotos: evidencia con `origen` cliente|bodega — la foto del
 *   cliente prueba lo que reclama, la de bodega el estado REAL al llegar
 *   (decisión del dueño 30-07: los DOS momentos).
 * - devolucion_movimientos: kardex LOCAL. JAMÁS escribe `stocks`/`bodegas`
 *   (espejo read-only de Bsale) — mismo contrato que produccion_movimientos
 *   (HANDOFF §8d); el push a Bsale espera a M04/D-005.
 *
 * MySQL 5.7: estados string(32), montos decimal(14,4), índices ≤191.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('devoluciones')) {
            return;
        }

        Schema::create('devoluciones', function (Blueprint $table) {
            $table->id();
            // Folio legible que el cliente muestra y bodega busca (DEV-00001).
            $table->string('folio', 32)->unique();
            // Route key del link público: el id jamás viaja (anti-enumeración,
            // patrón OrdenServicioCotizacion).
            $table->string('token', 64)->unique();
            $table->string('estado', 32)->default('solicitada'); // solicitada|recibida|evaluada|reembolsada|reingresada|rechazada
            $table->string('canal', 32); // mercado_libre|falabella|wordpress|mostrador|otro — a mano: M09 no existe
            $table->string('causa', 32)->nullable(); // transporte|fabrica|otro — null hasta que bodega evalúa

            // Cliente: enlace BLANDO al espejo M03 + denormalización (idioma de
            // la casa: OrdenServicio/AgendaTrabajo/Instalacion). El form público
            // puede traer un cliente que no está en el catálogo.
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('cliente_rut', 20)->nullable();
            $table->string('cliente_nombre', 191);
            $table->string('cliente_email', 191);
            $table->string('cliente_telefono', 32)->nullable();

            // Documento de venta: al espejo si el folio matchea; texto si no
            // (venta vieja fuera de la ventana de sync, orden de marketplace).
            $table->foreignId('documento_venta_id')->nullable()->constrained('documentos_venta')->nullOnDelete();
            $table->string('folio_referencia', 64)->nullable();

            // Reclamo de transporte (decisión del dueño 30-07: el DATO entra,
            // el flujo de reclamo no). Couriers externos — no confundir con la
            // flota propia de M18.
            $table->string('transportista', 64)->nullable();
            $table->string('seguimiento', 64)->nullable();
            $table->foreignId('conductor_id')->nullable()->constrained('conductores')->nullOnDelete();

            $table->decimal('monto_reembolso', 14, 4)->nullable(); // la magnitud que va a M14
            $table->text('motivo'); // lo que escribe el cliente
            $table->text('resolucion_motivo')->nullable(); // lo que escribe quien cierra

            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->dateTime('recibida_at')->nullable();
            $table->foreignId('recibida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resuelta_at')->nullable();
            $table->foreignId('resuelta_por')->nullable()->constrained('users')->nullOnDelete();

            // Rastro del envío público (mismo que la respuesta a cotización).
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 191)->nullable();
            $table->timestamps();

            // Queries reales: bandeja de bodega por antigüedad; reportes P-M13-04
            // por canal y causa; búsqueda del mostrador por RUT.
            $table->index(['estado', 'created_at']);
            $table->index(['canal', 'causa']);
            $table->index('cliente_rut');
        });

        Schema::create('devolucion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devolucion_id')->constrained('devoluciones')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('descripcion', 191); // lo que el cliente dice devolver
            $table->unsignedInteger('cantidad');
            $table->string('estado_producto', 32)->nullable(); // apto|danado|incompleto — lo fija bodega al evaluar
            $table->timestamps();
        });

        Schema::create('devolucion_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devolucion_id')->constrained('devoluciones')->cascadeOnDelete();
            $table->string('ruta'); // relativa al disco PRIVADO local (patrón orden_servicio_fotos)
            $table->string('origen', 32); // cliente|bodega — los DOS momentos de evidencia
            $table->timestamps();
        });

        Schema::create('devolucion_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devolucion_id')->constrained('devoluciones')->cascadeOnDelete();
            $table->foreignId('devolucion_item_id')->nullable()->constrained('devolucion_items')->nullOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->decimal('cantidad', 14, 4);
            $table->string('tipo', 32); // reingreso|merma
            // Texto libre a propósito: la estructura de bodegas es D-003 (EN
            // CURSO, Luis) — nada de M13 la asume.
            $table->string('bodega_destino', 64)->nullable();
            $table->string('observacion', 191)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devolucion_movimientos');
        Schema::dropIfExists('devolucion_fotos');
        Schema::dropIfExists('devolucion_items');
        Schema::dropIfExists('devoluciones');
    }
};
