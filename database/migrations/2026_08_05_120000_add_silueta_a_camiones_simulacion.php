<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Con qué SILUETA dibuja el visor 3D cada camión del simulador.
     *
     * Hasta ahora `carga3d.js` dibujaba lo mismo para los cuatro: una caja de
     * cabina pegada a una caja de carga. Eso está mal en los dos extremos del
     * catálogo — el «Contenedor 40'» no tiene cabina (viaja sobre el
     * semirremolque, tirado por el Actros, según su propia nota) y el HD35 de
     * 4,3 m no es un camión de reparto mediano. Dibujados todos iguales, el
     * visor no ayuda a reconocer contra qué se está cotizando, que es la mitad
     * de para qué existe.
     *
     * Es DATO DE DIBUJO, no una medida: `paraCalculo()` no lo mira, así que no
     * puede mover ningún cupo. Por eso vive acá y no en la lógica del motor.
     *
     * Nullable a propósito: un camión agregado mañana sin silueta se dibuja con
     * la que se deduce de su largo (SimuladorCargaController::silueta()), así
     * que la pantalla nunca queda sin dibujo por un dato que falte — la misma
     * lección que dejó el enganche con la flota (un dato que la pantalla
     * necesita no puede depender de un paso que quizá nadie haga).
     */
    public function up(): void
    {
        Schema::table('camiones_simulacion', function (Blueprint $table) {
            $table->string('silueta', 20)->nullable()->after('pasillo_cm');
        });
    }

    public function down(): void
    {
        Schema::table('camiones_simulacion', function (Blueprint $table) {
            $table->dropColumn('silueta');
        });
    }
};
