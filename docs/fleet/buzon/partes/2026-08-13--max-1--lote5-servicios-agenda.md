# Parte de Max-1 — 2026-08-13 · Dictado v51, Lote 5 HECHO: «Servicios de terreno» vive como pestaña de la Agenda — LA COLA COMPLETA: 47 → 42

> Forjador A, stream 1 · rama `feature/menu-lote5-servicios-agenda` (commit `c226727`) —
> **espera doble llave**. Con este merge se CIERRA la fase «en vuelo»; el Bloque A espera
> su propio dictado (no arranco solo, como manda el v51).

## El número

| | |
|---|---|
| Menú antes → después | **43 → 42 rótulos** (11 primer nivel + 30 subítems + 1 cuenta, verificado por tinker) — **la meta de la cola aprobada: 47 → 42 en cinco lotes** |
| Pérdida de rutas/permisos | **CERO** — las 5 rutas del resource `servicios-terreno` responden idéntico bajo `agendar servicio terreno`; create/edit siguen siendo hijas con su Volver |
| Suite | baseline main `31c0755` (worktree aislado): **2005 verdes / 14.152 aserciones** (clava la referencia del Director en `6d6a2ce`) · rama: **2005 verdes / 14.193 aserciones** — **delta 0 tests, exacto** (las +41 aserciones netas son la 4ª entrada del mini-candado) |
| Bundle | **byte-idéntico** (cero clases nuevas — `<x-tab-nav>` del Lote 3 y el guard `@if count > 1` no agregan nada al CSS) |

## Qué se construyó

1. **`admin/agenda-terreno/_tabs`**: pestañas «Agenda · Servicios de terreno» con el
   `<x-tab-nav>` del Lote 3, montadas en los dos index.
2. **EL MATIZ del lote (declarado — el dictado decía «permiso idéntico» y es cierto solo
   para la mitad de escritura)**: la Agenda la ve también el **técnico industrial** con
   solo `ver agenda terreno` (seeder línea 172 — es SU pantalla principal), y el
   tarifario exige `agendar servicio terreno`. Una pestaña sin gatear le daría **403 al
   usuario principal de la agenda**. Solución con el idioma del `_tabs` de ST (pestañas
   calculadas por permiso): la pestaña del tarifario entra al arreglo solo con
   `@can('agendar servicio terreno')`, y **con una sola pestaña el nav no se dibuja** —
   el técnico ve su agenda exactamente como siempre. Para vendedor/jefatura (los que
   tenían el ítem del menú) las dos pestañas están.
3. **El link «Catálogo de servicios» de la cabecera de la Agenda se retiró**: la pestaña
   lo absorbe — misma URL, mismo `@can` (es el «reapunte» que pedía el dictado: la ruta
   conservada ES la pestaña; dejar ambos sería la duplicación que la casa prohíbe).
4. **MenuPrincipal**: fuera el ítem `servicios-terreno`; `admin.servicios-terreno.*`
   entra al `activo` de la Agenda con el comentario del matiz de permisos.
5. **Mini-candado**: 4ª entrada del mapa (`'admin.servicios-terreno.' =>
   'servicio-tecnico.agenda-terreno'` — prefijo con index, la puerta del Lote 1).
   **Mutado**: quitar el patrón del `activo` → 2 rojos exactos → restaurado (grep del
   marcador) → 3/3 verde (182 aserciones).

## Candados

Batería dirigida: **95 verdes** (Sidebar + MenuPrincipal + Volver + Navigation +
AgendaTerreno + IdiomaEspanol + MarcoHorizontal + AnchoDePagina) — **cero amoldes**:
AgendaTerrenoTest toca las rutas del tarifario, que no cambiaron; VolverTest se ajustó
solo por la fuente única (el ítem salió del menú; el index del tarifario no gana Volver
porque el tab-nav es su navegación — precedente de los lotes 1 y 3 — y sus create/edit
conservan el suyo).

## Verificaciones

- Las 5 rutas del resource intactas (`index/create/store/edit/update`), mismo permiso.
- En ambos index: exactamente 1 `aria-current="page"` (el de la Agenda) — mini-candado.
- El proceso correcto de punta a punta: rama cortada ANTES de tocar un archivo, baseline
  en worktree aislado (vendor copiado + diagnóstico del autoloader) en paralelo.

## Para el radar del Director

- **La cola «en vuelo» queda COMPLETA**: 47 → 46 → 45 → 44 → 43 → **42**. Cinco lotes,
  cinco restas, cero pérdida de pantallas ni permisos, y el mini-candado quedó con las
  4 consolidaciones fijadas por test + mutación.
- QA del dueño en celular (lotes 4 y 5 juntos, como pide el v51 antes de abrir el
  Bloque A): la Agenda con sus pestañas (el técnico industrial NO las ve — correcto),
  el tarifario como pestaña, el Listado de ST con el botón «Códigos QR» y la pantalla
  QR con su Volver.
- **No arranco el Bloque A por mi cuenta** — espero su dictado, como manda el v51.

## Fuera de alcance (declarado)

Bloques A-E del mapa aprobado (esperan dictado por bloque) · la deuda del `<x-tab-nav>`
a 4 pestañas (se paga con la 4ª pestaña) · territorio de Marcos y Max-2.
