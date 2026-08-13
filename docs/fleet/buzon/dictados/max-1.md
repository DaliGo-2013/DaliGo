# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-13 (v49 — Lote 2 EN PRODUCCIÓN; GO Lote 3: Estado → Documentos). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ Lote 2 está EN PRODUCCIÓN (merge `ee1a72c`, doble llave 13-ago)

**Menú 46 → 45.** Suite del Director sobre el árbol mergeado: **2005 verdes / 14.067
aserciones, cero rojos** — tu delta de −4 se cumplió exacto contra una baseline que
Marcos ya había movido. Spot-checks 6/6. Rama borrada.

Dos cosas de tu lote que quedan como estándar:

1. **Declarar el test ajeno que muere con la superficie.** Retiraste
   `TrasladoServicioTest::…paso_en_camino` y en el mismo parte demostraste que la
   decisión del dueño NO se pierde: vive en `docs/reglas/`, y su candado contra datos
   reales («Va en camino desde Coquimbo» en la ficha) sigue verde. Así se retira un
   test: probando que la regla sobrevive en otra parte, no borrando y callando.
2. **Verificar la condición de STOP y reportarla aunque no aplique.** Confirmaste que
   el boceto era render puro de arreglos, cero tablas, cero escrituras.

Nota mía, de mi lado: el conflicto del merge fue solo `public/build/manifest.json`
(ambos padres recompilaron el bundle). Lo resolví **recompilando sobre el árbol
mergeado**, nunca eligiendo lado — un manifest elegido a dedo apunta a un CSS que no
contiene las clases del otro padre. Superset contra ambos: 0 clases usadas perdidas.

## 🟢 GO — Lote 3 · «Estado» pasa a pestaña de «Documentos» (45 → **44**)

El más barato de los que quedan: 1 ruta GET, 100 % lectura, mismo permiso
(`emitir documentos tributarios`), mismo controller, y el index de Documentos ya la
enlaza dos veces.

- **Anfitrión**: Documentos. Pestaña **«Estado de la conexión»**, con el mismo tab-nav
  y el mismo `aria-current="true"` del Lote 1 (no `"page"` — colisiona con el conteo de
  SidebarTest).
- **Hereda el mini-candado**: agrega la entrada al mapa `CONSOLIDADAS` de
  `MenuConsolidacionesTest`. Una línea, para eso se construyó genérico.
- **Ruta y permiso se CONSERVAN** — esto es una mudanza, no un retiro. Cero
  funcionalidad perdida; los 2 links existentes del index se reapuntan a la pestaña.
- **Decisión menor delegada a ti** (el mapa F0 la deja abierta): si el módulo queda de
  1 ítem, o conservas el acordeón por el `activo_extra` del documento de ST, o Facturación
  pasa a link directo. Elige y **declara el porqué en el parte**.
- **Ojo con `VolverTest`**: «Estado» está hoy en la lista de pantallas que SALEN del menú
  y por eso llevan Volver. Al volverse pestaña dentro de Documentos, eso cambia. Ajusta
  lo que el candado exija **por la fuente única**, sin amoldar el test a mano.

## Después de este lote (la cola aprobada, no arranques sin dictado)
Lote 4 QR→Listado ST · Lote 5 Servicios de terreno→Agenda (llegan a 42).
El resto del mapa F0 sigue esperando visto bueno del dueño, apartado por apartado.

## Territorio
- **Max-2** en pausa (M11 100 % construido).
- **Marcos** MUY activo en el simulador. Rama corta, push temprano, re-fetch religioso.
  Si recompilas el bundle, hazlo **último**, justo antes del push.

## Recordatorios
Rama nueva desde main FRESCO; suite COMPLETA de main fresco ANTES de empezar (baseline
del Director: **2005 / 14.067** en `ee1a72c`). Suite completa antes del push. Parte al
buzón → doble llave → Lote 4.

CIERRE: parte a docs/fleet/buzon/partes/ + push. Dos lotes, dos restas. El menú va bajando.
