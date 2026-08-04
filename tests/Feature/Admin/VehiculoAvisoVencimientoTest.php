<?php

namespace Tests\Feature\Admin;

use App\Models\Notificacion;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoAviso;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aviso automático de vencimiento de documentos de la flota (decisión del dueño
 * 04-08-2026: «semáforo + aviso 30 días antes»).
 *
 * Es lo único que la planilla no puede hacer: avisar sin que alguien la abra.
 * Estos candados fijan las dos cosas que hacen que un aviso automático sirva:
 * que salga cuando corresponde y que NO se repita todos los días (un aviso que
 * repite se aprende a ignorar, y ahí deja de ser un aviso).
 */
class VehiculoAvisoVencimientoTest extends TestCase
{
    use RefreshDatabase;

    private User $jefe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
        $this->jefe = tap(User::factory()->create(['name' => 'Jefe de Logística']))->assignRole('jefe_logistica');
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Notificacion> */
    private function avisos(?string $evento = null)
    {
        return Notificacion::query()
            ->where('canal', Notificacion::CANAL_DATABASE)
            ->when($evento, fn ($q) => $q->where('evento', $evento))
            ->when(! $evento, fn ($q) => $q->where('evento', 'like', 'vehiculo.%'));
    }

    public function test_avisa_cuando_falta_menos_de_un_mes(): void
    {
        $vehiculo = Vehiculo::factory()->alDia()->create([
            'ppu' => 'PSJW47',
            'permiso_circulacion_vence' => now()->addDays(12)->toDateString(),
        ]);

        $this->artisan('vehiculos:avisar-vencimientos')->assertSuccessful();

        $aviso = $this->avisos('vehiculo.documento_por_vencer')->firstOrFail();

        $this->assertSame($this->jefe->id, $aviso->user_id);
        // El cuerpo dice QUÉ documento y para cuándo: sin eso hay que abrir la
        // ficha para saber si es urgente.
        $this->assertStringContainsString('Permiso de circulación', $aviso->cuerpo);
        $this->assertStringContainsString('vence en 12 días', $aviso->cuerpo);
        $this->assertStringContainsString('PSJW47', $aviso->titulo);
        // Y aterriza donde se arregla: la ficha del vehículo.
        $this->assertSame(
            route('admin.vehiculos.show', $vehiculo),
            $aviso->urlDestinoPara($this->jefe),
        );
    }

    public function test_no_avisa_lo_que_esta_al_dia(): void
    {
        Vehiculo::factory()->alDia()->create();

        $this->artisan('vehiculos:avisar-vencimientos')->assertSuccessful();

        $this->assertSame(0, $this->avisos()->count());
    }

    public function test_no_repite_el_aviso_al_correr_todos_los_dias(): void
    {
        Vehiculo::factory()->alDia()->create(['soap_vence' => now()->addDays(5)->toDateString()]);

        $this->artisan('vehiculos:avisar-vencimientos');
        $this->artisan('vehiculos:avisar-vencimientos');
        $this->artisan('vehiculos:avisar-vencimientos');

        $this->assertSame(1, $this->avisos()->count());
    }

    public function test_al_renovar_el_documento_el_proximo_vencimiento_vuelve_a_avisar(): void
    {
        // El caso que rompe una idempotencia mal hecha: si la clave del aviso no
        // incluye la FECHA, renovar el SOAP deja al vehículo mudo para siempre.
        $vehiculo = Vehiculo::factory()->alDia()->create(['soap_vence' => now()->addDays(5)->toDateString()]);

        $this->artisan('vehiculos:avisar-vencimientos');
        $this->assertSame(1, $this->avisos()->count());

        $vehiculo->update(['soap_vence' => now()->addDays(20)->toDateString()]);
        $this->artisan('vehiculos:avisar-vencimientos');

        $this->assertSame(2, $this->avisos()->count());
    }

    public function test_avisa_lo_recien_vencido_con_su_propio_evento(): void
    {
        Vehiculo::factory()->alDia()->create(['rt_vence' => now()->subDays(3)->toDateString()]);

        $this->artisan('vehiculos:avisar-vencimientos');

        $aviso = $this->avisos('vehiculo.documento_vencido')->firstOrFail();
        $this->assertStringContainsString('venció hace 3 días', $aviso->cuerpo);
        // La consecuencia va en el cuerpo: es lo que ordena la prioridad.
        $this->assertStringContainsString('no puede circular', $aviso->cuerpo);
    }

