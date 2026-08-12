# Parte de Max-1 — 2026-08-12 · Dictado v47, Lote 1 HECHO: F1 Piloto «Precios → Catálogo»

> Forjador A, stream 1 · rama `feature/menu-f1-precios-catalogo` (commits `b8563ce` + merge `78d2726`)
> — **espera doble llave** (Director + Mauricio). Lote 2 (retiro del boceto Seguimiento) NO arranca
> hasta que este merge aterrice, como manda el dictado.

## El número

| | |
|---|---|
| Menú antes → después | **47 → 46 rótulos** (11 primer nivel + 34 subítems + 1 cuenta; verificado contra `MenuPrincipal::items()`) |
| Pérdida de rutas/permisos | **CERO** — las 3 rutas `admin.listas-precios.*` responden idéntico, mismo permiso (`manage productos`), URLs guardadas vivas |
| Suite | baseline main `ee15eb7`: **1930** · rama pre-merge: **1933** (+3 = el mini-candado, delta exacto) · post-merge con main avanzado: **1964 verdes (13.960 aserciones)** |
| Bundle | **byte-idéntico** — cero clases nuevas (las 9 del tab-nav ya existían vía el `_tabs` de ST); no viaja build |

## Qué se construyó

1. **Tab-nav** `admin/catalogo/_tabs.blade.php` (partial nuevo, idioma segmented del `_tabs` de ST):
   «Productos · Listas de precios», montado bajo la cabecera de ambas pantallas. La activa lleva
   `aria-current="true"` — **JAMÁS `"page"`**: colisionaría con el conteo de SidebarTest (comentario
   en el propio partial).
2. **MenuPrincipal**: fuera el ítem `precios`; `admin.listas-precios.*` entra al `activo` de
   Catálogo con comentario de consolidación.
3. **AccesosDashboard**: card «Precios» **retirada** (decisión declarada abajo).
4. **`MenuConsolidacionesTest`** — el mini-candado que el dictado pidió y **los lotes 2-5 heredan
   con una línea**: mapa `CONSOLIDADAS = [prefijo de ruta => ítem anfitrión]` con 3 asserts —
   (a) toda ruta del prefijo cubierta por el `activo` del anfitrión (el hueco del resaltado
   silencioso que detecté en F0, cerrado), (b) GET al index del prefijo → **exactamente 1**
   `aria-current="page"` y es el del anfitrión (forma contigua, doctrina anti verde-engañoso),
   (c) el ítem retirado no puede volver al menú.

## Decisiones declaradas (las que el dictado me dejó a mí)

- **«Pestaña» = tab-nav entre las dos pantallas existentes**, no merge físico: cada una conserva
  URL, controller y permiso → cero pérdida por construcción.
- **La card del Inicio se RETIRA, no se reapunta**: Catálogo ya tiene card y la pestaña queda a un
  clic; dos cards al mismo destino serían ruido. La key huérfana en `users.dashboard_colores` es
  tolerada (D-013).
- **La pestaña no lleva `<x-volver>`** (el tab-nav ES su navegación, misma jerarquía que su
  hermana); `listas-precios/show` conserva el suyo (sigue siendo hija del listado).
- El título «Listas de precios» de la pestaña **se conserva** (h1 distinto por pestaña es
  información; la pertenencia a Catálogo la comunica el tab-nav).
- **NO fijé el conteo del menú por test** (46 exacto sería un candado que Max-2/Marcos pisarían con
  cualquier ítem legítimo nuevo): la métrica viaja en los partes; la doctrina de densidad ya obliga
  a declarar ítems nuevos.

## Gate R-31 (la vara del piloto) — tabla de gates

