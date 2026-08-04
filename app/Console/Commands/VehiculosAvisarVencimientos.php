<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoAviso;
use App\Services\Notificaciones\NotificacionDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Throwable;

/**
 * Aviso automático de vencimiento de documentos de la flota (decisión del
 * dueño 04-08-2026: «semáforo + aviso 30 días antes»).
 *
 * Es lo que la planilla no puede hacer: avisar sin que alguien la abra. El
 * semáforo de la pantalla muestra el estado; este comando lo empuja.
 *
 * Dos hitos por documento: `por_vencer` (faltan <= 30 días) y `vencido`. Cada
 * (vehículo × documento × hito × fecha de vencimiento) se avisa UNA sola vez
 * —lo garantiza el unique de `vehiculo_avisos`— así que correr todos los días
 * no genera ruido. Al renovar el documento la fecha cambia y el próximo
 * vencimiento vuelve a ser avisable.
 */
class VehiculosAvisarVencimientos extends Command
{
    protected $signature = 'vehiculos:avisar-vencimientos {--dry-run : Muestra lo que avisaría, sin enviar ni registrar}';

    protected $description = 'Avisa por la app cuando un documento de un vehículo está por vencer (30 días) o venció';

    /**
     * Ventana hacia atrás del hito «vencido»: solo se avisa lo que venció en
     * los últimos 30 días.
     *
     * Es deliberado y no una limitación. La planilla trae documentos vencidos
     * hace años (hay revisiones técnicas de 2022), así que avisar "todo lo
     * vencido" habría disparado un centenar de notificaciones en la primera
     * corrida y enterrado justo lo que vence esta semana. La deuda histórica se
     * ve en rojo en el listado —permanente y sin caducar— que es su lugar.
     */
    private const DIAS_VENTANA_VENCIDO = 30;

    public function handle(NotificacionDispatcher $dispatcher): int
    {
        $seco = (bool) $this->option('dry-run');

        $destinatarios = $this->destinatarios();

        if ($destinatarios->isEmpty()) {
            // Sin destinatarios NO se registra nada a propósito: si marcáramos
            // los avisos como enviados, la novedad se perdería para siempre y
            // el día que exista el perfil de logística nadie se enteraría.
            $this->warn('Nadie tiene permiso para ver vehículos todavía: no se avisa ni se registra nada.');

            return self::SUCCESS;
        }

        $enviados = 0;
        $filas = [];

        foreach (Vehiculo::activos()->orderBy('ppu')->get() as $vehiculo) {
            foreach ($this->hitosNuevos($vehiculo, $seco) as $hito => $documentos) {
                if ($documentos === []) {
                    continue;
                }

                $filas[] = [$vehiculo->ppu, $hito, count($documentos), collect($documentos)->pluck('label')->implode(', ')];

                if ($seco) {
                    continue;
                }

                $enviados += $this->avisar($dispatcher, $vehiculo, $hito, $documentos, $destinatarios);
            }
        }

        if ($filas === []) {
            $this->info('Sin novedades: ningún documento entró en los 30 días ni venció recientemente.');

            return self::SUCCESS;
        }

        $this->table(['Patente', 'Hito', 'Docs', 'Documentos'], $filas);
        $this->info($seco
            ? 'Simulación: no se envió ni se registró nada.'
            : "Avisos despachados: {$enviados} (".$destinatarios->count().' destinatario(s)).');

        return self::SUCCESS;
    }

