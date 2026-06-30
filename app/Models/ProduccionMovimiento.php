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
    public const TIPO_PRODUCCION_PRIMERA = 'produccion_primera';
    public const TIPO_PRODUCCION_SEGUNDA = 'produccion_segunda';
    public const TIPO_MERMA = 'merma';

    public const TIPOS = [
        self::TIPO_CONSUMO_PREFORMA,
        self::TIPO_PRODUCCION_PRIMERA,
        self::TIPO_PRODUCCION_SEGUNDA,
        self::TIPO_MERMA,
    ];

    public const ETIQUETAS = [
        self::TIPO_CONSUMO_PREFORMA => 'Consumo de preforma',
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
     * Genera el kardex de un reporte aprobado. Idempotencia y transaccion son
     * responsabilidad del llamador (ProduccionController::aprobar). Reglas:
     *  - consumo_preforma = total contado (cada unidad contada consumio una
     *    preforma), contra la preforma de la asignacion.
     *  - por cada tanda: produccion_primera, produccion_segunda y merma
     *    (malo + danada) contra el producto del tipo de botellon.
     *  - solo se crean movimientos con cantidad > 0.
     */
    public static function generarParaReporte(ProduccionReporte $reporte): void
    {
        $reporte->loadMissing(['asignacion', 'registros.tipoBotellon']);

        $fecha = $reporte->fecha->toDateString();

        // 1) Consumo de preforma = suma de las TANDAS (1a + 2a + malo + danada),
        // NO el total denormalizado del reporte. Asi el kardex queda internamente
        // consistente (consumo == produccion + merma) aunque el admin haya editado
        // los totales del reporte via ajustar() sin recalcular las tandas: el
        // kardex es la verdad fisica de lo soplado; el ajuste del jefe es una capa
        // de reporte (queda marcado con motivo_ajuste). Ver bitacora M-1.
        $total = (int) $reporte->registros->sum(
            fn ($r) => (int) $r->primera + (int) $r->segunda + (int) $r->malo + (int) $r->danada
        );
        if ($total > 0) {
            static::registrar($reporte, $reporte->asignacion?->preforma_id, self::TIPO_CONSUMO_PREFORMA, $total, $fecha);
        }

        // 2) Por tanda: produccion 1a/2a y merma, contra el producto del tipo.
        foreach ($reporte->registros as $registro) {
            $productoId = $registro->tipoBotellon?->producto_id;

            static::registrar($reporte, $productoId, self::TIPO_PRODUCCION_PRIMERA, (int) $registro->primera, $fecha);
            static::registrar($reporte, $productoId, self::TIPO_PRODUCCION_SEGUNDA, (int) $registro->segunda, $fecha);
            static::registrar($reporte, $productoId, self::TIPO_MERMA, (int) $registro->malo + (int) $registro->danada, $fecha);
        }
    }

    private static function registrar(ProduccionReporte $reporte, ?int $productoId, string $tipo, int $cantidad, string $fecha): void
    {
        if ($cantidad <= 0) {
            return;
        }

        static::create([
            'reporte_id' => $reporte->id,
            'producto_id' => $productoId,
            'tipo' => $tipo,
            'cantidad' => $cantidad,
            'fecha' => $fecha,
        ]);
    }
}
