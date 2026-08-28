<?php

namespace App\Models;

use Database\Factories\LoteServicioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lote de ingreso a Servicio Técnico: agrupa las N máquinas que un conductor
 * retira en ruta a UNA empresa. Cada máquina es una OrdenServicio independiente
 * (enlazada por lote_id); el lote guarda lo que vive UNA vez (empresa, origen,
 * conductor, defaults) y da idempotencia al reenvío offline (lote_uuid).
 */
class LoteServicio extends Model implements AuditableContract
{
    /** @use HasFactory<LoteServicioFactory> */
    use AuditableTrait, HasFactory;

    protected $table = 'lotes_servicio';

    protected static function booted(): void
    {
        static::creating(function (self $lote) {
            if (blank($lote->codigo)) {
                $lote->codigo = self::generarCodigoUnico();
            }
        });
    }

    /** Código único e impredecible del lote (ej. LOTE-K7QM2X9P). Reintenta ante colisión. */
    public static function generarCodigoUnico(): string
    {
        do {
            $codigo = 'LOTE-'.Str::upper(Str::random(8));
        } while (static::where('codigo', $codigo)->exists());

        return $codigo;
    }

    protected $fillable = [
        'codigo',
        'lote_uuid',
        'cliente_id',
        'cliente_nombre',
        'cliente_rut',
        'cliente_email',
        'cliente_telefono',
        'origen_ciudad',
        'sucursal_id',
        'conductor_id',
        'conductor_nombre',
        'fecha_ingreso',
        'tipo_default',
        'facturacion_default',
        'falla_default',
        'total_ordenes',
        'capturado_at',
        'confirmada_at',
        'recibida_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'date',
            'total_ordenes' => 'integer',
            'capturado_at' => 'datetime',
            'confirmada_at' => 'datetime',
        ];
    }

    /** @return HasMany<OrdenServicio, $this> */
    public function ordenes(): HasMany
    {
        return $this->hasMany(OrdenServicio::class, 'lote_id');
    }

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /** @return BelongsTo<Sucursal, $this> */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /** @return BelongsTo<User, $this> */
    public function conductor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conductor_id');
    }

    /**
     * Aviso interno de ingreso (M15) para un LOTE (varias máquinas de una vez):
     * UN solo aviso resumido, no uno por máquina. Mismos destinatarios que el
     * ingreso por unidad (el evento es el mismo: 'taller.ingresado').
     * Secundario: el emisor lo envuelve en try/catch.
     */
    public function notificarIngresoInterno(): void
    {
        $datos = [
            // Un lote son N ordenes, asi que el "folio" del aviso es el codigo del
            // lote; el destino sigue siendo el listado (el lote no tiene ficha).
            'folio' => $this->codigo ?? '—',
            'cliente' => $this->cliente_nombre,
            'equipo' => OrdenServicio::etiquetaTipo($this->tipo_default),
            'maquinas' => $this->total_ordenes.' equipos',
            'sucursal' => $this->sucursal?->nombre ?: '—',
            'condicion' => $this->facturacion_default === 'garantia' ? 'Garantía' : 'Reparación',
            'url' => route('admin.servicio-tecnico.index'),
        ];

        $dispatcher = app(\App\Services\Notificaciones\NotificacionDispatcher::class);

        \App\Support\AudienciasNotificacion::destinatarios('taller.ingresado')
            ->each(fn (User $u) => $dispatcher->despachar('taller.ingresado', $this, $u, $datos));
    }
}
