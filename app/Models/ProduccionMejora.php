<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Propuesta de mejora del soplador (P-M11-23, kaizen digital): texto libre
 * que llega a la bandeja del jefe en el panel de produccion; el jefe la
 * revisa/aplica/descarta con respuesta opcional y el soplador ve el estado
 * en su historial de mi-reporte. Conversacion estructurada, sin M14/M15.
 *
 * Auditable como ProduccionNota: encima de la propuesta hay una DECISION del
 * jefe (estado + respuesta) y conviene la traza de quien cambio que — a
 * diferencia de las paradas/tandas (append-only, autoevidentes).
 */
class ProduccionMejora extends Model implements AuditableContract
{
    use AuditableTrait;

    // Estados del ciclo: pendiente es el de nacimiento (nunca un destino del
    // jefe); revisada = vista y en evaluacion; aplicada/descartada = finales.
    public const PENDIENTE = 'pendiente';

    public const REVISADA = 'revisada';

    public const APLICADA = 'aplicada';

    public const DESCARTADA = 'descartada';

    public const ESTADOS = [
        self::PENDIENTE,
        self::REVISADA,
        self::APLICADA,
        self::DESCARTADA,
    ];

    // Destinos validos de la accion del jefe (pendiente NO: es de nacimiento).
    public const DECISIONES = [
        self::REVISADA,
        self::APLICADA,
        self::DESCARTADA,
    ];

    // Abiertas = todavia esperan algo del jefe (la bandeja lista estas).
    public const ABIERTAS = [
        self::PENDIENTE,
        self::REVISADA,
    ];

    protected $table = 'produccion_mejoras';

    protected $fillable = [
        'soplador_id',
        'cliente_uuid',
        'texto',
        'estado',
        'respuesta',
    ];

    public function soplador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'soplador_id');
    }

    /**
     * Las que todavia esperan una decision del jefe (pendiente o revisada).
     */
    public function scopeAbiertas($query)
    {
        return $query->whereIn('estado', self::ABIERTAS);
    }
}
