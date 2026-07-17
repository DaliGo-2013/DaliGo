<?php

namespace App\Models;

use Database\Factories\InstalacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Registro de instalaciones en terreno del técnico industrial (Carlos Tablante):
 * plasma su Excel. Cada instalación lleva cliente, categoría/producto, si se
 * instaló y se puso en marcha, días trabajados, vendedor y datos de factura/pago.
 */
class Instalacion extends Model implements AuditableContract
{
    /** @use HasFactory<InstalacionFactory> */
    use AuditableTrait, HasFactory;

    protected $table = 'instalaciones';

    // Categoría del equipo instalado (columna CATEGORIA del Excel).
    public const CATEGORIAS = ['lavadora', 'llenadora', 'planta'];

    public const CATEGORIA_ETIQUETAS = [
        'lavadora' => 'Lavadora',
        'llenadora' => 'Llenadora',
        'planta' => 'Planta',
    ];

    // Forma de pago de la factura (columna FORMA DE PAGO del Excel).
    public const FORMAS_PAGO = ['transferencia', 'efectivo', 'deposito', 'cheque', 'webpay', 'debito', 'credito'];

    public const FORMA_PAGO_ETIQUETAS = [
        'transferencia' => 'Transferencia',
        'efectivo' => 'Efectivo',
        'deposito' => 'Depósito bancario',
        'cheque' => 'Cheque al día',
        'webpay' => 'Webpay',
        'debito' => 'Débito',
        'credito' => 'Crédito',
    ];

    // Vendedores del Excel: sugerencias para el datalist (texto libre editable).
    public const VENDEDORES_SUGERIDOS = [
        'Abigail Tovar', 'Carolina Medina', 'Carlos Toledo', 'Luis Figueroa',
        'Danika Toledo', 'Sergio Céspedes', 'Héctor Martínez', 'Cricelis Herrera',
        'Pedro Castillo',
    ];

    protected $fillable = [
        'fecha',
        'cliente_id',
        'cliente_nombre',
        'cliente_rut',
        'comuna_region',
        'categoria',
        'producto',
        'instalacion',
        'puesta_en_marcha',
        'dias',
        'vendedor',
        'n_factura',
        'fecha_factura',
        'forma_pago',
        'fecha_pago',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_factura' => 'date',
            'fecha_pago' => 'date',
            'instalacion' => 'boolean',
            'puesta_en_marcha' => 'boolean',
            'dias' => 'integer',
        ];
    }

    public function getCategoriaLabelAttribute(): string
    {
        return self::CATEGORIA_ETIQUETAS[$this->categoria] ?? ucfirst((string) $this->categoria);
    }

    public function getFormaPagoLabelAttribute(): ?string
    {
        if (blank($this->forma_pago)) {
            return null;
        }

        return self::FORMA_PAGO_ETIQUETAS[$this->forma_pago] ?? ucfirst((string) $this->forma_pago);
    }

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
