# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-11 (v45 — GO F0 de PLAN-MENU-DENSIDAD: auditoría + mapa del menú. SOLO DOCS). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## Proyecto nuevo del dueño: densidad de interfaz

**Lee `docs/planes/PLAN-MENU-DENSIDAD.md` (VIGENTE)** — directriz textual del dueño:
la lista lateral crece en cada lote porque construimos superficies sin preguntarnos si
podían vivir dentro de un apartado existente. Su lente, que es EL criterio del proyecto:

> **«¿Este apartado puede ser integrado con otro, o necesita vivir sí o sí solo?»**

Y: crear/editar/eliminar = visibles por PERMISO dentro de las pantallas, no superficies.
Ritmo explícito del dueño: **lento pero seguro, decisiones con calma** — este proyecto
no corre, piensa. (Autocrítica de la casa que te va a sonar: tus propios dictados de M11
te mandaron crear los ítems «Recetas» y «Moldes» sin este filtro. El proyecto arregla el
mecanismo, no culpa lotes pasados.)

## 🟢 GO — F0 · Auditoría + mapa (SOLO DOCS — cero código, cero riesgo)

Tu entregable es el **anexo §5 de PLAN-MENU-DENSIDAD.md** (lo llenas en tu rama, docs
only):

1. **Inventario**: los ~47 ítems de `MenuPrincipal` — por cada uno: qué pantallas
   cuelgan (rutas reales), permiso, qué rol lo usa, y tu estimación de frecuencia de
   uso (diaria/semanal/rara — infiérela del dominio: el kardex se mira a diario, la
   auditoría rara vez).
2. **Veredicto con el lente** por ítem: `integrable-en-X` (CON la propuesta concreta:
   pestaña de qué pantalla, sección de qué show, botón bajo qué permiso) o `vive-solo`
   (con el porqué en una línea). Sé valiente en el veredicto y conservador en el riesgo:
   el veredicto es propuesta, la decisión es del dueño.
3. **Mapa objetivo**: el menú como quedaría si TODAS tus propuestas se aprobaran
   (estructura completa, con el conteo antes/después — hoy 47).
4. **Priorización**: consolidaciones ordenadas por densidad ganada × esfuerzo × riesgo.
   El piloto F1 (Catálogo+Precios → uno) ya está decidido por el dueño — inclúyelo en el
   mapa pero no lo re-litigues.
5. **Ojo con los candados existentes**: tu análisis debe anotar, por consolidación, qué
   candados de menú se tocan (SidebarTest aria-current único, VolverTest, doble
   aria-current del comodín, badges accionables) — es insumo del lote que la ejecute.

**Qué NO hacer**: ni una línea de código, ni tocar MenuPrincipal, ni arrancar el piloto.
El mapa va al dueño para visto bueno ANTES de cualquier consolidación — decisión con
calma, apartado por apartado.

## Territorio
- **Max-2** forja P-M11-23 (kaizen) en paralelo — sin cruce (tú docs, él producción).
- **Marcos** activo. Tu rama es docs-only: cero riesgo de carrera de código (el parte
  igual con re-fetch).

## Recordatorios
Rama nueva desde main FRESCO (docs-only: sin suite obligatoria de arranque, pero corre
`PlanProyectoTest` si llegas a tocar RUTA-MAESTRA — no deberías). Parte al buzón →
revisión del Director → el mapa al dueño.

CIERRE: parte a docs/fleet/buzon/partes/ + push. La mejor interfaz es la que no hay que
recorrer.