| Gate | Estado | Evidencia |
|---|---|---|
| Suite completa | OK | 1933/1933 pre-merge (13.850 aserciones; +3 exacto sobre baseline); post-merge **1964/1964** (incluye los +31 de los lotes de Max-2 y Marcos que aterrizaron en main) |
| Mutación del mini-candado | OK | quitar `admin.listas-precios.*` del `activo` → **2 rojos exactos** (cobertura + resaltado: las dos mitades del hueco F0); test (c) verde como corresponde; restaurado con `git checkout --` + grep del marcador (`MenuPrincipal.php:67`) |
| Candados existentes | OK | 144 verdes en batería dirigida (Sidebar/MenuPrincipal/Volver/Navigation/Dashboard/Colores/ListaPrecio/FilasClickeables/Idioma/Producto) — **cero amoldes**: todos iteran desde la fuente única y se auto-adaptaron |
| Build / bundle | OK | `view:clear` + build → `git status public/build` limpio (byte-idéntico); las 9 clases del tab-nav verificadas presentes en `app-CnGWMpBJ.css` con `grep -F` — los 2 «0» del primer grep eran la trampa de escapado (bitácora 30-07) |
| MySQL 5.7 | OK (trivial) | cero migraciones en el diff |
| Locks | OK (trivial) | cero endpoints nuevos ni mutaciones de estado |
| Permisos por ruta | OK | `routes/web.php` intacto; ambas pantallas tras `manage productos` (hecho F0: permiso idéntico → la fusión no cambia visibilidad para nadie) |
| x-componentes / DRY | OK con observación | el tab-nav replica el idioma del `_tabs` de ST como partial (datos + markup en un solo lugar); **cuando el 3er tab-nav llegue (lotes 3/5) se extrae el componente compartido** — antes sería indirección para dos consumidores con formas distintas |
| Responsive 375/768/1024 | OK | banco con render real vía kernel in-process (patrón bitácora 26-07 — **sin ingresar contraseñas**; permisos en transacción con rollback): sin scroll horizontal en 3 anchos × 3 páginas; pestañas en 1 línea a 375 (165×32 c/u); activa `bg-white` + `brand-700`, inactiva `neutral-500`; a 1024 sidebar 264px con «Catálogo» resaltado y acordeón comercial abierto; Inicio sin card Precios |
| Bitácora (reincidencias) | OK | aria-current duplicado (28-07) → esquivado con `"true"`; verde-engañoso (20-07) → forma contigua + conteo + mutación; escapado de grep (30-07) → `grep -F` |
| Fan-out adversarial | NO CORRIÓ | los 4 lentes del workflow murieron por límite de sesión («resets 6pm») **antes de auditar** — lo reemplacé con esta self-audit R-30 sobre el checklist completo de R-31; si el Director quiere el fan-out independiente, se puede re-correr post-6pm sin bloquear la cola |

**VEREDICTO: APROBADO CON OBSERVACIONES** (la extracción del componente tab-nav al 3er uso; el
fan-out adversarial reemplazado por self-audit con evidencia).

## La carrera que sí llegó (re-fetch religioso)

Mientras corría mi gate, **main avanzó 11 commits**: aterrizaron el kaizen de Max-2 (P-M11-23
mergeado) y el segundo piso del simulador de Marcos. La detectó el propio gate (el diff contra
`origin/main` mostraba trabajo ajeno como «deleciones»). Respuesta: `git fetch` + merge de
`origin/main` a la rama (`78d2726`) — **cero solape verificado archivo por archivo** (mis 6 archivos,
`package.json` y `app.css` intactos del lado de ellos → sin `npm install`, sin rebuild, el CSS del
bundle es el mismo), `migrate` local para su migración nueva, candados del menú re-corridos sobre el
merge (**40 verdes**) y la suite completa re-lanzada sobre el HEAD mergeado.

## Verificación (resumen)

`git diff ee15eb7..b8563ce --stat` = 6 archivos, +153/−3 (2 nuevos: el partial y el test) · conteo
46 verificado por tinker · 3 rutas de listas-precios intactas · RUTA-MAESTRA no se toca (los lotes
del menú se rastrean en PLAN-MENU-DENSIDAD; el marcado de F1 es del Director con la doble llave,
como en F0).

## Para el radar del Director

- El mini-candado quedó **genérico a propósito**: el Lote 3 (Estado→Documentos) y el 5 (Servicios de
  terreno→Agenda) agregan su línea al mapa `CONSOLIDADAS` y heredan los 3 asserts. El Lote 2 (retiro
  del boceto) y el 4 (QR→Listado, que es mover un ítem, no consolidar rutas con anfitrión nuevo) se
  evalúan al llegar.
- QA del dueño en celular: las dos pantallas con pestañas (`/admin/productos` y
  `/admin/listas-precios`) + el Inicio sin la card. Su ritmo manda.

## Fuera de alcance (declarado)

Lotes 2-5 (esperan la doble llave de este) · el resto del mapa F0 sin visto bueno (ni tocado) ·
territorio Max-2 (kaizen — solo lo mergeé desde main) y Marcos (simulador — ídem).
