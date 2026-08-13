# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-13 (v50 — Lote 3 EN PRODUCCIÓN; GO Lote 4: Códigos QR → Listado de ST). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ Lote 3 está EN PRODUCCIÓN (merge `47785ad`, doble llave 13-ago)

**Menú 45 → 44.** Suite del Director sobre el árbol mergeado: **2005 verdes / 14.113
aserciones** — tu delta 0 y tus +46 aserciones, exactos. Conté el menú por mi cuenta
(11 + 32 + 1 = 44) y da. Bundle: `public/build/` ni aparece en el diff, byte-idéntico
como declaraste. Rama borrada.

Lo mejor del lote, para que se repita:

1. **Extrajiste `<x-tab-nav>` al 3er uso, no al 2º.** Lo prometiste en el parte del Lote 1
   y lo cumpliste tres lotes después sin que nadie te lo recordara. Y lo que NO metiste
   adentro vale igual: el `_tabs` de ST se queda fuera **a propósito** porque su contrato
   es otro, con el porqué escrito en el docblock. Eso es una abstracción hecha bien.
2. **Mutaste el candado extendido antes de creerle**: quitar la ruta del `activo` da 2
   rojos exactos, restaurar da 3/3 verde. Un candado sin mutar es decoración.
3. **La decisión que te delegué la resolviste con el argumento correcto**: colapsar el
   acordeón de Facturación daría 43 y cambiaría **en silencio** los números del mapa F0
   que el dueño aprobó. Detectar que una mejora local desalinea un acuerdo global es
   exactamente el criterio que quiero.
4. **Declaraste tu propio incidente de proceso** (editar el árbol parado en main y
   contaminar la baseline) sin que nadie preguntara, con la corrección hecha en el
   momento. Cero impacto en el resultado. Anotado y cerrado: rama primero, siempre.

## 🔎 Deuda que dejó tu componente (anotada, NO la arregles ahora)

`<x-tab-nav>` resuelve el ancho con `count($tabs) === 3 ? 'grid-cols-3' : 'grid-cols-2'`.
Con **4 pestañas cae a 2 columnas sin avisar** — justo el caso de «Configuración de
producción» (Máquinas/Tipos/Recetas/Moldes) si el dueño le da visto bueno. Hoy los dos
usos son de 2, así que no hay defecto: queda escrito en el plan y **se resuelve en el
lote que traiga la 4ª pestaña**, no antes.

## 🟢 GO — Lote 4 · «Códigos QR» pasa al Listado de Servicio Técnico (44 → **43**)

Ojo: este **no es el mismo patrón** que los lotes 1 y 3. Aquí no hay dos pantallas
hermanas de igual jerarquía, así que **no asumas pestaña**:

- QR es 1 ruta GET rara (genera el afiche imprimible por sucursal) bajo `manage servicio
  tecnico`. El mapa F0 lo describe como **botón/sección «Códigos QR» dentro del Listado**,
  junto al bloque por-confirmar que esos QR alimentan.
- **Elige la forma y declárala**: pestaña, botón en la cabecera del listado, o sección.
  El criterio no es la estética — es de qué jerarquía es la pantalla respecto del
  anfitrión. Si termina siendo pestaña, hereda `<x-tab-nav>`; si es botón, la pantalla
  QR pasa a ser **hija** y entonces **necesita su «Volver»** (el mapa lo anota: era ítem
  del menú, y al dejar de serlo `VolverTest` cambia de lado). Resuélvelo por la fuente
  única, sin amoldar el test a mano.
- **Permiso y ruta se conservan** — mudanza, no retiro.
- **Mini-candado**: agrega la entrada a `CONSOLIDADAS` y **mútala** como hiciste esta vez.
  Si la forma elegida es «botón» y no pestaña, dime en el parte si el candado sigue
  aplicando tal cual o necesita otra forma — es información de diseño, no un trámite.

## Después de este lote (la cola aprobada, no arranques sin dictado)
Lote 5 Servicios de terreno → Agenda de terreno (llega a **42**; permiso idéntico y la
agenda ya la enlaza desde su cabecera — ahí sí `<x-tab-nav>` con un `_tabs` de 10 líneas).
El resto del mapa F0 sigue esperando visto bueno del dueño, apartado por apartado.

## Territorio
- **Max-2** en pausa (M11 100 % construido).
- **Marcos** MUY activo en el simulador. Rama corta, push temprano, re-fetch religioso.

## Nota de infra (I-10, nueva en el tablero)
Hoy GitHub rechazó tres `git push` a main con **`Internal Server Error`** mientras su
página de estado decía «todo operacional». No es tu problema si te pasa: no toques el
árbol, empuja primero a una rama temporal (eso sube los objetos y aísla el fallo al ref)
y reintenta. Si el `--delete` de la temporal también cae, bórrala por API. Receta completa
en `docs/fleet/TABLERO-3-DIAS.md` § I-10.

## Recordatorios
Rama nueva desde main FRESCO **antes de tocar un solo archivo**; suite COMPLETA de main
fresco ANTES de empezar (baseline del Director: **2005 / 14.113** en `47785ad`). Suite
completa antes del push. Parte al buzón → doble llave → Lote 5.

CIERRE: parte a docs/fleet/buzon/partes/ + push. Tres lotes, tres restas. Va quedando fino.
