<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kardex local de produccion: el efecto de inventario de un reporte APROBADO.
 *
 * Se escribe SOLO al aprobar (regla #9 de la biblia) y NO toca las tablas
 * `stocks`/`bodegas` (espejo read-only de Bsale que la sync horaria pisa). Es
 * la verdad local de produccion, lista para empujarse a Bsale por API en una
 * fase futura (receptions/consumptions). El `producto_id` es nullable: si la
 * preforma/tipo aun no esta enlazado al catalogo, el movimiento igual cuenta
 * la cantidad por tipo (degradacion con gracia).
 */
class ProduccionMovimiento extends Model
{
    protected $table = 'produccion_movimientos';

    // Tipos de movimiento (constantes de clase, NO enum MySQL: MySQL 5.7-safe).
    public const TIPO_CONSUMO_PREFORMA = 'consumo_preforma';
    public const TIPO_CONSUMO_TAPA = 'consumo_tapa';
    public const TIPO_PRODUCCION_PRIMERA = 'produccion_primera';
    public const TIPO_PRODUCCION_SEGUNDA = 'produccion_segunda';
    public const TIPO_MERMA = 'merma';

    public const TIPOS = [
        self::TIPO_CONSUMO_PREFORMA,
        self::TIPO_CONSUMO_TAPA,
        self::TIPO_PRODUCCION_PRIMERA,
        self::TIPO_PRODUCCION_SEGUNDA,
        self::TIPO_MERMA,
    ];

    public const ETIQUETAS = [
        self::TIPO_CONSUMO_PREFORMA => 'Consumo de preforma',
        self::TIPO_CONSUMO_TAPA => 'Consumo de tapa',
        self::TIPO_PRODUCCION_PRIMERA => 'Producción 1ª',
        self::TIPO_PRODUCCION_SEGUNDA => 'Producción 2ª',
        self::TIPO_MERMA => 'Merma',
    ];

    protected $fillable = [
        'reporte_id',
        'producto_id',
        'tipo',
        'cantidad',
        'fecha',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'fecha' => 'date',
        ];
    }

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ProduccionReporte::class, 'reporte_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function etiquetaTipo(): string
    {
        return self::ETIQUETAS[$this->tipo] ?? $this->tipo;
    }

    /**
     * Calcula las lineas del kardex de un reporte SIN persistirlas: la fuente
     * UNICA que consumen el preview del reporte («al aprobar se registrara»)
     * y generarParaReporte() — asi el preview y el kardex real no pueden
     * divergir por construccion. Reglas (P-M11-10, PLAN-M11-FINAL F1):
     *
     *  - El consumo sale de la RECETA del producto del botellon: componentes
     *    = (buenos + merma) x cantidad — la merma TAMBIEN consumio preformas;
     *    descontar solo buenos infla el inventario teorico (benchmark M11).
     *  - Sin receta rige la receta IMPLICITA {preforma: 1} — el comportamiento
     *    historico EXACTO. Un solo camino de computo por tanda: el doble
     *    conteo es imposible por construccion (no hay rama legacy paralela).
     *  - El producto del consumo de preforma es la preforma REAL del turno
     *    (asignacion.preforma_id); la receta aporta solo la cantidad. La tapa
     *    va contra el componente de la receta (null = movimiento sin
     *    producto, degradacion con gracia).
     *  - La base es la suma de las TANDAS, jamas el total denormalizado del
     *    reporte (ajustable por el jefe sin recalcular tandas): el kardex es
     *    la verdad fisica de lo soplado. Ver bitacora M-1.
     *  - El redondeo es UNO por movimiento AGREGADO ((int) round(...)), nunca
     *    por tanda (no acumula error). La columna `cantidad` sigue integer a
     *    proposito: preformas y tapas son unidades fisicas; el decimal(14,4)
     *    de la receta es el parametro. No "arreglar" el (int) sin leer esto.
     *  - Por cada tanda: produccion 1a/2a y merma contra el producto del tipo
     *    (sin cambios respecto del kardex historico). Solo lineas > 0.
     *
     * @return array<int, array{tipo: string, producto_id: int|null, cantidad: int}>
     */
    public static function planParaReporte(ProduccionReporte $reporte): array
    {
        $reporte->loadMissing(['asignacion', 'registros.tipoBotellon']);

        $recetas = Receta::whereIn(
            'producto_id',
            $reporte->registros->map(fn ($r) => $r->tipoBotellon?->producto_id)->filter()->unique()->values(),
        )->get()->groupBy('producto_id');

        $preformas = 0.0;
        $tapas = [];    // componente_id (0 = sin enlazar) => unidades acumuladas

        foreach ($reporte->registros as $registro) {
            $unidades = (int) $registro->primera + (int) $registro->segunda + (int) $registro->malo + (int) $registro->danada;
            $porRol = $recetas->get($registro->tipoBotellon?->producto_id ?? 0, collect())->keyBy('rol');

            $preformas += $unidades * (float) ($porRol[Receta::ROL_PREFORMA]->cantidad ?? 1);

            if ($tapa = $porRol[Receta::ROL_TAPA] ?? null) {
                $clave = $tapa->componente_id ?? 0;
                $tapas[$clave] = ($tapas[$clave] ?? 0.0) + $unidades * (float) $tapa->cantidad;
            }
        }

        // La preforma fisica del turno manda; el componente de la receta es
        // solo el respaldo cuando la asignacion no la trae.
        $productoPreforma = $reporte->asignacion?->preforma_id
            ?? $recetas->collapse()->firstWhere('rol', Receta::ROL_PREFORMA)?->componente_id;

        $lineas = [];

        if ((int) round($preformas) > 0) {
            $lineas[] = ['tipo' => self::TIPO_CONSUMO_PREFORMA, 'producto_id' => $productoPreforma, 'cantidad' => (int) round($preformas)];
        }

        foreach ($tapas as $componenteId => $total) {
            if ((int) round($total) > 0) {
                $lineas[] = ['tipo' => self::TIPO_CONSUMO_TAPA, 'producto_id' => $componenteId ?: null, 'cantidad' => (int) round($total)];
            }
        }

        foreach ($reporte->registros as $registro) {
            $productoId = $registro->tipoBotellon?->producto_id;

            foreach ([
                [self::TIPO_PRODUCCION_PRIMERA, (int) $registro->primera],
                [self::TIPO_PRODUCCION_SEGUNDA, (int) $registro->segunda],
                [self::TIPO_MERMA, (int) $registro->malo + (int) $registro->danada],
            ] as [$tipo, $cantidad]) {
                if ($cantidad > 0) {
                    $lineas[] = ['tipo' => $tipo, 'producto_id' => $productoId, 'cantidad' => $cantidad];
                }
            }
        }

        return $lineas;
    }

    /**
     * Genera el kardex de un reporte aprobado: persiste planParaReporte().
     * Idempotencia y transaccion son responsabilidad del llamador
     * (ProduccionController::aprobar, con lock + guard movimientos()->exists()).
     */
    public static function generarParaReporte(ProduccionReporte $reporte): void
    {
        $fecha = $reporte->fecha->toDateString();

        foreach (static::planParaReporte($reporte) as $linea) {
            static::create($linea + ['reporte_id' => $reporte->id, 'fecha' => $fecha]);
        }
    }
}
