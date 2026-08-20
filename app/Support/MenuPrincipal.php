<?php

namespace App\Support;

use App\Models\AgendaTrabajo;
use App\Models\Aprobacion;
use App\Models\Conversacion;
use App\Models\Devolucion;
use App\Models\Notificacion;
use App\Models\OrdenServicio;
use App\Models\ProduccionReporte;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Fuente única del menú principal (sidebar V4 "menú Talana"): módulos, ítems,
 * rutas, permisos, íconos y badges. La sidebar desktop y el drawer móvil se
 * renderizan desde ESTE árbol — nunca más dos copias del menú que driftean
 * (el nav viejo ya había divergido: el @canany móvil de ST omitía
 * 'gestionar tiempos reparacion' y el ítem admin Notificaciones no existía
 * en móvil).
 *
 * Misma regla anti-purga que AccesosDashboard: aquí viven DATOS (labels,
 * routes, permisos, keys de ícono/badge); las CLASES CSS viven literales en
 * los componentes x-sidebar-* / x-layout.* (Tailwind v4 purga lo que no esté
 * literal en un Blade escaneado).
 *
 * Contrato de cada entrada:
 * - Módulo CON 'items'  => acordeón (details/summary). Su visibilidad se
 *   DERIVA de los ítems: se muestra si el usuario puede ver al menos uno.
 * - Módulo SIN 'items'  => link directo de primer nivel (Dashboard,
 *   Mi producción, Aprobaciones — el acceso 1-clic del operario es a
 *   propósito, decisión del dueño 2026-07-24).
 * - 'permiso'           => permiso spatie; '|' = "cualquiera de" (canAny),
 *   igual que el middleware de routes/web.php. null = todo autenticado.
 * - 'activo'            => patrones para request()->routeIs(); todo patrón
 *   debe matchear su propia 'route' (candado en MenuPrincipalTest).
 * - 'activo_extra'      => patrones a nivel módulo para pantallas de detalle
 *   sin ítem propio (ej. show/edit de ST) — abren el acordeón igual.
 * - 'badge'             => key simbólica resuelta en badges();
 *   'badge_title' lleva ':n' que se reemplaza por el conteo (el texto
 *   "N equipo(s) por atender" es contrato de DashboardTest).
 * - 'imprenta'          => true si el rótulo del link directo se muestra en
 *   MAYÚSCULAS, como los títulos de los acordeones (pedido del dueño
 *   2026-08-04). Es una bandera de DATOS: dice "este rótulo se lee como título
 *   de sección"; las clases siguen literales en x-layout.sidebar. Dashboard
 *   queda fuera a propósito — el dueño dijo que esa estaba bien.
 */
