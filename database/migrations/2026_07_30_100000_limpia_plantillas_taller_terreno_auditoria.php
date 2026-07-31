<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Limpia el CUERPO de las 7 plantillas de notificaciones internas del taller y
 * terreno, segun los hallazgos de la auditoria de la ruta completa (30-07-2026):
 *
 *   1. QUITA la {url} cruda del final. El correo de notificacion ya pinta el
 *      boton «Abrir en DaliGo» con ESE MISMO enlace (emails/notificacion.blade.php),
 *      asi que el link salia dos veces en cada correo interno. Las plantillas de
 *      aprobacion ya no la llevaban; el taller y terreno habian quedado atras.
 *   2. taller_ingresado: agrega {folio} (el dato con el que se busca la orden) y
 *      reemplaza «Revisalo y confirmalo» por «Falta confirmar la recepcion» —
 *      el imperativo le llegaba tambien a vendedores, que NO tienen el permiso
 *      'confirmar servicio tecnico'.
 *   3. cotizacion_autorizada: «Ventas autorizo» pasa a «{autorizada_por} autorizo»
 *      (el permiso lo tienen tambien tecnico y vendedor, asi que atribuia mal la
 *      decision) y «Tecnico: puedes proceder» pasa a tercera persona, porque el
 *      aviso va a cuatro roles y tuteaba a uno solo.
 *   4. terreno_rechazada: «Se aviso al cliente por correo» pasa a {aviso_cliente},
 *      que rellena AgendaTrabajo::avisarRechazoInterno con lo que paso de verdad
 *      (el envio puede fallar, y ahi hay que llamar al cliente).
 *
 * Por que una migracion y no solo el seeder: ConfiguracionSeeder usa firstOrCreate
 * (nunca pisa lo existente), asi que en staging/prod el seeder NO actualiza estas
 * filas. Esta migracion es la unica via.
 *
 * Seguro por diseno (patron NOTIF-1): solo cambia el cuerpo si sigue siendo el
 * default anterior — respeta cualquier plantilla editada desde la UI —, conserva
 * el `asunto`, es idempotente (al re-correr ya no coincide el viejo → no-op) e
 * invalida la cache rememberForever de Configuracion (deploy.sh no corre
 * cache:clear).
 *
 * OJO con el punto de partida: para las 5 claves que toco la migracion
 * 2026_07_22_180000, el default vigente en una BD ya migrada es su valor 'new';
 * por eso los 'old' de aca son exactamente esos.
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
     * Cuerpos antes/despues por clave. El 'new' debe calzar EXACTO con el default
     * del seeder para que el gate anti-pisado siga funcionando en el proximo cambio.
     *
     * @return array<string, array{old: string, new: string}>
     */
    private function plantillas(): array
    {
        return [
            'notif_plantilla_taller_ingresado' => [
                'old' => "{cliente} ingresó {maquinas} en {sucursal} ({condicion}).\nDetalle: {equipo}\n\nRevísalo y confírmalo en el listado: {url}",
                'new' => "Orden {folio} · {cliente} ingresó {maquinas} en {sucursal} ({condicion}).\nDetalle: {equipo}\nFalta confirmar la recepción.",
            ],
            'notif_plantilla_cotizacion_enviada' => [
                'old' => "Se envió la cotización de la orden {folio} a {cliente} por {total}.\nEquipo: {equipo}\nEnviada por: {enviada_por}.\n\nVer la orden: {url}",
                'new' => "Se envió la cotización de la orden {folio} a {cliente} por {total}.\nEquipo: {equipo}\nEnviada por: {enviada_por}.",
            ],
            'notif_plantilla_cotizacion_respondida' => [
                'old' => "El cliente {cliente} respondió la cotización de la orden {folio}: {respuesta}.\nEquipo: {equipo} · Monto: {total}.\n\nVer la orden: {url}",
                'new' => "El cliente {cliente} respondió la cotización de la orden {folio}: {respuesta}.\nEquipo: {equipo} · Monto: {total}.",
            ],
            'notif_plantilla_cotizacion_autorizada' => [
                'old' => "Ventas autorizó la reparación de la orden {folio} ({cliente}) por {total}.\nEquipo: {equipo}\nPago: {pago} · autorizó: {autorizada_por}.\nTécnico: puedes proceder con la reparación.\n\nVer la orden: {url}",
                'new' => "{autorizada_por} autorizó la reparación de la orden {folio} ({cliente}) por {total}.\nEquipo: {equipo}\nPago: {pago}.\nEl técnico ya puede proceder con la reparación.",
            ],
            'notif_plantilla_terreno_solicitada' => [
                'old' => "{cliente} pidió {tipo} en {ciudad}.\nServicio: {servicio} · Dirección: {direccion}\nTeléfono: {telefono} · Prefiere: {preferida}\nDetalle del cliente: {descripcion}\n\nCoordínala en la agenda de terreno: {url}",
                'new' => "{cliente} pidió {tipo} en {ciudad}.\nServicio: {servicio} · Dirección: {direccion}\nTeléfono: {telefono} · Prefiere: {preferida}\nDetalle del cliente: {descripcion}",
            ],
            'notif_plantilla_terreno_confirmada' => [
                'old' => "{cliente} respondió a su visita del {fecha}: {respuesta}.\nComentario del cliente: {nota}\n\nVer en la agenda: {url}",
                'new' => "{cliente} respondió a su visita del {fecha}: {respuesta}.\nComentario del cliente: {nota}",
            ],
            'notif_plantilla_terreno_rechazada' => [
                'old' => "Se rechazó la solicitud de {cliente} ({tipo}).\nMotivo: {motivo}\nRechazó: {rechazado_por} · Teléfono: {telefono} · Prefería: {preferida}\nSe avisó al cliente por correo.\n\nVer en la agenda: {url}",
                'new' => "Se rechazó la solicitud de {cliente} ({tipo}).\nMotivo: {motivo}\nRechazó: {rechazado_por} · Teléfono: {telefono} · Prefería: {preferida}\n{aviso_cliente}",
            ],
        ];
    }
};
