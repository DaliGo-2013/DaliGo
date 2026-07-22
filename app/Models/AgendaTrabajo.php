<?php

namespace App\Models;

use Database\Factories\AgendaTrabajoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Trabajo agendado del técnico industrial (servicio en terreno): lo agenda el
 * jefe o un vendedor con el cliente, la fecha, el servicio del catálogo (o un
 * detalle libre) y el técnico asignado; el técnico lo marca realizado.
 */
class AgendaTrabajo extends Model implements AuditableContract
{
    /** @use HasFactory<AgendaTrabajoFactory> */
    use AuditableTrait, HasFactory;

    protected $table = 'agenda_trabajos';

    // 'visita_tecnica' PRIMERO a propósito (pedido del dueño): el técnico va
    // donde el cliente, diagnostica y cotiza; después vienen los trabajos.
    public const TIPOS = ['visita_tecnica', 'mantencion', 'reparacion', 'instalacion'];

    public const TIPO_ETIQUETAS = [
        'visita_tecnica' => 'Visita técnica',
        'mantencion' => 'Mantención',
        'reparacion' => 'Reparación',
        'instalacion' => 'Instalación',
    ];

    // 'solicitado' = lo pidió el CLIENTE por el QR y espera coordinación
    // (sin fecha); al coordinar pasa a 'agendado' con fecha y técnico.
    public const ESTADOS = ['solicitado', 'agendado', 'realizado', 'cancelado'];

    // Variante de x-badge por estado. OJO: x-badge solo define brand|neutral|
    // danger (paleta del design system); espeja al taller: cerrado-bien =
    // neutral (como 'entregado'), cerrado-mal = danger (como 'sin_solucion').
    public const ESTADO_VARIANTES = [
        'solicitado' => 'brand',
        'agendado' => 'brand',
        'realizado' => 'neutral',
        'cancelado' => 'danger',
    ];

    protected $fillable = [
        'tipo',
        'fecha',
        'fecha_fin',
        'hora',
        'hora_fin',
        'fecha_preferida',
        'estado',
        'confirmacion_token',
        'confirmacion_enviada_at',
        'cliente_confirmacion',
        'cliente_confirmacion_at',
        'cliente_confirmacion_nota',
        'servicio_terreno_id',
        'cliente_id',
        'cliente_nombre',
        'cliente_rut',
        'cliente_telefono',
        'cliente_email',
        'direccion',
        'ciudad',
        'tecnico_id',
        'descripcion',
        'notas_tecnico',
        'motivo_cancelacion',
        'creado_por',
    ];