class MenuPrincipal
{
    public const MODULOS = [
        'inicio' => [
            'label' => 'Dashboard',
            'icon' => 'home',
            'route' => 'dashboard',
            'activo' => ['dashboard'],
            'permiso' => null,
        ],
        'comercial' => [
            'label' => 'Comercial',
            'icon' => 'shopping-cart',
            'items' => [
                // Consolidación F1 (PLAN-MENU-DENSIDAD): «Precios» dejó de ser
                // ítem y vive como pestaña del Catálogo (admin/catalogo/_tabs).
                // Sus rutas van AQUÍ, en el `activo` del anfitrión — si no, la
                // página queda sin resaltado en silencio (hueco detectado en
                // F0; candado en MenuConsolidacionesTest).
                'catalogo' => ['label' => 'Catálogo', 'route' => 'admin.productos.index', 'activo' => ['admin.productos.*', 'admin.listas-precios.*'], 'permiso' => 'manage productos'],
                'clientes' => ['label' => 'Clientes', 'route' => 'admin.clientes.index', 'activo' => ['admin.clientes.*'], 'permiso' => 'manage clientes'],
            ],
        ],
        'operacion' => [
            'label' => 'Operación',
            'icon' => 'building-office-2',
            'items' => [
                'inventario' => ['label' => 'Inventario', 'route' => 'admin.bodegas.index', 'activo' => ['admin.bodegas.*'], 'permiso' => 'manage productos'],
                // Los patrones de Producción se ENUMERAN (no `admin.produccion.*`)
                // — convención del gate 28-07, cuando el comodín se comía la ruta
                // del Kardex (entonces ítem propio) y resaltaba DOS ítems a la
                // vez. Misma convención que el ítem `listado` de ST. Lo vigila
                // SidebarTest::test_cada_ruta_del_menu_resalta_exactamente_un_item,
                // que falla tanto si dos ítems colisionan como si una ruta queda sin dueño.
                //
                // `admin.produccion.movimientos` (Kardex) entró a la lista por la
                // consolidación D1 (PLAN-MENU-DENSIDAD): tiene dos vidas — nació
                // huérfana con Volver, subió a ítem del menú en P-NAV-06 (27-jul,
                // perdió el Volver por P-NAV-08) y volvió a HIJA del panel el
                // 17-ago (el botón «Kardex» de la cabecera de produccion/index es
                // la entrada; el Volver está de vuelta). Vigilada por la 11ª
                // entrada de MenuConsolidacionesTest. La lista sigue explícita:
                // ítem retirado no es motivo para volver al comodín.
                'produccion' => ['label' => 'Producción', 'route' => 'admin.produccion.index', 'activo' => ['admin.produccion.index', 'admin.produccion.dia', 'admin.produccion.maquina', 'admin.produccion.tipo', 'admin.produccion.sopladores', 'admin.produccion.soplador', 'admin.produccion.asignar*', 'admin.produccion.reporte.*', 'admin.produccion.vivo', 'admin.produccion.notas.*', 'admin.produccion.movimientos'], 'permiso' => 'manage production', 'badge' => 'produccion_por_aprobar', 'badge_title' => ':n reporte(s) por aprobar'],
                // Consolidación E1 (PLAN-MENU-DENSIDAD, el cierre del mapa):
                // Máquinas + Tipos de botellón + Recetas + Moldes son UN ítem —
                // pestañas de admin/maquinas/_tabs, SIN gateo (los cuatro
                // comparten `manage production` por construcción). Anfitriona
                // Máquinas por ser la primera de la fila física (máquina →
                // molde → tipo → receta); la key `maquinas` se conserva (menos
                // churn, precedente C2). Los cuatro wildcards son limpios
                // porque cada familia tiene prefijo propio — la razón por la
                // que Recetas (P-M11-10) y Moldes (P-M11-12) nacieron fuera de
                // la enumeración de `produccion`, y sigue vigente. Tipos de
                // botellón recuperó su Volver al salir del menú (P-NAV-06 —
                // ver VolverTest). Rutas vigiladas en MenuConsolidacionesTest.
                'maquinas' => ['label' => 'Configuración de producción', 'route' => 'admin.maquinas.index', 'activo' => ['admin.maquinas.*', 'admin.tipos-botellon.*', 'admin.recetas.*', 'admin.moldes.*'], 'permiso' => 'manage production'],
                // Despachos se fue a LOGÍSTICA el 05-08 (pedido del dueño).
                // Devoluciones (M13, flujo A-12): el cliente declara por el
                // link público; bodega recibe/categoriza/resuelve acá.
                'devoluciones' => ['label' => 'Devoluciones', 'route' => 'admin.devoluciones.index', 'activo' => ['admin.devoluciones.*'], 'permiso' => 'view devoluciones|manage devoluciones', 'badge' => 'devoluciones_por_recibir', 'badge_title' => ':n devolución(es) por recibir'],
            ],
        ],
        // LOGÍSTICA (pedido del dueño 04-08-2026). Nace con la flota: reemplaza
        // la planilla «Control vehiculos». Va después de Operación porque es
        // operación de apoyo, no comercial ni administrativa.
        // SIN badge a propósito: el dueño eligió «semáforo + aviso», no contador
        // en el menú (AskUserQuestion 04-08). El vencimiento se ve en la lista
        // y llega por la campanita; agregarlo aquí sería UNA línea ('badge' =>
        // 'vehiculos_por_vencer' + su resolver) si algún día lo pide.
        'logistica' => [
            'label' => 'Logística',
            'icon' => 'truck',
            'items' => [
                // Despachos (M07) llegó desde Operación el 05-08 (pedido del
                // dueño). Va PRIMERO porque es el principio del flujo: de los
                // despachos salen los documentos con los que se arma la hoja de
                // ruta, que después se asigna a un vehículo y a un conductor.
                // El permiso es el MISMO que gatea su ruta en routes/web.php
                // (D-014) y no se tocó: el traslado es de orden en el menú, NO
                // de acceso — nadie gana ni pierde la pantalla. NO se duplica el
                // ítem en Operación: dos ítems con la misma ruta rompen el
                // candado de SidebarTest (una ruta resalta exactamente un ítem).
                'despachos' => ['label' => 'Despachos', 'route' => 'admin.despachos.index', 'activo' => ['admin.despachos.*'], 'permiso' => 'manage despachos'],
                // Hoja de ruta digital (P-DSP-08): la arma quien gestiona y la
                // VEN además los tres autorizadores de la cadena R11 — mismo
                // OR que gatea su ruta (D-014).
                'hojas-ruta' => ['label' => 'Hojas de ruta', 'route' => 'admin.hojas-ruta.index', 'activo' => ['admin.hojas-ruta.*'], 'permiso' => 'manage hojas ruta|autorizar pagos ruta|autorizar ruta|autorizar carga'],
                'vehiculos' => ['label' => 'Vehículos', 'route' => 'admin.vehiculos.index', 'activo' => ['admin.vehiculos.*'], 'permiso' => 'ver vehiculos|manage vehiculos'],
                // Conductores llegó desde Servicio Técnico el 04-08 (pedido del
                // dueño): quien administra la flota administra quién la maneja.
                // El permiso es el MISMO que gatea su ruta en routes/web.php
                // (D-014) y conserva 'manage servicio tecnico' porque el catálogo
                // alimenta el ingreso por lote y el traslado al taller: el
                // técnico no puede perderlo. NO se duplica el ítem en Servicio
                // Técnico — dos ítems con la misma ruta rompen el candado de
                // SidebarTest (una ruta resalta exactamente un ítem).
                // Simulador de carga: responde "¿cuanto entra en tal camion?" antes
                // de que el vendedor prometa. NO escribe nada operativo.
                // Consolidación B1 (PLAN-MENU-DENSIDAD): «Cargas reales» vive
                // como pestaña del Simulador (admin/carga/_tabs). ANTES era
                // ítem aparte a propósito —el simulador se usa ANTES de cargar
                // y las cargas reales se anotan DESPUÉS—, pero el dueño
                // resolvió (14-ago) que ese matiz de momento-de-uso no pesa
                // frente a la densidad: la pestaña no impide anotar después,
                // solo agrupa bajo un ítem. Mismo permiso (`simular carga`,
                // calibra esta misma calculadora); su ruta va AQUÍ, en el
                // `activo` del anfitrión (candado en MenuConsolidacionesTest).
                'carga' => ['label' => 'Simulador de carga', 'route' => 'admin.carga.index', 'activo' => ['admin.carga.*', 'admin.cargas-reales.*'], 'permiso' => 'simular carga'],
                'conductores' => ['label' => 'Conductores', 'route' => 'admin.conductores.index', 'activo' => ['admin.conductores.*'], 'permiso' => 'manage servicio tecnico|manage vehiculos'],
            ],
        ],
        // Facturación electrónica (M05). El módulo existe ANTES de poder emitir a
        // propósito: hoy muestra lo emitido (cero) y, sobre todo, QUÉ FALTA para
        // emitir — que es la información útil mientras no se emite. Ver
        // PROYECTO_DALIGO.md §10.
        'facturacion' => [
            'label' => 'Facturación',
            'icon' => 'document-text',
            'items' => [
                // Consolidación Lote 3 (PLAN-MENU-DENSIDAD): «Estado» dejó de
                // ser ítem y vive como pestaña de Documentos (admin/dte/_tabs).
                // Su ruta va AQUÍ, en el `activo` del anfitrión — si no, la
                // página queda sin resaltado en silencio (candado en
                // MenuConsolidacionesTest). El acordeón de 1 ítem se conserva
                // a propósito: el activo_extra de abajo lo necesita, y M05 va
                // a crecer cuando se habilite la emisión (parte del Lote 3).
                'documentos' => ['label' => 'Documentos', 'route' => 'admin.dte.index', 'activo' => ['admin.dte.index', 'admin.dte.estado'], 'permiso' => 'emitir documentos tributarios'],
            ],
            // La pantalla del documento de una orden cuelga de Servicio Técnico por
            // ruta, pero conceptualmente es de acá: abre este acordeón.
            'activo_extra' => ['admin.servicio-tecnico.documento'],
        ],
        'administracion' => [
            'label' => 'Administración',
            'icon' => 'shield-check',
            'items' => [
                // Consolidación C1 (PLAN-MENU-DENSIDAD): «Roles» dejó de ser
                // ítem y vive como pestaña de Usuarios (admin/users/_tabs),
                // GATEADA por `manage roles` — Usuarios lo ven los tres jefes
                // y definir roles es solo del admin. Su ruta va AQUÍ, en el
                // `activo` del anfitrión (candado en MenuConsolidacionesTest).
                'usuarios' => ['label' => 'Usuarios', 'route' => 'admin.users.index', 'activo' => ['admin.users.*', 'admin.roles.*'], 'permiso' => 'view users'],
                'sucursales' => ['label' => 'Sucursales', 'route' => 'admin.sucursales.index', 'activo' => ['admin.sucursales.*'], 'permiso' => 'manage sucursales'],
                // Configuración NO va aquí: es de cuenta, no de negocio-por-módulo
                // (ver self::CUENTA más abajo) — pedido del dueño 2026-07-24.
                // Consolidación C2 (PLAN-MENU-DENSIDAD, la primera de MÚLTIPLES
                // ítems): Auditoría + Notificaciones + Historial de aprobaciones
                // son UN ítem — pestañas de admin/audits/_tabs, cada una gateada
                // por SU permiso (los tres hoy son solo-admin por construcción).
                // El «Historial de…» que defendía el QA 15-07 (hallazgo #1: no
                // confundir con la bandeja) sobrevive por contexto: la pestaña
                // «Aprobaciones» vive DENTRO del Registro, la bandeja sigue sola
                // en la sidebar y la campanita conserva su link con el nombre
                // largo. Rutas consolidadas vigiladas en MenuConsolidacionesTest.
                'auditoria' => ['label' => 'Registro del sistema', 'route' => 'admin.audits.index', 'activo' => ['admin.audits.*', 'admin.notificaciones.*', 'admin.aprobaciones.*'], 'permiso' => 'view audit'],
            ],
        ],
        'mi-produccion' => [
            'label' => 'Mi producción',
            'icon' => 'user',
            'route' => 'produccion.mi.index',
            'activo' => ['produccion.mi.*'],
            'permiso' => 'report production',
            'badge' => 'mi_produccion_devueltos',
            'badge_title' => ':n reporte(s) devuelto(s)',
            'imprenta' => true,
        ],
        // Hoja de ruta del conductor (P-DSP-05): link directo por la misma
        // doctrina que Mi producción — la pantalla del operario no se esconde
        // tras un acordeón (y el conductor no tiene permisos de Operación, así
        // que ahí quedaría como único ítem huérfano de contexto).
        'mis-entregas' => [
            'label' => 'Mis entregas',
            'icon' => 'truck',
            'route' => 'entregas.index',
            'activo' => ['entregas.*'],
            'permiso' => 'confirmar entrega',
        ],
        'aprobaciones' => [
            'label' => 'Aprobaciones',
            'icon' => 'check-badge',
            'route' => 'aprobaciones.index',
            'activo' => ['aprobaciones.*'],
            'permiso' => 'aprobar solicitudes',
            'badge' => 'aprobaciones_bandeja',
            'badge_title' => ':n solicitud(es) por aprobar',
            'imprenta' => true,
        ],
        // Chat interno (MSG-4, PLAN-MENSAJES §5.5 — veredicto del dueño 32→33):
        // link de primer nivel por la MISMA doctrina que sus vecinos — es una
        // superficie personal transversal a todos los roles (todos con todos),
        // sin modulo de dominio que pueda ser su anfitrion. El badge cuenta
        // MIS mensajes sin leer (accion anclada al item donde se resuelve).
        'mensajes' => [
            'label' => 'Mensajes',
            'icon' => 'chat-bubble-left-right',
            'route' => 'mensajes.index',
            'activo' => ['mensajes.*'],
            'permiso' => 'usar mensajes',
            'badge' => 'mensajes_no_leidos',
            'badge_title' => ':n mensaje(s) sin leer',
        ],
        'servicio-tecnico' => [
            'label' => 'Servicio Técnico',
            'icon' => 'wrench-screwdriver',
            // Sin badge de módulo a propósito (decisión del dueño 24-07): los
            // números del menú son ACCIONES ancladas a su ítem — la carga del
            // taller (equipos activos) es un estado y vive en el Inicio y en
            // el Listado, no aquí.
            // Pantallas de detalle de ST (show, cotización, reparación…) no
            // tienen ítem propio pero deben abrir el acordeón del módulo.
            'activo_extra' => ['admin.servicio-tecnico.*'],
            'items' => [
                // Consolidaciones Lote 4 + A1 + A2 (PLAN-MENU-DENSIDAD): la
                // CONFIG del taller («Códigos QR», «Costos generales») entra
                // por el desplegable «Configuración» de la cabecera del
                // Listado, y el FLUJO «Traslados al taller» por su pestaña
                // (_tabs-listado) — cada entrada/pestaña gateada por SU
                // permiso. Sus rutas van AQUÍ, en el `activo` del anfitrión
                // (candado en MenuConsolidacionesTest).
                'listado' => ['label' => 'Listado', 'route' => 'admin.servicio-tecnico.index', 'activo' => ['admin.servicio-tecnico.index', 'admin.servicio-tecnico.qr', 'admin.tiempos-reparacion.*', 'admin.traslados.*'], 'permiso' => 'view servicio tecnico|manage servicio tecnico', 'badge' => 'st_por_confirmar', 'badge_title' => ':n ingreso(s) por confirmar'],
                // "Registrar ingreso" vive como botón dentro de Listado (no se duplica aquí).
                'lote' => ['label' => 'Ingreso por lote', 'route' => 'admin.servicio-tecnico.lote.create', 'activo' => ['admin.servicio-tecnico.lote.*'], 'permiso' => 'crear lote servicio'],
                'informe' => ['label' => 'Informe', 'route' => 'admin.servicio-tecnico.informe', 'activo' => ['admin.servicio-tecnico.informe', 'admin.servicio-tecnico.informe.*'], 'permiso' => 'ver informe dispensadores|ver informe industrial'],
                // Consolidación Lote 5 (PLAN-MENU-DENSIDAD): «Servicios de
                // terreno» dejó de ser ítem y vive como pestaña de la Agenda
                // (admin/agenda-terreno/_tabs). Su ruta va AQUÍ, en el `activo`
                // del anfitrión (candado en MenuConsolidacionesTest). La
                // pestaña se gatea con `agendar servicio terreno` en el _tabs:
                // el técnico industrial ve la Agenda con solo `ver agenda
                // terreno` y el tarifario le daría 403.
                'agenda-terreno' => ['label' => 'Agenda de terreno', 'route' => 'admin.agenda-terreno.index', 'activo' => ['admin.agenda-terreno.*', 'admin.servicios-terreno.*'], 'permiso' => 'ver agenda terreno|agendar servicio terreno', 'badge' => 'agenda_por_coordinar', 'badge_title' => ':n visita(s) por coordinar'],
                'instalaciones' => ['label' => 'Instalaciones', 'route' => 'admin.instalaciones.index', 'activo' => ['admin.instalaciones.*'], 'permiso' => 'gestionar instalaciones'],
                // Conductores se fue a LOGÍSTICA el 04-08 (pedido del dueño).
                // Sigue siendo visible para el técnico: el permiso del ítem es
                // canAny y conserva 'manage servicio tecnico'.
            ],
        ],
        // Carta Gantt transicional mientras la app se construye: link directo
        // de primer nivel AL FINAL del menú (pedido del dueño 03-08 — antes
        // vivía dentro de Administración). El avance oficial se lee del repo
        // (App\Support\PlanProyecto).
        'plan' => [
            'label' => 'Plan del proyecto',
            'icon' => 'presentation-chart-bar',
            'route' => 'plan.index',
            'activo' => ['plan.*'],
            'permiso' => 'ver plan proyecto',
        ],
    ];

