<?php

namespace Database\Seeders;

use App\Models\Aprobacion;
use App\Models\ReglaAprobacion;
use Illuminate\Database\Seeder;

class ReglasAprobacionSeeder extends Seeder
{
    /**
     * Reglas base del motor de aprobaciones (M14). Idempotente (firstOrCreate
     * por tipo_accion): nunca pisa una regla ajustada desde la BD. v1 siembra
     * UNA regla — ajuste de reporte de producción → aprueba `admin` (Luis),
     * sin cadena de escalamiento (admin es el tope; se estrena con M04).
     * Los consumidores futuros (M04/M05/M07/M13) agregan aquí su regla al
     * integrarse, junto con su handler y su tipo en Aprobacion::TIPOS_ACCION.
     */
    public function run(): void
    {
        ReglaAprobacion::firstOrCreate(
            ['tipo_accion' => Aprobacion::ACCION_AJUSTE_REPORTE],
            [
                'descripcion' => 'Ajuste de reporte de producción sobre el umbral de unidades',
                'activa' => true,
                'umbral_config' => 'umbral_ajuste_produccion_unidades',
                'rol_aprobador' => 'admin',
                'rol_escalamiento' => null,
            ],
        );

        // M13 · reembolso de devolución (PLAN-M13 §1.3): la magnitud es el
        // monto en CLP contra `umbral_aprobacion_clp` — la clave ya sembrada
        // que PLAN-M14 reservó para las reglas monetarias. Bajo el umbral se
        // auto-aprueba con registro; sobre él, la aprueba jefatura de ventas
        // (quien evalúa con el cliente en A-12). Admin siempre puede resolver.
        ReglaAprobacion::firstOrCreate(
            ['tipo_accion' => Aprobacion::ACCION_DEVOLUCION_REEMBOLSO],
            [
                'descripcion' => 'Reembolso de una devolución sobre el umbral en CLP',
                'activa' => true,
                'umbral_config' => 'umbral_aprobacion_clp',
                'rol_aprobador' => 'jefe_ventas',
                'rol_escalamiento' => null,
            ],
        );

        // Cita de terreno fijada por un VENDEDOR (dueño 13-08-2026): «que él siempre esté al
        // tanto de lo que hacen sus vendedores».
        //
        // SIN UMBRAL (`umbral_config` null): una cita no tiene monto que medir, así que la
        // regla matchea SIEMPRE y toda cita de vendedor espera autorización. El jefe de ventas
        // agendando no se auto-solicita nada — el motor exime a quien porta el rol aprobador.
        //
        // Sin escalamiento: si el jefe no contesta, la cita sigue esperando y el vendedor la ve
        // pendiente en «Mis solicitudes». Escalar a admin una decisión comercial sería mover la
        // responsabilidad a quien no habla con el cliente.
        ReglaAprobacion::firstOrCreate(
            ['tipo_accion' => Aprobacion::ACCION_AGENDA_CITA],
            [
                // 28-08-2026: la visita técnica entró a la lista (el jefe de ventas maneja la
                // agenda de terreno del técnico industrial), así que son los cuatro tipos. La
                // fila ya existe en producción y `firstOrCreate` no la pisa: este texto es
                // interno (no hay pantalla que lo muestre — la tarjeta de la bandeja usa
                // `Aprobacion::descripcion`, que se arma por solicitud), así que no lleva
                // migración one-shot.
                'descripcion' => 'Cita de terreno (visita técnica, mantención, reparación o instalación) fijada por un vendedor',
                'activa' => true,
                'umbral_config' => null,
                'rol_aprobador' => 'jefe_ventas',
                'rol_escalamiento' => null,
            ],
        );
    }
}
