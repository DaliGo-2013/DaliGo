<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registro LOCAL de los documentos tributarios que DaliGo manda a emitir
     * (M05). Hoy los emite Bsale por API; mañana podría ser otro proveedor o
     * emisión propia — por eso la tabla guarda `emisor` y NO asume Bsale.
     *
     * Por qué existe una tabla propia en vez de consultar al emisor cada vez:
     *   1. RESPALDO. El SII obliga a conservar los documentos por 6 años
     *      (Res. Ex. 45/2003). Si mañana se corta con el emisor, el historial
     *      de qué se emitió tiene que quedar en casa.
     *   2. IDEMPOTENCIA. `sales_id` es UNIQUE: es la barrera que impide que un
     *      doble clic, un reintento o un timeout emitan el mismo documento dos
     *      veces. Emitir dos veces no es un bug de UI, es un problema
     *      tributario que se arregla con nota de crédito.
     *   3. TRAZABILIDAD. De qué orden/venta salió cada folio.
     *
     * Los montos van en ENTEROS (pesos chilenos sin decimales), igual que el
     * resto del proyecto (mano_obra, costo_total de ordenes_servicio).
     *
     * Idempotente.
     */
    public function up(): void
    {
        if (Schema::hasTable('dte_emitidos')) {
            return;
        }

        Schema::create('dte_emitidos', function (Blueprint $table) {
            $table->id();

            // Quién lo emitió: 'bsale' hoy. Se guarda para poder convivir con
            // más de un emisor durante una migración (ver App\Services\Dte).
            $table->string('emisor', 20)->default('bsale');

            // Código de tipo de DTE del SII: 33 factura afecta, 34 exenta,
            // 39 boleta, 41 boleta exenta, 52 guía de despacho, 56 nota de
            // débito, 61 nota de crédito, 110/111/112 exportación.
            $table->unsignedSmallInteger('tipo_dte');

            // Folio que asigna el emisor (Bsale lo entrega en `number`). Queda
            // NULL mientras la emisión no se confirma: la fila nace ANTES de
            // llamar a la API, justamente para reservar el sales_id.
            $table->unsignedBigInteger('folio')->nullable();

            // Id del documento en el sistema del emisor (Bsale: document.id).
            $table->string('documento_externo_id', 40)->nullable();

            // Clave de idempotencia derivada del origen (ej. "ST-1234"). UNIQUE
            // a propósito: es la garantía de "un origen, un documento".
            $table->string('sales_id', 100)->unique();

            $table->string('receptor_rut', 20)->nullable();
            $table->string('receptor_nombre', 191)->nullable();

            $table->unsignedBigInteger('neto')->default(0);
            $table->unsignedBigInteger('iva')->default(0);
            $table->unsignedBigInteger('total')->default(0);

            // Estado ante el SII en NUESTRO vocabulario (App\Services\Dte\EstadoSii),
            // no el número crudo del emisor: Bsale usa una semántica invertida
            // (informedSii 0 = correcto) que no queremos filtrar a la app.
            $table->string('estado_sii', 20)->default('pendiente');
            $table->text('mensaje_sii')->nullable();

            // Respaldo del documento electrónico (URLs del emisor). 500 chars:
            // vienen firmadas y son largas.
            $table->string('url_xml', 500)->nullable();
            $table->string('url_pdf', 500)->nullable();

            // Origen en DaliGo. Nullable porque no todo documento nace de una
            // orden de servicio (a futuro: boleta de mostrador, cotización).
            $table->foreignId('orden_servicio_id')->nullable()
                ->constrained('ordenes_servicio')->nullOnDelete();
            $table->foreignId('sucursal_id')->nullable()
                ->constrained('sucursales')->nullOnDelete();
            $table->foreignId('emitido_por')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamp('emitido_at')->nullable();
            $table->timestamps();

            // Búsqueda por folio dentro de un tipo (lo que pide el contador) y
            // barrido de los que quedaron rechazados o pendientes de confirmar.
            $table->index(['tipo_dte', 'folio'], 'dte_emitidos_tipo_folio_index');
            $table->index('estado_sii', 'dte_emitidos_estado_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dte_emitidos');
    }
};
