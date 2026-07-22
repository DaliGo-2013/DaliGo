<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Cotización enviada al cliente desde una orden de servicio (P-M12-02, fase
 * correo). SNAPSHOT congelado de lo cotizado al momento del envío: la carta y
 * la página pública se renderizan SIEMPRE desde estas columnas, nunca desde la
 * orden (lo que el cliente aceptó es exactamente lo que se le mostró).
 *
 * El cliente responde ACEPTO / NO ACEPTO por un link firmado con el `token`
 * (sin comentario: decisión del dueño). Un re-envío crea fila nueva y marca la
 * anterior 'reemplazada'; las respondidas nunca se tocan (histórico de
 * autorizaciones). La respuesta NO cambia el estado de la orden.
 */
class OrdenServicioCotizacion extends Model implements AuditableContract
{
    use AuditableTrait;

    protected $table = 'orden_servicio_cotizaciones';

    public const ESTADOS = ['enviada', 'aceptada', 'rechazada', 'reemplazada'];

    // Variante de x-badge por estado (paleta del design system).
    public const ESTADO_VARIANTES = [
        'enviada' => 'warning',
        'aceptada' => 'brand',
        'rechazada' => 'danger',
        'reemplazada' => 'neutral',
    ];

    public const ESTADO_ETIQUETAS = [
        'enviada' => 'Enviada — esperando respuesta',
        'aceptada' => 'Aceptada por el cliente',
        'rechazada' => 'No aceptada por el cliente',
        'reemplazada' => 'Reemplazada por una más reciente',
    ];

    /**
     * Roles internos que se enteran de cada envío/respuesta de cotización
     * (decisión del dueño: técnico, jefatura de ventas y vendedores ven la ruta
     * completa de la máquina; admin siempre).
     */
    public const ROLES_AVISO = ['tecnico', 'jefe_ventas', 'vendedor', 'admin'];

    protected $fillable = [
        'orden_servicio_id',
        'token',
        'estado',
        'cliente_email',
        'trabajo_realizado',
        'causa_falla',
        'repuestos',
        'mano_obra',
        'descuento_pct',
        'descuento_motivo',
        'costo_repuestos',
        'costo_bruto',
        'descuento_monto',
        'costo_total',
        'vence_at',
        'correo_enviado_at',
        'respondida_at',
        'respuesta_ip',
        'respuesta_user_agent',
        'enviada_por',
        'pago_forma',
        'pago_comprobante_ruta',
        'pago_nota',
        'autorizada_at',
        'autorizada_por',
    ];

    // Forma de pago con que ventas coordina el cobro de la cotización aceptada.
    public const FORMAS_PAGO = [
        'sala_ventas' => 'Pagó en sala de ventas',
        'transferencia' => 'Transferencia',
        'efectivo' => 'Efectivo',
        'al_retiro' => 'Paga al retiro',
    ];

    protected function casts(): array
    {
        return [
            'repuestos' => 'array',
            'mano_obra' => 'integer',
            'descuento_pct' => 'integer',
            'costo_repuestos' => 'integer',
            'costo_bruto' => 'integer',
            'descuento_monto' => 'integer',
            'costo_total' => 'integer',
            'vence_at' => 'datetime',
            'correo_enviado_at' => 'datetime',
            'respondida_at' => 'datetime',
            'autorizada_at' => 'datetime',
        ];
    }

    /** ¿Ya se autorizó la reparación (ventas coordinó el pago)? */
    public function getEstaAutorizadaAttribute(): bool
    {
        return $this->autorizada_at !== null;
    }

    public function getPagoFormaLabelAttribute(): ?string
    {
        return $this->pago_forma ? (self::FORMAS_PAGO[$this->pago_forma] ?? $this->pago_forma) : null;
    }

    /** Binding de la ruta pública por token (el id no viaja en el link). */
    public function getRouteKeyName(): string
    {
        return 'token';
    }

