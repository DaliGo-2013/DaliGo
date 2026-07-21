<?php

namespace Tests\Feature;

use App\Models\Aprobacion;
use App\Models\Notificacion;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\ReglasAprobacionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * P-TZ-02 (PLAN-TIMEZONE §4.3-4): capa de RENDER. El storage sigue UTC; el
 * macro enChile() convierte SOLO al mostrar un timestamp con hora. El bug del
 * QA 15-07: el historial decía 15:45 cuando en Chile eran las 11:45 (+4h).
 * Los diffForHumans NO se tocan: un delta no depende del tz de render.
 */
class RenderEnChileTest extends TestCase
{
    use RefreshDatabase;

    // El instante del QA: 15:45 UTC = 11:45 en Chile (julio, invierno, UTC-4).
    private const INSTANTE_QA = '2026-07-15 15:45:00';

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        return tap(User::factory()->create())->assignRole('admin');
    }

    // --- El macro (§4.3) -------------------------------------------------------

    public function test_el_macro_convierte_utc_a_hora_chilena_de_invierno(): void
    {
        $utc = Carbon::parse(self::INSTANTE_QA, 'UTC');

        $this->assertSame('15-07-2026 11:45', $utc->enChile()->format('d-m-Y H:i'));
        // El macro COPIA: el instante original no muta (un ->tz() directo sobre
        // un Carbon mutable correría también todo lo que se calcule después).
        $this->assertSame('UTC', $utc->timezoneName);
        $this->assertSame('15:45', $utc->format('H:i'));
    }

    public function test_el_macro_respeta_el_dst_chileno_de_verano(): void
    {
        // Enero = verano chileno (UTC-3): el mismo reloj UTC queda a -3, no -4.
        $this->assertSame('12:45', Carbon::parse('2026-01-15 15:45:00', 'UTC')->enChile()->format('H:i'));
    }

    // --- Superficies E2E (§4.3: filas nuevas se leen en hora chilena) -----------

    public function test_el_historial_de_aprobaciones_muestra_la_hora_chilena(): void
    {
        $admin = $this->admin();
        $this->seed(ConfiguracionSeeder::class);
        $this->seed(ReglasAprobacionSeeder::class);
        Queue::fake();
        $this->travelTo(Carbon::parse(self::INSTANTE_QA, 'UTC'));

        Aprobacion::create([
            'tipo_accion' => Aprobacion::ACCION_AJUSTE_REPORTE,
            'motivo' => 'm', 'descripcion' => 'Render QA',
            'rol_aprobador' => 'admin',
        ]);

        $this->actingAs($admin)->get(route('admin.aprobaciones.index'))
            ->assertOk()
            ->assertSee('15-07-2026 11:45')      // la hora chilena real
            ->assertDontSee('15-07-2026 15:45'); // el +4h que vio el QA 15-07
    }

    public function test_la_bandeja_admin_de_notificaciones_muestra_la_hora_chilena(): void
    {
        $admin = $this->admin();
        $this->travelTo(Carbon::parse(self::INSTANTE_QA, 'UTC'));

        Notificacion::create([
            'evento' => 'sistema.prueba',
            'user_id' => $admin->id,
            'destinatario' => $admin->email,
            'canal' => Notificacion::CANAL_DATABASE,
            'titulo' => 'Prueba render',
            'cuerpo' => 'x',
            'estado' => Notificacion::ENVIADA,
            'enviada_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('admin.notificaciones.index'))
            ->assertOk()
            ->assertSee('15-07-2026 11:45')
            ->assertDontSee('15-07-2026 15:45');
    }

    // --- Relativos intactos (§4.4) ----------------------------------------------

    public function test_los_relativos_de_la_campanita_siguen_intactos(): void
    {
        $admin = $this->admin();
        $creada = Carbon::parse('2026-07-15 15:40:00', 'UTC');
        $this->travelTo($creada);

        Notificacion::create([
            'evento' => 'sistema.prueba',
            'user_id' => $admin->id,
            'destinatario' => $admin->email,
            'canal' => Notificacion::CANAL_DATABASE,
            'titulo' => 'Prueba relativos',
            'cuerpo' => 'x',
            'estado' => Notificacion::ENVIADA,
            'enviada_at' => now(),
        ]);

        // 5 minutos después, la bandeja personal sigue diciendo exactamente lo
        // que dice diffForHumans a secas ("hace 5 minutos"): la capa render no
        // toca los relativos. Se compara contra el valor computado (no un
        // string hardcodeado) para no depender del locale del entorno.
        $this->travelTo(Carbon::parse(self::INSTANTE_QA, 'UTC'));
        $this->actingAs($admin)->get(route('notificaciones.index'))
            ->assertOk()
            ->assertSee($creada->diffForHumans());
    }
}
