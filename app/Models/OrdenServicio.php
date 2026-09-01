<?php

namespace App\Models;

use Database\Factories\OrdenServicioFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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

        // Cualquier alta/baja/cambio invalida los conteos cacheados del historial
        // (§ver versionHistorial). Es un contador, no un borrado por clave: no hay
        // que saber QUE claves existen, y con el driver `database` un increment es
        // una sola escritura por llave primaria.
        static::saved(fn () => self::invalidarHistorial());
        static::deleted(fn () => self::invalidarHistorial());
    }

    /** Clave de version de los conteos del historial (año/mes) del listado. */
    private const CACHE_VERSION_HISTORIAL = 'dg.st.historial.v';

    /**
     * Version actual de los conteos del historial. Se usa DENTRO de la clave de
     * cache, asi que al subirla las entradas viejas quedan huerfanas y expiran
     * solas — no hace falta enumerarlas para borrarlas.
     */
    public static function versionHistorial(): int
    {
        return (int) Cache::get(self::CACHE_VERSION_HISTORIAL, 1);
    }

    /** Sube la version: el proximo listado recalcula los conteos. */
    public static function invalidarHistorial(): void
    {
        // `increment` de un valor ausente no hace nada en varios drivers, asi que
        // se siembra antes. add() es atomico: si otro proceso ya lo creo, no pisa.
        Cache::add(self::CACHE_VERSION_HISTORIAL, 1);
        Cache::increment(self::CACHE_VERSION_HISTORIAL);
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
    // del arreglo (va despues de la revision, antes de reparar).
    //
    // NO existe un estado de espera de repuesto (regla del dueño, 07-08-2026): el
    // taller no es bodega de acopio y un repuesto importado puede tardar hasta un
    // año, asi que la maquina no se queda esperando. El tecnico define EN EL
    // MOMENTO, contra el stock que hay: si el repuesto esta, se repara; si no
    // esta, se le dice al cliente (sin_solucion). Ver la migracion
    // 2026_08_07_120000_quita_estado_esperando_repuesto_de_ordenes_servicio.
    public const ESTADOS = ['recibido', 'en_revision', 'cotizacion', 'reparado', 'entregado', 'sin_solucion'];

    // Color del badge por etapa (variantes de x-badge), para leer el estado de un vistazo.
    public const ESTADO_VARIANTES = [
        'recibido' => 'brand',
        'en_revision' => 'info',
        'cotizacion' => 'warning',
        'reparado' => 'success',
        'entregado' => 'neutral',
        'sin_solucion' => 'danger',
    ];

    // Condicion del ingreso. Garantia: no se cobra (si esta vigente).
    // Reparacion: se cobra al cliente.
    public const FACTURACION = ['garantia', 'reparacion'];

    // Etiqueta visible de la condicion. Existe porque las cuatro pantallas que
    // ofrecen este selector (QR por unidad, QR por cantidad, mostrador y lote del
    // conductor) rotulaban con `ucfirst($f)` -> el CLIENTE leia "Garantia" y
    // "Reparacion" SIN TILDE. La clave guardada sigue sin tildes (es el valor de
    // la columna); la tilde vive solo aca, en el rotulo. Mismo patron que
    // TIPO_ETIQUETAS: fuente unica para que el rotulo no se escriba a mano.
    public const FACTURACION_ETIQUETAS = [
        'garantia' => 'Garantía',
        'reparacion' => 'Reparación',
    ];

    // Documento de compra que respalda la garantia.
    public const GARANTIA_DOC_TIPOS = ['factura', 'boleta'];

    // Duracion de la garantia DEL PRODUCTO, desde la fecha de compra.
    public const GARANTIA_MESES = 6;

    /**
     * Duracion de la garantia DE LA REPARACION, desde el dia en que se repara (dueño,
     * 14-08-2026: «a partir de la fecha de reparacion del dispensador entra en vigencia la
     * garantia por tres meses»).
     *
     * SON DOS GARANTIAS DISTINTAS y por eso son dos constantes:
     *   · GARANTIA_MESES (6)             → el PRODUCTO, contra la fecha de COMPRA. Es la que
     *                                      decide si un ingreso al taller se cobra o no.
     *   · GARANTIA_REPARACION_MESES (3)  → el TRABAJO que hizo el taller, contra la fecha de
     *                                      REPARACION. Es la que se le promete al cliente al
     *                                      entregarle el equipo.
     *
     * Reusar la de 6 para esto habria prometido el doble de cobertura sobre una reparacion, en
     * un correo que el cliente guarda.
     */
    public const GARANTIA_REPARACION_MESES = 3;

    /*
     * ACÁ VIVÍAN `TRABAJO_OTRO` y `TRABAJO_MAX`, y las dos murieron el 01-09-2026 con el mismo
     * cambio: el técnico dejó de escribir la frase del cliente (dueño, con el gerente al lado).
     *
     * · `TRABAJO_OTRO` era el centinela del «Otro — lo escribo yo» del selector. Ya no hay
     *   selector ni texto propio: se marcan trabajos y la frase se arma sola.
     * · `TRABAJO_MAX` (500) era el largo máximo del texto, copiado del VARCHAR donde la
     *   cotización guarda su snapshot. Al no haber campo que escribir, no hay dónde aplicar un
     *   `max:`, y el largo pasó a depender de cuántos trabajos se marquen — 21 dan 793
     *   caracteres. Por eso esa columna pasó a TEXT (migración 2026_09_01_120000) en vez de
     *   subirle el techo por segunda vez en cuatro días.
     */

    // Los precios del catálogo (repuestos, valor hora) se guardan CON IVA, así
    // que el total a pagar ya lo incluye. Para desglosarlo (neto + IVA = total).
    public const TASA_IVA = 0.19;

    // Umbral de "reparación cara": si el costo supera este % del precio de venta
    // del equipo, se advierte (como la pérdida total de los autos). No bloquea:
    // avisa para consultar al cliente si conviene repararlo.
    public const UMBRAL_REPARACION_ALTA = 0.40;

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
        'traslado_id',
        'traslado_recibida_at',
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
        'trabajos_extra',
        'mano_obra',
        'descuento_pct',
        'descuento_motivo',
        'fecha_aviso',
        'fecha_retiro',
        // El técnico le avisó al cliente que su equipo está listo (dueño 07-08).
        'listo_avisado_at',
        'listo_avisado_por',
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
            'listo_avisado_at' => 'datetime',
            'confirmada_at' => 'datetime',
            'traslado_recibida_at' => 'datetime',
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
     * Desde cuándo corre la garantía de LA REPARACIÓN.
     *
     * Es el día en que el taller dio el trabajo por terminado, que es cuando se le avisa al
     * cliente (`listo_avisado_at`). Si todavía no se avisó —el correo se manda ANTES de
     * estampar ese campo— corre desde hoy, que es el mismo día.
     */
    public function garantiaReparacionDesde(): Carbon
    {
        return $this->listo_avisado_at?->copy() ?? Carbon::now();
    }

    /** Cuándo vence la garantía de la reparación (3 meses; ver `GARANTIA_REPARACION_MESES`). */
    public function garantiaReparacionVence(): Carbon
    {
        return $this->garantiaReparacionDesde()->addMonths(self::GARANTIA_REPARACION_MESES);
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
     * Etiqueta visible de la condicion ('garantia' -> "Garantía"), con tilde.
     * Fallback a ucfirst por si aparece una condicion historica fuera del mapa.
     */
    public static function etiquetaFacturacion(?string $facturacion): string
    {
        if ($facturacion === null || $facturacion === '') {
            return '';
        }

        return self::FACTURACION_ETIQUETAS[$facturacion] ?? ucfirst($facturacion);
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
     * Neto del total a pagar. El total YA viene con IVA (precios del catálogo con
     * IVA), así que el neto se obtiene dividiendo por (1 + IVA).
     */
    public function getCostoNetoAttribute(): int
    {
        return (int) round($this->costo_total / (1 + self::TASA_IVA));
    }

    /** IVA contenido en el total (total − neto): así neto + IVA == total exacto. */
    public function getCostoIvaAttribute(): int
    {
        return $this->costo_total - $this->costo_neto;
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
    /**
     * Los trabajos del catálogo que el técnico MARCÓ en el parte. Es la fuente de la mano de
     * obra desde el 28-08-2026 (antes colgaba de que el TEXTO coincidiera palabra por palabra
     * con una fila del catálogo, así que una reparación mixta no podía coincidir con nada y
     * ajustarle una coma a una respuesta borraba el dinero).
     *
     * El pivote lleva `horas` CONGELADAS al guardar el parte y la mano de obra se calcula con
     * esas, no releyendo el catálogo: jefatura calibra las horas con el tiempo, y una orden ya
     * cotizada no puede cambiar de precio sola después — el snapshot de la cotización le
     * prometió un monto al cliente. Mismo criterio que `orden_servicio_repuestos.precio_unitario`.
     */
    public function trabajos(): BelongsToMany
    {
        return $this->belongsToMany(TiempoReparacion::class, 'orden_servicio_trabajos',
            'orden_servicio_id', 'tiempo_reparacion_id')
            ->withPivot('horas')
            ->withTimestamps()
            ->orderBy('tiempos_reparacion.grupo')
            ->orderBy('tiempos_reparacion.trabajo');
    }

    /**
     * Las horas de este parte, ya con el tope aplicado. Sale del PIVOTE (horas congeladas) y no
     * del catálogo vigente, por lo dicho arriba.
     */
    public function horasACobrar(): float
    {
        return TiempoReparacion::horasACobrar($this->trabajos->pluck('pivot.horas'));
    }

    /**
     * LA FRASE QUE LEE EL CLIENTE, armada con lo que el técnico MARCÓ. Desde el 01-09-2026 es lo
     * único que existe: el técnico ya no escribe (dueño, con el gerente al lado: «no quiero que
     * escriban por mala ortografía y agregar más información de la que no es necesaria»).
     *
     * SE ARMA ACÁ, EN EL SERVIDOR, y no se recibe del formulario. Es la diferencia entre quitar
     * el campo de la pantalla y quitar la capacidad de escribir: mientras el texto viaje en el
     * POST, cualquiera puede mandar lo que quiera y la ortografía vuelve por la puerta de atrás.
     * El formulario ya no manda `trabajo_realizado` y el JS solo dibuja una vista previa; la
     * frase que se guarda sale siempre de acá.
     *
     * El espejo en JS (`textoCliente` de `reparacionForm`, en resources/js/app.js) tiene que dar
     * el MISMO resultado o la pantalla prometería una frase distinta de la que se guarda —
     * misma convención que `TiempoReparacion::horasACobrar()`. Candado:
     * TrabajoArmadoTest::test_el_espejo_en_js_arma_la_misma_frase_que_el_servidor.
     *
     * @param  iterable<string>  $trabajos  los trabajos marcados, SIN su remate
     */
    public static function fraseDeTrabajos(iterable $trabajos, ?string $remate = null): string
    {
        $partes = collect($trabajos)->map(fn ($t) => trim((string) $t))->filter()->values();

        if ($partes->isEmpty()) {
            return '';
        }

        $frase = $partes->count() === 1
            ? $partes->first()
            : $partes->slice(0, -1)->implode(', ').' y '.$partes->last();

        // Solo la primera letra en mayúscula: el catálogo trae los trabajos capitalizados
        // («Cambio de caldera») y encadenados quedarían «Cambio de llave, Cambio de caldera».
        $frase = mb_strtoupper(mb_substr($frase, 0, 1)).mb_substr($frase, 1);
        $frase = preg_replace_callback(
            '/(, | y )(\p{Lu})/u',
            fn ($m) => $m[1].mb_strtolower($m[2]),
            $frase
        );

        return filled($remate) ? $frase.' — '.trim($remate) : $frase;
    }

    /** Lo escrito a mano, una línea por trabajo (el técnico las separa con salto de línea o coma). */
    public function trabajosExtraLista(): array
    {
        return collect(preg_split('/[\r\n]+/u', (string) $this->trabajos_extra))
            ->map(fn ($l) => trim($l))
            ->filter()
            ->values()
            ->all();
    }

    public function repuestos(): HasMany
    {
        return $this->hasMany(OrdenServicioRepuesto::class, 'orden_servicio_id');
    }

    /**
     * Documentos tributarios emitidos a partir de esta orden (M05).
     *
     * Es HasMany y no HasOne aunque lo normal sea uno: una orden puede terminar
     * con una boleta y despues su nota de credito, y los DTE no se borran.
     *
     * @return HasMany<DteEmitido, $this>
     */
    public function dtesEmitidos(): HasMany
    {
        return $this->hasMany(DteEmitido::class, 'orden_servicio_id');
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
     * Cotizaciones enviadas al cliente (snapshots; la mas reciente manda).
     *
     * @return HasMany<OrdenServicioCotizacion>
     */
    public function cotizaciones(): HasMany
    {
        return $this->hasMany(OrdenServicioCotizacion::class, 'orden_servicio_id');
    }

    /** La cotizacion mas reciente (o null si nunca se ha enviado una). */
    public function getUltimaCotizacionAttribute(): ?OrdenServicioCotizacion
    {
        return $this->cotizaciones()->latest('id')->first();
    }

    /**
     * Quien le aviso al cliente que su equipo estaba listo para retirar.
     *
     * @return BelongsTo<User, $this>
     */
    public function listoAvisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'listo_avisado_por');
    }

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Acota la consulta a lo que un usuario PUEDE VER en Servicio Técnico
     * (regla #2 del negocio: la gestión es por vendedor). Quien tiene el permiso
     * 'ver todo servicio tecnico' (técnico, jefe de ventas, jefe de bodega,
     * admin) ve todas las órdenes. El resto (vendedor / jefatura) solo ve las de
     * SU cartera: órdenes cuyo cliente (por RUT) está asignado a él o a un
     * vendedor a su cargo. El enlace es por `cliente_rut` porque `cliente_id` no
     * se popula al ingresar (mostrador ni QR); ambos RUT están normalizados
     * (Cliente::normalizarRut).
     *
     * Y lo que NADIE tiene asignado es de SALA DE VENTAS: ver el encabezado de
     * `esVisiblePara`, que es la misma regla. OJO: la regla vive DOS veces —acá en
     * SQL para el listado y allá en PHP para la ficha— y si divergen la ficha deja
     * entrar a algo que el listado esconde (o al revés). `ServicioTecnicoVisibilidadTest`
     * las compara caso por caso justamente por eso.
     */
    public function scopeVisiblePara(Builder $query, User $user): Builder
    {
        if ($user->can('ver todo servicio tecnico')) {
            return $query;
        }

        $vendedorIds = $user->idsCarteraServicioTecnico();

        return $query->where(function (Builder $q) use ($vendedorIds) {
            // Las de MI cartera…
            $q->whereIn('cliente_rut', function ($sub) use ($vendedorIds) {
                $sub->select('rut')
                    ->from('clientes')
                    ->whereIn('vendedor_id', $vendedorIds)
                    ->whereNotNull('rut');
            })
                // …más las que nadie tiene asignadas (sala de ventas). La orden sin
                // RUT entra acá: no hay ficha de cliente que se pueda asignar.
                ->orWhereNull('cliente_rut')
                ->orWhereNotIn('cliente_rut', function ($sub) {
                    $sub->select('rut')
                        ->from('clientes')
                        ->whereNotNull('vendedor_id')
                        ->whereNotNull('rut');
                });
        });
    }

    /**
     * ¿Este usuario puede ver esta orden? Misma regla que scopeVisiblePara, para
     * proteger las páginas de detalle por URL (show/foto/comprobante).
     *
     * Regla del dueño (07-08-2026): si el cliente tiene vendedor asignado, la orden
     * es de ESE vendedor (y de jefatura, que ve todo). Si NO lo tiene, el cliente es
     * de SALA DE VENTAS —donde se atiende a todo público—, y ellas la monitorean
     * hasta que se le asigne un vendedor o quede fijo en sala; en el sistema eso son
     * todos los `vendedor`, porque sala de ventas no es un rol aparte.
     *
     * Por qué el respaldo tiene que estar ACÁ y no solo en el reparto de avisos:
     * este método gobierna la ficha (`abort_unless(..., 403)`), las fotos, el
     * comprobante Y el link de la campanita (`Notificacion::urlDestinoPara` no
     * enlaza lo que el usuario no puede abrir). Avisarle a alguien que después no
     * puede entrar es un aviso muerto — el defecto que ya se corrigió una vez.
     *
     * Consecuencia HOY: el sync de Bsale no llena `clientes.vendedor_id`, así que
     * TODO cliente cae en sala de ventas y todo vendedor ve todo. El día que se
     * carguen las carteras cada orden se acota sola, sin tocar código.
     */
    public function esVisiblePara(User $user): bool
    {
        if ($user->can('ver todo servicio tecnico')) {
            return true;
        }

        // Cliente que nadie tiene asignado (o orden sin RUT) → sala de ventas.
        if (! $this->clienteTieneVendedor()) {
            return true;
        }

        return Cliente::whereIn('vendedor_id', $user->idsCarteraServicioTecnico())
            ->where('rut', $this->cliente_rut)
            ->exists();
    }

    /** ¿El cliente de esta orden ya tiene un vendedor asignado en su ficha? */
    public function clienteTieneVendedor(): bool
    {
        return filled($this->cliente_rut)
            && Cliente::where('rut', $this->cliente_rut)->whereNotNull('vendedor_id')->exists();
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

    // Quién recibe cada aviso vive en AudienciasNotificacion (editable por el
    // dueño en Configuración → Avisos): ahí quedaron los porqués de cada lista.

    /**
     * Avisa por M15 (campanita + correo según preferencias) a ventas y al técnico
     * que entró un equipo al taller (ingreso por QR, unidad). Secundario: el
     * emisor (controlador público) lo envuelve en try/catch para no tumbar el
     * ingreso si el aviso falla.
     */
    public function notificarIngresoInterno(): void
    {
        $equipo = collect([
            $this->tipo_equipo_label,
            $this->producto?->sku,
            $this->numero_serie ? 'N° '.$this->numero_serie : null,
        ])->filter()->implode(' · ');

        $datos = [
            // El folio es el dato con el que se busca la orden: sin el, el aviso
            // obligaba a buscar por nombre de cliente.
            'folio' => $this->folio,
            'cliente' => $this->cliente_nombre,
            'equipo' => $equipo !== '' ? $equipo : $this->tipo_equipo_label,
            'maquinas' => '1 equipo',
            'sucursal' => $this->sucursal?->nombre ?: ($this->ruta ? 'Ruta · '.$this->ruta : '—'),
            'condicion' => $this->condicion_efectiva === 'garantia' ? 'Garantía' : 'Reparación',
            // La ficha de la orden, no el listado: este aviso es de UNA orden y su
            // boton de confirmar esta en la ficha.
            'url' => route('admin.servicio-tecnico.show', $this),
        ];

        $dispatcher = app(\App\Services\Notificaciones\NotificacionDispatcher::class);

        \App\Support\AudienciasNotificacion::destinatarios('taller.ingresado')
            ->each(fn (User $u) => $dispatcher->despachar('taller.ingresado', $this, $u, $datos));
    }

    /**
     * Avisa a VENTAS por M15 (campanita + correo segun preferencias) que la orden
     * se cerro. Punto UNICO de los dos cierres —`taller.reparado` y
     * `taller.sin_solucion`— porque comparten destinatarios, datos y regla de
     * reparto: duplicarla era garantizar que un dia divergieran.
     *
     * El reparto lo define `esVisiblePara()`, el MISMO filtro que gobierna el
     * listado y la ficha —asi el aviso nunca llega a quien despues no puede
     * abrirlo—, y de ahi salen solas las tres mitades de la regla: jefatura tiene
     * 'ver todo servicio tecnico' y recibe TODAS; un vendedor recibe las de SU
     * cartera (`clientes.vendedor_id`, mas la de su equipo via `users.jefe_id`); y
     * lo que nadie tiene asignado cae en SALA DE VENTAS (dueño 07-08).
     *
     * Antes de ese respaldo estos avisos no le llegaban a NINGUN vendedor, porque
     * no hay carteras cargadas y el filtro se construyo antes que las carteras.
     *
     * Notificar a alguien que despues no puede ABRIR la orden seria el defecto que
     * ya se corrigio en la campanita (`Notificacion::urlDestinoPara`): un aviso que
     * termina en 403.
     *
     * @param  User|null  $actor  quien cerro la orden (no se autonotifica)
     * @param  array<string, mixed>  $extra  placeholders propios del evento
     */
    private function avisarACartera(string $evento, ?User $actor, array $extra = []): void
    {
        $equipo = collect([
            $this->tipo_equipo_label,
            $this->modelo,
            $this->numero_serie ? 'N° '.$this->numero_serie : null,
        ])->filter()->implode(' · ');

        $datos = array_merge([
            'folio' => $this->folio,
            'cliente' => $this->cliente_nombre,
            'equipo' => $equipo !== '' ? $equipo : $this->tipo_equipo_label,
            // Se rellenan SIEMPRE: un placeholder sin dato queda CRUDO en el texto
            // ({trabajo} literal en la campanita).
            'trabajo' => filled($this->trabajo_realizado) ? $this->trabajo_realizado : 'Sin detalle',
            'diagnostico' => filled($this->causa_falla) ? $this->causa_falla_label : 'Sin determinar',
            'tecnico' => $actor?->name ?: 'El técnico',
            'retiro' => $this->sucursal?->nombre ?: ($this->ruta ? 'Ruta · '.$this->ruta : '—'),
            'telefono' => filled($this->cliente_telefono) ? $this->cliente_telefono : 'sin teléfono registrado',
            'url' => route('admin.servicio-tecnico.show', $this),
        ], $extra);

        $dispatcher = app(\App\Services\Notificaciones\NotificacionDispatcher::class);

        \App\Support\AudienciasNotificacion::destinatarios($evento)
            ->reject(fn (User $u) => $actor && $u->id === $actor->id)
            ->filter(fn (User $u) => $this->esVisiblePara($u))
            ->each(fn (User $u) => $dispatcher->despachar($evento, $this, $u, $datos));
    }

    /** El equipo quedo listo: ventas le dice al cliente que puede retirarlo. */
    public function notificarReparado(?User $actor = null): void
    {
        $this->avisarACartera('taller.reparado', $actor);
    }

    /**
     * No tuvo arreglo. Ventas necesita el aviso igual que en «reparado» —el
     * equipo hay que retirarlo—, pero la conversacion es otra: presupuesto de
     * reemplazo, o garantia si la causa fue falla de fabrica. Por eso el aviso
     * interno SI lleva el diagnostico del tecnico, y el correo al cliente no
     * (ver `SinSolucionCliente`).
     *
     * @param  bool|null  $avisadoAlCliente  ¿salio el correo al cliente? true/false
     *   se reflejan TAL CUAL en el aviso interno. Nunca se afirma a ciegas que al
     *   cliente se le aviso: sin correo en la ficha, o con el SMTP caido, el aviso
     *   diria «ya se le aviso» y NADIE lo llamaria (mismo defecto que se corrigio
     *   en el rechazo de terreno el 30-07). null = no se intento.
     */
    public function notificarSinSolucion(?User $actor = null, ?bool $avisadoAlCliente = null): void
    {
        $telefono = filled($this->cliente_telefono) ? $this->cliente_telefono : 'sin teléfono registrado';

        $this->avisarACartera('taller.sin_solucion', $actor, [
            'aviso_cliente' => match ($avisadoAlCliente) {
                true => "Al cliente ya se le avisó por correo. Falta coordinar el retiro y, si corresponde, el reemplazo ({$telefono}).",
                false => "NO se pudo avisar al cliente por correo: hay que llamarlo ({$telefono}).",
                null => "Falta avisarle al cliente ({$telefono}).",
            },
        ]);
    }

    /**
     * Traslado en el que esta maquina viajo a la casa matriz (null si se recibio
     * directamente ahi, o si es anterior al registro de traslados).
     */
    public function traslado(): BelongsTo
    {
        return $this->belongsTo(TrasladoServicio::class, 'traslado_id');
    }

    /**
     * ¿Esta maquina esta en una sucursal que NO repara? Es la que tiene que
     * viajar: la casa matriz (es_central) es donde se repara; Abate y Coquimbo
     * reciben pero no reparan.
     */
    public function getDebeViajarAttribute(): bool
    {
        return $this->sucursal !== null && ! $this->sucursal->es_central;
    }

    /**
     * ¿Todavia NO esta en el taller? Regla del dueño (03-08-2026): una maquina no
     * se puede reparar si no fue recepcionada en la matriz. Cubre los dos casos
     * que antes eran invisibles: sigue en la sucursal (sin traslado) o va en
     * camino (traslado sin confirmar).
     *
     * Las ordenes anteriores al registro llevan `traslado_recibida_at` sellado por
     * la migracion one-shot, asi que NO quedan bloqueadas.
     */
    public function getEnTransitoAttribute(): bool
    {
        return $this->debe_viajar && $this->traslado_recibida_at === null;
    }

    /** Motivo legible del bloqueo, para decirle al tecnico QUE falta. */
    public function getMotivoNoLlegoAttribute(): ?string
    {
        if (! $this->en_transito) {
            return null;
        }

        return $this->traslado_id
            ? 'Va en camino desde '.($this->sucursal?->nombre ?? 'la sucursal').' (traslado '.($this->traslado?->codigo ?? '').'): falta confirmar la recepción en el taller.'
            : 'Sigue en '.($this->sucursal?->nombre ?? 'la sucursal').': todavía no se despachó al taller.';
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
    public const ESTADOS_PENDIENTES_TECNICO = ['recibido', 'en_revision', 'cotizacion', 'reparado'];

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
