<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una carga que se cargó DE VERDAD, anotada al lado de lo que el simulador había dicho.
 *
 * Es la única fuente de un factor de corrección propio: el motor calcula un TECHO por
 * rejilla exacta y la estiba real siempre queda por debajo (amarres, hilera del piso
 * girada, gente que necesita pasar). Cuánto por debajo no se deduce — se cuenta.
 */
class CargaReal extends Model
{
    use HasFactory;

    protected $table = 'cargas_reales';

    protected $fillable = [
        'fecha', 'camion_simulacion_id', 'tipo_bulto_id', 'estiba',
        'simulado', 'real', 'user_id', 'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'simulado' => 'integer',
            'real' => 'integer',
        ];
    }

    public function camion(): BelongsTo
    {
        return $this->belongsTo(CamionSimulacion::class, 'camion_simulacion_id');
    }

    public function bulto(): BelongsTo
    {
        return $this->belongsTo(TipoBulto::class, 'tipo_bulto_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Cuánto de lo prometido entró de verdad. 0,87 = entró el 87% del techo.
     *
     * NO se guarda en la base a propósito: es una división de dos columnas que ya están
     * ahí, y un número derivado persistido se desactualiza en silencio el día que cambia
     * la fórmula o se corrige uno de los dos datos.
     *
     * Puede dar MÁS de 1: significa que se cargó más de lo que el simulador prometía, y
     * eso no es un error de tipeo sino la señal más valiosa de la tabla — quiere decir que
     * alguna medida del catálogo está corta. Fue exactamente el caso del HD35 el 11-08.
     */
    public function factor(): ?float
    {
        return $this->simulado > 0 ? round($this->real / $this->simulado, 4) : null;
    }

    /** Diferencia en unidades, con signo: positiva si entró MÁS de lo prometido. */
    public function diferencia(): int
    {
        return $this->real - $this->simulado;
    }
}