    /**
     * Motivos para rechazar/cancelar una solicitud (los elige quien coordina).
     * 'otro' habilita un detalle libre. El texto resuelto se guarda en
     * `motivo_cancelacion` y se muestra al cliente en el correo de rechazo.
     */
    public const MOTIVOS_CANCELACION = [
        'tecnico_vacaciones' => 'Técnico de vacaciones',
        'tecnico_viaje' => 'Técnico de viaje / fuera de zona',
        'atraso_pagos' => 'Atraso en pagos',
        'equipo_otra_marca' => 'El equipo no es Dali (no trabajamos otras marcas)',
        'sin_disponibilidad' => 'Sin disponibilidad para la fecha',
        'otro' => 'Otro motivo',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_fin' => 'date',
            'fecha_preferida' => 'date',
            'confirmacion_enviada_at' => 'datetime',
            'cliente_confirmacion_at' => 'datetime',
        ];
    }

    /**
     * Hora en formato corto "HH:MM" para la vista calendario (la columna `time`
     * viene como "HH:MM:SS"). Null si el trabajo aún no tiene hora asignada.
     */
    public function getHoraCortaAttribute(): ?string
    {
        return $this->hora ? substr((string) $this->hora, 0, 5) : null;
    }

    /**
     * Franja horaria de 2 horas a la que cae el trabajo en la agenda del técnico
     * (08:00, 10:00, 12:00, 14:00, 16:00, 18:00 …): la hora redondeada hacia abajo
     * al bloque par. Deja holgura para viajar entre trabajos. Null si no tiene hora.
     */
    public function getFranjaAttribute(): ?string
    {
        if (! $this->hora_corta) {
            return null;
        }

        $h = (int) substr($this->hora_corta, 0, 2);

        return sprintf('%02d:00', $h - ($h % 2));
    }

    /** Hora de término "HH:MM" (columna `time` viene "HH:MM:SS"). Null si no hay. */
    public function getHoraFinCortaAttribute(): ?string
    {
        return $this->hora_fin ? substr((string) $this->hora_fin, 0, 5) : null;
    }

    /** ¿El trabajo abarca más de un día (viaje)? */
    public function getAbarcaVariosDiasAttribute(): bool
    {
        return $this->fecha && $this->fecha_fin && ! $this->fecha_fin->isSameDay($this->fecha);
    }

    /** Etiqueta del rango de fechas: "7 al 10 de septiembre" o el día suelto. */
    public function getRangoFechasLabelAttribute(): ?string
    {
        if (! $this->fecha) {
            return null;
        }
        if (! $this->abarca_varios_dias) {
            return $this->fecha->translatedFormat('d \d\e F');
        }

        $mismoMes = $this->fecha->month === $this->fecha_fin->month;

        return $mismoMes
            ? $this->fecha->translatedFormat('d').' al '.$this->fecha_fin->translatedFormat('d \d\e F')
            : $this->fecha->translatedFormat('d \d\e F').' al '.$this->fecha_fin->translatedFormat('d \d\e F');
    }

    /** Etiqueta del rango de horas: "08:00 a 18:00", "08:00", o null si sin hora. */
    public function getRangoHorasLabelAttribute(): ?string
    {
        if (! $this->hora_corta) {
            return null;
        }

        return $this->hora_fin_corta && $this->hora_fin_corta !== $this->hora_corta
            ? $this->hora_corta.' a '.$this->hora_fin_corta
            : $this->hora_corta;
    }

    /**
     * Trabajos YA comprometidos (agendado/realizado, con fecha) que se solapan con
     * el rango [$desde, $hasta] — para bloquear que se agende encima cuando el
     * técnico está ocupado/de viaje. El solape considera fecha_fin (o la fecha si
     * es de un día). Portable MySQL 5.7 / SQLite (sin funciones de fecha crudas).
     *
     * @return \Illuminate\Support\Collection<int, AgendaTrabajo>
     */
    public static function conflictos(string $desde, string $hasta, ?int $exceptId = null): \Illuminate\Support\Collection
    {
        return static::query()
            ->whereIn('estado', ['agendado', 'realizado'])
            ->whereNotNull('fecha')
            ->when($exceptId, fn (Builder $q) => $q->where('id', '!=', $exceptId))
            ->whereDate('fecha', '<=', $hasta)
            ->where(function (Builder $q) use ($desde) {
                $q->where(fn (Builder $w) => $w->whereNotNull('fecha_fin')->whereDate('fecha_fin', '>=', $desde))
                    ->orWhere(fn (Builder $w) => $w->whereNull('fecha_fin')->whereDate('fecha', '>=', $desde));
            })
            ->orderBy('fecha')
            ->get();
    }

    /**
     * Solicitudes del cliente (QR) que esperan coordinación: sin fecha real
     * todavía. Aparecen en el bloque "Por coordinar" de la agenda.
     *
     * @param  Builder<AgendaTrabajo>  $query
     */
    public function scopePorCoordinar($query)
    {
        return $query->where('estado', 'solicitado')->orderBy('id');
    }

    /**
     * Roles que reciben aviso cuando entra una solicitud "por coordinar": son
     * quienes conversan con el cliente y coordinan la visita antes de fijarla en
     * la agenda de Carlos (jefe de ventas + vendedores; admin para monitoreo).
     */
    public const ROLES_AVISO_COORDINAR = ['jefe_ventas', 'vendedor', 'admin'];

    /**
     * Avisa por M15 (campanita + correo según preferencias) a ventas que hay una
     * solicitud del cliente por coordinar. Se llama al crearla desde el QR. No
     * debe tumbar el flujo público: el emisor la envuelve en try/catch.
     */
    public function notificarPorCoordinar(): void
    {
        $datos = [
            'cliente' => $this->cliente_nombre,
            'tipo' => $this->tipo_label,
            'ciudad' => $this->ciudad ?: 'sin ciudad',
            'telefono' => $this->cliente_telefono ?: 's/i',
            'preferida' => $this->fecha_preferida?->format('d-m-Y') ?: 'sin fecha preferida',
            'url' => route('admin.agenda-terreno.index'),
        ];

        $dispatcher = app(\App\Services\Notificaciones\NotificacionDispatcher::class);

        User::role(self::ROLES_AVISO_COORDINAR)->get()->unique('id')
            ->each(fn (User $u) => $dispatcher->despachar('terreno.solicitada', $this, $u, $datos));
    }

    // --- Confirmación del cliente a la cita agendada -------------------------

    public const CONFIRMACION_ETIQUETAS = [
        'confirmada' => 'El cliente confirmó que asistirá',
        'no_puede' => 'El cliente avisó que NO puede ese día',
    ];

    /**
     * Prepara una confirmación para enviar al cliente: asegura el token, resetea
     * cualquier respuesta previa (la cita cambió) y estampa el envío. El emisor
     * (controller) manda el correo justo después. Guarda al vuelo.
     */
    public function prepararConfirmacionCliente(): void
    {
        $this->forceFill([
            'confirmacion_token' => $this->confirmacion_token ?: \Illuminate\Support\Str::random(64),
            'confirmacion_enviada_at' => now(),
            'cliente_confirmacion' => null,
            'cliente_confirmacion_at' => null,
            'cliente_confirmacion_nota' => null,
        ])->save();
    }

    /**
     * ¿El cliente todavía puede confirmar? Cita agendada, con token, con fecha
     * futura (no tiene sentido confirmar una visita ya pasada) y sin responder
     * aún (la primera respuesta manda; reprogramar la reabre reseteándola).
     */
    public function esConfirmable(): bool
    {
        return $this->estado === 'agendado'
            && filled($this->confirmacion_token)
            && $this->fecha
            && $this->fecha->gte(\App\Support\FechaNegocio::ahora()->startOfDay())
            && blank($this->cliente_confirmacion);
    }

    public function getClienteConfirmacionLabelAttribute(): ?string
    {
        return $this->cliente_confirmacion
            ? (self::CONFIRMACION_ETIQUETAS[$this->cliente_confirmacion] ?? $this->cliente_confirmacion)
            : null;
    }

    /**
     * Avisa a ventas (jefe + vendedores) la respuesta del cliente a la cita.
     * Se despacha después de registrar la respuesta (si el aviso falla, la
     * respuesta ya quedó).
     */
    public function avisarConfirmacionInterna(): void
    {
        $datos = [
            'cliente' => $this->cliente_nombre,
            'tipo' => $this->tipo_label,
            'fecha' => $this->fecha?->format('d-m-Y').($this->hora_corta ? ' '.$this->hora_corta : ''),
            'respuesta' => $this->cliente_confirmacion === 'confirmada' ? 'CONFIRMÓ que asistirá' : 'NO puede ese día',
            'nota' => $this->cliente_confirmacion_nota ?: 'sin comentario',
            'url' => route('admin.agenda-terreno.index', $this->fecha ? ['anio' => $this->fecha->year, 'mes' => $this->fecha->month, 'dia' => $this->fecha->toDateString()] : []),
        ];

        $dispatcher = app(\App\Services\Notificaciones\NotificacionDispatcher::class);

        User::role(self::ROLES_AVISO_COORDINAR)->get()->unique('id')
            ->each(fn (User $u) => $dispatcher->despachar('terreno.confirmada', $this, $u, $datos));
    }

    /**
     * Avisa a ventas (jefe + vendedores) que una solicitud fue RECHAZADA y por
     * qué (misma tribu que el resto del flujo de terreno). Se despacha después de
     * registrar el rechazo; el emisor lo envuelve en try/catch (secundario).
     */
    public function avisarRechazoInterno(): void
    {
        $datos = [
            'cliente' => $this->cliente_nombre,
            'tipo' => $this->tipo_label,
            'motivo' => $this->motivo_cancelacion ?: 'sin especificar',
            'url' => route('admin.agenda-terreno.index'),
        ];

        $dispatcher = app(\App\Services\Notificaciones\NotificacionDispatcher::class);

        User::role(self::ROLES_AVISO_COORDINAR)->get()->unique('id')
            ->each(fn (User $u) => $dispatcher->despachar('terreno.rechazada', $this, $u, $datos));
    }

    public function getTipoLabelAttribute(): string
    {
        return self::TIPO_ETIQUETAS[$this->tipo] ?? ucfirst((string) $this->tipo);
    }

    public function getEstadoVarianteAttribute(): string
    {
        return self::ESTADO_VARIANTES[$this->estado] ?? 'brand';
    }

    /**
     * Trabajos de un mes calendario, en orden de agenda (fecha ascendente).
     * whereDate en ambos bordes: portable (MySQL 5.7 / SQLite) y usa el índice.
     *
     * @param  Builder<AgendaTrabajo>  $query
     */
    public function scopeDelMes($query, int $anio, int $mes)
    {
        $desde = Carbon::create($anio, $mes, 1);

        return $query
            ->whereDate('fecha', '>=', $desde->toDateString())
            ->whereDate('fecha', '<=', $desde->copy()->endOfMonth()->toDateString())
            ->orderBy('fecha')->orderBy('id');
    }

    /** @return BelongsTo<ServicioTerreno, $this> */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(ServicioTerreno::class, 'servicio_terreno_id');
    }

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /** @return BelongsTo<User, $this> */
    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    /**
     * Repuestos usados en el trabajo (los registra el técnico al cerrar).
     *
     * @return HasMany<AgendaTrabajoRepuesto, $this>
     */
    public function repuestos(): HasMany
    {
        return $this->hasMany(AgendaTrabajoRepuesto::class);
    }
}