    public function test_la_deuda_historica_no_inunda_la_campanita(): void
    {
        // La planilla trae revisiones técnicas vencidas hace años. Avisarlas
        // todas habría enterrado justo lo que vence esta semana; se ven en rojo
        // en el listado, que es su lugar.
        Vehiculo::factory()->alDia()->create(['rt_vence' => now()->subYears(2)->toDateString()]);

        $this->artisan('vehiculos:avisar-vencimientos');

        $this->assertSame(0, $this->avisos()->count());
    }

    public function test_un_solo_aviso_por_vehiculo_con_todos_sus_documentos(): void
    {
        // RT y emisiones casi siempre vencen el mismo día, y permiso con SOAP
        // también: avisar por documento dejaría 4 filas del mismo camión.
        Vehiculo::factory()->alDia()->create([
            'ppu' => 'RVBD34',
            'rt_vence' => now()->addDays(10)->toDateString(),
            'emisiones_vence' => now()->addDays(10)->toDateString(),
            'permiso_circulacion_vence' => now()->addDays(15)->toDateString(),
        ]);

        $this->artisan('vehiculos:avisar-vencimientos');

        $this->assertSame(1, $this->avisos()->count());

        $aviso = $this->avisos()->firstOrFail();
        $this->assertStringContainsString('Revisión técnica', $aviso->cuerpo);
        $this->assertStringContainsString('Certificado de emisiones', $aviso->cuerpo);
        $this->assertStringContainsString('Permiso de circulación', $aviso->cuerpo);
        // Pero la bitácora registra los tres por separado: si mañana se renueva
        // solo uno, los otros dos siguen sabiendo que ya se avisaron.
        $this->assertSame(3, VehiculoAviso::count());
    }

    public function test_un_vehiculo_fuera_de_la_flota_no_avisa(): void
    {
        Vehiculo::factory()->create([
            'estado' => Vehiculo::ESTADO_VENDIDO,
            'baja_motivo' => 'Venta febrero 2023',
            'soap_vence' => now()->addDays(5)->toDateString(),
        ]);

        $this->artisan('vehiculos:avisar-vencimientos');

        $this->assertSame(0, $this->avisos()->count());
    }

    public function test_el_semirremolque_no_recibe_aviso_de_emisiones(): void
    {
        Vehiculo::factory()->alDia()->create([
            'tipo' => 'semirremolque',
            'emisiones_vence' => now()->addDays(5)->toDateString(),
        ]);

        $this->artisan('vehiculos:avisar-vencimientos');

        $this->assertSame(0, $this->avisos()->count());
    }

    public function test_sin_destinatarios_no_marca_nada_como_avisado(): void
    {
        // Si marcáramos los avisos como enviados sin nadie a quien avisar, la
        // novedad se perdería y el día que exista el perfil de logística nadie
        // se enteraría. Es el mismo criterio que el traslado con las cuentas de
        // sucursal que todavía no existen.
        $this->jefe->removeRole('jefe_logistica');
        Vehiculo::factory()->alDia()->create(['soap_vence' => now()->addDays(5)->toDateString()]);

        $this->artisan('vehiculos:avisar-vencimientos')->assertSuccessful();

        $this->assertSame(0, VehiculoAviso::count());
        $this->assertSame(0, $this->avisos()->count());

        // Y cuando aparece el perfil, el aviso sale.
        $this->jefe->assignRole('jefe_logistica');
        $this->artisan('vehiculos:avisar-vencimientos');

        $this->assertSame(1, $this->avisos()->count());
    }

    public function test_dry_run_no_envia_ni_registra(): void
    {
        Vehiculo::factory()->alDia()->create(['soap_vence' => now()->addDays(5)->toDateString()]);

        $this->artisan('vehiculos:avisar-vencimientos', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, VehiculoAviso::count());
        $this->assertSame(0, $this->avisos()->count());
    }

    public function test_avisa_a_todos_los_que_pueden_ver_la_flota(): void
    {
        // El aviso no está cableado a un rol: sigue al PERMISO. Cuando exista el
        // perfil de cobranzas, basta darle 'ver vehiculos' desde la UI de Roles.
        $cobranzas = tap(User::factory()->create())->givePermissionTo('ver vehiculos');
        Vehiculo::factory()->alDia()->create(['soap_vence' => now()->addDays(5)->toDateString()]);

        $this->artisan('vehiculos:avisar-vencimientos');

        $destinatarios = $this->avisos()->pluck('user_id')->all();
        $this->assertContains($this->jefe->id, $destinatarios);
        $this->assertContains($cobranzas->id, $destinatarios);
    }
}
