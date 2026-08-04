<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Trabajo extra en paralelo del plan del proyecto (/plan): una feature que se
 * vio necesaria mientras se construía la app pero NO está en la planificación
 * oficial. Por eso es lo ÚNICO de esa página que vive en BD y se edita desde
 * la UI (permiso 'gestionar plan proyecto'): el plan oficial se parsea del
 * repo (App\Support\PlanProyecto) y no se toca por acá — doctrina de estado
 * único. Si un extra crece hasta volverse plan, se promueve al tracker §10
 * de RUTA-MAESTRA (commit) y se borra de aquí.
 */
class PlanExtra extends Model
{
    protected $table = 'plan_extras';

    /** Lista cerrada (validación con Rule::in — no enum de BD): los mismos 3 estados visuales del Gantt. */
    public const ESTADOS = ['no_iniciada', 'en_curso', 'finalizada'];

    public const LABELS = [
        'no_iniciada' => 'No iniciada',
        'en_curso' => 'En curso',
        'finalizada' => 'Finalizada',
    ];

    protected $fillable = ['titulo', 'descripcion', 'estado', 'avance', 'responsable'];

    protected function casts(): array
    {
        return [
            'avance' => 'integer',
        ];
    }
}
