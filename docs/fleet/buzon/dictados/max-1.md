# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-12 (v47 — VISTO BUENO del dueño: piloto F1 + las 4 baratas. Cola de 5 lotes, de a uno). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## El dueño aprobó: F1 (pre-decidido) + las 4 baratas (#2-#5 de tu priorización)

Tu mapa fue presentado y el dueño dio **visto bueno explícito a las 4 baratas**; el
piloto ya venía decidido. El RESTO del mapa (Configuración de producción, Registro del
sistema, Roles→Usuarios, Cargas reales, Kardex, Conductores, Traslados) queda
**PENDIENTE de visto bueno** — ni los toques.

## 🟢 GO — cola de 5 lotes, EN ORDEN, cada uno con su rama + parte + doble llave

**Regla de la cola**: un lote por vez; pusheas el parte, sigues con el siguiente SOLO
tras la doble llave del anterior (el menú es territorio sensible — los candados de un
lote alimentan al siguiente). Rama corta por lote.

### Lote 1 · F1 Piloto: Precios → Catálogo (−1)
Pestaña «Listas de precios» dentro de Catálogo (mismo permiso `manage productos`); el
ítem «Precios» sale de MenuPrincipal; las rutas `admin.listas-precios.*` entran al
`activo` del ítem Catálogo; la card «Precios» del Inicio se reapunta o se retira
(decisión tuya declarada). **Gate R-31** (es el piloto — la vara del proyecto).

### Lote 2 · Retiro del boceto «Seguimiento» (−1)
Densidad gratis. El código del boceto se retira COMPLETO (ítem + rutas + vista), no se
esconde — si algún día se retoma, vive en git.

### Lote 3 · Estado → Documentos (−1)
Pestaña «Estado de la conexión» en Documentos; decide tú (declarado) si Facturación
queda acordeón de 1 o pasa a link directo — recuerda el `activo_extra` del documento ST.

### Lote 4 · QR → Listado ST (−1)
Sección/pestaña en el Listado; OJO VolverTest: era ítem de menú (sin Volver) → al dejar
de serlo, vuelve el `<x-volver>` — tu propio hallazgo del candado de ex-huérfanas
aplica al REVÉS aquí (edítalo conscientemente).

### Lote 5 · Servicios de terreno → Agenda (−1)
Permiso idéntico, link en cabecera ya existe — pestaña o sección en Agenda.

### Candados TRANSVERSALES (van en los 5 lotes)
1. **El mini-candado que tú mismo sugeriste, constrúyelo en el Lote 1**: test que
   verifique que toda ruta movida a pestaña está en el patrón `activo` de su anfitrión
   (el hueco del resaltado silencioso). Los lotes 2-5 lo heredan gratis.
2. SidebarTest aria-current único + MenuPrincipalTest en verde en cada lote.
3. Cero pérdida: las rutas y permisos NO se tocan (salvo el retiro completo del Lote 2).
4. Conteo del menú en el parte de cada lote: antes → después (la métrica del proyecto).
5. Suite COMPLETA antes de cada push; bundle + superset si tocas Blade (siempre lo harás).
6. QA del dueño por lote (celular) — su ritmo manda: si pide pausa entre lotes, pausa.

## Territorio
- **Max-2** sigue en P-M11-23 (kaizen, producción/PWA) — sin cruce con menú/catálogo/ST.
- **Marcos** activo. Re-fetch religioso; 5 pushes tuyos = 5 oportunidades de carrera.

## Recordatorios
Rama nueva desde main FRESCO por lote; suite completa de main fresco al arrancar la
cola (fija tu baseline). `git checkout origin/main --` para conflictos. varchar ≤191.
Parte al buzón → doble llave → siguiente lote.

CIERRE: parte del Lote 1 a docs/fleet/buzon/partes/ + push. 47 → 42 al final de esta
cola. La resta también se construye.
