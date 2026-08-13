# Parte de Max-1 — 2026-08-13 · Dictado v49, Lote 3 HECHO: «Estado» vive como pestaña de Documentos

> Forjador A, stream 1 · rama `feature/menu-lote3-estado-documentos` (commit `f85bc3a`) —
> **espera doble llave**. El Lote 4 (QR→Listado ST) no arranca hasta que este merge aterrice.

## El número

| | |
|---|---|
| Menú antes → después | **45 → 44 rótulos** (11 primer nivel + 32 subítems + 1 cuenta, verificado por tinker) |
| Pérdida de rutas/permisos | **CERO** — mudanza, no retiro: `admin.dte.estado` responde idéntico, mismo permiso (`emitir documentos tributarios`), mismo controller; los 2 links del index a la pantalla siguen válidos (apuntan a la misma ruta, que ahora ES la pestaña) |
| Suite | baseline main `93c37f4` (worktree aislado): **2005 verdes / 14.067 aserciones** (clava la referencia del Director) · rama: **2005 verdes / 14.113 aserciones** — **delta 0 tests, exacto** (el lote no agrega ni quita tests; las +46 aserciones netas son el mini-candado extendido y los loops por-ítem) |
| Bundle | **byte-idéntico** — el rebuild reprodujo exactamente el `app-KksVf5DA.css` de main (cero clases nuevas; las del tab-nav ya existían desde el Lote 1) |

## Qué se construyó

1. **`<x-tab-nav>`** (`components/tab-nav.blade.php`) — el componente compartido de las
   pestañas de consolidación, **extraído al 3er uso exactamente como lo prometió el parte
   del Lote 1** (y como lo pedía la observación DRY de su gate R-31). Props `:tabs`/`label`;
   la activa lleva `aria-current="true"`, jamás `"page"` (colisiona con el conteo de
   SidebarTest — el porqué vive en el docblock del componente). `catalogo/_tabs` migró a
   usarlo; **el `_tabs` de ST queda fuera a propósito**: vive en páginas de detalle (fuera
   del conteo), gatea pestañas por permiso y ahí `aria-current="page"` es correcto.
2. **`admin/dte/_tabs`**: «Documentos · Estado de la conexión», montado en las dos
   pantallas (`dte/index` bajo el status-alert, `dte/estado` al tope del cuerpo).
3. **MenuPrincipal**: fuera el ítem `estado-facturacion`; `admin.dte.estado` entra al
   `activo` de Documentos con el comentario de consolidación.
4. **`estado.blade.php`**: el comentario que justificaba el no-Volver («es un ítem del
   menú») ahora cita la razón vigente (es pestaña; el tab-nav es su navegación —
   precedente Lote 1). Es el ajuste «por la fuente única» que pidió el dictado: ningún
   test se amoldó a mano — VolverTest deriva de `MenuPrincipal::items()` y dejó de
   visitar la pantalla solo.
5. **Mini-candado heredado + generalizado**: `CONSOLIDADAS` ganó la línea
   `'admin.dte.estado' => 'facturacion.documentos'`, y la «puerta a visitar» ahora acepta
   una **ruta hoja exacta** (este caso) además del `{prefijo}index` del Lote 1. Mutado de
   nuevo: quitar la ruta del `activo` del anfitrión → **2 rojos exactos** (cobertura +
   pantalla con 0 aria-current, el hueco silencioso de F0) → restaurado (grep del
   marcador) → re-verde 3/3 (94 aserciones).

## Decisiones declaradas (las que el dictado me delegó)

- **El acordeón de Facturación SE CONSERVA con 1 ítem** (no pasa a link directo), por
  tres razones: (1) su `activo_extra` (`admin.servicio-tecnico.documento`) le da casa a
  la pantalla del documento de una orden — el acordeón se abre y titula la topbar cuando
  el usuario está ahí; (2) la aritmética aprobada de la cola (44 → 42 con los lotes 4-5)
  asume esta forma — pasarlo a link directo daría 43 y cambiaría en silencio los números
  del mapa F0 que el dueño vio; (3) M05 va a crecer al habilitar la emisión (el POST a
  Bsale ya existe), y colapsar hoy para re-expandir en semanas sería flip-flop.
- **Los 2 links contextuales del index se quedan**: son CTAs con contexto («qué falta
  para emitir → Estado») y apuntan a la ruta conservada, que ahora es la pestaña. El
  tab-nav agrega la estructura; los links agregan el porqué.
- El h1 de la pantalla sigue «Estado de la facturación» (precedente Lote 1: el h1 es de
  la pantalla, la pertenencia la comunica el tab-nav); la pestaña se rotula «Estado de
  la conexión» como pidió el dictado.
- Los anchos de página difieren entre pestañas (`listado` en Documentos, `formulario` en
  Estado) y se conservan: cada pantalla mantiene el ancho que su contenido pide — la
  barra se ve más angosta en Estado, y eso es el layout correcto, no un defecto.

## Incidente de proceso (declarado)

Arranqué la baseline local y **edité el árbol antes de tiempo y todavía parado en main**
(sin rama): la baseline quedó contaminada y la detuve. Correcciones en el momento: rama
cortada con los cambios (`git checkout -b` se los lleva), y la **baseline limpia corrió
en un worktree aislado de `93c37f4`** con `vendor` COPIADO (jamás junction) y el
diagnóstico del autoloader de la bitácora 10-08 verificado (resuelve al worktree).
Cero impacto en el resultado — pero el orden correcto era rama primero, y queda anotado.

## Candados

Batería dirigida: **87 verdes** (Sidebar + MenuPrincipal + Volver + Navigation +
ModuloFacturacion + AnchoDePagina + MarcoHorizontal + IdiomaEspanol + FilasClickeables)
— cero amoldes; `ModuloFacturacionTest` pega a las rutas, que no cambiaron. El conteo de
`aria-current="page"` en las dos pantallas de dte: exactamente 1 (el del anfitrión
Documentos), verificado por el mini-candado.

## Para el radar del Director

- `<x-tab-nav>` queda listo para el Lote 5 (Servicios de terreno→Agenda): su `_tabs` será
  un archivo de datos de 10 líneas. El Lote 4 (QR→Listado) es mover un ítem, no
  consolidar con pestaña — se evalúa al llegar.
- QA del dueño en celular: `/admin/documentos-tributarios` y
  `/admin/documentos-tributarios/estado` (las pestañas nuevas), el acordeón Facturación
  con un solo ítem, y las pestañas del Catálogo intactas (migraron de partial a
  componente sin cambio visual).

## Fuera de alcance (declarado)

Lotes 4-5 (esperan la doble llave de este) · el resto del mapa F0 sin visto bueno ·
el `_tabs` de ST (contrato distinto, a propósito) · territorio de Marcos y Max-2.