    /**
     * Ítems del ÁREA DE CUENTA (dropdown del pie de la sidebar, junto a
     * Perfil/Cerrar sesión) — NO del árbol de navegación. Lista plana, sin
     * acordeón. Pedido del dueño 2026-07-24: "Configuración" no pertenece a
     * Administración (mezcla parámetros de negocio con Usuarios/Roles/
     * Auditoría); Perfil y Configuración son conceptualmente distintos
     * (autoservicio del usuario vs. reglas del sistema) así que quedan como
     * links SEPARADOS en el mismo menú, no fusionados en una pantalla.
     */
    public const CUENTA = [
        'configuracion' => ['label' => 'Configuración', 'route' => 'admin.configuracion.index', 'activo' => ['admin.configuracion.*'], 'permiso' => 'manage settings'],
    ];

    /**
     * Prioridad de ítems POR ROL dentro de un módulo (pedido del dueño
     * 2026-07-28). NO cambia la VISIBILIDAD —eso se sigue derivando del permiso,
     * doctrina de este archivo— solo el ORDEN: para un perfil cuyo trabajo diario
     * no es el taller, sus ítems flotan al tope de su módulo y el resto queda
     * debajo como secundario, conservando su orden relativo.
     *
     * Hoy solo el técnico industrial (Carlos Tablante): su día es la agenda de
     * terreno y las instalaciones; Listado / Ingreso por lote los usa recién
     * cuando cubre a alguien por enfermedad o vacaciones, no como entrada.
     *
     * Estructura: rol => [ moduloKey => [ keys de ítem en orden prioritario ] ].
     * Un usuario con varios roles acumula las prioridades de todos (sin duplicar).
     */
    public const PRIORIDAD_POR_ROL = [
        'tecnico_industrial' => [
            'servicio-tecnico' => ['agenda-terreno', 'instalaciones'],
        ],
    ];

