<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Espejo read-only de un documento de venta (DTE) de Bsale.
 *
 * Bsale es el dueño de estos datos: la única escritura legítima es
 * DocumentSync (upsert por bsale_document_id, sin delete). La anulación se
 * detecta por cancellation_status/cancellation_at, no borrando la fila —
 * PERO solo es fresca dentro del resolape de la sync (~1 día tras la emisión):
 * una anulación posterior no se re-lee hasta que haya webhooks (D-005). Todo
 * consumidor que ACTÚE sobre un documento (p.ej. crear un despacho) debe
 * re-verificar el doc puntual contra Bsale antes, no confiar en este flag.
 * Los montos vienen en CLP (enteros en la API; decimal por consistencia con
 * el resto del espejo). Fechas Bsale llegan como epoch y se guardan datetime.
 */
class DocumentoVenta extends Model implements AuditableContract
{
    use AuditableTrait;

    // El pluralizador inglés fallaría (documento_ventas); igual que `despachos`.
    protected $table = 'documentos_venta';

    protected $fillable = [
        'bsale_document_id',
        'folio',
        'bsale_document_type_id',
        'emitido_at',
        'neto',
        'iva',
        'total',
        'state',
        'commercial_state',
        'cancellation_status',
        'cancellation_at',
        'informed_sii',
        'url_pdf',
        'url_public',
        'token',
        'cliente_id',
        'bodega_id',
    ];

    protected function casts(): array
    {
        return [
            'emitido_at' => 'datetime',
            'cancellation_at' => 'datetime',
            'neto' => 'decimal:4',
            'iva' => 'decimal:4',
            'total' => 'decimal:4',
        ];
    }

    /** @return HasMany<DocumentoVentaDetalle, $this> */
    public function detalles(): HasMany
    {
        return $this->hasMany(DocumentoVentaDetalle::class, 'documento_venta_id');
    }

    /** @return HasMany<Despacho, $this> */
    public function despachos(): HasMany
    {
        return $this->hasMany(Despacho::class, 'documento_venta_id');
    }

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /** @return BelongsTo<Bodega, $this> */
    public function bodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class);
    }

    /** Anulado en Bsale (cancellation_status distinto de 0/null). */
    public function estaAnulado(): bool
    {
        return ($this->cancellation_status ?? 0) !== 0;
    }
}
