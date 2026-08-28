<?php

namespace App\Support;

use App\Models\Configuracion;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;

/**
 * FUENTE ÚNICA de «quién recibe cada aviso» (pedido del dueño 28-08-2026: «es
 * súper confuso saber qué se le está mostrando a quién»). Antes de esta clase
 * los destinatarios vivían repartidos en 9 constantes ROLES_AVISO_*, 4 listas
 * inline y 5 despachos por permiso, en 12 archivos emisores — no había ningún
 * lugar donde verlos ni cambiarlos.
 *
 * Cada evento editable tiene su clave `notif_roles_{evento}` en Configuración
 * (simétrica a `notif_plantilla_*`); el valor de HOY queda como DEFAULT, así
 * que con la BD virgen el comportamiento es byte-idéntico al histórico. La
 * pantalla que edita la matriz es Configuración → Avisos y destinatarios.
 *
 * Esta clase decide QUIÉN recibe; PreferenciaCanal decide por qué CANAL lo
 * recibe cada usuario (correo sí/no). Son capas distintas y no se mezclan.
 * Los filtros de 2º nivel (cartera `esVisiblePara`, anti-autoaviso, el push
 * del vendedor del cliente) siguen viviendo en cada emisor: acá solo se
 * resuelve la lista base de roles.
 */
final class AudienciasNotificacion
{
    /** Grupo de Configuracion de las claves notif_roles_* (el index técnico las excluye; se editan en su pantalla). */
    public const GRUPO = 'notif_destinatarios';

    /**
     * Defaults HISTÓRICOS: roles por evento editable, escritos UNA sola vez.
     * Los comentarios conservan la decisión del dueño que fijó cada lista
     * (migrados de las constantes ROLES_AVISO_* que esta clase reemplaza).
     */
    public const DEFAULTS = [
        // Taller · el técnico que repara + ventas; el jefe de bodega NO va:
        // ya ve la cola «por confirmar» en la barra.
        'taller.ingresado' => ['tecnico', 'jefe_ventas', 'vendedor', 'admin'],
        // Cierres de la orden: es VENTAS quien llama al cliente. El técnico NO
        // va (es quien marca el estado; avisarle de su propia acción es ruido).
        // El reparto fino lo decide avisarACartera(), no esta lista.
        'taller.reparado' => ['jefe_ventas', 'vendedor', 'admin'],
        'taller.sin_solucion' => ['jefe_ventas', 'vendedor', 'admin'],
        // «Ruta completa de la máquina» (dueño): técnico + ventas + admin.
        'taller.listo_para_retiro' => ['tecnico', 'jefe_ventas', 'vendedor', 'admin'],
        'cotizacion.enviada' => ['tecnico', 'jefe_ventas', 'vendedor', 'admin'],
        'cotizacion.respondida' => ['tecnico', 'jefe_ventas', 'vendedor', 'admin'],
        'cotizacion.retiro_avisado' => ['tecnico', 'jefe_ventas', 'vendedor', 'admin'],
        // Aviso de PLATA: sin el técnico (dueño 07-08 — el taller no coordina
        // cobros; el aviso de pago era ruido en su campanita).
        'cotizacion.autorizada' => ['jefe_ventas', 'vendedor', 'admin'],
        'garantia.detalle_enviado' => ['tecnico', 'jefe_ventas', 'vendedor', 'admin'],
        // Terreno · quienes conversan con el cliente y coordinan la visita.
        'terreno.solicitada' => ['jefe_ventas', 'vendedor', 'admin'],
        'terreno.confirmada' => ['jefe_ventas', 'vendedor', 'admin'],
        'terreno.rechazada' => ['jefe_ventas', 'vendedor', 'admin'],
        // Cierres de terreno: jefe de ventas y admin SIEMPRE (si dependiera
        // solo del vendedor, con las carteras sin asignar no llegaría a nadie).
        // El vendedor del cliente se SUMA en el emisor, no en esta lista.
        'terreno.realizado' => ['jefe_ventas', 'admin'],
        'terreno.no_realizado' => ['jefe_ventas', 'admin'],
        // Traslados sucursal → casa matriz (dueño 03-08).
        'traslado.despachado' => ['tecnico', 'jefe_bodega', 'jefe_ventas', 'admin'],
        // De vuelta a quien despachó: cierra el círculo (salió, llegó).
        'traslado.recibido' => ['jefe_sucursal', 'admin', 'jefe_ventas'],
        // Una DIFERENCIA la ve jefatura además de las dos puntas.
        'traslado.diferencias' => ['admin', 'jefe_ventas', 'jefe_bodega', 'jefe_sucursal', 'tecnico'],
        'devolucion.solicitada' => ['jefe_bodega', 'jefe_ventas', 'admin'],
        // R15: jefe_despacho decide, jefe_logistica arma las hojas, admin supervisa.
        'despacho.parada_rechazada' => ['jefe_despacho', 'jefe_logistica', 'admin'],
        // ── Ex-por-PERMISO (cambio semántico aceptado por el dueño 28-08) ──
        // Estos 6 se despachaban a User::permission(...); ahora van por rol
        // editable. El default = los roles que HOY tienen ese permiso según
        // RolesAndPermissionsSeeder, así la BD virgen avisa a la misma gente.
        // Un rol custom con el permiso deja de recibir salvo que se marque.
        'vehiculo.documento_por_vencer' => ['jefe_logistica', 'conductor', 'admin'],
        'vehiculo.documento_vencido' => ['jefe_logistica', 'conductor', 'admin'],
        'produccion.meta_en_riesgo' => ['jefe_bodega', 'admin'],
        'molde.umbral_mantencion' => ['jefe_bodega', 'admin'],
        'molde.correctiva_pendiente' => ['jefe_bodega', 'admin'],
        'bodega.nueva' => ['admin'],
    ];

