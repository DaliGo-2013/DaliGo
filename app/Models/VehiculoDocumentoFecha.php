<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El vencimiento de un documento CREADO desde la app, para un vehículo.
 *
 * Los cinco de la ley son columnas de `vehiculos`; esto es el equivalente para los
 * tipos que se agregan después (ver `VehiculoDocumentoTipo`). Sin fila = sin fecha
 * cargada, exactamente igual que una columna en null.
 */
class VehiculoDocumentoFecha extends Model
{
    protected $table = 'vehiculo_documento_fechas';

    protected $fillable = ['vehiculo_id', 'tipo_id', 'vence'];

    protected $casts = ['vence' => 'date'];

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(VehiculoDocumentoTipo::class, 'tipo_id');
    }
}
