<?php

namespace Tests\Feature;

use App\Models\Aprobacion;
use App\Models\OrdenServicio;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shell V4 (sidebar acordeón + topbar). Complementa a NavigationTest (gateo
 * de ítems por rol) con los contratos propios del shell nuevo. Anclas por
 * FORMA CONTIGUA que los componentes producen a propósito (doctrina anti
 * verde-engañoso): `href="…" aria-current="page"` (sidebar-item),
 * `<details open data-modulo="…"` (sidebar-group) y
 * `text-neutral-900">Título` (h1 de la topbar).
 */
class SidebarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function usuarioCon(string $rol): User
    {
        return tap(User::factory()->create())->assignRole($rol);
    }

    public function test_item_activo_lleva_aria_current_y_solo_su_acordeon_abre(): void
    {
        $respuesta = $this->actingAs($this->usuarioCon('admin'))
            ->get(route('admin.productos.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.productos.index').'" aria-current="page"', false)
            ->assertSee('<details open data-modulo="comercial"', false);

        // Exactamente UN acordeón abierto: si el cálculo del módulo activo se
        // pierde y todos llegan `open` (o ninguno), esto se pone rojo.
        $this->assertSame(
            1,
            substr_count($respuesta->getContent(), '<details open'),
            'Debe abrir exactamente el acordeón del módulo activo.'
        );
    }

    /**
     * Unicidad del ítem activo, DERIVADA de MenuPrincipal (el candado que
     * faltaba, gate 28-07): en la página de un ítem debe haber EXACTAMENTE un
     * `aria-current="page"`. Falla en los dos sentidos —dos ítems resaltados a
     * la vez (un patrón comodín que se come la ruta de un hermano, como pasó al
     * entrar el Kardex bajo `admin.produccion.*`) o ninguno (una ruta del menú
     * que ningún patrón cubre)— y cubre sola cada ítem que se agregue mañana.
     */
    public function test_cada_ruta_del_menu_resalta_exactamente_un_item(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo(\Spatie\Permission\Models\Permission::all());

        $revisados = 0;

        foreach (\App\Support\MenuPrincipal::items() as $key => $item) {
            // Los `cuenta.*` (Configuración) viven en el dropdown del PIE, no en
            // el árbol de navegación: `items()` los incluye para los candados de
            // route/permiso, pero no los renderiza `x-sidebar-item`, así que no
            // emiten aria-current por diseño.
            if (str_starts_with($key, 'cuenta.')) {
                continue;
            }

            // Los ítems con parámetros en la URL no se pueden pedir sin fixtures.
            $ruta = \Illuminate\Support\Facades\Route::getRoutes()->getByName($item['route']);
            if ($ruta === null || $ruta->parameterNames() !== []) {
                continue;
            }

            $html = $this->actingAs($usuario)->get(route($item['route']))->assertOk()->getContent();
            $revisados++;

            $this->assertSame(1, substr_count($html, 'aria-current="page"'),
                "En [{$item['route']}] (ítem [{$key}]) debe haber EXACTAMENTE un ítem del menú resaltado: "
                .'0 = ningún patrón cubre la ruta; 2+ = un patrón comodín se come la ruta de un hermano.');
        }

        $this->assertGreaterThan(10, $revisados, 'Se revisaron muy pocos ítems del menú.');
    }

    public function test_link_directo_activo_lleva_aria_current(): void
    {
        $this->actingAs($this->usuarioCon('admin'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('dashboard').'" aria-current="page"', false);
    }

    public function test_pagina_de_detalle_abre_el_acordeon_via_activo_extra(): void
    {
        // El contrato activo_extra (MenuPrincipal): las pantallas de detalle
        // de ST sin ítem propio abren el acordeón del módulo y titulan la
        // topbar igual.
        $orden = OrdenServicio::factory()->create();

        $this->actingAs($this->usuarioCon('admin'))
            ->get(route('admin.servicio-tecnico.show', $orden))
            ->assertOk()
            ->assertSee('<details open data-modulo="servicio-tecnico"', false)
            ->assertSee('text-neutral-900">Servicio Técnico', false);
    }

    public function test_el_documento_tributario_abre_facturacion_y_no_servicio_tecnico(): void
    {
        // Gate P-NAV-05. Esta pantalla la reclaman DOS módulos: facturacion la
        // nombra en su activo_extra, y el comodín de servicio-tecnico
        // ('admin.servicio-tecnico.*') también la matchea. moduloActivo()
        // devuelve el PRIMER módulo que matchea, así que hoy gana Facturación
        // solo porque está declarado antes en MODULOS.
        //
        // Es la misma clase de defecto que el comodín de Producción comiéndose
        // al Kardex (bitácora 28-07), pero a nivel de MÓDULO: el candado
        // test_cada_ruta_del_menu_resalta_exactamente_un_item itera ÍTEMS, y
        // esta ruta no es ítem de nadie — así que nada la cubría. Sin este
        // test, reordenar MODULOS cambia en silencio qué acordeón abre y qué
        // título muestra la topbar.
        $orden = OrdenServicio::factory()->create();

        $this->actingAs($this->usuarioCon('admin'))
            ->get(route('admin.servicio-tecnico.documento', $orden))
            ->assertOk()
            ->assertSee('<details open data-modulo="facturacion"', false)
            ->assertDontSee('<details open data-modulo="servicio-tecnico"', false)
            ->assertSee('text-neutral-900">Facturación', false);
    }

    public function test_drawer_cerrado_no_queda_en_el_orden_de_tabulacion(): void
    {
        // Gate P-NAV-05, hallazgo ALTO. El <aside> es UNO solo (sidebar fija en
        // lg:, drawer debajo) y es el PRIMER nodo del shell. Ocultarlo solo con
        // max-lg:-translate-x-full lo saca de la vista pero NO del orden de
        // tabulación ni del árbol de accesibilidad: en móvil, con el menú
        // cerrado, un usuario de teclado o lector de pantalla recorría todo el
        // menú invisible antes de llegar al contenido.
        //
        // max-lg:invisible (visibility:hidden) sí saca del tab order, y el
        // prefijo max-lg: deja el escritorio intacto. Va CONTIGUO al translate
        // para que el assert no pueda pasar por otra clase de la página
        // (doctrina anti verde-engañoso) y para fijar que ambas viajan juntas:
        // si una se va sin la otra, esto se pone rojo.
        $this->actingAs($this->usuarioCon('admin'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('max-lg:-translate-x-full max-lg:invisible', false);
    }

    public function test_la_hamburguesa_declara_el_panel_que_controla(): void
    {
        // aria-expanded sin aria-controls anuncia un estado sin decir de qué.
        // El id del <aside> es el destino, y ambos toggles lo apuntan.
        $this->actingAs($this->usuarioCon('admin'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="dg-menu-lateral"', false)
            ->assertSee('aria-controls="dg-menu-lateral"', false);
    }

    public function test_hamburguesa_y_campana_movil_presentes_en_toda_pagina_autenticada(): void
    {
        // Campana móvil SIEMPRE visible en la barra (hallazgo QA 14-07) y
        // hamburguesa del drawer — también fuera del dashboard. El aria-label
        // sin conteo solo lo produce la campana móvil (el partial desktop usa
        // sr-only con paréntesis; CampanitaTest cubre los conteos).
        $this->actingAs($this->usuarioCon('admin'))
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Abrir menú')
            ->assertSee('aria-label="Notificaciones"', false);
    }

    public function test_acordeon_es_exclusivo_por_grupo_nativo(): void
    {
        // Contrato del acordeón exclusivo (pedido UX 24-07): los <details>
        // comparten name="dg-menu" — el navegador cierra solo la categoría
        // anterior al abrir otra. Si un refactor quita el atributo, el menú
        // vuelve a multi-abierto en silencio.
        $this->actingAs($this->usuarioCon('admin'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('name="dg-menu"', false);
    }

    public function test_campanita_vive_en_la_cabecera_antes_del_bloque_de_usuario(): void
    {
        // Pedido del dueño 24-07: la campanita se mudó de la cabecera del pie
        // (donde se veía "extraña" junto al nombre) a la cabecera de la
        // sidebar. Candado estructural por posición en el HTML (evita
        // depender de clases CSS que otros avatares legítimos de la página
        // también usan, ej. el círculo de técnico en el listado de ST).
        $contenido = $this->actingAs($this->usuarioCon('admin'))
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $posCampanita = strpos($contenido, 'data-menu-campanita');
        $posUsuario = strpos($contenido, 'data-menu-usuario');

        $this->assertNotFalse($posCampanita, 'Falta el marcador de la campanita en la cabecera.');
        $this->assertNotFalse($posUsuario, 'Falta el marcador del bloque de usuario en el pie.');
        $this->assertLessThan($posUsuario, $posCampanita, 'La campanita debe aparecer ANTES que el bloque de usuario.');
    }

    public function test_pie_de_la_sidebar_sin_avatar_de_iniciales(): void
    {
        // Pedido del dueño: el círculo de iniciales "es ruido, no aporta".
        // Acotado al bloque data-menu-usuario (no a la página completa): la
        // página SÍ puede tener otros avatares legítimos (ej. el círculo de
        // técnico en un listado), así que no basta un assertDontSee global.
        $contenido = $this->actingAs($this->usuarioCon('admin'))
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $bloqueUsuario = substr($contenido, strpos($contenido, 'data-menu-usuario'));

        $this->assertStringNotContainsString(
            'bg-neutral-100 text-sm font-semibold uppercase',
            $bloqueUsuario,
            'El pie de la sidebar todavía renderiza el círculo de iniciales (x-avatar).'
        );
    }

    public function test_drawer_movil_nace_oculto_sin_flash(): void
    {
        // Candado del anti-flash pre-Alpine: la clase estática
        // max-lg:-translate-x-full debe venir del SERVIDOR (Alpine solo la
        // retira al abrir). Si alguien la mueve al binding dinámico, el drawer
        // parpadea abierto en cada carga móvil.
        $this->actingAs($this->usuarioCon('admin'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('w-[300px] max-lg:-translate-x-full', false);
    }

    public function test_sidebar_se_poda_por_permisos_fuera_del_dashboard(): void
    {
        // Generaliza AprobacionAccionableTest: la poda del menú aplica en
        // TODAS las páginas, no solo en el dashboard.
        $this->actingAs($this->usuarioCon('soplador'))
            ->get(route('produccion.mi.index'))
            ->assertOk()
            ->assertSee(route('produccion.mi.index'), false)
            ->assertDontSee(route('admin.users.index'), false)
            ->assertDontSee('Administración');
    }

    public function test_categoria_del_item_activo_queda_marcada_aunque_se_cierre(): void
    {
        // data-activo lleva las clases ESTÁTICAS del "aquí estás trabajando":
        // sobreviven al colapso manual y al acordeón exclusivo (pedido del
        // dueño 24-07 — cerrar Comercial estando en Catálogo no debe apagar
        // la señal).
        $this->actingAs($this->usuarioCon('admin'))
            ->get(route('admin.productos.index'))
            ->assertOk()
            ->assertSee('data-activo="true"', false);
    }

    public function test_badges_de_pendientes_se_ven_en_el_menu(): void
    {
        // 1 solicitud pendiente (admin ve toda bandeja) + 1 ingreso QR por
        // confirmar → pills con su title-contrato en el link directo
        // Aprobaciones, el ítem Listado y la suma de la categoría ST cerrada.
        Aprobacion::create([
            'tipo_accion' => Aprobacion::ACCION_AJUSTE_REPORTE,
            'motivo' => 'm', 'descripcion' => 'd', 'rol_aprobador' => 'jefe_bodega',
        ]);
        OrdenServicio::factory()->create(['fuente' => 'qr', 'confirmada_at' => null]);
        \App\Models\AgendaTrabajo::factory()->create(['estado' => 'solicitado']);

        $this->actingAs($this->usuarioCon('admin'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('1 solicitud(es) por aprobar')
            ->assertSee('1 ingreso(s) por confirmar')
            ->assertSee('1 visita(s) por coordinar')
            ->assertSee('pendiente(s) en esta sección')
            ->assertSee('group-open:hidden', false);
    }

    public function test_hub_de_la_campanita_gatea_las_funciones_por_permiso(): void
    {
        // Admin con historia de solicitante ve las 4 funciones del hub.
        $admin = $this->usuarioCon('admin');
        Aprobacion::create([
            'tipo_accion' => Aprobacion::ACCION_AJUSTE_REPORTE,
            'motivo' => 'm', 'descripcion' => 'd', 'rol_aprobador' => 'admin',
            'solicitante_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Bandeja de aprobaciones')
            ->assertSee('Mis solicitudes')
            ->assertSee('Historial de aprobaciones')
            ->assertSee('Panel de notificaciones');

        // Soplador sin permisos NI solicitudes: el hub no aporta ninguno de
        // esos hrefs (coherente con el candado de AprobacionAccionableTest:
        // "Mis solicitudes" solo aparece para quien tiene historia).
        $this->actingAs($this->usuarioCon('soplador'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Bandeja de aprobaciones')
            ->assertDontSee('Mis solicitudes')
            ->assertDontSee('Historial de aprobaciones')
            ->assertDontSee('Panel de notificaciones');
    }

    public function test_topbar_muestra_el_titulo_del_modulo_activo(): void
    {
        // Forma contigua del h1 de la topbar (`text-neutral-900">Label`):
        // el label de la sidebar y el page-header de la página NO la
        // producen, así que esto falla si la topbar deja de titular.
        $this->actingAs($this->usuarioCon('admin'))
            ->get(route('admin.servicio-tecnico.index'))
            ->assertOk()
            ->assertSee('text-neutral-900">Servicio Técnico', false);
    }

    public function test_perfil_cae_al_nombre_de_la_app_como_titulo(): void
    {
        // Ruta fuera del menú (perfil): el h1 de la topbar cae al nombre de
        // la app (forma contigua — el <title> y el brand de la sidebar no
        // producen `text-neutral-900">DaliGo</h1>`) y ningún acordeón abre.
        $this->actingAs($this->usuarioCon('admin'))
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('text-neutral-900">'.config('app.name', 'DaliGo').'</h1>', false)
            ->assertDontSee('<details open', false);
    }

    public function test_configuracion_vive_en_el_menu_de_cuenta_no_en_administracion(): void
    {
        // Pedido del dueño 24-07: Configuración salió de la categoría
        // Administración y ahora es un link del dropdown de usuario (junto a
        // Perfil), gateado por el mismo permiso 'manage settings' de siempre.
        $contenido = $this->actingAs($this->usuarioCon('admin'))
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $rutaConfig = route('admin.configuracion.index');

        $inicioAdmin = strpos($contenido, 'data-modulo="administracion"');
        $finAdmin = strpos($contenido, '</details>', $inicioAdmin);
        $bloqueAdministracion = substr($contenido, $inicioAdmin, $finAdmin - $inicioAdmin);

        $inicioCuenta = strpos($contenido, 'data-menu-usuario');
        $bloqueCuenta = substr($contenido, $inicioCuenta);

        $this->assertStringNotContainsString(
            $rutaConfig,
            $bloqueAdministracion,
            'Configuración ya no debe listarse dentro de la categoría Administración.'
        );
        $this->assertStringContainsString(
            $rutaConfig,
            $bloqueCuenta,
            'Configuración debe aparecer en el dropdown de usuario (pie de la sidebar).'
        );
    }

    public function test_configuracion_no_aparece_para_rol_sin_manage_settings(): void
    {
        $this->actingAs($this->usuarioCon('vendedor'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.configuracion.index'), false);
    }
}
