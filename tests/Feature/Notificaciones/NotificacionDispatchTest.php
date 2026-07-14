<?php

namespace Tests\Feature\Notificaciones;

use App\Jobs\EnviarNotificacion;
use App\Mail\NotificacionMail;
use App\Models\Configuracion;
use App\Models\Notificacion;
use App\Models\PreferenciaCanal;
use App\Models\User;
use App\Services\Notificaciones\Canal;
use App\Services\Notificaciones\CanalMail;
use App\Services\Notificaciones\NotificacionDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class NotificacionDispatchTest extends TestCase
{
    use RefreshDatabase;

    private function dispatcher(): NotificacionDispatcher
    {
        return app(NotificacionDispatcher::class);
    }

    // --- Dispatch por preferencia (P-M15-02) ---

    public function test_dispatch_a_usuario_crea_database_y_mail_por_default(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $creadas = $this->dispatcher()->despachar('sistema.prueba', null, $user, ['nombre' => $user->name]);

        $this->assertSame(2, $creadas->count());
        $this->assertDatabaseHas('notificaciones', [
            'evento' => 'sistema.prueba', 'user_id' => $user->id,
            'canal' => Notificacion::CANAL_DATABASE, 'estado' => Notificacion::PENDIENTE,
        ]);
        $this->assertDatabaseHas('notificaciones', [
            'evento' => 'sistema.prueba', 'user_id' => $user->id,
            'canal' => Notificacion::CANAL_MAIL, 'estado' => Notificacion::PENDIENTE,
        ]);
        // WhatsApp NO por default (stub hasta D-007, opt-in).
        $this->assertDatabaseMissing('notificaciones', [
            'user_id' => $user->id, 'canal' => Notificacion::CANAL_WHATSAPP,
        ]);
        Queue::assertPushed(EnviarNotificacion::class, 2);
    }

    public function test_opt_out_de_mail_respeta_preferencia(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        PreferenciaCanal::create([
            'user_id' => $user->id, 'evento' => 'sistema.prueba',
            'canal' => Notificacion::CANAL_MAIL, 'habilitado' => false,
        ]);

        $creadas = $this->dispatcher()->despachar('sistema.prueba', null, $user);

        $this->assertSame(1, $creadas->count());
        $this->assertSame(Notificacion::CANAL_DATABASE, $creadas->first()->canal);
    }

    public function test_whatsapp_es_opt_in(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        PreferenciaCanal::create([
            'user_id' => $user->id, 'evento' => 'sistema.prueba',
            'canal' => Notificacion::CANAL_WHATSAPP, 'habilitado' => true,
        ]);

        $creadas = $this->dispatcher()->despachar('sistema.prueba', null, $user);

        $this->assertSame(3, $creadas->count()); // database + mail + whatsapp
        $this->assertDatabaseHas('notificaciones', [
            'user_id' => $user->id, 'canal' => Notificacion::CANAL_WHATSAPP,
        ]);
    }

    public function test_destinatario_externo_solo_recibe_mail(): void
    {
        Queue::fake();

        $creadas = $this->dispatcher()->despachar('sistema.prueba', null, 'externo@example.com');

        $this->assertSame(1, $creadas->count());
        $this->assertDatabaseHas('notificaciones', [
            'canal' => Notificacion::CANAL_MAIL, 'destinatario' => 'externo@example.com', 'user_id' => null,
        ]);
    }

    public function test_evento_desconocido_es_rechazado(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->dispatcher()->despachar('evento.inventado', null, 'x@example.com');
    }

    public function test_plantilla_de_configuracion_renderiza_placeholders(): void
    {
        Queue::fake();
        Configuracion::create([
            'clave' => 'notif_plantilla_sistema_prueba',
            'valor' => json_encode(['asunto' => 'Hola {nombre}', 'cuerpo' => 'Prueba para {nombre}.']),
            'tipo' => Configuracion::TIPO_JSON,
            'grupo' => 'notificaciones',
            'descripcion' => 'Plantilla de prueba',
        ]);

        $creadas = $this->dispatcher()->despachar('sistema.prueba', null, 'x@example.com', ['nombre' => 'Mauricio']);

        $this->assertSame('Hola Mauricio', $creadas->first()->titulo);
        $this->assertSame('Prueba para Mauricio.', $creadas->first()->cuerpo);
    }

    public function test_sin_plantilla_usa_la_etiqueta_del_catalogo(): void
    {
        Queue::fake();

        $creadas = $this->dispatcher()->despachar('sistema.prueba', null, 'x@example.com');

        $this->assertSame(Notificacion::EVENTOS['sistema.prueba'], $creadas->first()->titulo);
    }

    // --- Job de envio: transiciones de estado (PLAN-M15 §1.2) ---

    public function test_job_envia_mail_y_marca_enviada(): void
    {
        Queue::fake();
        Mail::fake();
        $user = User::factory()->create();
        $mail = $this->dispatcher()->despachar('sistema.prueba', null, $user)
            ->firstWhere('canal', Notificacion::CANAL_MAIL);

        (new EnviarNotificacion($mail->id))->handle();

        Mail::assertSent(NotificacionMail::class, fn ($m) => $m->hasTo($user->email));
        $fresh = $mail->fresh();
        $this->assertSame(Notificacion::ENVIADA, $fresh->estado);
        $this->assertNotNull($fresh->enviada_at);
    }

    public function test_job_fallo_marca_fallida_con_backoff(): void
    {
        Queue::fake();
        $this->freezeTime();
        // Canal mail que siempre falla (simula SMTP caido).
        $this->app->bind(CanalMail::class, fn () => new class implements Canal
        {
            public function enviar(Notificacion $notificacion): void
            {
                throw new RuntimeException('SMTP caído (simulado)');
            }
        });
        $mail = $this->dispatcher()->despachar('sistema.prueba', null, 'x@example.com')->first();

        (new EnviarNotificacion($mail->id))->handle();

        $fresh = $mail->fresh();
        $this->assertSame(Notificacion::FALLIDA, $fresh->estado);
        $this->assertSame(1, $fresh->intentos);
        $this->assertSame('SMTP caído (simulado)', $fresh->ultimo_error);
        // Primer reintento: +5 min (default notif_backoff_minutos [5, 15, 60]).
        // Comparacion a nivel de segundo: la columna datetime trunca microsegundos.
        $this->assertSame(
            now()->addMinutes(5)->toDateTimeString(),
            $fresh->programada_para->toDateTimeString(),
        );
    }

    public function test_job_guarda_el_error_completo_mas_alla_del_corte_viejo(): void
    {
        Queue::fake();
        // Micro-backlog M15-b: el job truncaba ultimo_error a 1000 chars y la
        // cola del error SMTP (donde suele estar la causa) se perdia ANTES de
        // llegar a la vista. Un error de 1500 chars debe guardarse integro.
        $errorLargo = str_repeat('a', 1400).' [CAUSA-REAL-AL-FINAL]';
        $this->app->bind(CanalMail::class, fn () => new class($errorLargo) implements Canal
        {
            public function __construct(private string $mensaje)
            {
            }

            public function enviar(Notificacion $notificacion): void
            {
                throw new RuntimeException($this->mensaje);
            }
        });
        $mail = $this->dispatcher()->despachar('sistema.prueba', null, 'x@example.com')->first();

        (new EnviarNotificacion($mail->id))->handle();

        $this->assertSame($errorLargo, $mail->fresh()->ultimo_error);
        $this->assertStringEndsWith('[CAUSA-REAL-AL-FINAL]', $mail->fresh()->ultimo_error);
    }

    public function test_job_agota_reintentos_y_queda_fallida_terminal(): void
    {
        Queue::fake();
        $this->app->bind(CanalMail::class, fn () => new class implements Canal
        {
            public function enviar(Notificacion $notificacion): void
            {
                throw new RuntimeException('sigue caído');
            }
        });
        $mail = $this->dispatcher()->despachar('sistema.prueba', null, 'x@example.com')->first();
        // Ya fallo 2 veces; el proximo intento es el 3° = notif_reintentos_max default.
        $mail->update(['intentos' => 2]);

        (new EnviarNotificacion($mail->id))->handle();

        $fresh = $mail->fresh();
        $this->assertSame(Notificacion::FALLIDA, $fresh->estado);
        $this->assertSame(3, $fresh->intentos);
        $this->assertNull($fresh->programada_para); // terminal: el reintentador la ignora
    }

    public function test_job_ignora_notificacion_ya_procesada(): void
    {
        Queue::fake();
        Mail::fake();
        $mail = $this->dispatcher()->despachar('sistema.prueba', null, 'x@example.com')->first();
        $mail->update(['estado' => Notificacion::ENVIADA, 'enviada_at' => now()]);

        (new EnviarNotificacion($mail->id))->handle();

        Mail::assertNothingSent(); // doble encolado no re-envia
    }

    public function test_canal_database_se_marca_enviada_sin_transporte(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $database = $this->dispatcher()->despachar('sistema.prueba', null, $user)
            ->firstWhere('canal', Notificacion::CANAL_DATABASE);

        (new EnviarNotificacion($database->id))->handle();

        $this->assertSame(Notificacion::ENVIADA, $database->fresh()->estado);
        // Y aparece en la campanita (no-leidas).
        $this->assertSame(1, Notificacion::campanitaDe($user->id)->count());
    }

    public function test_stub_whatsapp_loguea_y_tiene_exito(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        PreferenciaCanal::create([
            'user_id' => $user->id, 'evento' => 'sistema.prueba',
            'canal' => Notificacion::CANAL_WHATSAPP, 'habilitado' => true,
        ]);
        $whatsapp = $this->dispatcher()->despachar('sistema.prueba', null, $user)
            ->firstWhere('canal', Notificacion::CANAL_WHATSAPP);

        (new EnviarNotificacion($whatsapp->id))->handle();

        $this->assertSame(Notificacion::ENVIADA, $whatsapp->fresh()->estado);
    }

    // --- Preferencias: auditoria (ajuste visto bueno 2026-07-02) ---

    public function test_preferencia_es_auditable_y_notificacion_no(): void
    {
        $this->assertContains(
            \OwenIt\Auditing\Contracts\Auditable::class,
            class_implements(PreferenciaCanal::class),
            'PreferenciaCanal debe auditarse (quién se dio de baja de qué).'
        );
        $this->assertNotContains(
            \OwenIt\Auditing\Contracts\Auditable::class,
            class_implements(Notificacion::class) ?: [],
            'Notificacion NO se audita: alto volumen, la fila es su propia traza.'
        );
    }
}