    /**
     * Árbol podado por permisos para el usuario: módulos con al menos un ítem
     * visible (o links directos permitidos). La visibilidad del módulo se
     * deriva — no existe una lista @canany aparte que pueda driftear.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function para(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $arbol = [];
        foreach (self::MODULOS as $key => $modulo) {
            if (isset($modulo['items'])) {
                $items = array_filter(
                    $modulo['items'],
                    fn (array $item) => self::puedeVer($user, $item['permiso'])
                );
                if ($items !== []) {
                    $items = self::priorizarPorRol($user, $key, $items);
                    $arbol[$key] = array_merge($modulo, ['items' => $items]);
                }
            } elseif (self::puedeVer($user, $modulo['permiso'])) {
                $arbol[$key] = $modulo;
            }
        }

        return $arbol;
    }

    /**
     * Ítems del área de cuenta visibles para el usuario (poda por permiso,
     * mismo criterio que para()). Lista plana para el dropdown del pie.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function cuenta(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return array_filter(
            self::CUENTA,
            fn (array $item) => self::puedeVer($user, $item['permiso'])
        );
    }

    /**
     * Módulo cuyo patrón 'activo' (de ítems o del módulo) matchea la ruta
     * actual: abre su acordeón y da el título de la topbar. null si la ruta
     * no pertenece al menú (ej. perfil).
     *
     * @return array<string, mixed>|null
     */
    public static function moduloActivo(): ?array
    {
        foreach (self::MODULOS as $key => $modulo) {
            if (request()->routeIs(...self::patronesDe($modulo))) {
                return array_merge($modulo, ['key' => $key]);
            }
        }

        return null;
    }

