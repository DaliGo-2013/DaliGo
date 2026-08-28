<?php

namespace Tests\Feature;

use App\Mail\IngresoTallerRecibido;
use App\Models\LoteServicio;
use App\Models\OrdenServicio;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * AL CLIENTE SE LE DICE EL PLAZO, NO UNA FECHA.
 *
 * Pedido del dueño (14-08-2026): «para la sucursal de Mirador son 10 días hábiles pero para
 * Abate Molina y Coquimbo son 15 días hábiles, pero no quiero que la app lo calcule, solo diga
 * 15 días hábiles o 10 días, después que el cliente lo calcule por sí solo, porque ha pasado
 * casos que el técnico se enferma o está de vacaciones y si uno promete una fecha y no cumple es
 * mucho compromiso y hay quejas y reclamos».
 *
 * POR QUÉ HAY CANDADO Y NO SOLO UN BORRADO: la fecha estimada la calcula el servidor y se guarda
 * en `ordenes_servicio.fecha_entrega`, que SIGUE viva porque la usan el flujo de salidas del
 * dashboard y el informe de gestión. Lo que cambia es a quién se le muestra: adentro sí, al
 * cliente no. Sin candado, cualquiera vuelve a poner la fecha en el correo «para que el cliente
 * sepa» y el reclamo vuelve con ella.
 *
 * Son TRES superficies las que ve el cliente y las tres tienen que decir lo mismo: el correo de
 * ingreso, la pantalla de listo del QR y la del ingreso por cantidad.
 */
class PlazoSinFechaPrometidaTest extends TestCase
{
    use RefreshDatabase;

    /** La fecha que el servidor calcula y guarda: no tiene que aparecer en nada del cliente. */
    private const FECHA_ESTIMADA = '2026-08-20';

