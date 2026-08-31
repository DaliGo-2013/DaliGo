<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements AuditableContract, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles, AuditableTrait;

    /**
     * Atributos que NO se auditan (owen-it no respeta $hidden).
     *
     * @var array<int, string>
     */
    protected $auditExclude = [
        'password',
        'remember_token',
        'dashboard_colores', // preferencia de UI, no evento de negocio
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'sucursal_id',
        'zona_id',
        'jefe_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            // Mapa {card => color} de los accesos del Inicio (TEXT en BD por
            // MySQL 5.7). NO va en $fillable: solo escribe su endpoint dedicado.
            'dashboard_colores' => 'array',
        ];
    }

    /**
     * Default HISTÓRICO de los roles que cuentan como soplador para Producción
     * (asignar producción, historial por soplador, notas). Fallback de la
     * clave `produccion_roles_soplador` — regla de oro de parametrizar.
     */
    public const ROLES_SOPLADOR = ['soplador'];

    /**
     * Roles vigentes que cuentan como soplador, leídos de la clave
     * `produccion_roles_soplador` (editable en Configuración, una por línea)
     * con el cinturón del consumidor: se descartan los roles que no existen y
     * una lista rota o VACÍA cae al default — a diferencia de las audiencias
     * de avisos, acá el vacío deliberado NO existe: un selector de sopladores
     * sin opciones bloquea la operación de la planta.
     *
     * @return list<string>
     */
    public static function rolesSoplador(): array
    {
        $crudo = Configuracion::get('produccion_roles_soplador');

        if (! is_array($crudo)) {
            return self::ROLES_SOPLADOR;
        }

        $vigentes = array_values(array_intersect(
            array_unique(array_filter($crudo, 'is_string')),
            \Spatie\Permission\Models\Role::pluck('name')->all(),
        ));

        return $vigentes === [] ? self::ROLES_SOPLADOR : $vigentes;
    }

    /**
     * Los usuarios asignables como soplador (pedido del dueño 28-08-2026: en
     * «Asignar producción» aparecía cualquiera — el selector poblaba por el
     * PERMISO 'report production', que admin y jefaturas también tienen). Por
     * ROL y desde una sola fuente: agregar o quitar gente es asignar el rol en
     * Usuarios, y qué roles cuentan se edita en Configuración.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public static function sopladores(): \Illuminate\Database\Eloquent\Collection
    {
        return self::role(self::rolesSoplador())->orderBy('name')->get();
    }

    /** ¿Este usuario cuenta como soplador? (la validación del guardado usa el MISMO universo que el selector). */
    public function esSoplador(): bool
    {
        return $this->hasAnyRole(self::rolesSoplador());
    }

    /**
     * Sucursal a la que pertenece el usuario (opcional).
     *
     * @return BelongsTo<Sucursal, User>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * Zona comercial que atiende el usuario (vendedor), opcional (D-006).
     *
     * @return BelongsTo<Zona, User>
     */
    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    /**
     * Jefe directo (jefatura) de este usuario, si tiene.
     *
     * @return BelongsTo<User, User>
     */
    public function jefe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_id');
    }

    /**
     * Vendedores a cargo de este usuario (jefatura -> su equipo).
     *
     * @return HasMany<User>
     */
    public function subordinados(): HasMany
    {
        return $this->hasMany(User::class, 'jefe_id');
    }

    /**
     * IDs de los vendedores cuya cartera de clientes este usuario puede ver en
     * Servicio Técnico: la propia + la de sus subordinados directos. Un vendedor
     * sin equipo obtiene solo su propio id; una jefatura suma la de su equipo.
     * Es la base del scope de visibilidad (ver App\Models\OrdenServicio).
     *
     * @return list<int>
     */
    public function idsCarteraServicioTecnico(): array
    {
        return $this->subordinados()->pluck('id')->push($this->id)->all();
    }
}
