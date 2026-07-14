<?php

namespace App\Models;

use Database\Factories\OrdenServicioFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Orden de servicio tecnico: el ingreso de una maquina/lavadora al taller.
 * Espeja el Excel de OneDrive dentro de DaliGo. El dueno del equipo se enlaza
 * por RUT a la ficha de clientes (cliente_id), opcional para no frenar un
 * ingreso de mostrador. Auditable: queda registro de quien ingreso/edito.
 */
class OrdenServicio extends Model implements AuditableContract
{
    /** @use HasFactory<OrdenServicioFactory> */
    use AuditableTrait, HasFactory;

    protected static function booted(): void
    {
        // Cada orden recibe al crearse un codigo unico impredecible (reemplaza al
        // folio correlativo, que era enumerable). Cubre QR, mostrador y factory
        // sin tocar los controladores.
        static::creating(function (self $orden) {
            if (blank($orden->codigo)) {
                $orden->codigo = self::generarCodigoUnico();
            }
        });
    }

    /** Codigo unico e impredecible para el folio (ej. ST-K7QM2X9P). Reintenta ante colision. */
    public static function generarCodigoUnico(): string
    {
        do {
            $codigo = 'ST-'.Str::upper(Str::random(8));
        } while (static::where('codigo', $codigo)->exists());

        return $codigo;
    }

    // 'otro' es el comodin (equipos que no calzan con los tipos con nombre).
    public const TIPOS = ['dispensador', 'lavadora', 'bomba', 'herramienta', 'otro'];

    // Etiqueta visible por tipo (el valor guardado es la clave, en minuscula y
    // sin espacios). Se usa en selectores, listados, detalle e informe para que
    // el rotulo sea consistente en todos lados (ej. 'bomba' -> "Bomba de agua").
    public const TIPO_ETIQUETAS = [
        'dispensador' => 'Dispensador',
        'lavadora' => 'Lavadora',
        'bomba' => 'Bomba de agua',
        'herramienta' => 'Herramienta',
        'otro' => 'Otro',
    ];

    // Tipos cuyo N° de serie es OBLIGATORIO: tienen una serie unica e importante
    // (dispensadores y lavadoras). El resto (bomba/herramienta/otro) es opcional
    // -> no tienen serie unica por equipo. Usado por la validacion y por el
    // formulario (asterisco + required dinamico segun el tipo elegido).
    public const SERIE_OBLIGATORIA_TIPOS = ['dispensador', 'lavadora'];

    // Causa de la falla que diagnostica el TECNICO al reparar (opcional; null =
    // sin determinar). Indicador clave: separa las fallas por mal uso del cliente
    // (oportunidad de capacitacion) de las de desgaste normal o defecto de fabrica.
    public const CAUSAS_FALLA = ['mal_uso', 'uso_normal', 'falla_fabrica'];

    public const CAUSA_FALLA_ETIQUETAS = [
        'mal_uso' => 'Mal uso del cliente',
        'uso_normal' => 'Desgaste por uso normal',
        'falla_fabrica' => 'Falla de fábrica / defecto',
    ];

    // Categoría de cierre SOLO para máquinas propias (IMP. DALI) que se
    // reacondicionan para revender: con qué calidad termina la máquina.
    public const CATEGORIAS = ['primera', 'segunda', 'desarme'];

    public const CATEGORIA_ETIQUETAS = [
        'primera' => 'Primera',
        'segunda' => 'Segunda',
        'desarme' => 'Desarme',
    ];

    // Lista simple (NO transiciones): el formulario las ofrece en un <select>.
    // 'cotizacion' = se le paso presupuesto al cliente y se espera su aprobacion
    // del arreglo (va despues de la revision, antes de pedir repuestos/reparar).
    public const ESTADOS = ['recibido', 'en_revision', 'cotizacion', 'esperando_repuesto', 'reparado', 'entregado', 'sin_solucion'];

    // Color del badge por etapa (variantes de x-badge), para leer el estado de un vistazo.
    public const ESTADO_VARIANTES = [
        'recibido' => 'brand',
        'en_revision' => 'info',
        'cotizacion' => 'warning',
        'esperando_repuesto' => 'warning',
        'reparado' => 'success',
        'entregado' => 'neutral',
        'sin_solucion' => 'danger',
    ];

    // Condicion del ingreso. Garantia: no se cobra (si esta vigente).
    // Reparacion: se cobra al cliente.
    public const FACTURACION = ['garantia', 'reparacion'];

    // Documento de compra que respalda la garantia.
    public const GARANTIA_DOC_TIPOS = ['factura', 'boleta'];

    // Duracion de la garantia desde la fecha de compra.
    public const GARANTIA_MESES = 6;

    // Descuentos permitidos sobre el total de una reparacion cobrable (%).
    public const DESCUENTOS_PCT = [10, 15, 20];

