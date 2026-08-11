<?php

namespace App\Models;

use App\Support\FechaNegocio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Vehículo de la flota (módulo LOGÍSTICA, pedido del dueño 04-08-2026).
 *
 * CON AuditableTrait a propósito: la fecha de un documento es justamente el
 * dato que después nadie recuerda haber cambiado. Igual que el traslado de
 * máquinas, el registro sirve si dice QUIÉN.
 */
class Vehiculo extends Model implements AuditableContract
{
    /** @use HasFactory<\Database\Factories\VehiculoFactory> */
    use AuditableTrait, HasFactory;

    protected $table = 'vehiculos';

    /** Días de anticipación del aviso de vencimiento (decisión del dueño 04-08). */
    public const DIAS_AVISO = 30;

    public const ESTADO_ACTIVO = 'activo';
    public const ESTADO_VENDIDO = 'vendido';
    public const ESTADO_BAJA = 'baja';

    public const ESTADOS = [
        self::ESTADO_ACTIVO => 'Activo',
        self::ESTADO_VENDIDO => 'Vendido',
        self::ESTADO_BAJA => 'Dado de baja',
    ];

    public const TIPOS = [
        'camioneta' => 'Camioneta',
        'camion' => 'Camión',
        'furgon' => 'Furgón de carga',
        'tractocamion' => 'Tractocamión',
        'semirremolque' => 'Semirremolque',
    ];

    public const COMBUSTIBLES = [
        'diesel' => 'Diésel',
        'gasolina' => 'Gasolina 95',
        'electrico' => 'Eléctrico',
    ];

    /**
     * Bases sugeridas. Es una LISTA SUGERIDA, no un enum: el campo acepta
     * cualquier texto porque la flota se mueve y no queremos un deploy para
     * agregar una ubicación. Ver el comentario de la migración.
     *
     * Las TRES sucursales que operan (dato del dueño, 04-08-2026): Mirador,
     * Abate Molina y Coquimbo, y no hay más. Y es donde vive la flota real:
     * Mirador 13, Coquimbo 3, Abate Molina 1.
     *
     * Lo que salió, y por qué:
     * - **Concepción, Antofagasta y Viña del Mar**: las sucursales CERRARON.
     * - **Buzeta**: es una bodega de mercadería, no una sucursal — ahí no se
     *   dejan vehículos.
     * - **Damimed y Jefaturas**: eran valores de la planilla, pero ninguno de
     *   los 17 vehículos de la flota los usa (los que los tenían no eran de la
     *   flota actual — ver §1 de docs/reglas/flota-de-vehiculos.md).
     *
     * Sacar un valor de acá NO rompe una ficha que ya lo tenga: el campo es
     * texto libre justamente para eso, y por eso es una lista SUGERIDA y no un
     * enum. Si mañana un vehículo va a otra parte, se escribe y listo.
     */
    public const BASES = [
        'Mirador',
        'Abate Molina',
        'Coquimbo',
    ];

    /**
     * Documentos con vencimiento: columna => etiqueta. Fuente única del
     * semáforo, del formulario, de la ficha y del comando de avisos — que son
     * cuatro lugares donde antes se habría copiado la misma lista.
     */
    public const DOCUMENTOS = [
        'rt_vence' => 'Revisión técnica / homologación',
        'emisiones_vence' => 'Certificado de emisiones',
        'permiso_circulacion_vence' => 'Permiso de circulación',
        'soap_vence' => 'SOAP',
        'extintor_vence' => 'Mantención del extintor',
    ];

    // Estados de un documento, del peor al mejor (el orden ES la prioridad
    // con la que se resuelve el estado del vehículo).
    public const DOC_VENCIDO = 'vencido';
    public const DOC_POR_VENCER = 'por_vencer';
    public const DOC_AL_DIA = 'al_dia';
    public const DOC_SIN_REGISTRO = 'sin_registro';
    public const DOC_NO_APLICA = 'no_aplica';

