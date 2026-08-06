<?php

namespace App\Services\Carga;

/**
 * Un pallet ARMADO, para el simulador de carga (pedido del dueño 05/06-08-2026: «a
 * veces cargamos en pallet… que el pallet aparezca al lado del camión en el piso con
 * la opción de armarlo y luego subirlo al camión»).
 *
 * LA IDEA CLAVE, y por eso no hizo falta ningún motor nuevo: **un pallet es una caja
 * de carga**. Cuántas unidades entran sobre un pallet se responde con el MISMO
 * `CalculoDeCarga::cupo()` que responde cuántas entran en un camión, cambiando la caja
 * de 12 m por una de 1,20 m. Y el pallet armado vuelve al motor como un BULTO más, así
 * que cuántos pallets entran en el camión también sale de `cupo()`. Dos llamadas al
 * mismo cálculo verificado, cero heurísticas nuevas.
 *
 * MEDIDAS. Las dos que dictó el dueño (06-08) son las estándar: 120 × 100 y 120 × 80.
 * La base son 14,4 cm según la ficha del EPAL, y acá se usan **15 cm enteros**: el
 * motor trabaja en centímetros enteros a propósito (§2.5 de las reglas) y redondear la
 * base HACIA ARRIBA deja menos altura útil, o sea el error va hacia abajo, que es el
 * credo. Todo es editable igual — el dueño pidió «deja la opción de modificar».
 *
 * ALTURA. Por defecto 1,80 m en total. El rango que se maneja en la práctica es 1,60 a
 * 2,20 m (dato que trajo el dueño): más abajo se desaprovecha el camión y más arriba se
 * vuelve inestable y no pasa por la puerta del contenedor.
 */
class PalletSimulado
{
    /** Alto de la base de madera, en cm enteros (la ficha del EPAL dice 14,4). */
    public const BASE_CM = 15;

    /** Altura total por defecto del pallet armado, en cm. */
    public const ALTO_DEFECTO = 180;

    /** Topes de la altura editable. El de arriba es la altura útil de un contenedor
     *  estándar (2,39 m) menos el hueco que hay que dejar para entrar con la horquilla. */
    public const ALTO_MIN = 40;

    public const ALTO_MAX = 230;

    /**
     * Los dos pallets estándar que dictó el dueño (06-08-2026).
     *
     * La clave es la que viaja en la URL; cambiarla rompe los enlaces guardados.
     */
    public const TIPOS = [
        'industrial' => ['nombre' => 'Industrial 120 × 100', 'largo' => 120, 'ancho' => 100],
        'epal' => ['nombre' => 'EUR/EPAL 120 × 80', 'largo' => 120, 'ancho' => 80],
    ];

    public function __construct(
        public readonly int $largo_cm,
        public readonly int $ancho_cm,
        public readonly int $alto_cm,
        public readonly int $base_cm = self::BASE_CM,
    ) {}

    /**
     * Arma un pallet desde lo que llegó del formulario, acotando todo a rangos sanos.
     *
     * Acota en vez de rechazar porque estos números vienen de un `<input number>` que el
     * usuario puede tipear: un 5000 de más no debe reventar la pantalla, y tampoco vale
     * calcular con él.
     */
    public static function desdeFormulario(?string $tipo, ?int $largo, ?int $ancho, ?int $alto, ?int $base = null): self
    {
        // `array_values(...)[0]` y no `reset(self::TIPOS)`: reset() toma su argumento por
        // referencia y una constante de clase no se puede pasar así (Error en runtime).
        $medidas = self::TIPOS[$tipo] ?? array_values(self::TIPOS)[0];

        return new self(
            largo_cm: max(40, min(300, $largo ?: $medidas['largo'])),
            ancho_cm: max(40, min(300, $ancho ?: $medidas['ancho'])),
            alto_cm: max(self::ALTO_MIN, min(self::ALTO_MAX, $alto ?: self::ALTO_DEFECTO)),
            base_cm: max(5, min(30, $base ?: self::BASE_CM)),
        );
    }

    /** Alto que queda para APILAR encima de la madera. */
    public function altoUtilCm(): int
    {
        return max(0, $this->alto_cm - $this->base_cm);
    }

    /**
     * El pallet visto como CAJA DE CARGA, para preguntarle al motor cuántas unidades
     * entran encima. Sin pasillo: en un pallet no se camina.
     */
    public function comoCajaDeCarga(): array
    {
        return [
            'largo' => $this->largo_cm,
            'ancho' => $this->ancho_cm,
            'alto' => $this->altoUtilCm(),
            'peso_max_kg' => null,
            'pasillo' => 0,
        ];
    }

    /**
     * El pallet ARMADO visto como BULTO, para preguntarle al motor cuántos entran en el
     * camión.
     *
     * · `rotacion: horizontal` — gira 90° sobre el piso pero no se tumba (ver
     *   CalculoDeCarga::orientaciones).
     * · `apilable_max: 1` — no se apila un pallet sobre otro. La estiba real a veces lo
     *   hace cuando la carga aguanta, pero prometerlo sin una regla de soporte por kilo
     *   sería exagerar, que es lo único que este simulador no puede hacer.
     *
     * @param  float  $pesoKg  peso del pallet armado (madera + su carga)
     */
    public function comoBulto(float $pesoKg = 0.0, int $unidadesEncima = 1): array
    {
        return [
            'largo' => $this->largo_cm,
            'ancho' => $this->ancho_cm,
            'alto' => $this->alto_cm,
            'peso' => $pesoKg,
            'unidades' => max(1, $unidadesEncima),
            'apilable_max' => 1,
            'orientacion_fija' => false,
            'rotacion' => 'horizontal',
        ];
    }
}