    /** TTL del caché de badges: perf (hallazgo 2026-07-24) — el sidebar dispara
     *  hasta 6 COUNTs en CADA página; 10s de margen es imperceptible para
     *  contadores de "pendientes" y corta ese costo en navegaciones seguidas.
     *  Decisión del dueño 2026-07-24 (AskUserQuestion): cachear, 10s exactos. */
    private const TTL_BADGES = 10;

    /**
     * Resolución centralizada de badges (key simbólica => conteo). Migrado
     * del View::composer de AppServiceProvider: COUNT liviano sobre la
     * columna indexada `estado`, solo para quien puede ver servicio técnico.
     *
     * Dos capas: memoizado EN EL REQUEST (request()->attributes, como antes
     * — sidebar y topbar lo piden en la misma página, 0 costo extra) y, por
     * fuera, cacheado 10s por usuario (Cache::remember) para no repetir los
     * COUNTs en cada navegación dentro de esa ventana. Nunca cachea para
     * usuario null (candado de MenuPrincipalTest llama badges(null); no vale
     * la pena cachear "todo en cero").
     *
     * @return array<string, int>
     */
    public static function badges(?User $user): array
    {
        $atributos = request()->attributes;
        $key = 'dg.menu.badges.'.($user?->id ?? 0);

        if (! $atributos->has($key)) {
            $atributos->set($key, $user
                ? Cache::remember($key, self::TTL_BADGES, fn () => self::resolverBadges($user))
                : self::resolverBadges(null));
        }

        return $atributos->get($key);
    }

