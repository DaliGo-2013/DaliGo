<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Camión del SIMULADOR de carga: una caja de carga TIPO, con nombre («HD35»,
 * «Contenedor 40'»), separada a propósito de la flota real (decisión del dueño
 * 05-08-2026 — ver la migración).
 *
 * Se audita por el mismo motivo que TipoBulto: sus medidas alimentan una
 * promesa comercial («te caben 140»), y si alguien cambia un número conviene
 * saber quién y cuándo.
 */
class CamionSimulacion extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'camiones_simulacion';

    /**
     * Siluetas que sabe dibujar el visor (`carga3d.js`). Es dato de DIBUJO: no
     * entra en `paraCalculo()`, así que elegir mal una silueta afea el lienzo
     * pero no puede alterar un cupo. Si se agrega una acá, hay que agregarla
     * también en el `switch` del visor — lo vigila CamionesSimulacionSeederTest.
     */
    public const SILUETAS = [
        'semirremolque' => 'Tracto + acoplado (o contenedor)',
        'camion' => 'Camión de reparto (cabina + caja)',
        'camion_liviano' => 'Camión liviano / furgón chico',
    ];

    protected $fillable = [
        'nombre', 'largo_cm', 'ancho_cm', 'alto_cm',
        'peso_max_kg', 'pasillo_cm', 'activo', 'notas', 'silueta',
    ];

    protected function casts(): array
    {
        return [
            'largo_cm' => 'integer',
            'ancho_cm' => 'integer',
            'alto_cm' => 'integer',
            'peso_max_kg' => 'integer',
            'pasillo_cm' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /** Volumen útil de la caja, en m³. */
    public function volumenM3(): float
    {
        return round(($this->largo_cm / 100) * ($this->ancho_cm / 100) * ($this->alto_cm / 100), 1);
    }

    /** Forma que espera CalculoDeCarga (cupo() y carga()), sin Eloquent adentro. */
    public function paraCalculo(): array
    {
        return [
            'largo' => $this->largo_cm,
            'ancho' => $this->ancho_cm,
            'alto' => $this->alto_cm,
            'peso_max_kg' => $this->peso_max_kg,
            'pasillo' => $this->pasillo_cm,
        ];
    }
}
