<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log append-only de cada lectura del QR de un despacho (anti-fraude M07):
 * el escaneo válido, el doble retiro y el estado inválido dejan fila igual.
 * Nunca se edita ni se borra (sin updated_at); es la evidencia de la alerta.
 */
class EscaneoDespacho extends Model
{
    protected $table = 'escaneos_despacho';

    public const UPDATED_AT = null; // append-only

    public const VALIDO = 'valido';
    public const DOBLE_RETIRO = 'doble_retiro';
    public const ESTADO_INVALIDO = 'estado_invalido';

    public const RESULTADOS = [
        self::VALIDO,
        self::DOBLE_RETIRO,
        self::ESTADO_INVALIDO,
    ];

    protected $fillable = [
        'despacho_id',
        'user_id',
        'resultado',
        'detalle',
    ];

    /** @return BelongsTo<Despacho, $this> */
    public function despacho(): BelongsTo
    {
        return $this->belongsTo(Despacho::class, 'despacho_id');
    }

    /** @return BelongsTo<User, $this> */
    public function operador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