    /** Los 7 resolvers de badges: cada uno se gatea por SU permiso y tolera
     *  $user null. Todos son COUNTs sobre columnas indexadas. */
    private static function resolverBadges(?User $user): array
    {
        return [
            'st_por_confirmar' => ($user && $user->can('confirmar servicio tecnico'))
                ? OrdenServicio::porConfirmar()->count()
                : 0,
            'agenda_por_coordinar' => ($user && $user->canAny(['ver agenda terreno', 'agendar servicio terreno']))
                ? AgendaTrabajo::porCoordinar()->count()
                : 0,
            'aprobaciones_bandeja' => ($user && $user->can('aprobar solicitudes'))
                ? Aprobacion::bandejaDe($user)->count()
                : 0,
            'produccion_por_aprobar' => ($user && $user->can('manage production'))
                ? ProduccionReporte::pendientes()->count()
                : 0,
            'mi_produccion_devueltos' => ($user && $user->can('report production'))
                ? ProduccionReporte::devueltosDe($user->id)->count()
                : 0,
            // M13: gateado por MANAGE (quien puede ACTUAR recibiendola en
            // bodega; view solo consulta) — misma logica que st_por_confirmar.
            'devoluciones_por_recibir' => ($user && $user->can('manage devoluciones'))
                ? Devolucion::porRecibir()->count()
                : 0,
            // Solo visibilidad del link "Mis solicitudes" del hub: quien
            // nunca ha solicitado nada no necesita verlo (y el candado de
            // AprobacionAccionableTest exige que ninguna superficie ajena
            // a la fila aporte ese href para un usuario sin historia).
            // Chat interno (MSG-4): MIS mensajes sin leer — la MISMA fuente
            // que la firma del poll (Conversacion::noLeidosDeUsuario), asi el
            // badge y el refresco no pueden contar distinto.
            'mensajes_no_leidos' => ($user && $user->can('usar mensajes'))
                ? Conversacion::noLeidosDeUsuario($user->id)
                : 0,
            'mis_solicitudes' => $user
                ? Aprobacion::where('solicitante_id', $user->id)->count()
                : 0,
        ];
    }