    private const FECHA_ESTIMADA_VISIBLE = '20-08-2026';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
    }

    private function sucursal(string $codigo = 'MIRADOR', string $nombre = 'El Mirador'): Sucursal
    {
        return Sucursal::firstOrCreate(
            ['codigo' => $codigo],
            ['activa' => true, 'nombre' => $nombre, 'es_central' => $codigo === 'MIRADOR'],
        );
    }

    private function orden(array $overrides = []): OrdenServicio
    {
        return OrdenServicio::factory()->create(array_merge([
            'estado' => 'recibido',
            'fuente' => 'qr',
            'cliente_nombre' => 'Ana Cliente',
            'cliente_email' => 'ana@correo.cl',
            'falla_reportada' => 'No enfría',
            'sucursal_id' => $this->sucursal()->id,
            'fecha_ingreso' => '2026-08-06',
            'fecha_entrega' => self::FECHA_ESTIMADA,
        ], $overrides));
    }

    // ─────────────────────────────────────────── el correo de ingreso

    public function test_el_correo_de_ingreso_no_le_promete_una_fecha(): void
    {
        $html = (new IngresoTallerRecibido($this->orden()))->render();

        $this->assertStringNotContainsString(self::FECHA_ESTIMADA_VISIBLE, $html, 'Volvió la fecha de entrega al correo del cliente.');
        $this->assertStringNotContainsString('Entrega estimada', $html);
        // La fecha de INGRESO sí va: es un hecho, no una promesa.
        $this->assertStringContainsString('06-08-2026', $html);
    }

    public function test_el_correo_de_ingreso_dice_el_plazo_en_dias_habiles(): void
    {
        $this->assertStringContainsString('hasta 10 días hábiles', (new IngresoTallerRecibido($this->orden()))->render());
    }

    // ─────────────────────────────────────────── las dos pantallas del QR

    public function test_la_pantalla_de_listo_del_qr_dice_el_plazo_y_no_la_fecha(): void
    {
        $orden = $this->orden();

        $this->get(URL::signedRoute('ingreso-taller.gracias', ['orden' => $orden->id]))
            ->assertOk()
            ->assertSee('hasta 10 días hábiles')
            ->assertDontSee('Entrega estimada')
            ->assertDontSee(self::FECHA_ESTIMADA_VISIBLE);
    }

    public function test_la_pantalla_del_ingreso_por_cantidad_dice_el_plazo_de_su_sucursal(): void
    {
        // Coquimbo recibe pero no repara: manda el equipo a Mirador, y por eso son 15.
        $sucursal = $this->sucursal('COQUIMBO', 'Coquimbo');
        $lote = LoteServicio::factory()->create(['sucursal_id' => $sucursal->id, 'total_ordenes' => 1]);
        $this->orden(['lote_id' => $lote->id, 'sucursal_id' => $sucursal->id]);

        $this->get(URL::signedRoute('ingreso-taller.lote.gracias', ['lote' => $lote->id]))
            ->assertOk()
            ->assertSee('hasta 15 días hábiles')
            ->assertDontSee('Entrega estimada')
            ->assertDontSee(self::FECHA_ESTIMADA_VISIBLE);
    }

    // ─────────────────────────────────────────── el plazo de cada sucursal

    /**
     * Los tres plazos, tal como los dictó el dueño. Están en config y no en el texto porque son
     * números de negocio: si mañana Coquimbo empieza a reparar, se cambia acá y cambia en las
     * tres superficies a la vez.
     */
    public function test_cada_sucursal_tiene_el_plazo_que_dijo_el_dueno(): void
    {
        $this->assertSame(10, $this->sucursal()->dias_reparacion);
        $this->assertSame(15, $this->sucursal('COQUIMBO', 'Coquimbo')->dias_reparacion);
        $this->assertSame(15, $this->sucursal('ABATE-MOLINA', 'Abate Molina')->dias_reparacion);
    }

    /**
     * EL PLAZO NO PUEDE DEPENDER DE CÓMO SE TIPEÓ EL CÓDIGO. Hallazgo del 14-08-2026 mirando el
     * listado de Sucursales de producción: el código de la casa matriz estaba guardado como
     * «Mirador» (alguien lo retipeó al editar la ficha), y el plazo se resolvía con
     * `$map[$this->codigo]` — un índice de array de PHP, que sí distingue mayúsculas. El mapa
     * tiene la clave `MIRADOR`, así que Mirador caía al default de 15 días hábiles y el correo
     * prometía 15 donde el dueño dijo 10. Es EXACTAMENTE la diferencia del correo real que él
     * mostró (ingreso 06-08 → entrega 27-08 en vez del 20-08).
     *
     * Por qué nadie lo vio: la consulta que arma el selector del QR usa `whereIn`, y en MySQL
     * eso es case-insensitive por colación — o sea, la sucursal aparecía y funcionaba todo,
     * menos el número que el cliente recibe por escrito.
     */
    public function test_el_plazo_no_depende_de_como_se_tipeo_el_codigo(): void
    {
        foreach (['Mirador', 'mirador', ' MIRADOR '] as $comoSeTipeo) {
            $sucursal = new Sucursal(['codigo' => $comoSeTipeo]);

            $this->assertSame(10, $sucursal->dias_reparacion, "Con el código «{$comoSeTipeo}» el plazo dejó de ser el de Mirador.");
        }

        // Y un código que de verdad no está en el mapa sigue cayendo al default.
        $this->assertSame(15, (new Sucursal(['codigo' => 'BUZETA']))->dias_reparacion);
    }

    // ─────────────────────────────────────────── adentro sí se ve

    /**
     * LA FECHA NO SE BORRÓ, SE DEJÓ DE PROMETER: el taller la sigue viendo en la ficha (la usan
     * el flujo de salidas del dashboard y el informe). Este candado es el que distingue «no se
     * le manda al cliente» de «se eliminó de la app».
     */
    public function test_adentro_la_ficha_sigue_mostrando_la_fecha_estimada(): void
    {
        $orden = $this->orden();
        $admin = tap(User::factory()->create())->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.servicio-tecnico.show', $orden))
            ->assertOk()
            ->assertSee(self::FECHA_ESTIMADA_VISIBLE);

        $this->assertSame(self::FECHA_ESTIMADA, $orden->fresh()->fecha_entrega->toDateString());
    }
}
