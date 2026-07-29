<?php

namespace App\Models;

use App\Services\Dte\EstadoSii;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Documento tributario que DaliGo mandó a emitir (M05).
 *
 * Es el registro LOCAL de lo emitido: quién, cuándo, con qué folio y qué dijo el
 * SII. No reemplaza al emisor (Bsale hoy) sino que lo respalda — el SII obliga a
 * conservar los documentos 6 años, y si algún día se corta con el emisor este es
 * el rastro que queda en casa.
 *
 * Auditable: es un documento tributario, así que interesa saber quién tocó qué.
 *
 * @property string $estado_sii Una constante de App\Services\Dte\EstadoSii
 */
class DteEmitido extends Model implements AuditableContract
{
    use AuditableTrait;

    protected $table = 'dte_emitidos';

    /**
     * Valores por defecto en el MODELO, no solo en la tabla: una fila recién
     * creada tiene que conocer su propio estado sin ir a releer la BD. Sin esto,
     * `DteEmitido::create([...])->estado_sii` devuelve null (el default de MySQL
     * existe pero Eloquent no lo trae de vuelta) y el código que decida sobre
     * ese null trataría un documento nuevo como si no tuviera estado.
     */
    protected $attributes = [
        'emisor' => 'bsale',
        'estado_sii' => EstadoSii::PENDIENTE,
        'neto' => 0,
        'iva' => 0,
        'total' => 0,
    ];

    protected $fillable = [
        'emisor',
        'tipo_dte',
        'folio',
        'documento_externo_id',
        'sales_id',
        'receptor_rut',
        'receptor_nombre',
        'neto',
        'iva',
        'total',
        'estado_sii',
        'mensaje_sii',
        'url_xml',
        'url_pdf',
        'orden_servicio_id',
        'sucursal_id',
        'emitido_por',
        'emitido_at',
    ];

    protected function casts(): array
    {
        return [
            'tipo_dte' => 'integer',
            'folio' => 'integer',
            'neto' => 'integer',
            'iva' => 'integer',
            'total' => 'integer',
            'emitido_at' => 'datetime',
        ];
    }

    /** Etiqueta del tipo de documento (33 → "Factura electrónica"). */
    public const TIPO_ETIQUETAS = [
        33 => 'Factura electrónica',
        34 => 'Factura exenta',
        39 => 'Boleta electrónica',
        41 => 'Boleta exenta',
        52 => 'Guía de despacho',
        56 => 'Nota de débito',
        61 => 'Nota de crédito',
        110 => 'Factura de exportación',
        111 => 'Nota de débito de exportación',
        112 => 'Nota de crédito de exportación',
    ];

    public function getTipoLabelAttribute(): string
    {
        return self::TIPO_ETIQUETAS[$this->tipo_dte] ?? "DTE {$this->tipo_dte}";
    }

    public function getEstadoLabelAttribute(): string
    {
        return EstadoSii::etiqueta($this->estado_sii);
    }

    public function getEstadoVarianteAttribute(): string
    {
        return EstadoSii::variante($this->estado_sii);
    }

    /**
     * Folio legible para el usuario (el número que el cliente ve en el papel).
     * Sin folio todavía = "sin folio" en vez de un cero confuso.
     */
    public function getFolioLabelAttribute(): string
    {
        return $this->folio ? (string) $this->folio : 'sin folio';
    }

    /** @return BelongsTo<OrdenServicio, $this> */
    public function ordenServicio(): BelongsTo
    {
        return $this->belongsTo(OrdenServicio::class);
    }

    /** @return BelongsTo<Sucursal, $this> */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /** @return BelongsTo<User, $this> */
    public function emitidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emitido_por');
    }

    /**
     * Los que aún no tienen veredicto del SII: los persigue el cron de
     * reconsulta. Un documento que se queda en 'enviado' para siempre es un
     * documento que nadie sabe si vale.
     */
    public function scopePorReconsultar(Builder $query): Builder
    {
        return $query
            ->whereIn('estado_sii', [EstadoSii::PENDIENTE, EstadoSii::ENVIADO])
            ->whereNotNull('documento_externo_id');
    }

    /** Rechazados por el SII: hay que corregirlos con nota de crédito. */
    public function scopeRechazados(Builder $query): Builder
    {
        return $query->where('estado_sii', EstadoSii::RECHAZADO);
    }
}
