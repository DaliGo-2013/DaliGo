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
     * El codigo es una LLAVE, no un rotulo: normalizado a MAYUSCULAS y sin espacios para
     * compararlo. Lo escribe una persona en el formulario de sucursales, y de ahi salen
     * busquedas en config (el plazo de reparacion, las sucursales que reciben taller).
     */
    public static function normalizaCodigo(?string $codigo): string
    {
        return mb_strtoupper(trim((string) $codigo));
    }

    /**
     * Dias habiles de reparacion de esta sucursal (plazo de entrega estimado).
     * Configurable en config/servicio_tecnico.php por codigo de sucursal.
     *
     * SE COMPARA NORMALIZADO, y no es una precaucion teorica: en produccion el codigo de la
     * casa matriz estaba guardado como «Mirador» (alguien lo retipeo al editar la ficha) y este
     * metodo hacia `$map[$this->codigo]` — un indice de array de PHP, case-sensitive. El mapa
     * tiene `MIRADOR`, asi que Mirador caia al default de 15 dias habiles y el correo le
     * prometia al cliente 15 donde el dueño dijo 10 (hallazgo del 14-08-2026; es la diferencia
     * exacta del correo real: ingreso 06-08 → entrega 27-08 en vez del 20-08).
     *
     * Nadie lo vio porque todo lo demas que usa el codigo pasa por SQL (`whereIn`) y en MySQL
     * eso es case-insensitive por colacion: la sucursal aparecia en el selector del QR y
     * funcionaba, menos el numero que el cliente recibe por escrito.
     */
    public function getDiasReparacionAttribute(): int
    {
        return $this->diasReparacionConfigurados()
            ?? (int) config('servicio_tecnico.dias_reparacion_default', 15);
    }

    /**
     * Los dias que esta sucursal tiene configurados A NOMBRE PROPIO, o null si no tiene entrada
     * y por lo tanto usa el default. Es la diferencia que el listado de Sucursales muestra: un
     * plazo heredado del default se ve igual que uno decidido, y esa confusion es la que dejo
     * siete semanas de correos prometiendo 15 dias donde la regla decia 10.
     */
    public function diasReparacionConfigurados(): ?int
    {
        $codigo = self::normalizaCodigo($this->codigo);

        // Las claves del mapa tambien se normalizan: si mañana alguien agrega 'buzeta' en
        // config, tiene que valer igual que 'BUZETA'.
        foreach (config('servicio_tecnico.dias_reparacion', []) as $clave => $dias) {
            if (self::normalizaCodigo((string) $clave) === $codigo) {
                return (int) $dias;
            }
        }

        return null;
    }

    /** El plazo que se le promete al cliente sale del default y no de una decision propia. */
    public function getPlazoEsPorDefectoAttribute(): bool
    {
        return $this->diasReparacionConfigurados() === null;
    }

    /**
     * Esta sucursal RECIBE servicio tecnico (version en PHP de scopeRecepcionServicioTecnico,
     * para preguntarselo a una fila que ya se cargo). Compara normalizado: el scope se apoya en
     * que MySQL no distingue mayusculas y en PHP eso no vale.
     */
    public function getRecibeServicioTecnicoAttribute(): bool
    {
        return in_array(
            self::normalizaCodigo($this->codigo),
            array_map([self::class, 'normalizaCodigo'], config('servicio_tecnico.sucursales_recepcion', [])),
            true,
        );
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

    /**
     * Bodegas (espejo Bsale) clasificadas bajo esta sucursal (M04-F1).
     *
     * @return HasMany<Bodega>
     */
    public function bodegas(): HasMany
    {
        return $this->hasMany(Bodega::class);
    }
}
