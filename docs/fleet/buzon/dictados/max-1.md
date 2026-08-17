# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-17 (v62 — QA del dueño del Bloque C ✅: GO Bloque D, lote D1 Kardex→Producción). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ QA del Bloque C aprobado por el dueño (17-ago)

Verificado en celular: Administración con 3 ítems, pestañas de Usuarios y del Registro
funcionando, bandeja y campanita intactas, Inicio con una card. **Bloque C cerrado con
QA.** Marcador: 47 → 36 en producción.

## 🔨 GO — Lote D1: «Kardex» entra al ítem «Producción» (36 → 35)

El lote más corto del mapa — pero con un candado CON HISTORIA que hay que tratar con
respeto. El reconocimiento del Director:

### El cruce (hecho): permiso IDÉNTICO por construcción

Kardex (`admin.produccion.movimientos`) e ítem Producción comparten el MISMO string de
permiso: `manage production` (MenuPrincipal L83 y L86). Una sola audiencia definida una
vez — la precondición limpia de B1. **Sin gateo, sin canAny, sin nudo.**

### La forma (mudanza, no pestaña)

D1 NO lleva tab-nav: el panel de Producción YA tiene el botón «Kardex» en su cabecera
(`produccion/index.blade.php` L7) y el reporte enlaza «Ver kardex completo» (L232). La
entrada existe; lo que sobra es el ítem del menú.

1. **`MenuPrincipal`**: fuera el ítem `kardex` (L86); `admin.produccion.movimientos`
   entra a la lista explícita del `activo` de `produccion` (L83). OJO: ese `activo` es
   lista explícita A PROPÓSITO (el prefijo `admin.produccion.*` lo comparten las rutas
   del soplador que viven en «Mi producción») — respeta la lista, no metas wildcard.
2. **El candado P-NAV-06 (`VolverTest::test_las_ex_huerfanas_estan_en_el_menu`) se pone
   ROJO a propósito** — es la mitad del lote. Su propio texto dicta la salida: «si
   alguien las SACA del menú, vuelven a quedar huérfanas y necesitan su Volver de
   vuelta». Kardex pasa de ítem a HIJA del panel:
   - `movimientos.blade.php` recupera su **`<x-volver>`** (se lo quitaron cuando subió a
     ítem — P-NAV-06 27-jul; ahora el flujo es menú Producción → botón Kardex → Volver).
   - En el candado, la ruta **sale de la lista de ex-huérfanas CON RASTRO**: comentario
     que cuente las dos vidas (huérfana → ítem 27-jul → hija con Volver por D1 17-ago,
     vigilada ahora por la 11ª entrada del mini-candado). La historia no se borra.
   - Si `test_ningun_item_del_menu_lleva_volver` u otro derivado de la fuente única se
     mueve solo, decláralo — nunca amoldes a mano lo que deriva.
3. **`MenuConsolidacionesTest`: 11ª entrada** (`admin.produccion.movimientos` →
   anfitrión `produccion`; forma exacta del mapa como esté el candado). **Mutación**:
   quitar la ruta del `activo` → rojos exactos → restaurar → verde.
4. **Cards del Inicio**: revisa `AccesosDashboard` por card «Kardex» — si existe, misma
   decisión C1/C2 (retirar o reapuntar con porqué; Producción ya tendrá la suya).
5. Prefijos: `admin.produccion.movimientos` es ruta EXACTA en el activo (sin comodín) —
   cero riesgo de encender al soplador. Corre igual tu `Str::is` contra
   `mi-produccion`/rutas del soplador y decláralo.
6. Comentario con rastro en MenuPrincipal si el ítem kardex tenía nota defendiéndolo.

### Verificación (invariante)
Rama `feature/menu-d1-kardex-produccion` desde main FRESCO. Suite COMPLETA de main
fresco ANTES (baseline Director: **2186/15.184** en `6fa64cd`). Batería dirigida: Volver +
MenuConsolidaciones + Sidebar + MenuPrincipal + Navigation + Dashboard +
**ProduccionKardexTest y la carpeta Produccion completa** (el kardex real: backflush,
filtros, permiso). Conteo tinker: **35**. Parte al buzón; espera doble llave. NO
arranques E.

## 📡 Después de D1 — el CIERRE: Bloque E (v63)
QA del dueño del Bloque D (corto: Producción resalta en el Kardex, Volver funciona,
sidebar sin el ítem) → **E1 Configuración de producción 4→1** (Máquinas · Tipos de
botellón · Recetas · Moldes, todas bajo `manage production`): la mayor densidad del mapa
(−3), anfitrión nuevo, y ahí se paga la deuda del `<x-tab-nav>`: **`grid-cols-4` NO
existe** (hoy `count===3 ? cols-3 : cols-2` — con 4 caería a 2 columnas sin avisar).
Máquinas y Tipos de botellón son TAMBIÉN ex-huérfanas de P-NAV-06 — mismo trato que
Kardex hoy: este lote es el ensayo del molde.

## Estado
Max-2 en pausa (v24). Marcos activo. Baseline: 2186/15.184 en `6fa64cd`.

CIERRE: GO D1. Un lote, un parte, una llave. El penúltimo — fierro.
