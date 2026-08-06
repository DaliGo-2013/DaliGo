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

    /**
     * Si a este producto se le puede ELEGIR la estiba. Hoy es a TODOS.
     *
     * Antes solo los de orientación fija: al resto el motor le probaba las 6 rotaciones
     * y ofrecerle «acostado» era ofrecer empeorar el resultado. El dueño lo dio vuelta
     * (06-08-2026): «que los dispensadores, cualesquiera que sea, tengan la opción de
     * pie y acostado» — en la práctica un dispensador VIAJA parado aunque el motor
     * pudiera acomodarlo tumbado, así que forzar la estiba es una necesidad real, no un
     * capricho. Lo que protege del empeoramiento accidental es el default `auto`.
     */
    public function puedeAcostarse(): bool
    {
        return true;
    }

    /**
     * Las TRES formas de estibar un pack, tal como las describió el dueño (05-08-2026:
     * «dependiendo de la capacidad del camión van como en las fotos, de costado frente a
     * frente, a veces van con el pico de la botella mirando a la puerta»).
     *
     * La bolsa medida son 130 × 26 × 51: cinco botellones PARADOS en fila —el 51 es el
     * alto del botellón, el 26 su diámetro y el 130 la fila de cinco—. De ahí salen las
     * tres, y cada una es una rotación distinta del MISMO pack:
     *
     *   pie      130 × 26 × 51   los botellones parados
     *   costado  130 × 51 × 26   tumbados, el eje del botellón cruzando el camión
     *   pico      51 × 130 × 26  tumbados, el eje mirando a la puerta (la fila cruza)
     *
     * `costado` y `pico` se diferencian en un giro de 90° sobre el piso. Parece lo mismo
     * y NO lo es, porque el motor no rota estos bultos (son de orientación fija): en el
     * HD35 dan 270 y 240 botellones respectivamente — «pico a la puerta» desperdicia 70
     * cm de ancho al cruzar la fila de 130 en una caja de 200.
     */
    public const ESTIBAS = [
        'pie' => 'De pie',
        'costado' => 'Acostado de costado',
        'pico' => 'Acostado, pico a la puerta',
    ];

    /**
     * Lo que se OFRECE en el selector: `auto` primero, y las tres forzadas.
     *
     * `auto` deja al motor hacer lo de siempre según el producto: a los de orientación
     * fija los calcula de pie (su dato de catálogo), y a los libres les prueba las 6
     * rotaciones y se queda con la que más entra. Es el predeterminado porque es el
     * comportamiento con el que se verificaron todas las referencias — elegir una estiba
     * forzada es una decisión consciente, nunca un accidente del formulario.
     */
    public const ESTIBAS_ELEGIBLES = [
        'auto' => 'Automático (la que más entra)',
        'pie' => 'De pie',
        'costado' => 'Acostado de costado',
        'pico' => 'Acostado, pico a la puerta',
    ];

    /**
     * Forma que espera CalculoDeCarga::cupo(), sin que el servicio conozca Eloquent.
     *
     * La ESTIBA no es cosmética y el número CAMBIA: en el HD35 son 420 botellones de pie,
     * 270 de costado y 240 con el pico a la puerta.
     *
     * Con una estiba FORZADA (pie/costado/pico) el bulto viaja al motor con esas medidas
     * y `orientacion_fija = true`, sea cual sea su dato de catálogo: forzar la estiba de
     * un producto que el motor rotaba libre (un dispensador, 06-08) significa justamente
     * sacarle esa libertad. Con `auto`, cada producto se comporta como siempre.
     */
    public function paraCalculo(string $estiba = 'auto', ?int $apilado = null): array
    {
        // Un valor inventado cae a `auto` en vez de calcular con medidas giradas que
        // nadie pidió. Y en los de orientación fija, `auto` ES `pie`: su dato de
        // catálogo dice que no se rotan solos.
        if (! isset(self::ESTIBAS_ELEGIBLES[$estiba])) {
            $estiba = 'auto';
        }
        $forzada = $estiba !== 'auto';
        if (! $forzada && $this->orientacion_fija) {
            $estiba = 'pie';
        }

        [$largo, $ancho, $alto] = match ($estiba) {
            'costado' => [$this->largo_cm, $this->alto_cm, $this->ancho_cm],
            'pico' => [$this->alto_cm, $this->largo_cm, $this->ancho_cm],
            default => [$this->largo_cm, $this->ancho_cm, $this->alto_cm],
        };

        return [
            'largo' => $largo,
            'ancho' => $ancho,
            'alto' => $alto,
            'peso' => (float) $this->peso_kg,
            'unidades' => $this->unidades,
            // El tope de apilado del catálogo se puede PISAR para una simulación.
            //
            // El dueño lo pidió mirando el hueco que quedaba arriba de la carga
            // (06-08-2026): «ahí también se pueda cargar bidones porque en la vida
            // cotidiana se usa todo el espacio». Ese hueco no era un error del dibujo ni
            // del acomodo: era este tope, que corta antes que la altura de la caja.
            //
            // El del catálogo sigue siendo el predeterminado —es el dato que él dictó y
            // con el que se verificaron los cupos de referencia—, pero la simulación es
            // justamente el lugar para probar «¿y si apilo 9?». Es una pregunta legítima
            // que solo él puede responder: cuántas aguanta la bolsa de abajo es dato de
            // terreno, no de geometría.
            'apilable_max' => max(1, $apilado ?: $this->apilable_max),
            'orientacion_fija' => $forzada ? true : $this->orientacion_fija,
        ];
    }

    /**
     * La estiba que el VISOR tiene que dibujar: una de las tres concretas, nunca
     * `auto`. Para un producto libre en `auto` da `pie` — su bloque se dibuja como caja
     * con la orientación que eligió el motor, así que la palabra no cambia nada.
     */
    public static function estibaEfectiva(string $estiba): string
    {
        return isset(self::ESTIBAS[$estiba]) ? $estiba : 'pie';
    }
}
