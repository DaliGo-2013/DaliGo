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

    /**
     * Roles que reciben el aviso de una instalación registrada.
     *
     * Solo JEFATURA: el jefe de ventas maneja la agenda de terreno del técnico
     * industrial y pidió enterarse de todo lo del área (28-08-2026). Los
     * vendedores NO van — el `vendedor` de la planilla es texto libre copiado del
     * Excel, no un usuario, así que no hay a quién dirigir el aviso; y mandárselo
     * a todos convertiría la campanita en un tablón. El técnico industrial
     * tampoco: es quien registra, y avisarle de su propia acción es ruido (mismo
     * criterio que el resto del módulo).
     */
    public const ROLES_AVISO = ['jefe_ventas', 'admin'];

    /**
     * Avisa por M15 (campanita + correo según preferencias) que se registró una
     * instalación. Secundario: el emisor lo envuelve en try/catch — un aviso que
     * falle no debe tumbar un registro que ya quedó guardado.
     *
     * @param  User|null  $actor  quien la registró (no se autonotifica)
     */
    public function notificarRegistro(?User $actor = null): void
    {
        $hitos = collect([
            $this->instalacion ? 'instalada' : null,
            $this->puesta_en_marcha ? 'puesta en marcha' : null,
        ])->filter()->implode(' y ');

        $datos = [
            'cliente' => $this->cliente_nombre,
            'equipo' => collect([$this->categoria_label, $this->producto])->filter()->implode(' · '),
            'lugar' => filled($this->comuna_region) ? $this->comuna_region : '—',
            'fecha' => $this->fecha?->format('d-m-Y') ?: '—',
            // Se rellenan SIEMPRE: un placeholder sin dato queda CRUDO en el texto.
            'hitos' => $hitos !== '' ? ucfirst($hitos) : 'Sin hitos marcados',
            'dias' => (string) ($this->dias ?? 0),
            'vendedor' => filled($this->vendedor) ? $this->vendedor : 'sin vendedor anotado',
            'registrada_por' => $actor?->name ?: ($this->creado_por ?: 'El técnico industrial'),
            'url' => route('admin.instalaciones.edit', $this),
        ];

        $dispatcher = app(\App\Services\Notificaciones\NotificacionDispatcher::class);

        User::role(self::ROLES_AVISO)->get()->unique('id')
            ->reject(fn (User $u) => $actor && $u->id === $actor->id)
            ->each(fn (User $u) => $dispatcher->despachar('instalacion.registrada', $this, $u, $datos));
    }
}
