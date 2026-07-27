<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Enriquece el CUERPO de 3 plantillas de notificaciones INTERNAS con campos que
 * ya estaban a mano pero no se mostraban:
 *   - terreno.solicitada  → servicio, dirección, detalle escrito por el cliente.
 *   - terreno.rechazada   → quién rechazó, teléfono, fecha preferida.
 *   - cotizacion.{enviada,respondida,autorizada} → equipo (tipo + modelo).
 *
 * Por qué una migración y no solo el seeder: ConfiguracionSeeder usa
 * firstOrCreate (nunca pisa lo existente), así que en entornos ya sembrados
 * (staging/prod) el seeder NO actualiza estas filas. Esta migración es la única
 * vía para propagar el cambio a esas BD.
 *
 * Seguro por diseño: solo cambia el cuerpo si sigue siendo el default anterior
 * (respeta plantillas editadas desde la UI), conserva el `asunto` tal cual, es
 * idempotente (al re-correr ya no coincide el default viejo → no-op) e invalida
 * la cache rememberForever de Configuracion (deploy.sh no corre cache:clear).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->intercambiarCuerpos('old', 'new');
    }

    public function down(): void
    {
        $this->intercambiarCuerpos('new', 'old');
    }

    /**
     * Cambia el `cuerpo` de cada plantilla de $desde → $hacia SOLO si su cuerpo
     * actual coincide con el default $desde. Conserva el resto del JSON (asunto).
     *
     * @param  'old'|'new'  $desde
     * @param  'old'|'new'  $hacia
     */
    private function intercambiarCuerpos(string $desde, string $hacia): void
    {
        foreach ($this->plantillas() as $clave => $cuerpos) {
            $row = DB::table('configuraciones')->where('clave', $clave)->first();
            if (! $row) {
                continue;
            }

            $valor = json_decode((string) $row->valor, true);
            if (! is_array($valor) || ($valor['cuerpo'] ?? null) !== $cuerpos[$desde]) {
                continue; // ausente, ilegible o personalizado → no se toca.
            }

            $valor['cuerpo'] = $cuerpos[$hacia];
            DB::table('configuraciones')
                ->where('clave', $clave)
                ->update(['valor' => json_encode($valor, JSON_UNESCAPED_UNICODE)]);

            Cache::forget('config.'.$clave);
        }
    }

    /**
     * Cuerpos antes/después por clave. Deben calzar EXACTO con el default del
     * seeder (ConfiguracionSeeder) para que el gate anti-pisado funcione.
     *
     * @return array<string, array{old: string, new: string}>
     */
    private function plantillas(): array
    {
        return [
            'notif_plantilla_terreno_solicitada' => [
                'old' => "{cliente} pidió {tipo} en {ciudad}.\nTeléfono: {telefono} · Prefiere: {preferida}\n\nCoordínala en la agenda de terreno: {url}",
                'new' => "{cliente} pidió {tipo} en {ciudad}.\nServicio: {servicio} · Dirección: {direccion}\nTeléfono: {telefono} · Prefiere: {preferida}\nDetalle del cliente: {descripcion}\n\nCoordínala en la agenda de terreno: {url}",
            ],
            'notif_plantilla_terreno_rechazada' => [
                'old' => "Se rechazó la solicitud de {cliente} ({tipo}).\nMotivo: {motivo}\nSe avisó al cliente por correo.\n\nVer en la agenda: {url}",
                'new' => "Se rechazó la solicitud de {cliente} ({tipo}).\nMotivo: {motivo}\nRechazó: {rechazado_por} · Teléfono: {telefono} · Prefería: {preferida}\nSe avisó al cliente por correo.\n\nVer en la agenda: {url}",
            ],
            'notif_plantilla_cotizacion_enviada' => [
                'old' => "Se envió la cotización de la orden {folio} a {cliente} por {total}.\nEnviada por: {enviada_por}.\n\nVer la orden: {url}",
                'new' => "Se envió la cotización de la orden {folio} a {cliente} por {total}.\nEquipo: {equipo}\nEnviada por: {enviada_por}.\n\nVer la orden: {url}",
            ],
            'notif_plantilla_cotizacion_respondida' => [
                'old' => "El cliente {cliente} respondió la cotización de la orden {folio}: {respuesta}.\nMonto: {total}.\n\nVer la orden: {url}",
                'new' => "El cliente {cliente} respondió la cotización de la orden {folio}: {respuesta}.\nEquipo: {equipo} · Monto: {total}.\n\nVer la orden: {url}",
            ],
            'notif_plantilla_cotizacion_autorizada' => [
                'old' => "Ventas autorizó la reparación de la orden {folio} ({cliente}) por {total}.\nPago: {pago} · autorizó: {autorizada_por}.\nTécnico: puedes proceder con la reparación.\n\nVer la orden: {url}",
                'new' => "Ventas autorizó la reparación de la orden {folio} ({cliente}) por {total}.\nEquipo: {equipo}\nPago: {pago} · autorizó: {autorizada_por}.\nTécnico: puedes proceder con la reparación.\n\nVer la orden: {url}",
            ],
        ];
    }
};
