<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Tipo de bulto del simulador de carga: lo que se sube al camión de una vez.
 *
 * NO es un producto. Los botellones viajan en bolsas de 5, así que la unidad de
 * carga es la bolsa; un mismo SKU puede viajar de varias formas y lo que importa
 * acá es la caja envolvente del bulto ARMADO, medida como viaja.
 *
 * Se audita: son datos que alimentan una promesa comercial ("le caben 140"), y si
 * alguien cambia una medida conviene saber quién y cuándo.
 */
class TipoBulto extends Model implements AuditableContract
{
    use Auditable, HasFactory;

    protected $table = 'tipos_bulto';

    protected $fillable = [
        'nombre', 'categoria',
        'largo_cm', 'ancho_cm', 'alto_cm', 'peso_kg',
        'unidades', 'apilable_max', 'soporta_peso_encima',
        'orientacion_fija', 'peligrosa', 'peligrosa_codigo',
        'activo', 'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'largo_cm' => 'integer',
            'ancho_cm' => 'integer',
            'alto_cm' => 'integer',
            'peso_kg' => 'decimal:2',
            'unidades' => 'integer',
            'apilable_max' => 'integer',
            'soporta_peso_encima' => 'boolean',
            'orientacion_fija' => 'boolean',
            'peligrosa' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /** Volumen de la caja envolvente, en m³. */
    public function volumenM3(): float
    {
        return round(($this->largo_cm / 100) * ($this->ancho_cm / 100) * ($this->alto_cm / 100), 4);
    }

    /**
     * Con qué FORMA lo dibuja el visor 3D.
     *
     * Los botellones no viajan como una caja: son 5 bidones parados en fila dentro
     * de una bolsa (foto del dueño 05-08 — se ven los 5 picos gathered arriba, y las
     * medidas 130 × 26 × 51 cuadran con 5 × 26 cm de diámetro). Es la carga diaria,
     * así que dibujarla como un ladrillo naranja era lo que menos se parecía a la
     * realidad.
     *
     * Es dato de DIBUJO: `paraCalculo()` no lo mira, así que no puede mover un cupo.
     * Todo lo que no sea botellones se sigue dibujando como bulto rectangular, que es
     * lo que son las cajas y los dispensadores.
     */
    public function formaVisor(): string
    {
        return $this->categoria === 'botellones' ? 'botellones' : 'caja';
    }

    /** Forma que espera CalculoDeCarga::cupo(), sin que el servicio conozca Eloquent. */
    public function paraCalculo(): array
    {
        return [
            'largo' => $this->largo_cm,
            'ancho' => $this->ancho_cm,
            'alto' => $this->alto_cm,
            'peso' => (float) $this->peso_kg,
            'unidades' => $this->unidades,
            'apilable_max' => $this->apilable_max,
            'orientacion_fija' => $this->orientacion_fija,
        ];
    }
}
