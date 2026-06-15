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

    public const TIPOS = ['maquina', 'lavadora', 'otro'];

    // Lista simple (NO transiciones): el formulario las ofrece en un <select>.
    public const ESTADOS = ['recibido', 'en_revision', 'esperando_repuesto', 'reparado', 'entregado', 'sin_solucion'];

    // Garantia: no se cobra. Boleta: se cobra la reparacion.
    public const FACTURACION = ['garantia', 'boleta'];

    // El pluralizador ingles haria 'orden_servicios'; fijamos la tabla correcta.
    protected $table = 'ordenes_servicio';

    protected $fillable = [
        'cliente_id',
        'producto_id',
        'sucursal_id',
        'fecha_ingreso',
        'tipo_equipo',
        'modelo',
        'numero_serie',
        'falla_reportada',
        'accesorios',
        'estado',
        'facturacion',
        'observaciones',
        'fecha_entrega',
        'fuente',
    ];

    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'date',
            'fecha_entrega' => 'date',
        ];
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
