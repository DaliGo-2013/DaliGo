<?php

namespace Tests\Feature\Notificaciones;

use App\Jobs\EnviarNotificacion;
use App\Models\Notificacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificacionReintentarTest extends TestCase
{
    use RefreshDatabase;

    private function fallida(array $overrides = []): Notificacion
    {
        return Notificacion::create(array_merge([
            'evento' => 'sistema.prueba',
            'canal' => Notificacion::CANAL_MAIL,
            'destinatario' => 'x@example.com',
            'titulo' => 'T',
            'cuerpo' => 'C',
            'estado' => Notificacion::FALLIDA,
            'intentos' => 1,
            'programada_para' => now()->subMinute(),
        ], $overrides));
    }

    public function test_reencola_fallida_vencida(): void
    {
        Queue::fake();
        $n = $this->fallida();

        $this->artisan('notificaciones:reintentar')->assertSuccessful();

        $this->assertSame(Notificacion::PENDIENTE, $n->fresh()->estado);
        Queue::assertPushed(EnviarNotificacion::class, 1);
    }

    public function test_no_reclama_fallida_no_vencida(): void
    {
        Queue::fake();
        $n = $this->fallida(['programada_para' => now()->addMinutes(10)]);

        $this->artisan('notificaciones:reintentar')->assertSuccessful();

        $this->assertSame(Notificacion::FALLIDA, $n->fresh()->estado);
        Queue::assertNothingPushed();
    }

    public function test_no_reclama_terminal_sin_programada_para(): void
    {
        Queue::fake();
        // Agotó reintentos: programada_para NULL (terminal).
        $n = $this->fallida(['programada_para' => null, 'intentos' => 3]);

        $this->artisan('notificaciones:reintentar')->assertSuccessful();

        $this->assertSame(Notificacion::FALLIDA, $n->fresh()->estado);
        Queue::assertNothingPushed();
    }

    public function test_no_reclama_si_agoto_el_maximo_de_intentos(): void
    {
        Queue::fake();
        // Vencida pero intentos ya == max (3): no debe reclamarse.
        $n = $this->fallida(['intentos' => 3, 'programada_para' => now()->subMinute()]);

        $this->artisan('notificaciones:reintentar')->assertSuccessful();

        $this->assertSame(Notificacion::FALLIDA, $n->fresh()->estado);
        Queue::assertNothingPushed();
    }

    public function test_segunda_corrida_no_re_despacha_lo_ya_pendiente(): void
    {
        Queue::fake();
        $this->fallida();

        $this->artisan('notificaciones:reintentar'); // 1ª: reclama y encola
        $this->artisan('notificaciones:reintentar'); // 2ª: ya está pendiente, nada que reclamar

        Queue::assertPushed(EnviarNotificacion::class, 1);
    }
}
