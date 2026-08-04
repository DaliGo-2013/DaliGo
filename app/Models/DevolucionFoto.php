<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Foto de evidencia de una devolución (M13). `ruta` es relativa al disco
 * PRIVADO `local` (se sirve solo con sesión, nunca por URL pública — patrón
 * orden_servicio_fotos). `origen` distingue los DOS momentos de evidencia
 * (decisión del dueño 30-07): la del CLIENTE prueba lo que reclama; la de
 * BODEGA prueba el estado real al llegar — para un reclamo al transportista
 * hacen falta las dos.
 */
class DevolucionFoto extends Model
{
    use HasFactory;

    protected $table = 'devolucion_fotos';

    public const CLIENTE = 'cliente';
    public const BODEGA = 'bodega';

    protected $fillable = ['devolucion_id', 'ruta', 'origen'];

    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(Devolucion::class);
    }
}
