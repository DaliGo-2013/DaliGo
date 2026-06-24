<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Orden de servicio tecnico: el ingreso de una maquina/lavadora al taller.
 * Espeja el Excel de OneDrive dentro de DaliGo. El dueno del equipo se enlaza
 * por RUT a la ficha de clientes (cliente_id), opcional para no frenar un
 * ingreso de mostrador. Auditable: queda registro de quien ingreso/edito.
 */
class OrdenServicio extends Model implements AuditableContract
{
    /** @use HasFactory<\Database\Factories\OrdenServicioFactory> */
    use HasFactory, AuditableTrait;

    public const TIPOS = ['maquina', 'lavadora', 'herramienta', 'otro'];

    // Lista simple (NO transiciones): el formulario las ofrece en un <select>.
    public const ESTADOS = ['recibido', 'en_revision', 'esperando_repuesto', 'reparado', 'entregado', 'sin_solucion'];

    // Condicion del ingreso. Garantia: no se cobra (si esta vigente).
    // Reparacion: se cobra al cliente.
    public const FACTURACION = ['garantia', 'reparacion'];

    // Documento de compra que respalda la garantia.
    public const GARANTIA_DOC_TIPOS = ['factura', 'boleta'];

    // Duracion de la garantia desde la fecha de compra.
    public const GARANTIA_MESES = 6;

    // El pluralizador ingles haria 'orden_servicios'; fijamos la tabla correcta.
    protected $table = 'ordenes_servicio';

    protected $fillable = [
        'cliente_id',
        'cliente_nombre',
        'cliente_rut',
        'producto_id',
        'sucursal_id',
        'fecha_ingreso',
        'tipo_equipo',
        'modelo',
        'numero_serie',
        'falla_reportada',
        'estado',
        'facturacion',
        'garantia_doc_tipo',
        'garantia_doc_numero',
        'garantia_doc_fecha',
        'observaciones',
        'fecha_entrega',
        'fuente',
    ];

    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'date',
            'fecha_entrega' => 'date',
            'garantia_doc_fecha' => 'date',
        ];
    }

    /**
     * Fecha en que vence la garantia: 6 meses desde la compra. Null si no hay
     * documento de compra cargado.
     */
    public function getGarantiaVenceAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->garantia_doc_fecha?->copy()->addMonths(self::GARANTIA_MESES);
    }

    /**
     * Garantia vigente al momento de ingresar el equipo al taller: la compra
     * esta dentro de la ventana de 6 meses respecto de la fecha de ingreso.
     */
    public function getGarantiaVigenteAttribute(): bool
    {
        if ($this->facturacion !== 'garantia' || ! $this->garantia_doc_fecha || ! $this->fecha_ingreso) {
            return false;
        }

        return $this->garantia_vence->gte($this->fecha_ingreso);
    }

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Producto Dali del catalogo (el "codigo" del equipo, por SKU).
     *
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * Folio visible derivado del id: #000123. No hay columna propia (evita un
     * contador paralelo); el id ya es unico e indexado.
     */
    public function getFolioAttribute(): string
    {
        return '#'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
