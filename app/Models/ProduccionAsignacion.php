<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProduccionAsignacion extends Model
{
    protected $table = 'produccion_asignaciones';

    // En qué formato llegó la preforma del turno. Fuente única para el
    // selector del form de asignar y su validación (Rule::in).
    public const PROCEDENCIAS = ['saco', 'caja'];

    protected $fillable = [
        'soplador_id',
        'fecha',
        'turno',
        'asignadas',
        'preforma_id',
        'procedencia',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'asignadas' => 'integer',
        ];
    }

    public function soplador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'soplador_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /**
     * Preforma del turno (producto del catalogo). Nullable: asignaciones
     * historicas o sin preforma elegida.
     */
    public function preforma(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'preforma_id');
    }

    public function reporte(): HasOne
    {
        return $this->hasOne(ProduccionReporte::class, 'asignacion_id');
    }

    /**
     * Asignadas por dia (mapa Y-m-d => int) para el rango. Una query agregada;
     * la comparten el panel del jefe y el pulso del Inicio (M16-v1).
     */
    public static function asignadasPorDia(string $desde, string $hasta)
    {
        return static::whereDate('fecha', '>=', $desde)->whereDate('fecha', '<=', $hasta)
            ->selectRaw('fecha, COALESCE(SUM(asignadas),0) a')
            ->groupBy('fecha')
            ->get()
            ->mapWithKeys(fn ($r) => [\Illuminate\Support\Carbon::parse($r->fecha)->toDateString() => (int) $r->a]);
    }
}
