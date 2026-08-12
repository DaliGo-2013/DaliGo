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
        'camion_hino' => 'HINO 500 (cabina propia)',
        'camion' => 'Camión de reparto genérico (cabina + caja)',
        'camion_liviano' => 'Hyundai HD35 (cabina propia)',
        'camion_nqr' => 'Chevrolet NQR / Isuzu N-Series (cabina propia)',
    ];

    protected $fillable = [
        'nombre', 'largo_cm', 'ancho_cm', 'alto_cm',
        'peso_max_kg', 'pasillo_cm', 'entre_ejes_cm', 'eje_trasero_cm',
        'activo', 'notas', 'silueta',
    ];

    protected function casts(): array
    {
        return [
            'largo_cm' => 'integer',
            'ancho_cm' => 'integer',
            'alto_cm' => 'integer',
            'peso_max_kg' => 'integer',
            'pasillo_cm' => 'integer',
            'entre_ejes_cm' => 'integer',
            'eje_trasero_cm' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /**
     * ¿Se le puede repartir el peso entre los ejes? Hacen falta LOS DOS números.
     *
     * Con uno solo no hay brazo de palanca: sin la distancia entre ejes no se sabe
     * contra qué se reparte, y sin la posición del trasero no se sabe dónde cae la
     * carga respecto de él. Medio dato da medio cálculo, que acá es un número
     * inventado con cara de medido.
     */
    public function tieneEjes(): bool
    {
        return $this->entre_ejes_cm > 0 && $this->eje_trasero_cm > 0;
    }

    /**
     * Dónde cae el eje DELANTERO respecto del frente de la caja, en cm.
     *
     * Sale de restar, no se guarda: un tercer número podría contradecir a los otros
     * dos. Da NEGATIVO en un camión normal, porque el eje delantero va debajo de la
     * cabina, o sea adelante de donde arranca la caja.
     */
    public function ejeDelanteroCm(): ?int
    {
        return $this->tieneEjes() ? $this->eje_trasero_cm - $this->entre_ejes_cm : null;
    }

    /** Volumen útil de la caja, en m³. */
    public function volumenM3(): float
    {
        return round(($this->largo_cm / 100) * ($this->ancho_cm / 100) * ($this->alto_cm / 100), 1);
    }

    /**
     * Forma que espera CalculoDeCarga (cupo() y carga()), sin Eloquent adentro.
     *
     * EL CAMIÓN QUE SALE A MEDIO CARGAR (lote 5, pedido del dueño): pasa todo el
     * tiempo —el camión vuelve de un reparto con carga arriba, o se le suma un
     * pedido a uno que ya está armado— y hasta ahora había que simular a ojo con un
     * camión más chico. `$ocupadoCm` son los metros de piso que YA están tomados,
     * contra la cabina, y `$ocupadoKg` lo que ya pesa arriba.
     *
     * Los dos van JUNTOS y no de a uno: descontar el espacio sin descontar los kilos
     * dejaría el cartel de sobrepeso en verde con el camión pasado, que es peor que
     * no tener la función. Por eso son un solo parámetro de esta llamada y no dos
     * campos sueltos que alguien pueda completar a medias.
     */
    public function paraCalculo(int $ocupadoCm = 0, float $ocupadoKg = 0.0): array
    {
        $ocupadoCm = max(0, min($ocupadoCm, $this->largo_cm));

        return [
            'largo' => $this->largo_cm,
            'ancho' => $this->ancho_cm,
            'alto' => $this->alto_cm,
            // Lo que ya pesa arriba sale del tope: lo que queda es lo que se puede
            // sumar. Nunca negativo — un camión ya pasado admite 0 kg más, no «-200».
            'peso_max_kg' => $this->peso_max_kg === null
                ? null
                : max(0, $this->peso_max_kg - (int) round($ocupadoKg)),
            'pasillo' => $this->pasillo_cm,
            'ocupado' => $ocupadoCm,
        ];
    }
}
