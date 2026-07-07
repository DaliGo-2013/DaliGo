<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * Verifica que las 4 syncs de Bsale queden registradas en el scheduler (lo que
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

    public function test_las_syncs_estan_agendadas(): void
    {
        $comandos = implode("\n", $this->comandosAgendados());

        $this->assertStringContainsString('bsale:sync-catalog', $comandos);
        $this->assertStringContainsString('bsale:sync-clients', $comandos);
        $this->assertStringContainsString('bsale:sync-prices', $comandos);
        $this->assertStringContainsString('bsale:sync-stock', $comandos);
    }

    public function test_las_syncs_van_en_la_grilla_de_15(): void
    {
        // I-01 (2026-07-07): HostGator reescribe los crons <15 min, así que el
        // cron real es `*/15` (dispara :00/:15/:30/:45). Toda sync debe caer
        // EXACTO en esa grilla o no corre jamás (asi murieron :20/:40/:50).
        $esperadas = [
            'bsale:sync-catalog' => '0 * * * *',
            'bsale:sync-clients' => '15 * * * *',
            'bsale:sync-prices' => '30 * * * *',
            'bsale:sync-stock' => '45 * * * *',
        ];

        foreach ($esperadas as $comando => $expresion) {
            $evento = collect(app(Schedule::class)->events())
                ->first(fn ($e) => str_contains((string) $e->command, $comando));

            $this->assertNotNull($evento, "Falta la sync {$comando} en el scheduler.");
            $this->assertSame(
                $expresion,
                $evento->expression,
                "{$comando} fuera de la grilla */15: con el cron de cPanel en */15 no correría jamás.",
            );
        }
    }

    public function test_las_syncs_no_se_solapan(): void
    {
        $eventos = collect(app(Schedule::class)->events())
            ->filter(fn ($e) => str_contains((string) $e->command, 'bsale:sync-'));

        $this->assertCount(4, $eventos);
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
