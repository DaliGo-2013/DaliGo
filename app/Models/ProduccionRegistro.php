<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tanda de produccion dentro de un reporte: maquina + tipo de botellon +
 * cantidades. Append-only: cada "Agregar" del soplador crea una fila nueva
 * (los timestamps por tanda alimentan futuras metricas de ritmo por maquina).
 * Sin auditoria: alto volumen y autoevidente; la traza de totales vive en
 * el audit del reporte.
 */
class ProduccionRegistro extends Model
{
    protected $table = 'produccion_registros';

    /**
     * Motivos de defecto por CALIDAD (MIPROD-1, pedido del dueño 21-08 con la
     * pantalla en mano — reemplaza a la antigua MOTIVOS_DEFECTO compartida):
     * una SEGUNDA es por definición un defecto estético (si fuera más grave,
     * sería mala), así que su lista nace con una sola opción; las MALAS
     * conservan el catálogo SIN «Scrap de arranque» (decisión informada del
     * dueño: el desglose de scrap del OEE pierde su fuente hacia adelante —
     * las tandas históricas lo conservan porque el motivo se PERSISTE por
     * fila). Ambas listas son editables en Configuración (una por línea);
     * estas constantes son el fallback con BD virgen.
     */
    public const MOTIVOS_SEGUNDA = [
        'Detalles estéticos',
    ];

    public const MOTIVOS_MALAS = [
        'Burbujas / aire',
        'Rebaba',
        'Cuello o rosca deforme',
        'Mal sellado',
        'Punto frío',
        'Contaminación / suciedad',
        'Material quemado',
        'Espesor irregular',
        'Rayas o marcas',
    ];

    /**
     * Listas vigentes (claves `produccion_motivos_segunda` /
     * `produccion_motivos_malas`). La lista sigue CERRADA para el operario
     * (Rule::in lee la vigente) — cambia quién la escribe.
     *
     * @return array<int, string>
     */
    public static function motivosSegunda(): array
    {
        return Configuracion::getLista('produccion_motivos_segunda', self::MOTIVOS_SEGUNDA);
    }

    /** @return array<int, string> */
    public static function motivosMalas(): array
    {
        return Configuracion::getLista('produccion_motivos_malas', self::MOTIVOS_MALAS);
    }

    protected $fillable = [
        'reporte_id',
        'cliente_uuid',
        'maquina_id',
        'tipo_botellon_id',
        'primera',
        'segunda',
        'motivo_segunda',
        'malo',
        'motivo_malo',
        'danada',
    ];

    protected function casts(): array
    {
        return [
            'primera' => 'integer',
            'segunda' => 'integer',
            'malo' => 'integer',
            'danada' => 'integer',
        ];
    }

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ProduccionReporte::class, 'reporte_id');
    }

    public function maquina(): BelongsTo
    {
        return $this->belongsTo(Maquina::class, 'maquina_id');
    }

    public function tipoBotellon(): BelongsTo
    {
        return $this->belongsTo(TipoBotellon::class, 'tipo_botellon_id');
    }
}
