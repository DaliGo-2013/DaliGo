<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public const TIPOS = ['dispensador', 'lavadora', 'herramienta', 'otro'];

    // Tipos cuyo N° de serie es OBLIGATORIO: tienen una serie unica e importante
    // (dispensadores y lavadoras). El resto (herramienta/otro, ej. bombas de agua)
    // es opcional -> no tienen serie unica por equipo. Usado por la validacion y
    // por el formulario (asterisco + required dinamico segun el tipo elegido).
    public const SERIE_OBLIGATORIA_TIPOS = ['dispensador', 'lavadora'];

    // Lista simple (NO transiciones): el formulario las ofrece en un <select>.
    // 'cotizacion' = se le paso presupuesto al cliente y se espera su aprobacion
    // del arreglo (va despues de la revision, antes de pedir repuestos/reparar).
    public const ESTADOS = ['recibido', 'en_revision', 'cotizacion', 'esperando_repuesto', 'reparado', 'entregado', 'sin_solucion'];

    // Color del badge por etapa (variantes de x-badge), para leer el estado de un vistazo.
    public const ESTADO_VARIANTES = [
        'recibido' => 'brand',
        'en_revision' => 'info',
        'cotizacion' => 'warning',
        'esperando_repuesto' => 'warning',
        'reparado' => 'success',
        'entregado' => 'neutral',
        'sin_solucion' => 'danger',
    ];

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
        'cliente_telefono',
        'cliente_email',
        'producto_id',
        'sucursal_id',
        'fecha_ingreso',
        'tipo_equipo',
        'modelo',
        'numero_serie',
        'falla_reportada',
        'falla_tecnico',
        'estado',
        'facturacion',
        'garantia_doc_tipo',
        'garantia_doc_numero',
        'garantia_doc_fecha',
        'observaciones',
        'fecha_entrega',
        // Etapa de taller (tecnico).
        'trabajo_realizado',
        'mano_obra',
        'fecha_aviso',
        'fecha_retiro',
        'fuente',
        'confirmada_at',
        'recibida_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'date',
            'fecha_entrega' => 'date',
            'garantia_doc_fecha' => 'date',
            'fecha_aviso' => 'date',
            'fecha_retiro' => 'date',
            'confirmada_at' => 'datetime',
            'mano_obra' => 'integer',
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
     * Condicion efectiva para mostrar y cobrar: es garantia SOLO si esta vigente;
     * si la garantia vencio o no tiene documento de respaldo, es reparacion (se
     * cobra). Evita mostrar "garantia vencida" como si fuera garantia.
     */
    public function getCondicionEfectivaAttribute(): string
    {
        return ($this->facturacion === 'garantia' && $this->garantia_vigente) ? 'garantia' : 'reparacion';
    }

    /**
     * Variante de color del badge segun el estado actual.
     */
    public function getEstadoVarianteAttribute(): string
    {
        return self::ESTADO_VARIANTES[$this->estado] ?? 'brand';
    }

    /**
     * Costo de los repuestos: suma de cantidad x precio de cada uno.
     */
    public function getCostoRepuestosAttribute(): int
    {
        return (int) $this->repuestos->sum(fn (OrdenServicioRepuesto $r) => $r->subtotal);
    }

    /**
     * Costo total a pagar: repuestos + mano de obra. Solo tiene sentido cobrar
     * cuando la condicion es reparacion (garantia no cobra).
     */
    public function getCostoTotalAttribute(): int
    {
        return $this->costo_repuestos + (int) ($this->mano_obra ?? 0);
    }

    /**
     * Repuestos usados en la reparacion.
     *
     * @return HasMany<OrdenServicioRepuesto>
     */
    public function repuestos(): HasMany
    {
        return $this->hasMany(OrdenServicioRepuesto::class, 'orden_servicio_id');
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

    /**
     * Llego por QR y el encargado todavia no la confirmo (no recibio la maquina
     * fisica). Estas son las que aparecen en el bloque "Por confirmar" del taller.
     */
    public function getPorConfirmarAttribute(): bool
    {
        return $this->fuente === 'qr' && $this->confirmada_at === null;
    }

    /**
     * Ordenes ingresadas por QR que aun esperan la confirmacion del encargado.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<OrdenServicio>  $query
     */
    public function scopePorConfirmar($query)
    {
        return $query->where('fuente', 'qr')->whereNull('confirmada_at');
    }
}
