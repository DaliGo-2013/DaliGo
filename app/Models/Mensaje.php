<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mensaje del chat interno (MSG-1, PLAN-MENSAJES §5.1). Append-only: no se
 * edita ni se borra (traza honesta); el emisor va nullOnDelete para que el
 * mensaje sobreviva al usuario (se pinta «—»).
 *
 * Sin Auditable a proposito: alto volumen y la fila es su propia traza.
 */
class Mensaje extends Model
{
    protected $table = 'mensajes';

    // Tope de texto validado tambien en el servicio (columna string(1000)).
    public const TEXTO_MAX = 1000;

    protected $fillable = [
        'conversacion_id',
        'emisor_id',
        'texto',
    ];

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(Conversacion::class, 'conversacion_id');
    }

    public function emisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emisor_id');
    }
}