    // Motivo que justifica un descuento (obligatorio si hay descuento > 0).
    public const DESCUENTO_MOTIVOS = [
        'cliente_grande' => 'Cliente grande',
        'negociacion' => 'Negociación con el cliente',
        'demora' => 'Demora en la reparación',
    ];

    // El pluralizador ingles haria 'orden_servicios'; fijamos la tabla correcta.
    protected $table = 'ordenes_servicio';

    protected $fillable = [
        'codigo',
        'cliente_id',
        'cliente_nombre',
        'cliente_rut',
        'cliente_telefono',
        'cliente_email',
        'producto_id',
        'sucursal_id',
        'ruta',
        'lote_id',
        'fecha_ingreso',
        'tipo_equipo',
        'modelo',
        'numero_serie',
        'falla_reportada',
        'falla_tecnico',
        'causa_falla',
        'categoria',
        'estado',
        'facturacion',
        'garantia_doc_tipo',
        'garantia_doc_numero',
        'garantia_doc_fecha',
        'observaciones',
        'fecha_entrega',
        // Etapa de taller (tecnico).
        'trabajo_realizado',
        'mano_obra',
        'descuento_pct',
        'descuento_motivo',
        'fecha_aviso',
        'fecha_retiro',
        'fuente',
        'confirmada_at',
        'recibida_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'date',
            'fecha_entrega' => 'date',
            'garantia_doc_fecha' => 'date',
            'fecha_aviso' => 'date',
            'fecha_retiro' => 'date',
            'confirmada_at' => 'datetime',
            'mano_obra' => 'integer',
            'descuento_pct' => 'integer',
        ];
    }

    /**
     * Fecha en que vence la garantia: 6 meses desde la compra. Null si no hay
     * documento de compra cargado.
     */
    public function getGarantiaVenceAttribute(): ?Carbon
    {
        return $this->garantia_doc_fecha?->copy()->addMonths(self::GARANTIA_MESES);
    }

    /**
     * Garantia vigente al momento de ingresar el equipo al taller: la compra
     * esta dentro de la ventana de 6 meses respecto de la fecha de ingreso.
     */
    public function getGarantiaVigenteAttribute(): bool
    {
        if ($this->facturacion !== 'garantia' || ! $this->garantia_doc_fecha || ! $this->fecha_ingreso) {
            return false;
        }

        return $this->garantia_vence->gte($this->fecha_ingreso);
    }

    /**
     * Condicion efectiva para mostrar y cobrar: es garantia SOLO si esta vigente;
     * si la garantia vencio o no tiene documento de respaldo, es reparacion (se
     * cobra). Evita mostrar "garantia vencida" como si fuera garantia.
     */
    public function getCondicionEfectivaAttribute(): string
    {
        return ($this->facturacion === 'garantia' && $this->garantia_vigente) ? 'garantia' : 'reparacion';
    }

    /**
     * Variante de color del badge segun el estado actual.
     */
    public function getEstadoVarianteAttribute(): string
    {
        return self::ESTADO_VARIANTES[$this->estado] ?? 'brand';
    }

    /**
     * Etiqueta visible del tipo de equipo (ej. 'bomba' -> "Bomba de agua").
     * Fallback a ucfirst para tipos historicos que no esten en el mapa.
     */
    public static function etiquetaTipo(?string $tipo): string
    {
        if ($tipo === null || $tipo === '') {
            return '';
        }

        return self::TIPO_ETIQUETAS[$tipo] ?? ucfirst($tipo);
    }

    public function getTipoEquipoLabelAttribute(): string
    {
        return self::etiquetaTipo($this->tipo_equipo);
    }

    /**
     * ¿Es una máquina PROPIA de la empresa (IMP. DALI / IMPORTADORA DALI)?
     * Se detecta por el nombre del "cliente" (ignora puntos, espacios y mayús/minús).
     * Cuando es propia: RUT/teléfono/correo dejan de ser obligatorios y se habilita
     * la categoría de cierre (primera/segunda/desarme) para reventa.
     */
    public static function esMaquinaPropia(?string $nombre): bool
    {
        // Normaliza puntos y comas a espacios y colapsa: "IMP. DALI", "IMP DALI",
        // "IMP.DALI", "IMP, DALI", "IMPORTADORA DALI" y "DALI" son la misma empresa.
        $n = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(['.', ','], ' ', (string) $nombre))));

        return in_array($n, ['IMP DALI', 'IMPORTADORA DALI', 'DALI'], true);
    }

    public function getEsPropiaAttribute(): bool
    {
        return self::esMaquinaPropia($this->cliente_nombre);
    }

    public function getCategoriaLabelAttribute(): ?string
    {
        return $this->categoria ? (self::CATEGORIA_ETIQUETAS[$this->categoria] ?? ucfirst($this->categoria)) : null;
    }

    /**
     * Etiqueta visible de la causa de la falla. Null = "Sin determinar" (el
     * tecnico aun no la diagnostico o no aplica).
     */
    public function getCausaFallaLabelAttribute(): string
    {
        return self::CAUSA_FALLA_ETIQUETAS[$this->causa_falla] ?? 'Sin determinar';
    }

    /**
     * Costo de los repuestos: suma de cantidad x precio de cada uno.
     */
    public function getCostoRepuestosAttribute(): int
    {
        return (int) $this->repuestos->sum(fn (OrdenServicioRepuesto $r) => $r->subtotal);
    }

    /**
     * Costo bruto (antes de descuento): repuestos + mano de obra.
     */
    public function getCostoBrutoAttribute(): int
    {
        return $this->costo_repuestos + (int) ($this->mano_obra ?? 0);
    }

    /**
     * Monto del descuento en pesos (porcentaje sobre el costo bruto).
     */
    public function getDescuentoMontoAttribute(): int
    {
        return (int) round($this->costo_bruto * ((int) ($this->descuento_pct ?? 0)) / 100);
    }

    /**
     * Costo total a pagar: bruto menos el descuento. Solo tiene sentido cobrar
     * cuando la condicion es reparacion (garantia no cobra).
     */
    public function getCostoTotalAttribute(): int
    {
        return $this->costo_bruto - $this->descuento_monto;
    }

    /**
     * Etiqueta visible del motivo del descuento (null si no hay descuento).
     */
    public function getDescuentoMotivoLabelAttribute(): ?string
    {
        return $this->descuento_motivo
            ? (self::DESCUENTO_MOTIVOS[$this->descuento_motivo] ?? $this->descuento_motivo)
            : null;
    }

    /**
     * Repuestos usados en la reparacion.
     *
     * @return HasMany<OrdenServicioRepuesto>
     */
    public function repuestos(): HasMany
    {
        return $this->hasMany(OrdenServicioRepuesto::class, 'orden_servicio_id');
    }

    /**
     * Fotos de respaldo del estado fisico del equipo al ingresarlo.
     *
     * @return HasMany<OrdenServicioFoto>
     */
    public function fotos(): HasMany
    {
        return $this->hasMany(OrdenServicioFoto::class, 'orden_servicio_id');
    }

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Producto Dali del catalogo (el "codigo" del equipo, por SKU).
     *
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * Folio visible = codigo unico impredecible (ST-XXXXXXXX). Se reemplazo el
     * correlativo #000123 porque era enumerable (un cliente podia espiar ordenes
     * ajenas). El fallback al id con ceros es solo defensivo por si alguna fila
     * historica quedara sin codigo.
     */
    public function getFolioAttribute(): string
    {
        return $this->codigo ?: '#'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    // Origen del ingreso. 'mostrador' (staff en persona, no requiere confirmar),
    // 'qr' (cliente por QR) y 'ruta' (conductor retira en ruta, lote). Las dos
    // ultimas llegan fisicamente despues -> se confirman en Mirador.
    public const FUENTE_RUTA = 'ruta';

    public const FUENTES_POR_CONFIRMAR = ['qr', 'ruta'];

    /**
     * Llego por QR o por lote en ruta y el encargado todavia no la confirmo (no
     * recibio la maquina fisica). Estas aparecen en el bloque "Por confirmar".
     */
    public function getPorConfirmarAttribute(): bool
    {
        return in_array($this->fuente, self::FUENTES_POR_CONFIRMAR, true)
            && $this->confirmada_at === null;
    }

    /**
     * Ordenes ingresadas por QR o por lote en ruta que aun esperan confirmacion.
     *
     * @param  Builder<OrdenServicio>  $query
     */
    public function scopePorConfirmar($query)
    {
        return $query->whereIn('fuente', self::FUENTES_POR_CONFIRMAR)->whereNull('confirmada_at');
    }

    /** @return BelongsTo<LoteServicio, $this> */
    public function lote(): BelongsTo
    {
        return $this->belongsTo(LoteServicio::class, 'lote_id');
    }

    // Estados "activos" para el contador de la barra: TODO lo que sigue en el
    // taller, desde que se recibe hasta antes de cerrarse. Deja fuera solo los
    // dos estados terminales (entregado, sin_solucion).
    public const ESTADOS_PENDIENTES_TECNICO = ['recibido', 'en_revision', 'cotizacion', 'esperando_repuesto', 'reparado'];

    /**
     * Ordenes activas (aun en el taller): cualquier estado salvo entregado /
     * sin_solucion. Es el numero del contador de la barra.
     *
     * @param  Builder<OrdenServicio>  $query
     */
    public function scopePendientesTecnico($query)
    {
        return $query->whereIn('estado', self::ESTADOS_PENDIENTES_TECNICO);
    }
}