    /**
     * Datos de la campanita M15 para el shell (v1 sin polling, se refresca al
     * navegar). Memoizado en el request igual que badges(): el pie de la
     * sidebar (desktop) y la campana de la barra móvil los piden en la misma
     * página sin duplicar las 2 queries.
     *
     * @return array{noLeidas: \Illuminate\Support\Collection, conteo: int}
     */
    public static function campanita(?User $user): array
    {
        $atributos = request()->attributes;
        $key = 'dg.menu.campanita.'.($user?->id ?? 0);

        if (! $atributos->has($key)) {
            $atributos->set($key, [
                'noLeidas' => Notificacion::campanitaDe($user?->id)->latest('id')->take(5)->get(),
                'conteo' => Notificacion::campanitaDe($user?->id)->count(),
            ]);
        }

        return $atributos->get($key);
    }

    /**
     * Mapa plano "modulo" o "modulo.item" => definición, para los candados de
     * MenuPrincipalTest (espejo de AccesosDashboard::cards()). Incluye
     * self::CUENTA (prefijo "cuenta.*") — aunque esos ítems no viven en el
     * árbol de navegación, deben pasar los mismos candados de route/permiso
     * y seguir siendo encontrables por el candado que verifica que las cards
     * del Inicio sean subconjunto del menú (AccesosDashboard tiene un card
     * de Configuración).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function items(): array
    {
        $items = [];
        foreach (self::MODULOS as $key => $modulo) {
            if (isset($modulo['items'])) {
                foreach ($modulo['items'] as $subKey => $item) {
                    $items["{$key}.{$subKey}"] = $item;
                }
            } else {
                $items[$key] = $modulo;
            }
        }
        foreach (self::CUENTA as $key => $item) {
            $items["cuenta.{$key}"] = $item;
        }

        return $items;
    }

    /** Patrones routeIs de un módulo: los suyos + los de sus ítems + extras. */
    private static function patronesDe(array $modulo): array
    {
        $patrones = array_merge(
            $modulo['activo'] ?? [],
            $modulo['activo_extra'] ?? []
        );
        foreach ($modulo['items'] ?? [] as $item) {
            $patrones = array_merge($patrones, $item['activo']);
        }

        return $patrones;
    }