    /** @return BelongsTo<OrdenServicio, $this> */
    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenServicio::class, 'orden_servicio_id');
    }

    /** @return BelongsTo<User, $this> */
    public function enviadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviada_por');
    }

    /** @return BelongsTo<User, $this> */
    public function autorizadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizada_por');
    }

    /** ¿El cliente todavía puede responder? (enviada y no vencida). */
    public function esRespondible(): bool
    {
        return $this->estado === 'enviada'
            && (! $this->vence_at || $this->vence_at->isFuture());
    }

    /** ¿Venció sin respuesta? */
    public function getVencidaAttribute(): bool
    {
        return $this->estado === 'enviada' && $this->vence_at && $this->vence_at->isPast();
    }

    public function getEstadoLabelAttribute(): string
    {
        if ($this->vencida) {
            return 'Vencida sin respuesta';
        }

        return self::ESTADO_ETIQUETAS[$this->estado] ?? ucfirst((string) $this->estado);
    }

    public function getEstadoVarianteAttribute(): string
    {
        return $this->vencida ? 'neutral' : (self::ESTADO_VARIANTES[$this->estado] ?? 'neutral');
    }

    /**
     * Aviso interno por M15 (campanita + correo según preferencias) a todos los
     * roles de ROLES_AVISO. El origen morph es la ORDEN (urlDestino aterriza en
     * su detalle). Los {placeholders} calzan con las plantillas del seeder.
     *
     * @param  array<string, mixed>  $extra  placeholders adicionales (ej. respuesta, enviada_por)
     */
    public function avisarInternos(string $evento, array $extra = []): void
    {
        $orden = $this->orden;
        $datos = array_merge([
            'folio' => $orden->folio,
            'cliente' => $orden->cliente_nombre,
            'total' => '$'.number_format((int) $this->costo_total, 0, ',', '.'),
            'url' => route('admin.servicio-tecnico.show', $orden),
            'cotizacion_id' => $this->id,
        ], $extra);

        $dispatcher = app(\App\Services\Notificaciones\NotificacionDispatcher::class);

        User::role(self::ROLES_AVISO)->get()->unique('id')
            ->each(fn (User $u) => $dispatcher->despachar($evento, $orden, $u, $datos));
    }

    /**
     * Crea una cotización NUEVA congelando el estado actual de la orden, y marca
     * como 'reemplazada' toda cotización previa aún 'enviada' (su link deja de
     * servir). Las respondidas no se tocan. Todo en una transacción.
     */
    public static function crearDesde(OrdenServicio $orden, User $user): self
    {
        return DB::transaction(function () use ($orden, $user) {
            static::query()
                ->where('orden_servicio_id', $orden->id)
                ->where('estado', 'enviada')
                ->update(['estado' => 'reemplazada']);

            $vigenciaDias = (int) Configuracion::get('cotizacion_vigencia_dias', 5);

            return static::create([
                'orden_servicio_id' => $orden->id,
                'token' => Str::random(64),
                'estado' => 'enviada',
                'cliente_email' => $orden->cliente_email,
                'trabajo_realizado' => $orden->trabajo_realizado,
                'causa_falla' => $orden->causa_falla,
                'repuestos' => $orden->repuestos->map(fn (OrdenServicioRepuesto $r) => [
                    'nombre' => $r->nombre,
                    'cantidad' => $r->cantidad,
                    'precio_unitario' => $r->precio_unitario,
                    'subtotal' => $r->subtotal,
                ])->values()->all(),
                'mano_obra' => (int) $orden->mano_obra,
                'descuento_pct' => (int) $orden->descuento_pct,
                'descuento_motivo' => $orden->descuento_motivo,
                'costo_repuestos' => (int) $orden->costo_repuestos,
                'costo_bruto' => (int) $orden->costo_bruto,
                'descuento_monto' => (int) $orden->descuento_monto,
                'costo_total' => (int) $orden->costo_total,
                'vence_at' => $vigenciaDias > 0 ? now()->addDays($vigenciaDias)->endOfDay() : null,
                'enviada_por' => $user->id,
            ]);
        });
    }
}
