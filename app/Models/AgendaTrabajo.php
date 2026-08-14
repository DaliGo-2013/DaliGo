<?php

namespace App\Models;

use App\Mail\AgendaTrabajoAviso;
use Database\Factories\AgendaTrabajoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
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

    /**
     * El ÚNICO tipo que puede pedir un cliente por el QR (pedido del técnico
     * industrial, 13-08-2026). El cliente no sabe —ni tiene por qué— si lo suyo
     * es una mantención, una reparación o una instalación: eso lo determina el
     * técnico en la planta. Los otros tres tipos existen solo para el flujo
     * INTERNO, donde el vendedor o el jefe de ventas agenda el trabajo después
     * de la visita, hablando con el cliente.
     */
    public const TIPO_PUBLICO = 'visita_tecnica';

    /**
     * Los tipos de cita que el JEFE DE VENTAS tiene que autorizar cuando las fija un vendedor
     * (dueño, 13-08-2026: «cuando un vendedor fije una cita con un cliente por mantención,
     * reparación o instalación… que él siempre esté al tanto de lo que hacen sus vendedores»).
     *
     * La VISITA TÉCNICA queda afuera a propósito: es la que pide el cliente por el QR y el
     * vendedor solo la coordina — no es un compromiso que el vendedor decida por su cuenta.
     */
    public const TIPOS_QUE_AUTORIZA_JEFATURA = ['mantencion', 'reparacion', 'instalacion'];

    /**
     * La solicitud de autorización que esta cita tiene ESPERANDO, si tiene alguna.
     *
     * Existe para que la agenda pueda decir «esperando autorización» en vez de mostrar la cita
     * como una solicitud más del cliente: la fila vive en el bloque «Por coordinar» —está
     * 'solicitado' y sin fecha— pero no la pidió ningún cliente, la fijó un vendedor.
     */
    public function aprobacionPendiente(): \Illuminate\Database\Eloquent\Relations\MorphOne
    {
        return $this->morphOne(Aprobacion::class, 'aprobable')
            ->where('tipo_accion', Aprobacion::ACCION_AGENDA_CITA)
            ->where('estado', Aprobacion::ESTADO_PENDIENTE);
    }

    /** ¿Esta cita está esperando el visto bueno del jefe de ventas? */
    public function esperandoAutorizacion(): bool
    {
        return $this->aprobacionPendiente()->exists();
    }

    public const TIPO_ETIQUETAS = [
        'visita_tecnica' => 'Visita técnica',
        'mantencion' => 'Mantención',
        'reparacion' => 'Reparación',
        'instalacion' => 'Instalación',
    ];

    // 'solicitado' = lo pidió el CLIENTE por el QR y espera coordinación
    // (sin fecha); al coordinar pasa a 'agendado' con fecha y técnico.
    //
    // 'no_realizado' (dueño 14-08-2026) = el técnico FUE y no se pudo hacer:
    // faltaba un repuesto, el cliente no quiso, lo que sea. Es un estado FINAL y
    // distinto de 'cancelado': cancelado es la coordinación diciendo «no va»
    // ANTES (y le avisa al cliente por correo); no_realizado es el terreno
    // contando qué pasó cuando ya se fue. Si hay que volver, ventas agenda un
    // trabajo nuevo — así la agenda no arrastra un pendiente eterno.
    public const ESTADOS = ['solicitado', 'agendado', 'realizado', 'no_realizado', 'cancelado'];

    /** Los dos cierres que hace el técnico en terreno, y que avisan a ventas. */
    public const ESTADOS_CIERRE = ['realizado', 'no_realizado'];

    /**
     * Quién recibe el aviso de cierre además del vendedor del cliente. El jefe de
     * ventas y admin van SIEMPRE: si dependiera solo del vendedor, hoy el aviso
     * no le llegaría a nadie (las carteras están sin asignar).
     */
    public const ROLES_AVISO_CIERRE = ['jefe_ventas', 'admin'];

    // Variante de x-badge por estado. OJO: x-badge solo define brand|neutral|
    // danger (paleta del design system); espeja al taller: cerrado-bien =
    // neutral (como 'entregado'), cerrado-mal = danger (como 'sin_solucion').
    public const ESTADO_VARIANTES = [
        'solicitado' => 'brand',
        'agendado' => 'brand',
        'realizado' => 'neutral',
        // Cerrado-mal = danger, igual que 'cancelado' y que el 'sin_solucion' del
        // taller: no es un error del sistema, es un resultado que hay que ver.
        'no_realizado' => 'danger',
        'cancelado' => 'danger',
    ];

    /** Rótulo del estado para pantallas y Excel ('no_realizado' se lee mal crudo). */
    public const ESTADO_ETIQUETAS = [
        'solicitado' => 'Solicitado',
        'agendado' => 'Agendado',
        'realizado' => 'Realizado',
        'no_realizado' => 'No realizado',
        'cancelado' => 'Cancelado',
    ];

    protected $fillable = [
        'tipo',
        'fecha',
        'fecha_fin',
        'hora',
        'hora_fin',
        'fecha_preferida',
        'hora_preferida',
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
        'disponibilidad',
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
     * ¿La agenda está ocupada ese día, hasta cuándo, y cuál es el próximo día libre?
     *
     * Pedido del dueño (13-08-2026): «cuando el cliente ingrese una fecha que diga si está
     * disponible u ocupada, un cartel de advertencia que no se puede ese día o varios días».
     *
     * NO ES UNA REGLA NUEVA. Es `conflictos()` —la misma que impide agendar encima de un
     * técnico ocupado y la misma que YA rechaza la fecha preferida al enviar el formulario
     * público— mirada de otra forma. Un segundo criterio de «ocupado» sería una pantalla que
     * dice «hay disponibilidad» y un servidor que después contesta que no; el reclamo que
     * esto viene a resolver es justamente haberse enterado tarde.
     *
     * UNA SOLA CONSULTA para toda la ventana. Preguntar día por día son 90 consultas por cada
     * fecha que el cliente tantea, y esto vive en una pantalla pública sin login.
     *
     * DOS TRABAJOS PEGADOS SON UN SOLO TRAMO (7 al 10 y 11 al 12 → «del 7 al 12»): al cliente
     * no le sirve saber dónde termina un trabajo, le sirve saber cuándo puede venir el técnico.
     *
     * @param  int  $diasVentana  hasta dónde se busca el próximo libre. Si no aparece ninguno
     *                            dentro de la ventana devuelve null: «no sé» y «el 15» son
     *                            respuestas distintas y no se inventa una fecha.
     * @return array{estado: string, ocupado: bool, desde: ?string, hasta: ?string, dias: int, proximo_libre: ?string, hora_hasta: ?string, motivo_cierre: ?string}
     */
    public static function disponibilidad(string $fecha, int $diasVentana = 90): array
    {
        $pedida = Carbon::parse($fecha)->toDateString();
        $finVentana = Carbon::parse($pedida)->addDays(max(1, $diasVentana))->toDateString();

        $ocupados = [];
        $arranque = null;

        foreach (static::conflictos($pedida, $finVentana) as $t) {
            $desde = $t->fecha->toDateString();
            $hasta = ($t->fecha_fin ?? $t->fecha)->toDateString();

            for ($d = Carbon::parse($desde); $d->toDateString() <= $hasta; $d->addDay()) {
                $ocupados[$d->toDateString()] = true;
            }

            // El tramo puede EMPEZAR antes del día pedido: un viaje del 7 al 10 con el cliente
            // eligiendo el 9 tiene que decir «del 7 al 10», no «del 9 al 10».
            if ($desde <= $pedida && $hasta >= $pedida && ($arranque === null || $desde < $arranque)) {
                $arranque = $desde;
            }
        }

        // Feriados, vacaciones y medias jornadas, en UNA consulta para toda la ventana.
        $cierres = AgendaCierre::mapaDeDias($pedida, $finVentana);
        $estaCerrado = fn (string $d) => isset($cierres[$d]) && $cierres[$d]->tipo === AgendaCierre::TIPO_CERRADO;

        // Un día se puede pedir si el técnico trabaja ese día, no está cerrado y no tiene
        // nada encima. La media jornada NO lo saca de la lista: ese día sí se puede, hasta
        // cierta hora — y eso se dice, no se esconde.
        $sePuede = fn (string $d) => static::esLaborable($d) && ! $estaCerrado($d) && ! isset($ocupados[$d]);

        // EL PRÓXIMO LIBRE se busca con la MISMA condición, no solo esquivando trabajos: si
        // no, un viernes ocupado ofrecería el sábado y el cliente elegiría un día en que no
        // hay nadie — y el servidor se lo rechazaría igual al enviar.
        $siguiente = null;
        for ($d = Carbon::parse($pedida)->addDay(); $d->toDateString() <= $finVentana; $d->addDay()) {
            if ($sePuede($d->toDateString())) {
                $siguiente = $d->toDateString();
                break;
            }
        }

        if ($sePuede($pedida)) {
            // MEDIA JORNADA: el día está disponible, pero hasta cierta hora. Se avisa en vez
            // de callarlo — que el cliente se entere el mismo día de que el técnico se iba a
            // las 14 es exactamente el problema que este cartel vino a resolver.
            $media = isset($cierres[$pedida]) ? $cierres[$pedida] : null;

            return ['estado' => $media ? 'parcial' : 'libre', 'ocupado' => false,
                'desde' => null, 'hasta' => null, 'dias' => 0, 'proximo_libre' => null,
                'hora_hasta' => $media?->hora_corta, 'motivo_cierre' => null];
        }

        // DÍA QUE NO SE PUEDE PEDIR Y NO ES POR UN TRABAJO: o el técnico no atiende ese día
        // de la semana, o hay un cierre cargado (feriado, vacaciones…). Se distingue solo
        // para elegir el TEXTO; hacia afuera ninguno de los dos dice el motivo de fondo.
        if (! isset($ocupados[$pedida])) {
            return ['estado' => 'cerrado', 'ocupado' => true, 'desde' => null, 'hasta' => null,
                'dias' => 0, 'proximo_libre' => $siguiente, 'hora_hasta' => null,
                'motivo_cierre' => $estaCerrado($pedida) ? 'cierre' : 'no_laborable'];
        }

        $desde = $arranque ?? $pedida;
        $hasta = $pedida;
        while (isset($ocupados[Carbon::parse($hasta)->addDay()->toDateString()])) {
            $hasta = Carbon::parse($hasta)->addDay()->toDateString();
        }

        return [
            'estado' => 'ocupado',
            'ocupado' => true,
            'desde' => $desde,
            'hasta' => $hasta,
            'dias' => (int) Carbon::parse($desde)->diffInDays($hasta) + 1,
            'proximo_libre' => $siguiente,
            'hora_hasta' => null,
            'motivo_cierre' => null,
        ];
    }

    /**
     * Días en que el técnico industrial atiende: LUNES A VIERNES (dueño, 13-08-2026: «trabaja
     * solo de lunes a viernes el técnico y los feriados no trabaja»).
     *
     * Van como constante y no como configuración porque es un dato del negocio, no una
     * preferencia que alguien cambie desde una pantalla. Lo que SÍ se carga —feriados,
     * vacaciones, medias jornadas— vive en `AgendaCierre`, y `disponibilidad()` es el único
     * lugar donde se juntan: el resto del sistema pregunta «¿se puede ese día?» y no tiene que
     * saber por qué no.
     *
     * LO QUE EL CLIENTE NO TIENE POR QUÉ SABER (decisión del dueño): el motivo. «No es tan
     * importante que la gente sepa que está de vacaciones, simplemente no está disponible».
     * Así que hacia afuera esto contesta *que* no se puede, nunca *por qué*.
     *
     * EL HORARIO, día por día (dueño, 14-08-2026): «lunes y martes 08:00 a 17:30 y miércoles a
     * viernes 08:00 a 16:30». Un día laborable es, exactamente, un día que TIENE horario: así no
     * hay dos listas que puedan contradecirse (una de días y otra de horas), que es como una
     * termina diciendo que el sábado se atiende de 8 a 5.
     */
    public const HORARIO = [
        1 => ['08:00', '17:30'],   // lunes
        2 => ['08:00', '17:30'],   // martes
        3 => ['08:00', '16:30'],   // miércoles
        4 => ['08:00', '16:30'],   // jueves
        5 => ['08:00', '16:30'],   // viernes
    ];

    /** Cada cuántos minutos se ofrece una hora para elegir. */
    public const PASO_MINUTOS = 30;

    /** ¿El técnico atiende ese día de la SEMANA? (los feriados y cierres van aparte) */
    public static function esLaborable(string $fecha): bool
    {
        return isset(self::HORARIO[Carbon::parse($fecha)->isoWeekday()]);
    }

    /** El horario de ese día: ['08:00', '17:30'], o null si no se atiende. */
    public static function horarioDe(string $fecha): ?array
    {
        return self::HORARIO[Carbon::parse($fecha)->isoWeekday()] ?? null;
    }

    /**
     * Las horas que el cliente puede elegir para ese día.
     *
     * SE CORTA MEDIA HORA ANTES DEL CIERRE: la hora que elige es la de LLEGADA del técnico, y
     * ofrecer las 17:30 cuando el día termina 17:30 es ofrecer una visita de cero minutos.
     *
     * Y SI EL DÍA ES DE MEDIA JORNADA, se corta ahí: el cierre que cargó el jefe de ventas
     * manda sobre el horario general, que es lo que uno espera de una excepción.
     *
     * @return list<string> «08:00», «08:30», … (vacío si ese día no se atiende)
     */
    public static function horasDisponibles(string $fecha): array
    {
        $horario = self::horarioDe($fecha);

        if ($horario === null) {
            return [];
        }

        [$abre, $cierra] = $horario;

        // El cierre de media jornada solo puede ADELANTAR el cierre, nunca estirarlo: si
        // alguien carga «hasta las 19:00» en un día que termina 16:30, el día termina 16:30.
        $media = AgendaCierre::mapaDeDias($fecha, $fecha)[$fecha] ?? null;
        if ($media?->tipo === AgendaCierre::TIPO_MEDIA_JORNADA && $media->hora_corta) {
            $cierra = min($cierra, $media->hora_corta);
        }

        $horas = [];
        $t = Carbon::parse($fecha.' '.$abre);
        $tope = Carbon::parse($fecha.' '.$cierra)->subMinutes(self::PASO_MINUTOS);

        while ($t->lessThanOrEqualTo($tope)) {
            $horas[] = $t->format('H:i');
            $t->addMinutes(self::PASO_MINUTOS);
        }

        return $horas;
    }

    /** «08:00 a 17:30» para ese día, o null si no se atiende. */
    public static function horarioLabel(string $fecha): ?string
    {
        $h = self::horarioDe($fecha);

        return $h ? $h[0].' a '.$h[1] : null;
    }

    /** La hora preferida en formato corto «14:00» (la columna `time` viene «14:00:00»). */
    public function getHoraPreferidaCortaAttribute(): ?string
    {
        return $this->hora_preferida ? substr((string) $this->hora_preferida, 0, 5) : null;
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
            'servicio' => $this->servicio?->nombre ?: '—',
            'ciudad' => $this->ciudad ?: 'sin ciudad',
            'direccion' => $this->direccion ?: '—',
            'telefono' => $this->cliente_telefono ?: 's/i',
            // Con la hora si la eligió: quien va a llamar necesita las dos cosas juntas.
            'preferida' => $this->fecha_preferida
                ? $this->fecha_preferida->format('d-m-Y').($this->hora_preferida_corta ? ' a las '.$this->hora_preferida_corta : '')
                : 'sin fecha preferida',
            // Lo que escribió el cliente: es lo más útil para quien coordina.
            'descripcion' => $this->descripcion ?: '—',
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
     * ¿Hay que pedirle al cliente que confirme la fecha? El cliente ya "eligió"
     * su `fecha_preferida` al pedir por el QR: si la cita quedó agendada para ESE
     * día, pedirle confirmar sería redundante (doble confirmación). Solo se pide
     * cuando la fecha agendada DIFIERE de la que pidió (o no indicó ninguna).
     */
    /**
     * Avisa al cliente por correo de su cita (agendada / movida / anulada).
     *
     * VIVE EN EL MODELO y no en el controlador porque hay DOS caminos que llegan acá: el
     * vendedor que agenda, y el jefe de ventas que AUTORIZA una cita días después (ver
     * `Acciones\CitaTerreno`). Con el aviso en el controlador, la cita autorizada por el
     * camino diferido salía sin avisarle a nadie — el cliente se enteraba por teléfono o no
     * se enteraba.
     *
     * El try/catch es a propósito: un correo que falla no puede tumbar ni el agendamiento ni
     * la transacción de la aprobación.
     */
    public function avisarAlCliente(string $motivo): void
    {
        try {
            if ($motivo !== 'anulada') {
                // Solo se le pide CONFIRMAR si la fecha agendada difiere de la que pidió (si
                // la respetamos, ya la eligió → correo informativo sin botón).
                if ($this->requiereConfirmacionCliente()) {
                    $this->prepararConfirmacionCliente();
                } else {
                    $this->marcarAvisoSinConfirmacion();
                }
            }

            Mail::to($this->cliente_email)->send(new AgendaTrabajoAviso($this, $motivo));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function requiereConfirmacionCliente(): bool
    {
        if (! $this->fecha) {
            return false;
        }

        return blank($this->fecha_preferida) || ! $this->fecha->isSameDay($this->fecha_preferida);
    }

    /**
     * Deja la cita como "avisada sin confirmación pendiente" (se agendó en el día
     * que el cliente pidió): sin token ni respuesta esperada. El correo sale como
     * informativo (sin botón de confirmar).
     */
    public function marcarAvisoSinConfirmacion(): void
    {
        $this->forceFill([
            'confirmacion_token' => null,
            'confirmacion_enviada_at' => null,
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
    public function avisarRechazoInterno(?string $rechazadoPor = null, ?bool $avisadoAlCliente = null): void
    {
        $datos = [
            'cliente' => $this->cliente_nombre,
            'tipo' => $this->tipo_label,
            'motivo' => $this->motivo_cancelacion ?: 'sin especificar',
            // Quién rechazó lo trae el caller (el modelo no ve al request/user).
            'rechazado_por' => $rechazadoPor ?: '—',
            'telefono' => $this->cliente_telefono ?: '—',
            'preferida' => $this->fecha_preferida?->format('d-m-Y') ?: '—',
            // Si al cliente se le avisó DE VERDAD lo sabe el caller (el envío puede
            // fallar o no haber correo). La plantilla decía siempre "Se avisó al
            // cliente por correo", que es justo lo que hace que nadie lo llame
            // cuando el correo no salió.
            'aviso_cliente' => match ($avisadoAlCliente) {
                true => 'Se le avisó al cliente por correo.',
                false => 'NO se pudo avisar al cliente por correo: contáctalo por teléfono.',
                null => '',
            },
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

    public function getEstadoLabelAttribute(): string
    {
        return self::ESTADO_ETIQUETAS[$this->estado] ?? ucfirst(str_replace('_', ' ', (string) $this->estado));
    }

    /**
     * El vendedor a cargo del cliente de este trabajo, o null.
     *
     * OJO: hoy devuelve null SIEMPRE, y eso es correcto — las carteras
     * (`clientes.vendedor_id`) están sin asignar por decisión del dueño hasta la
     * reunión con gerencia, y no existe ningún usuario con rol vendedor. Está
     * escrito así para que el día que se asignen, el aviso le llegue solo sin
     * tocar código.
     *
     * Busca por `cliente_id` y, si el trabajo entró por QR sin enlazar, por RUT
     * — que es la misma escalera que usa el resto del flujo público.
     */
    public function vendedorDelCliente(): ?User
    {
        $cliente = $this->cliente_id
            ? Cliente::find($this->cliente_id)
            : (filled($this->cliente_rut) ? Cliente::buscarPorRut($this->cliente_rut) : null);

        return $cliente?->vendedor;
    }

    /**
     * Avisa el CIERRE del trabajo a quien tiene que actuar por la zona: el jefe
     * de ventas y el vendedor del cliente (pedido del dueño 14-08-2026).
     *
     * El jefe de ventas y admin van siempre —si el aviso dependiera solo del
     * vendedor, hoy no le llegaría a nadie— y el vendedor se suma cuando exista.
     *
     * Los avisos son SECUNDARIOS: si fallan, el cierre del trabajo ya está
     * guardado y no se revierte (mismo criterio que el rechazo).
     *
     * @param  array<string, mixed>  $extra
     */
    public function avisarCierre(string $evento, array $extra = []): void
    {
        $datos = array_merge([
            'cliente' => $this->cliente_nombre,
            'tipo' => $this->tipo_label,
            'fecha' => $this->fecha?->format('d-m-Y') ?? '—',
            'ciudad' => $this->ciudad ?: '—',
            'direccion' => $this->direccion ?: '—',
            'tecnico' => $this->tecnico?->name ?? '—',
            'detalle' => $this->notas_tecnico ?: '—',
            'url' => route('admin.agenda-terreno.index'),
        ], $extra);

        $dispatcher = app(\App\Services\Notificaciones\NotificacionDispatcher::class);

        User::role(self::ROLES_AVISO_CIERRE)->get()
            ->when($this->vendedorDelCliente(), fn ($u, $v) => $u->push($v))
            ->unique('id')
            ->each(fn (User $u) => $dispatcher->despachar($evento, $this, $u, $datos));
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
