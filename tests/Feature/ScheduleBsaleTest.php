<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * Verifica que las 3 syncs de Bsale queden registradas en el scheduler (lo que
 * el cron de cPanel ejecutara via `schedule:run`). No corre las syncs; solo
 * inspecciona el registro definido en routes/console.php.
 */
class ScheduleBsaleTest extends TestCase
{
    /** @return array<int, string> comandos agendados, en orden de registro */
    private function comandosAgendados(): array
    {
        return array_map(
            fn ($event) => (string) $event->command,
            app(Schedule::class)->events(),
        );
    }

    public function test_las_tres_syncs_estan_agendadas(): void
    {
        $comandos = implode("\n", $this->comandosAgendados());

        $this->assertStringContainsString('bsale:sync-catalog', $comandos);
        $this->assertStringContainsString('bsale:sync-clients', $comandos);
        $this->assertStringContainsString('bsale:sync-prices', $comandos);
    }

    public function test_las_syncs_no_se_solapan(): void
    {
        $eventos = collect(app(Schedule::class)->events())
            ->filter(fn ($e) => str_contains((string) $e->command, 'bsale:sync-'));

        $this->assertCount(3, $eventos);
        foreach ($eventos as $evento) {
            $this->assertTrue($evento->withoutOverlapping, "La sync {$evento->command} debe tener withoutOverlapping.");
        }
    }

    public function test_el_catalogo_se_agenda_antes_que_los_precios(): void
    {
        // Los precios matchean por bsale_variant_id contra productos: el catalogo
        // debe refrescarse primero.
        $comandos = $this->comandosAgendados();

        $idxCatalogo = $this->indiceDe($comandos, 'bsale:sync-catalog');
        $idxPrecios = $this->indiceDe($comandos, 'bsale:sync-prices');

        $this->assertNotNull($idxCatalogo);
        $this->assertNotNull($idxPrecios);
        $this->assertLessThan($idxPrecios, $idxCatalogo);
    }

    private function indiceDe(array $comandos, string $needle): ?int
    {
        foreach ($comandos as $i => $cmd) {
            if (str_contains($cmd, $needle)) {
                return $i;
            }
        }

        return null;
    }
}
