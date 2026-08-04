<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Una parada de la hoja de ruta = un despacho (que a su vez es 1:1 con el
 * documento de venta). El unique GLOBAL de despacho_id garantiza que un
 * despacho no viva en dos hojas a la vez.
 *
 * Auditable a propósito: la edición del orden y del estado de cobro es
 * exactamente lo que R6 exige rastrear («quién y qué»).
 */
class HojaRutaParada extends Model implements AuditableContract
{
    use AuditableTrait;

    protected $table = 'hoja_ruta_paradas';

    public const COBRO_PAGADO = 'pagado';

    public const COBRO_EN_ENTREGA = 'cobrar_en_entrega';

    public const COBRO_CREDITO = 'credito';

    public const ESTADOS_COBRO = [
        self::COBRO_PAGADO,
        self::COBRO_EN_ENTREGA,
        self::COBRO_CREDITO,
    ];

    public const RESULTADO_ENTREGADA = 'entregada';

    public const RESULTADO_RECHAZADA = 'rechazada';

    public const RESULTADO_REPROGRAMADA = 'reprogramada';

    public const RESULTADOS = [
        self::RESULTADO_ENTREGADA,
        self::RESULTADO_RECHAZADA,
        self::RESULTADO_REPROGRAMADA,
    ];

    protected $fillable = [
        'hoja_de_ruta_id',
        'despacho_id',
        'orden',
        'estado_cobro',
        'cobro_metodo',
        'cobro_monto',
        'resultado',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'cobro_monto' => 'integer',
        ];
    }

    public function hoja(): BelongsTo
    {
        return $this->belongsTo(HojaDeRuta::class, 'hoja_de_ruta_id');
    }

    public function despacho(): BelongsTo
    {
        return $this->belongsTo(Despacho::class);
    }
}