    protected $fillable = [
        'ppu',
        'alias',
        'marca',
        'modelo',
        'anio',
        'tipo',
        'combustible',
        'vin',
        'numero_motor',
        'cilindrada',
        'pbv_kg',
        'capacidad_carga_kg',
        // Caja de carga (Simulador de carga): medidas UTILES, por dentro.
        'largo_util_cm', 'ancho_util_cm', 'alto_util_cm', 'pasillo_cm',
        'presion_psi',
        'base',
        'conductor_nombre',
        'estado',
        'baja_at',
        'baja_motivo',
        'rt_vence',
        'emisiones_vence',
        'permiso_circulacion_vence',
        'soap_vence',
        'extintor_vence',
        'extintor_capacidad_kg',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'cilindrada' => 'integer',
            'pbv_kg' => 'integer',
            'capacidad_carga_kg' => 'integer',
            'largo_util_cm' => 'integer',
            'ancho_util_cm' => 'integer',
            'alto_util_cm' => 'integer',
            'pasillo_cm' => 'integer',
            'presion_psi' => 'integer',
            'extintor_capacidad_kg' => 'decimal:1',
            'baja_at' => 'date',
            'rt_vence' => 'date',
            'emisiones_vence' => 'date',
            'permiso_circulacion_vence' => 'date',
            'soap_vence' => 'date',
            'extintor_vence' => 'date',
        ];
    }

    /**
     * Avisos de vencimiento ya enviados por este vehículo.
     *
     * @return HasMany<VehiculoAviso>
     */
    public function avisos(): HasMany
    {
        return $this->hasMany(VehiculoAviso::class);
    }

    /**
     * Respaldos digitales de los documentos (la foto del SOAP, del permiso…).
     * El vigente de cada documento es el más nuevo; el resto es historial.
     *
     * @return HasMany<VehiculoDocumento>
     */
    public function respaldos(): HasMany
    {
        return $this->hasMany(VehiculoDocumento::class);
    }

    /** @param  Builder<Vehiculo>  $query */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_ACTIVO);
    }

    /**
     * Búsqueda por patente, alias, marca, modelo, chofer o base. Un solo campo
     * de búsqueda: en la planilla se busca con Ctrl+F sin pensar en columnas.
     *
     * @param  Builder<Vehiculo>  $query
     */
    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        $termino = trim((string) $termino);

        if ($termino === '') {
            return $query;
        }

        // La patente se escribe con o sin guion ("TJGW-15" / "TJGW15"): se
        // normaliza el término para que ambas formas encuentren la fila.
        $plano = str_replace(['-', ' ', '.'], '', $termino);

        return $query->where(function (Builder $q) use ($termino, $plano) {
            foreach (['ppu', 'alias', 'marca', 'modelo', 'conductor_nombre', 'base', 'vin'] as $col) {
                $q->orWhere($col, 'like', "%{$termino}%");
            }
            $q->orWhere('ppu', 'like', "%{$plano}%");
        });
    }

    public function getEsActivoAttribute(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    /** Cómo se nombra el vehículo en pantalla: la patente manda, el alias acompaña. */
    public function getNombreAttribute(): string
    {
        return $this->alias ? "{$this->ppu} · {$this->alias}" : (string) $this->ppu;
    }

    public function getTipoLabelAttribute(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo ?? '—';
    }

    public function getCombustibleLabelAttribute(): ?string
    {
        return $this->combustible ? (self::COMBUSTIBLES[$this->combustible] ?? $this->combustible) : null;
    }

    public function getEstadoLabelAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    /**
     * Marca + modelo en una línea (lo que la planilla tiene en dos columnas).
     */
    public function getMarcaModeloAttribute(): ?string
    {
        return trim(($this->marca ?? '').' '.($this->modelo ?? '')) ?: null;
    }

    /**
     * Un documento NO le aplica a este vehículo. Hoy la única regla: el
     * semirremolque no rinde certificado de emisiones (no tiene motor) — en la
     * planilla eso está escrito a mano como "NO APLICA" en la celda. Modelarlo
     * como regla y no como dato evita que alguien lo lea como "sin registrar"
     * y lo persiga para siempre.
     */
    public function documentoAplica(string $clave): bool
    {
        if ($clave === 'emisiones_vence' && $this->tipo === 'semirremolque') {
            return false;
        }

        return true;
    }

    /**
     * Los documentos del vehículo con su estado resuelto. Es la fuente del
     * semáforo (lista y ficha) y de los avisos.
     *
     * @return list<array{clave: string, label: string, vence: ?\Illuminate\Support\Carbon, estado: string, dias: ?int}>
     */
    public function documentos(): array
    {
        // Se compara FECHA contra FECHA, parseando las dos desde 'Y-m-d'.
        //
        // GOTCHA: FechaNegocio::ahora() vive en hora de Chile (-04) y la columna
        // `date` se hidrata en la timezone de la app (UTC). Restar esos dos
        // instantes deja 20 horas de resto, y el (int) las convierte en un día
        // MENOS: un documento que vence en 31 días daba 30 y entraba en la
        // franja de aviso. Parseando ambas desde su 'Y-m-d' la diferencia es
        // siempre un número entero de días, sin importar la zona.
        $hoy = Carbon::parse(FechaNegocio::hoy());
        $docs = [];

        foreach (self::DOCUMENTOS as $clave => $label) {
            $vence = $this->{$clave};

            if (! $this->documentoAplica($clave)) {
                $estado = self::DOC_NO_APLICA;
                $dias = null;
            } elseif ($vence === null) {
                $estado = self::DOC_SIN_REGISTRO;
                $dias = null;
            } else {
                // Días que faltan: negativo = ya venció. diffInDays con false
                // conserva el signo (sin él, "venció hace 5 días" daría 5).
                $dias = (int) $hoy->diffInDays(Carbon::parse($vence->toDateString()), false);
                $estado = match (true) {
                    $dias < 0 => self::DOC_VENCIDO,
                    $dias <= self::DIAS_AVISO => self::DOC_POR_VENCER,
                    default => self::DOC_AL_DIA,
                };
            }

            $docs[] = ['clave' => $clave, 'label' => $label, 'vence' => $vence, 'estado' => $estado, 'dias' => $dias];
        }

        return $docs;
    }

    /**
     * El peor estado de sus documentos: es lo que se muestra en la fila del
     * listado. Un vehículo que no está activo no tiene estado documental (no
     * circula), así que no contamina el semáforo de la flota.
     */
    public function getEstadoDocumentalAttribute(): string
    {
        if (! $this->es_activo) {
            return self::DOC_NO_APLICA;
        }

        $estados = array_column($this->documentos(), 'estado');

        // SIN_REGISTRO va ANTES de AL_DIA a propósito: una fecha que no está
        // cargada NO es "al día". Con 4 documentos vigentes y el SOAP sin fecha,
        // decir "Al día" es exactamente la mentira que la planilla permitía
        // (una celda vacía se lee como si estuviera bien).
        foreach ([self::DOC_VENCIDO, self::DOC_POR_VENCER, self::DOC_SIN_REGISTRO, self::DOC_AL_DIA] as $prioridad) {
            if (in_array($prioridad, $estados, true)) {
                return $prioridad;
            }
        }

        return self::DOC_NO_APLICA;
    }

    /**
     * Documentos vencidos o por vencer, en orden de urgencia. Lo que hay que
     * mostrar en la fila sin obligar a abrir la ficha.
     *
     * @return list<array{clave: string, label: string, vence: ?\Illuminate\Support\Carbon, estado: string, dias: ?int}>
     */
    public function documentosCriticos(): array
    {
        $criticos = array_values(array_filter(
            $this->documentos(),
            fn (array $d) => in_array($d['estado'], [self::DOC_VENCIDO, self::DOC_POR_VENCER], true)
        ));

        usort($criticos, fn (array $a, array $b) => $a['dias'] <=> $b['dias']);

        return $criticos;
    }

    /**
     * Etiqueta humana del plazo de un documento. "Vence en 12 días" /
     * "Venció hace 3 días" — nunca un número pelado, que obliga a interpretar.
     */
    public static function plazoLabel(?int $dias): string
    {
        return match (true) {
            $dias === null => 'Sin registrar',
            $dias < -1 => 'Venció hace '.abs($dias).' días',
            $dias === -1 => 'Venció ayer',
            $dias === 0 => 'Vence hoy',
            $dias === 1 => 'Vence mañana',
            default => "Vence en {$dias} días",
        };
    }

    /**
     * Variante de <x-badge> para un estado documental. Paleta ESTRICTA de la
     * app (CLAUDE.md): rojo SOLO para lo negativo, naranjo de marca para lo
     * que requiere acción, neutro para lo que está en reposo. Los tres colores
     * del Excel (rojo/amarillo/verde) se traducen a rojo/naranjo/gris — el
     * verde no existe en esta app.
     */
    public static function variante(string $estadoDocumental): string
    {
        return match ($estadoDocumental) {
            self::DOC_VENCIDO => 'danger',
            self::DOC_POR_VENCER => 'brand',
            default => 'neutral',
        };
    }

    public static function estadoDocumentalLabel(string $estado): string
    {
        return match ($estado) {
            self::DOC_VENCIDO => 'Vencido',
            self::DOC_POR_VENCER => 'Por vencer',
            self::DOC_AL_DIA => 'Al día',
            // "Sin fecha" y no "Sin documentos": el documento puede existir en la
            // carpeta; lo que falta es la fecha en el sistema.
            self::DOC_SIN_REGISTRO => 'Sin fecha',
            default => 'No aplica',
        };
    }
}
