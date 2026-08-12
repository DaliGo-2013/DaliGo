<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DOCUMENTOS QUE SE PUEDEN CREAR (pedido del dueño 11-08-2026: «otra opción para
 * crear uno nuevo si a futuro pidieran»).
 *
 * Los cinco de siempre —revisión técnica, emisiones, permiso de circulación, SOAP y
 * extintor— viven como COLUMNAS de `vehiculos` y ahí se quedan: son los que exigen
 * la ley y el semáforo, están en el Excel y en el comando de avisos, y moverlos a
 * una tabla sería un refactor grande a cambio de nada.
 *
 * Lo que se agrega es la vía para los que aparezcan después (una póliza de carga
 * peligrosa, un certificado de la grúa) sin tocar código ni migrar nada:
 *
 *  · `vehiculo_documento_tipos` es el catálogo, con a QUÉ tipos de vehículo aplica
 *    —vacío = a todos—. Esto último no es un adorno: un documento que aplica a toda
 *    la flota deja a los 17 vehículos en «sin fecha» el día que se crea, y si en
 *    realidad era solo para los camiones, ese semáforo rojo es ruido.
 *  · `vehiculo_documento_fechas` guarda el vencimiento por vehículo. Sin fila = sin
 *    fecha cargada, igual que una columna en null.
 *
 * La foto usa la tabla de respaldos que ya existe, con la clave `tipo:{id}`: por eso
 * `vehiculo_documentos.documento` es un string y no un enum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiculo_documento_tipos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);
            // A qué tipos de vehículo aplica. Lista vacía = a todos.
            $table->json('aplica_a')->nullable();
            // Se DESACTIVA en vez de borrarse: borrar el tipo dejaría huérfanas las
            // fechas y las fotos que ya se cargaron, que son el registro de que ese
            // papel existió. Desactivado sale del semáforo y de los formularios.
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('vehiculo_documento_fechas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->cascadeOnDelete();
            $table->foreignId('tipo_id')->constrained('vehiculo_documento_tipos')->cascadeOnDelete();
            $table->date('vence')->nullable();
            $table->timestamps();

            // Un vehículo tiene UNA fecha por tipo. Sin esto, dos guardados seguidos
            // dejarían dos filas y el semáforo leería cualquiera de las dos.
            $table->unique(['vehiculo_id', 'tipo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiculo_documento_fechas');
        Schema::dropIfExists('vehiculo_documento_tipos');
    }
};
