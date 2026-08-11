<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un respaldo digital de un documento del vehículo: la foto del SOAP, del
 * permiso de circulación, etc., ya comprimida por CompresorDeDocumentos.
 *
 * El VIGENTE es el más nuevo por (vehículo, documento); todo lo anterior es
 * historial y no se borra al subir uno nuevo. El archivo vive en storage/
 * (privado) y se sirve solo por la ruta autenticada — nunca por URL directa:
 * lleva patente y datos del vehículo, que es dato personal bajo la 21.719.
 */
class VehiculoDocumento extends Model
{
    protected $table = 'vehiculo_documentos';

    protected $fillable = ['vehiculo_id', 'documento', 'ruta', 'tamano_kb', 'subido_por'];

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    /** El más nuevo primero: el [0] es el vigente, el resto es historial. */
    public function scopeDelDocumento(Builder $q, int $vehiculoId, string $clave): Builder
    {
        return $q->where('vehiculo_id', $vehiculoId)
            ->where('documento', $clave)
            ->orderByDesc('id');
    }
}