    /**
     * Quién recibe el aviso: los que pueden ver la flota (hoy gerencia y el
     * jefe de logística; cobranzas cuando exista su perfil, sin tocar código).
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function destinatarios(): \Illuminate\Support\Collection
    {
        $permisos = Permission::whereIn('name', ['ver vehiculos', 'manage vehiculos'])
            ->pluck('name')
            ->all();

        if ($permisos === []) {
            return collect();
        }

        return User::permission($permisos)->get()->unique('id')->values();
    }

    /**
     * Documentos de este vehículo que TODAVÍA no se han avisado, agrupados por
     * hito. Reclamar el aviso (crear la fila) va antes de notificar: si el
     * envío falla, no se reintenta desde acá — la cola de M15 ya reintenta.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function hitosNuevos(Vehiculo $vehiculo, bool $seco): array
    {
        $nuevos = [VehiculoAviso::HITO_POR_VENCER => [], VehiculoAviso::HITO_VENCIDO => []];

        foreach ($vehiculo->documentos() as $documento) {
            $hito = match ($documento['estado']) {
                Vehiculo::DOC_POR_VENCER => VehiculoAviso::HITO_POR_VENCER,
                Vehiculo::DOC_VENCIDO => $documento['dias'] >= -self::DIAS_VENTANA_VENCIDO
                    ? VehiculoAviso::HITO_VENCIDO
                    : null,
                default => null,
            };

            if ($hito === null) {
                continue;
            }

            // GOTCHA: `vence` se pasa como Carbon a medianoche, NO como string
            // 'Y-m-d'. El cast `date` del modelo ESCRIBE 'Y-m-d H:i:s', así que
            // un firstOrCreate buscando '2026-08-09' no encontraba la fila que
            // el mismo comando había escrito como '2026-08-09 00:00:00' y
            // reventaba contra el unique. Con un Carbon, la lectura y la
            // escritura usan el mismo formato.
            $clave = [
                'vehiculo_id' => $vehiculo->id,
                'documento' => $documento['clave'],
                'hito' => $hito,
                'vence' => $documento['vence']->copy()->startOfDay(),
            ];

            if ($seco) {
                if (! VehiculoAviso::where($clave)->exists()) {
                    $nuevos[$hito][] = $documento;
                }

                continue;
            }

            $aviso = VehiculoAviso::firstOrCreate($clave, ['avisado_at' => now()]);

            if ($aviso->wasRecentlyCreated) {
                $nuevos[$hito][] = $documento;
            }
        }

        return $nuevos;
    }

    /**
     * Un aviso por vehículo y por hito, con TODOS sus documentos dentro.
     *
     * Agrupado a propósito: RT y emisiones casi siempre vencen el mismo día, y
     * permiso de circulación con SOAP también, así que avisar por documento
     * dejaría cuatro notificaciones del mismo camión el mismo día.
     *
     * @param  list<array<string, mixed>>  $documentos
     * @param  \Illuminate\Support\Collection<int, User>  $destinatarios
     */
    private function avisar(
        NotificacionDispatcher $dispatcher,
        Vehiculo $vehiculo,
        string $hito,
        array $documentos,
        \Illuminate\Support\Collection $destinatarios,
    ): int {
        $evento = $hito === VehiculoAviso::HITO_VENCIDO
            ? 'vehiculo.documento_vencido'
            : 'vehiculo.documento_por_vencer';

        $datos = [
            'patente' => $vehiculo->ppu,
            'vehiculo' => $vehiculo->nombre,
            'base' => $vehiculo->base ?: 'sin base asignada',
            'conductor' => $vehiculo->conductor_nombre ?: 'sin conductor asignado',
            'total' => count($documentos),
            'documentos' => collect($documentos)
                ->map(fn (array $d) => '· '.$d['label'].': '.$d['vence']->format('d-m-Y')
                    .' ('.mb_strtolower(Vehiculo::plazoLabel($d['dias'])).')')
                ->implode("\n"),
            'url' => route('admin.vehiculos.show', $vehiculo),
        ];

        $enviados = 0;

        foreach ($destinatarios as $destinatario) {
            try {
                $dispatcher->despachar($evento, $vehiculo, $destinatario, $datos);
                $enviados++;
            } catch (Throwable $e) {
                // Un destinatario con el correo mal configurado no puede dejar
                // sin aviso a los demás ni tumbar el cron.
                Log::warning('Aviso de vencimiento de vehículo no despachado', [
                    'vehiculo' => $vehiculo->ppu,
                    'evento' => $evento,
                    'user_id' => $destinatario->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $enviados;
    }
}
