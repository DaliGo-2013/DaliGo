<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Devolución (M13, flujo A-12): el cliente la declara desde el link público
 * firmado con evidencia fotográfica; bodega la recibe, la categoriza y la
 * resuelve — reembolso vía M14, reingreso al kardex LOCAL, o rechazo.
 *
 * Vocabulario a propósito: NO se usa `devolver`/`devuelto` (eso es M11, el
 * jefe devolviendo un reporte al soplador) ni `returns` (así llama Bsale a
 * las notas de crédito). M13 registra la DECISIÓN de reembolso; la nota de
 * crédito es de M05.
 *
 * Auditable: este registro existe porque hoy nada queda registrado (una sola
 * encargada concentra todo sin apoyo — biblia A-12).
 */
class Devolucion extends Model implements AuditableContract
{
    use AuditableTrait;
    use HasFactory;

    // El pluralizador inglés fallaría (devolucions); igual que `despachos`.
    protected $table = 'devoluciones';

    public const SOLICITADA = 'solicitada';
    public const RECIBIDA = 'recibida';
    public const EVALUADA = 'evaluada';
    public const REEMBOLSADA = 'reembolsada';
    public const REINGRESADA = 'reingresada';
    public const RECHAZADA = 'rechazada';

    public const ESTADOS = [
        self::SOLICITADA, self::RECIBIDA, self::EVALUADA,
        self::REEMBOLSADA, self::REINGRESADA, self::RECHAZADA,
    ];

    /** Canal de la venta original (clave => etiqueta). A MANO: M09 no existe. */
    public const CANALES = [
        'mercado_libre' => 'Mercado Libre',
        'falabella' => 'Falabella',
        'wordpress' => 'Tienda web',
        'mostrador' => 'Mostrador',
        'otro' => 'Otro',
    ];

    /** Causa del daño/devolución (la fija bodega al evaluar). */
    public const CAUSAS = [
        'transporte' => 'Daño en transporte',
        'fabrica' => 'Defecto de fábrica',
        'otro' => 'Otro',
    ];

    protected $fillable = [
        'folio', 'token', 'estado', 'canal', 'causa',
        'cliente_id', 'cliente_rut', 'cliente_nombre', 'cliente_email', 'cliente_telefono',
        'documento_venta_id', 'folio_referencia',
        'transportista', 'seguimiento', 'conductor_id',
        'monto_reembolso', 'motivo', 'resolucion_motivo',
        'sucursal_id', 'recibida_at', 'recibida_por', 'resuelta_at', 'resuelta_por',
        'ip', 'user_agent',
    ];

    /** El token de acceso público jamás va al log de auditoría. */
    protected array $auditExclude = ['token'];

    protected function casts(): array
    {
        return [
            'monto_reembolso' => 'decimal:4',
            'recibida_at' => 'datetime',
            'resuelta_at' => 'datetime',
        ];
    }

    /** Binding de la ruta pública por token (el id no viaja en el link). */
    public function getRouteKeyName(): string
    {
        return 'token';
    }

    /** Folio legible único (DEV-00042). Reintenta ante colisión. */
    public static function generarFolioUnico(): string
    {
        do {
            $folio = 'DEV-'.Str::upper(Str::random(6));
        } while (static::where('folio', $folio)->exists());

        return $folio;
    }

    public function esResuelta(): bool
    {
        return in_array($this->estado, [self::REEMBOLSADA, self::REINGRESADA, self::RECHAZADA], true);
    }

    /**
     * Declaradas por el cliente que bodega aún no recibe: la ACCIÓN pendiente
     * que ancla el badge del menú (doctrina de badges) — espejo único para el
     * badge y cualquier superficie futura.
     */
    public function scopePorRecibir($query)
    {
        return $query->where('estado', self::SOLICITADA);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DevolucionItem::class);
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(DevolucionFoto::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(DevolucionMovimiento::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function documentoVenta(): BelongsTo
    {
        return $this->belongsTo(DocumentoVenta::class, 'documento_venta_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Conductor::class);
    }

    public function recibidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recibida_por');
    }

    public function resueltaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelta_por');
    }
}
