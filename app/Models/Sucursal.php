<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Sucursal extends Model implements AuditableContract
{
    /** @use HasFactory<\Database\Factories\SucursalFactory> */
    use HasFactory, AuditableTrait;

    // El pluralizador de Laravel haria 'sucursals'; fijamos la tabla correcta.
    protected $table = 'sucursales';

    protected $fillable = [
        'nombre',
        'codigo',
        'ciudad',
        'direccion',
        'es_central',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'es_central' => 'boolean',
            'activa' => 'boolean',
        ];
    }

    /**
     * Usuarios asignados a esta sucursal.
     *
     * @return HasMany<User>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Sucursales que RECIBEN servicio tecnico (activas y con su codigo en
     * config/servicio_tecnico.php `sucursales_recepcion`). Se usa en el selector
     * de la portada y en la pagina de codigos QR: Buzeta no recibe ST, asi que
     * no aparece. La reparacion siempre es en Mirador (casa matriz).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Sucursal>  $query
     */
    public function scopeRecepcionServicioTecnico($query)
    {
        return $query->where('activa', true)
            ->whereIn('codigo', config('servicio_tecnico.sucursales_recepcion', []))
            ->orderBy('nombre');
    }

    /**
     * Dias habiles de reparacion de esta sucursal (plazo de entrega estimado).
     * Configurable en config/servicio_tecnico.php por codigo de sucursal.
     */
    public function getDiasReparacionAttribute(): int
    {
        $map = config('servicio_tecnico.dias_reparacion', []);

        return $map[$this->codigo] ?? (int) config('servicio_tecnico.dias_reparacion_default', 15);
    }

    /**
     * Fecha de entrega estimada de una reparacion que ingresa en $desde:
     * dias_reparacion dias habiles a partir del dia SIGUIENTE, saltando
     * sabados, domingos y feriados (config/feriados.php). Espejo en PHP de
     * sumarDiasHabiles de app.js (ordenServicioForm): el JS solo la muestra
     * en vivo; la que se guarda la calcula el servidor.
     */
    public function fechaEntregaEstimada(\Illuminate\Support\Carbon|string $desde): \Illuminate\Support\Carbon
    {
        $d = \Illuminate\Support\Carbon::parse($desde);
        $feriados = array_values(config('feriados', []));

        for ($sumados = 0; $sumados < $this->dias_reparacion;) {
            $d->addDay();
            if ($d->isWeekend() || in_array($d->toDateString(), $feriados, true)) {
                continue;
            }
            $sumados++;
        }

        return $d;
    }

    /**
     * Maquinas sopladoras de esta sucursal.
     *
     * @return HasMany<Maquina>
     */
    public function maquinas(): HasMany
    {
        return $this->hasMany(Maquina::class);
    }
}