    /**
     * Eventos cuyo destinatario NO es una lista editable de roles: la regla en
     * español que la matriz muestra como fila informativa. El candado
     * estructural exige DEFAULTS ∪ NO_EDITABLES == Notificacion::EVENTOS: un
     * evento nuevo sin clasificar acá pone la suite en rojo.
     */
    public const NO_EDITABLES = [
        'sistema.prueba' => 'A quien dispara la prueba (uno mismo).',
        'aprobacion.solicitada' => 'Al rol aprobador que definen las reglas de aprobación.',
        'aprobacion.escalada' => 'Al rol al que escala según las reglas de aprobación.',
        'aprobacion.resuelta' => 'A quien pidió la aprobación (el solicitante).',
        'terreno.agendado' => 'Al técnico asignado; si no hay, a todos los técnicos industriales.',
        'terreno.reagendado' => 'Al técnico asignado (y al anterior, si se reasignó).',
        'terreno.cancelado' => 'Al técnico asignado; si no hay, a todos los técnicos industriales.',
        'devolucion.recibida' => 'Al cliente que declaró la devolución, por correo.',
        'devolucion.resuelta' => 'Al cliente que declaró la devolución, por correo.',
        'bodega.baja_completada' => 'A quien solicitó la baja de la bodega.',
        'bodega.stock_en_baja' => 'A quien solicitó la baja de la bodega.',
        'mensaje.recibido' => 'A quien recibe el mensaje.',
    ];

    /**
     * Familia de cada evento por su prefijo → título de la tarjeta en la
     * pantalla de avisos. Derivado acá y no a mano en la vista.
     */
    public const FAMILIAS = [
        'taller' => 'Taller',
        'garantia' => 'Taller',
        'cotizacion' => 'Cotizaciones',
        'terreno' => 'Terreno',
        'traslado' => 'Traslados al taller',
        'devolucion' => 'Devoluciones',
        'despacho' => 'Despachos',
        'vehiculo' => 'Flota',
        'produccion' => 'Producción',
        'molde' => 'Producción',
        'bodega' => 'Bodegas',
        'aprobacion' => 'Aprobaciones',
        'mensaje' => 'Mensajes',
        'sistema' => 'Sistema',
    ];

    /** Clave de Configuracion del evento (simétrica a notif_plantilla_*). */
    public static function clave(string $evento): string
    {
        return 'notif_roles_'.str_replace('.', '_', $evento);
    }

    public static function esEditable(string $evento): bool
    {
        return isset(self::DEFAULTS[$evento]);
    }

    /**
     * Roles vigentes del evento, con el cinturón del consumidor (patrón
     * getLista): la regla es «el vacío deliberado se respeta; el vacío por
     * descomposición cae al default».
     *
     * - clave ausente / valor que no es array → DEFAULT (nunca sembrada, o rota);
     * - array vacío → [] — el dueño desmarcó todos los roles a propósito
     *   (la pantalla lo muestra como «Nadie recibe este aviso»);
     * - array con elementos → se filtran los que no son string y los roles que
     *   ya no existen en la BD; si con eso queda vacío, DEFAULT (una lista que
     *   se pudrió no puede silenciar un aviso sin que nadie lo haya decidido).
     *
     * @return list<string>
     */
    public static function rolesPara(string $evento): array
    {
        if (! self::esEditable($evento)) {
            throw new \InvalidArgumentException("El evento [{$evento}] no tiene audiencia editable por roles.");
        }

        $crudo = Configuracion::get(self::clave($evento));

        if (! is_array($crudo)) {
            return self::DEFAULTS[$evento];
        }

        if ($crudo === []) {
            return []; // silencio deliberado: se respeta.
        }

        $existentes = Role::pluck('name')->all();
        $vigentes = array_values(array_intersect(
            array_unique(array_filter($crudo, 'is_string')),
            $existentes,
        ));

        return $vigentes === [] ? self::DEFAULTS[$evento] : $vigentes;
    }

    /**
     * Usuarios destinatarios del evento, deduplicados (absorbe el
     * `->unique('id')` que cada emisor repetía). Con la audiencia silenciada
     * devuelve una colección vacía: el `each` del emisor no hace nada y nada
     * revienta. OJO: no llamar User::role([]) — spatie no define ese caso.
     *
     * @return Collection<int, User>
     */
    public static function destinatarios(string $evento): Collection
    {
        $roles = self::rolesPara($evento);

        if ($roles === []) {
            return new Collection;
        }

        return User::role($roles)->get()->unique('id')->values();
    }
}
