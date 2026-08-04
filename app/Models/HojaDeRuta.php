<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Hoja de ruta digital (P-DSP-08, PLAN-DESPACHOS-V2): una salida de un
 * vehículo (R2), armada por zona (R21), con las paradas elegidas desde los
 * documentos de venta y la cadena de 3 llaves secuenciales (R11).
 *
 * El folio lo asigna HojaRutaService::crear() (correlativo desde 1000 bajo
 * lock, R25); las transiciones las aplica el service bajo lock con el mapa
 * TRANSICIONES — nunca se escribe `estado` a mano desde un controller.
 */
class HojaDeRuta extends Model implements AuditableContract
{
    /** @use HasFactory<\Database\Factories\HojaDeRutaFactory> */
    use AuditableTrait, HasFactory;

    protected $table = 'hojas_de_ruta';

    public const BORRADOR = 'borrador';

    public const PAGOS_OK = 'pagos_ok';

    public const RUTA_AUTORIZADA = 'ruta_autorizada';

    public const CARGADA = 'cargada';

    public const EN_RUTA = 'en_ruta';

    public const CERRADA = 'cerrada';

    public const ESTADOS = [
        self::BORRADOR,
        self::PAGOS_OK,
        self::RUTA_AUTORIZADA,
        self::CARGADA,
        self::EN_RUTA,
        self::CERRADA,
    ];

    /**
     * La máquina de estados: desde cada estado, el ÚNICO destino válido.
     * Secuencial estricta a propósito (R11) — no hay saltos ni retrocesos;
     * deshacer una llave es territorio de la edición auditada (P-DSP-10).
     * La transición cerrada la implementa P-DSP-10 (cierre del jefe de
     * logística, R18); el estado ya existe para que la constante no cambie.
     */
    public const TRANSICIONES = [
        self::BORRADOR => self::PAGOS_OK,
        self::PAGOS_OK => self::RUTA_AUTORIZADA,
        self::RUTA_AUTORIZADA => self::CARGADA,
        self::CARGADA => self::EN_RUTA,
        self::EN_RUTA => self::CERRADA,
    ];

    /** El folio arranca en 1000 (pedido de Luis, R25): max(folio, 999) + 1. */
    public const FOLIO_PISO = 999;

    protected $fillable = [
        'folio',
        'sucursal_id',
        'zona_id',
        'vehiculo_id',
        'vehiculo',
        'patente',
        'conductor_id',
        'peoneta_nombre',
        'estado',
        'pagos_ok_at',
        'pagos_ok_por',
        'ruta_autorizada_at',
        'ruta_autorizada_por',
        'cargada_at',
        'cargada_por',
        'en_ruta_at',
        'en_ruta_por',
    ];

    protected function casts(): array
    {
        return [
            'folio' => 'integer',
            'pagos_ok_at' => 'datetime',
            'ruta_autorizada_at' => 'datetime',
            'cargada_at' => 'datetime',
            'en_ruta_at' => 'datetime',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    /** FK blanda al catálogo M18; el snapshot vehiculo/patente es la verdad histórica. */
    public function vehiculoCatalogo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class, 'vehiculo_id');
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conductor_id');
    }

    public function paradas(): HasMany
    {
        return $this->hasMany(HojaRutaParada::class, 'hoja_de_ruta_id')->orderBy('orden');
    }

    // Quién dio cada llave (la columna *_por guarda el id; estas relaciones
    // resuelven el nombre para la pantalla — camelCase para no chocar con
    // el atributo snake_case).
    public function pagosOkPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pagos_ok_por');
    }

    public function rutaAutorizadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ruta_autorizada_por');
    }

    public function cargadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cargada_por');
    }

    public function enRutaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'en_ruta_por');
    }

    public function scopeEnRuta(Builder $query): Builder
    {
        return $query->where('estado', self::EN_RUTA);
    }

    /** ¿Puede pasar de su estado actual a $destino? (solo el paso siguiente). */
    public function puedeTransicionarA(string $destino): bool
    {
        return (self::TRANSICIONES[$this->estado] ?? null) === $destino;
    }
}