    private static function puedeVer(User $user, ?string $permiso): bool
    {
        return $permiso === null || $user->canAny(explode('|', $permiso));
    }

    /**
     * Reordena los ítems YA PODADOS de un módulo según PRIORIDAD_POR_ROL: los
     * prioritarios del/los rol(es) del usuario suben al tope (en el orden
     * declarado, sin duplicar entre roles), y el resto queda debajo con su
     * orden original. Estable: si el usuario no tiene prioridades para este
     * módulo, devuelve los ítems tal cual. Solo mueve ítems que estén visibles
     * (los prioritarios que el permiso ocultó se ignoran).
     *
     * @param  array<string, array<string, mixed>>  $items
     * @return array<string, array<string, mixed>>
     */
    private static function priorizarPorRol(User $user, string $moduloKey, array $items): array
    {
        $orden = [];
        foreach (self::PRIORIDAD_POR_ROL as $rol => $modulos) {
            if (isset($modulos[$moduloKey]) && $user->hasRole($rol)) {
                foreach ($modulos[$moduloKey] as $itemKey) {
                    if (! in_array($itemKey, $orden, true)) {
                        $orden[] = $itemKey;
                    }
                }
            }
        }

        if ($orden === []) {
            return $items;
        }

        $arriba = [];
        foreach ($orden as $itemKey) {
            if (isset($items[$itemKey])) {
                $arriba[$itemKey] = $items[$itemKey];
            }
        }

        // $arriba primero (prioridad), luego el resto en su orden original.
        return $arriba + array_diff_key($items, $arriba);
    }
}
