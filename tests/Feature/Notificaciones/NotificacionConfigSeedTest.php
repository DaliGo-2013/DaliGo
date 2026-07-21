<?php

namespace Tests\Feature\Notificaciones;

use App\Models\Configuracion;
use App\Models\Notificacion;
use App\Services\Notificaciones\NotificacionDispatcher;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificacionConfigSeedTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVES_M15 = [
        'notif_plantilla_sistema_prueba',
        'notif_reintentos_max',
        'notif_backoff_minutos',
        'notif_remitente_nombre',
        // P-M12-02 · Cotización del taller (avisos internos)
        'notif_plantilla_cotizacion_enviada',
        'notif_plantilla_cotizacion_respondida',
        // Agenda de terreno · solicitud por coordinar + confirmación del cliente
        'notif_plantilla_terreno_solicitada',
        'notif_plantilla_terreno_confirmada',
    ];

    public function test_seeder_es_idempotente_no_duplica_claves(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        // Conteo DERIVADO de la 1ª corrida, no hardcodeado: el catalogo crece
        // cuando un modulo suma plantillas (M14 agrego las suyas el 2026-07-20)
        // y fijar el numero rompia con cada alta — mismo patron ya aplicado en
        // PreferenciasCanalTest (bitacora 2026-07-13). La intencion del test es
        // la IDEMPOTENCIA, no el tamaño del catalogo.
        $antes = Configuracion::where('grupo', 'notificaciones')->count();

        $this->seed(ConfiguracionSeeder::class); // 2ª corrida: no duplica

        $this->assertSame(
            $antes,
            Configuracion::where('grupo', 'notificaciones')->count(),
            'La 2ª corrida del seeder no debe duplicar las claves notif_*.'
        );
        foreach (self::CLAVES_M15 as $clave) {
            $this->assertSame(1, Configuracion::where('clave', $clave)->count(), "Clave duplicada: {$clave}");
        }
    }

    public function test_claves_se_leen_casteadas_a_su_tipo(): void
    {
        $this->seed(ConfiguracionSeeder::class);

        $this->assertSame(3, Configuracion::get('notif_reintentos_max'));
        $this->assertSame([5, 15, 60], Configuracion::get('notif_backoff_minutos'));
        $this->assertSame('DaliGo', Configuracion::get('notif_remitente_nombre'));

        $plantilla = Configuracion::get('notif_plantilla_sistema_prueba');
        $this->assertIsArray($plantilla);
        $this->assertArrayHasKey('asunto', $plantilla);
        $this->assertArrayHasKey('cuerpo', $plantilla);
    }

    public function test_reseed_no_pisa_un_valor_editado_desde_la_ui(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        Configuracion::set('notif_reintentos_max', 5); // el admin lo sube desde la UI

        $this->seed(ConfiguracionSeeder::class); // re-seed del deploy

        $this->assertSame(5, Configuracion::get('notif_reintentos_max'), 'firstOrCreate no debe pisar el valor editado.');
    }

    public function test_dispatcher_renderiza_con_la_plantilla_sembrada(): void
    {
        Queue::fake();
        $this->seed(ConfiguracionSeeder::class);

        $creadas = app(NotificacionDispatcher::class)->despachar(
            'sistema.prueba',
            null,
            'externo@example.com',
            ['nombre' => 'Mauricio', 'fecha' => '2026-07-04'],
        );

        $notificacion = $creadas->first();
        $this->assertSame('Notificación de prueba — Mauricio', $notificacion->titulo);
        $this->assertStringContainsString('Hola Mauricio:', $notificacion->cuerpo);
        $this->assertStringContainsString('Enviada el 2026-07-04.', $notificacion->cuerpo);
        $this->assertSame(Notificacion::PENDIENTE, $notificacion->estado);
    }
}
