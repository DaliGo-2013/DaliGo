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
 * El cliente responde ACEPTO / NO ACEPTO por un link firmado con el `token`,
 * con un «¿por qué?» opcional (`respuesta_motivo`). OJO: el 30-07 el dueño había
 * pedido SIN comentario; el 06-08 dio vuelta esa decisión — quiere leer el
 * motivo del cliente en la campanita. Sigue siendo de UNA pasada (no abre
 * conversación). Un re-envío crea fila nueva y marca la anterior 'reemplazada';
 * las respondidas nunca se tocan (histórico de autorizaciones). La respuesta NO
 * cambia el estado de la orden.
 *
 * Si la respuesta fue NO ACEPTO, el equipo puede avisarle al cliente por correo
 * que pase a retirar su equipo sin reparar (`retiro_avisado_at/_por`, dueño
 * 06-08): un solo aviso por cotización, con registro de quién lo mandó.
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

    /**
     * Avisos de PLATA (el pago de una cotización): sin el técnico. Desde el
     * 07-08 el taller no coordina cobros —repara con la sola aceptación del
     * cliente y avisa cuando el equipo está listo—, así que el aviso de
     * «reparación autorizada / pago registrado» era ruido en su campanita.
     */
    public const ROLES_AVISO_PAGO = ['jefe_ventas', 'vendedor', 'admin'];

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
        'respuesta_motivo',
        'retiro_avisado_at',
        'retiro_avisado_por',
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
            'retiro_avisado_at' => 'datetime',
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

    /**
     * Desglose de IVA del total cotizado. El total ya viene CON IVA, así que el
     * neto se obtiene dividiendo por (1 + IVA) y el IVA es la diferencia (neto +
     * IVA == total exacto).
     */
    public function getCostoNetoAttribute(): int
    {
        return (int) round((int) $this->costo_total / (1 + OrdenServicio::TASA_IVA));
    }

    public function getCostoIvaAttribute(): int
    {
        return (int) $this->costo_total - $this->costo_neto;
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

    /** @return BelongsTo<User, $this> */
    public function retiroAvisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retiro_avisado_por');
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
     * @param  list<string>|null  $roles  a quién avisar (por defecto ROLES_AVISO;
     *   los avisos de plata usan ROLES_AVISO_PAGO, que excluye al técnico)
     * @param  bool  $porCartera  si true, cada destinatario pasa por `esVisiblePara`
     *   —el MISMO filtro de la ficha, así el aviso no llega a quien no puede
     *   abrirlo—: el vendedor del cliente si lo tiene, sala de ventas si no. El
     *   técnico y jefatura lo pasan solos porque tienen 'ver todo servicio tecnico';
     *   para el taller un ACEPTO es su luz verde para reparar, y
     *   `AvisoCarteraSalaDeVentasTest` lo fija por si ese permiso cambia.
     */
    public function avisarInternos(string $evento, array $extra = [], ?array $roles = null, bool $porCartera = false): void
    {
        $orden = $this->orden;
        // Qué máquina es (tipo + modelo si lo hay): identifica el equipo cotizado.
        $equipo = trim($orden->tipo_equipo_label.' '.($orden->modelo ?? ''));
        $datos = array_merge([
            'folio' => $orden->folio,
            'cliente' => $orden->cliente_nombre,
            'equipo' => $equipo !== '' ? $equipo : '—',
            'total' => '$'.number_format((int) $this->costo_total, 0, ',', '.'),
            'url' => route('admin.servicio-tecnico.show', $orden),
            'cotizacion_id' => $this->id,
        ], $extra);

        $dispatcher = app(\App\Services\Notificaciones\NotificacionDispatcher::class);

        User::role($roles ?? self::ROLES_AVISO)->get()->unique('id')
            ->filter(fn (User $u) => ! $porCartera || $orden->esVisiblePara($u))
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

            return static::create(static::datosDesde($orden, $user) + [
                'token' => Str::random(64),
                'estado' => 'enviada',
            ]);
        });
    }

    /**
     * EL DIAGNOSTICO COMO LO LEE EL CLIENTE. El snapshot guarda la clave del enum
     * (`uso_normal`) y la carta imprimia esa clave tal cual: el cliente recibia «Diagnostico
     * del tecnico: uso_normal». Se descubrio mirando la VISTA PREVIA el 20-08-2026 — que es
     * exactamente para lo que existe.
     *
     * Un valor fuera del enum se imprime TAL CUAL, no como «Sin determinar»: las cotizaciones
     * viejas guardaron texto libre («Filtracion interna») y eso si es informacion para el
     * cliente. Por eso no reusa el accessor de la orden, que normaliza a «Sin determinar».
     */
    public function getCausaFallaLabelAttribute(): string
    {
        return OrdenServicio::CAUSA_FALLA_ETIQUETAS[$this->causa_falla] ?? (string) $this->causa_falla;
    }
    /**
     * EL SNAPSHOT, SIN GUARDARLO. Lo comparten el envio real y la VISTA PREVIA de la carta
     * (dueño 20-08-2026: «una ventana previa donde se vea la cotizacion y despues se pueda
     * enviar»), y por eso vive en un solo metodo: una vista previa que calcula los numeros por
     * su cuenta es peor que no tenerla — mostraria un total y el cliente recibiria otro.
     *
     * Todo sale de la ORDEN (sus accessors de plata y su relacion de repuestos), asi que
     * tambien sirve sobre una orden hidratada en memoria.
     *
     * @return array<string, mixed>
     */
    public static function datosDesde(OrdenServicio $orden, ?User $user = null): array
    {
        $vigenciaDias = (int) Configuracion::get('cotizacion_vigencia_dias', 5);

        return [
            'orden_servicio_id' => $orden->id,
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
            'enviada_por' => $user?->id,
        ];
    }

    /**
     * Un BORRADOR en memoria para previsualizar la carta: los mismos numeros que va a recibir
     * el cliente, sin fila en la base y sin reemplazar la cotizacion vigente.
     *
     * Se le pone `created_at` porque la carta imprime la fecha, y se le enchufa la orden como
     * relacion cargada para que la vista no intente ir a buscarla (no tiene id).
     */
    public static function borradorDesde(OrdenServicio $orden, ?User $user = null): self
    {
        $borrador = new static(static::datosDesde($orden, $user));
        $borrador->estado = 'enviada';
        $borrador->created_at = now();
        $borrador->setRelation('orden', $orden);

        return $borrador;
    }
}
