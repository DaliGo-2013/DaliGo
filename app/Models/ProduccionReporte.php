<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class ProduccionReporte extends Model implements AuditableContract
{
    // Auditable: tres escritores tocan los mismos totales (soplador via
    // recalculo, jefe via ajuste, y el recalculo pisando ajustes); la traza
    // queda en el visor de auditoria.
    use AuditableTrait;

    protected $table = 'produccion_reportes';

    // Estados del flujo.
    public const BORRADOR = 'borrador';
    public const ENVIADO = 'enviado';
    public const APROBADO = 'aprobado';
    public const DEVUELTO = 'devuelto';

    // Centinela del chip "Otro" del selector de motivo (la vista lo manda como
    // valor del radio; el controlador lo resuelve al texto libre de motivo_otro).
    public const MOTIVO_OTRO = '__otro__';

    // Motivos por los que lo producido no cuadra con lo asignado. Lista abierta:
    // se ofrecen como chips tocables y, si ninguno aplica, el operario escribe en
    // "Otro" (por eso 'motivo' no se restringe con Rule::in). Fuente unica para
    // la vista; antes vivian hardcodeados en mi-reporte.blade.php.
    public const MOTIVOS_DIFERENCIA = [
        'Faltaron preformas',
        'Falla de máquina',
        'Mantención de máquina',
        'Cambio de molde',
        'Molde dañado',
        'Preformas defectuosas',
        'Corte de luz',
    ];

    // Notas frecuentes para las observaciones: el operario las inserta tocando un
    // chip (no son una lista cerrada; el textarea queda como respaldo editable).
    public const NOTAS_COMUNES = [
        'Máquina con falla intermitente',
        'Preformas con polvo o humedad',
        'Molde frío al iniciar el turno',
        'Corte de luz breve',
        'Material de baja calidad',
    ];

    protected $fillable = [
        'asignacion_id',
        'soplador_id',
        'fecha',
        'turno',
        'asignadas',
        'primera',
        'segunda',
        'malo',
        'danada',
        'motivo',
        'obs',
        'estado',
        'enviado_at',
        'revisado_por',
        'revisado_at',
        'motivo_ajuste',
        'devuelto_motivo',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'asignadas' => 'integer',
            'primera' => 'integer',
            'segunda' => 'integer',
            'malo' => 'integer',
            'danada' => 'integer',
            'enviado_at' => 'datetime',
            'revisado_at' => 'datetime',
        ];
    }

    // --- Relaciones ---

    public function asignacion(): BelongsTo
    {
        return $this->belongsTo(ProduccionAsignacion::class, 'asignacion_id');
    }

    public function soplador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'soplador_id');
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    public function registros(): HasMany
    {
        return $this->hasMany(ProduccionRegistro::class, 'reporte_id');
    }

    /**
     * Movimientos del kardex local generados al aprobar (regla #9).
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(ProduccionMovimiento::class, 'reporte_id');
    }

    // --- Derivados ---

    /**
     * Total contado = preformas que el soplador dio cuenta (1a + 2a + malo +
     * danada). Es la base de la diferencia con lo asignado y del consumo de
     * preforma en el kardex (cada unidad contada consumio una preforma).
     */
    public function getTotalAttribute(): int
    {
        return (int) $this->primera + (int) $this->segunda + (int) $this->malo + (int) $this->danada;
    }

    /**
     * Producido vendible = 1a + 2a. NO incluye malos ni preformas danadas (que
     * consumieron preforma pero no rindieron botellon). Es la metrica honesta
     * de productividad; "total" es consumo, no produccion.
     */
    public function getProducidoAttribute(): int
    {
        return (int) $this->primera + (int) $this->segunda;
    }

    /**
     * Merma = malo + danada (preforma consumida sin botellon vendible).
     */
    public function getMermaAttribute(): int
    {
        return (int) $this->malo + (int) $this->danada;
    }

    public function getDiferenciaAttribute(): int
    {
        return (int) $this->asignadas - $this->total;
    }

    public function getTasaPrimeraAttribute(): int
    {
        return $this->tasaDe($this->primera);
    }

    public function getTasaSegundaAttribute(): int
    {
        return $this->tasaDe($this->segunda);
    }

    public function getTasaMaloAttribute(): int
    {
        return $this->tasaDe($this->malo);
    }

    public function getTasaDanadaAttribute(): int
    {
        return $this->tasaDe($this->danada);
    }

    /**
     * Porcentaje (entero) de una categoria sobre el total producido. Base para
     * todas las tasas del reporte; agregar una nueva tasa = un accessor que
     * llame aqui con la cantidad correspondiente. 0 si aun no hay total.
     */
    protected function tasaDe(?int $cantidad): int
    {
        return $this->total > 0 ? (int) round((int) $cantidad / $this->total * 100) : 0;
    }

    /**
     * Sincroniza los totales denormalizados desde los registros (tandas).
     * Llamar dentro de la misma transaccion que crea/borra el registro.
     * Limpia motivo_ajuste: un ajuste del jefe pierde sentido si el detalle
     * cambio despues (solo puede pasar en estado devuelto = re-reportar).
     */
    public function recalcularDesdeRegistros(): void
    {
        $sumas = $this->registros()
            ->selectRaw('COALESCE(SUM(primera), 0) AS t_primera, COALESCE(SUM(segunda), 0) AS t_segunda, COALESCE(SUM(malo), 0) AS t_malo, COALESCE(SUM(danada), 0) AS t_danada')
            ->first();

        $this->primera = (int) $sumas->t_primera;
        $this->segunda = (int) $sumas->t_segunda;
        $this->malo = (int) $sumas->t_malo;
        $this->danada = (int) $sumas->t_danada;
        $this->motivo_ajuste = null;
        $this->save();
    }

    // --- Helpers de estado ---

    public function editablePorSoplador(): bool
    {
        return in_array($this->estado, [self::BORRADOR, self::DEVUELTO], true);
    }

    public function esPendienteDeRevision(): bool
    {
        return $this->estado === self::ENVIADO;
    }

    /**
     * Arma un resumen de produccion (producido/merma/tasas/avance) a partir de
     * las 4 cantidades y lo asignado. Fuente unica de las formulas para el
     * panel del jefe y el tablero del Inicio, asi todos calculan igual.
     */
    public static function armarResumen(int $p1, int $p2, int $mal, int $dan, int $asignadas): array
    {
        $producido = $p1 + $p2;
        $merma = $mal + $dan;
        $total = $producido + $merma;

        return [
            'asignadas' => $asignadas,
            'producido' => $producido,
            'merma' => $merma,
            'total' => $total,
            'merma_pct' => $total > 0 ? (int) round($merma / $total * 100) : 0,
            'tasa1' => $total > 0 ? (int) round($p1 / $total * 100) : 0,
            'avance' => $asignadas > 0 ? (int) round($producido / $asignadas * 100) : 0,
        ];
    }

    /**
     * Serie por dia desde los reportes (totales denormalizados), keyed por
     * Y-m-d. Una sola query agregada (whereDate + groupBy, 5.7-safe); la
     * comparten el panel del jefe y el pulso del Inicio (M16-v1).
     */
    public static function seriePorDia(string $desde, string $hasta, ?int $sopladorId = null)
    {
        return static::whereDate('fecha', '>=', $desde)->whereDate('fecha', '<=', $hasta)
            ->when($sopladorId, fn ($q) => $q->where('soplador_id', $sopladorId))
            ->selectRaw('fecha, COALESCE(SUM(primera),0) p1, COALESCE(SUM(segunda),0) p2, COALESCE(SUM(malo),0) mal, COALESCE(SUM(danada),0) dan, COUNT(*) reportes')
            ->groupBy('fecha')
            ->get()
            ->keyBy(fn ($r) => \Illuminate\Support\Carbon::parse($r->fecha)->toDateString());
    }

    // --- Scopes ---

    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', self::ENVIADO);
    }

    public function scopeDelDia(Builder $query, $fecha = null): Builder
    {
        return $query->whereDate('fecha', $fecha ?? now()->toDateString());
    }
}
