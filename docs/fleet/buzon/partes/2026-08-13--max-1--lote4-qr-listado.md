# Parte de Max-1 — 2026-08-13 · Dictado v50, Lote 4 HECHO: «Códigos QR» entra por el Listado de ST

> Forjador A, stream 1 · rama `feature/menu-lote4-qr-listado` (commit `3338424`) —
> **espera doble llave**. El Lote 5 (Servicios de terreno→Agenda) no arranca hasta que
> este merge aterrice.

## El número

| | |
|---|---|
| Menú antes → después | **44 → 43 rótulos** (11 primer nivel + 31 subítems + 1 cuenta, verificado por tinker) |
| Pérdida de rutas/permisos | **CERO** — mudanza: `admin.servicio-tecnico.qr` responde idéntico bajo `manage servicio tecnico`; el afiche imprimible es el mismo |
| Suite | baseline main `61948cb` (worktree aislado): **2005 verdes / 14.113 aserciones** (clava la referencia del Director en `47785ad`) · rama: **2005 verdes / 14.152 aserciones** — **delta 0 tests, exacto** (las +39 aserciones netas: la 3ª entrada del mini-candado y los asserts nuevos del amolde de NavigationTest) |
| Bundle | **byte-idéntico** — el botón reusa el idioma literal del botón secundario de cabecera de productos/index |

## La forma elegida (la decisión que el dictado me delegó): BOTÓN, no pestaña

- **Jerarquía** (el criterio del dictado): QR no es hermana del Listado — es una
  utilidad de impresión (1 GET que genera el afiche por sucursal). Hermana = pestaña;
  utilidad = **hija** con botón de entrada y su «Volver».
- **El hecho técnico que mata la pestaña: los permisos NO son idénticos.** Listado =
  `view|manage servicio tecnico`; QR = solo `manage`. `<x-tab-nav>` no gatea pestañas —
  su precondición documentada (lotes 1 y 3) es permiso idéntico — así que una pestaña se
  la mostraría al vendedor con `view`, que recibe 403 al tocarla. El botón se gatea con
  `@can('manage servicio tecnico')`, exactamente como el CTA «Registrar ingreso» que ya
  vive en esa misma cabecera.
- **Ubicación — desviación de la LETRA del mapa F0, declarada**: el mapa decía «junto al
  bloque por-confirmar». Ese bloque es doblemente condicional (`@can('confirmar…')` y
  solo si hay pendientes): anclar ahí la única entrada la haría indescubrible la mayoría
  del tiempo. El botón va en la cabecera del Listado — permanente, y queda visualmente
  encima de ese mismo bloque cuando aparece. El espíritu del veredicto («dentro del
  Listado») se cumple.

## La respuesta a la pregunta del Director sobre el mini-candado

**Aplica TAL CUAL con botón — cero cambios de forma.** El candado nunca asumió tab-nav:
verifica (a) que la ruta consolidada esté cubierta por el `activo` del anfitrión y
(b) que la pantalla consolidada resalte exactamente al anfitrión en la sidebar — eso es
verdad con pestaña, botón o sección. La ruta hoja entra por la «puerta» generalizada del
Lote 3 (ruta exacta registrada → se visita directo). Tercera línea del mapa
`CONSOLIDADAS`, **mutada**: quitar `admin.servicio-tecnico.qr` del `activo` de `listado`
→ 2 rojos exactos (cobertura + 0 aria-current en la pantalla) → restaurar → 3/3 verde
(137 aserciones).

## Qué se tocó

1. **`servicio-tecnico/index.blade.php`**: botón secundario «Códigos QR» en el slot
   `action`, dentro del `@can('manage servicio tecnico')` existente, junto a «Registrar
   ingreso»; el comentario de las acciones secundarias explica la excepción y el porqué
   del gateo.
2. **`servicio-tecnico/qr.blade.php`**: pasa a HIJA — `:back`/`backTitle` en su
   page-header (doctrina P-NAV-08) y el comentario del no-Volver reemplazado por la
   razón vigente. `VolverTest` se ajustó SOLO por la fuente única: al salir el ítem del
   menú, el candado de ítems dejó de visitarla, y la doctrina de hijas pide el Volver
   que ahora tiene. Cero amoldes en VolverTest.
3. **`MenuPrincipal`**: fuera el ítem `qr`; la ruta entra al `activo` ENUMERADO de
   `listado` (la convención anti-comodín del propio módulo, gate 28-07).
4. **`MenuConsolidacionesTest`**: la línea del Lote 4.

## Amolde declarado (doctrina PwaTest 13-07)

`NavigationTest` fijaba «Códigos QR» como ítem del menú: el admin lo VEÍA en la sidebar
(`test_admin_ve_todos_los_accesos…`, rojo al quitar el ítem) y el vendedor NO
(`test_solo_lectura…`, quedaba verde-trivial). El contrato cambió legítimamente y el
amolde **preserva la intención en la ubicación nueva** (estándar del Lote 2: probar que
la regla sobrevive en otra parte): el admin ahora se assertea viendo el botón y el href
en la cabecera del Listado, y el vendedor se assertea NO viéndolo ahí (el gateo por
`manage` que justifica la forma botón, fijado por test). Batería dirigida: **97 verdes**
(Sidebar, MenuPrincipal, Volver, Navigation, IdiomaEspanol, IngresoTallerPublico,
ServicioTecnicoVisibilidad).

## Verificaciones

- Ruta `admin.servicio-tecnico.qr` intacta bajo su permiso (los signed-URL tests de
  IngresoTallerPublicoTest pasan sin tocarse).
- En la pantalla QR: exactamente 1 `aria-current="page"` y es el del Listado
  (mini-candado); el acordeón ST abre igual (su `activo_extra` comodín ya la cubría — lo
  que faltaba era el resaltado del ítem, que es justo lo que el candado fija).
- El proceso del Lote 3 corregido: **rama cortada ANTES de tocar un solo archivo**, y la
  baseline corrió en worktree aislado (vendor copiado + diagnóstico del autoloader) en
  paralelo a la construcción.

## Para el radar del Director

- QA del dueño en celular: el Listado de ST con el botón «Códigos QR» junto a «Registrar
  ingreso» (visible solo para quien gestiona), la pantalla QR con su «Volver», y el
  acordeón de ST sin el ítem.
- El Lote 5 (Servicios de terreno→Agenda) vuelve al patrón pestaña: permiso idéntico y
  `<x-tab-nav>` listo — el `_tabs` será de 10 líneas, como anotó el dictado.

## Fuera de alcance (declarado)

Lote 5 (espera doble llave + dictado) · el mapa F0 aprobado en bloques (espera dictados)
· la deuda del `<x-tab-nav>` a 4 pestañas (anotada, se paga con la 4ª pestaña) ·
territorio de Marcos (simulador) y Max-2.
