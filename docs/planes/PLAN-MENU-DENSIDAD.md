# PLAN-MENU-DENSIDAD · Consolidar la interfaz — menos menús, pantallas más densas

> **Estado: VIGENTE (definido por el dueño, 2026-08-11)** — proyecto de ritmo LENTO por
> decisión explícita: «poco a poco, lento pero seguro, y tomar las decisiones con
> calma». Sin fecha objetivo. Un lote = una consolidación. Autor: Director, sobre la
> directriz textual del dueño.

## 0. El problema en una frase

Cada módulo nuevo agregó superficies al menú sin preguntarse si podían vivir dentro de
un apartado existente: hoy MenuPrincipal declara **~47 ítems** (Operación sola carga 8
subítems) y la lista lateral crece en cada lote. Construimos componentes enteros donde a
veces bastaba una pestaña, una sección o un botón gateado por permiso.

## 1. El lente (criterio único de cada veredicto — palabras del dueño)

> **«¿Este apartado puede ser integrado con otro, o necesita vivir sí o sí solo?»**

Y su corolario: **crear/editar/eliminar son elementos visibles mediante permisos** dentro
de las pantallas — no superficies aparte del menú.

## 2. Principios

1. **Cero pérdida de funcionalidad.** Consolidar = reubicar. Nada se borra; todo lo que
   hoy se puede hacer, se sigue pudiendo hacer — desde otro lugar más denso.
2. **El lente manda.** Cada ítem del menú recibe veredicto: `integrable-en-X` (con la
   propuesta concreta de dónde y cómo) o `vive-solo` (con el porqué).
3. **Permisos gatean acciones, no menús.** El patrón ya existe (entrada a editar en el
   show, @can en botones); esta doctrina lo vuelve regla de diseño.
4. **El menú es territorio sensible** (E-NAV cerrada con QA 10/10): cada consolidación
   pasa gate R-31, los candados de MenuPrincipal/SidebarTest/aria-current, y QA del
   dueño en celular.
5. **Un lote = una consolidación = una doble llave.** Nada de big-bang; las decisiones
   se toman de a una y con calma.
6. **Doctrina preventiva** (ya en CLAUDE.md §Navegación): ítem de menú nuevo exige
   declarar en el parte por qué no puede vivir en un apartado existente.

## 3. Fases

### F0 · Auditoría + mapa (Max-1 — SOLO DOCS, cero código)
Inventario completo de los ítems de MenuPrincipal: contenido real (rutas/pantallas que
cuelgan), permiso, rol que lo usa, frecuencia estimada de uso, y **veredicto con el
lente**. Entregable: anexo §5 de ESTE archivo con la tabla completa + el **mapa
propuesto** (menú objetivo) + consolidaciones priorizadas (densidad ganada × esfuerzo ×
riesgo). **El mapa se presenta al dueño para visto bueno ANTES de tocar código** — él
decide cuáles van y en qué orden.

### F1 · Piloto (ya decidido por el dueño): Catálogo + Precios → UNO
Las apps de productos integran el precio con el producto. «Precios» (espejo de listas
Bsale) pasa a vivir dentro de Catálogo — pestaña/sección visible por el permiso que YA
comparten (`manage productos` en ambos ítems: consolidación natural). El ítem «Precios»
sale del menú. Gate R-31 + QA del dueño. **Arranca solo tras el visto bueno del mapa**
(el piloto también se beneficia de ver el bosque completo primero).

### F2+ · Las consolidaciones aprobadas del mapa, de a una
Candidatas que la auditoría DEBE evaluar (ninguna pre-decidida — el veredicto es de la
F0 y la decisión del dueño): Kardex dentro de Producción · Recetas/Máquinas/Moldes/Tipos
de botellón como una sola superficie de «configuración de producción» · Cargas reales
dentro del Simulador · Documentos+Estado (Facturación) → uno · Historial de aprobaciones
junto a Aprobaciones o Auditoría · lo que la auditoría encuentre.

## 4. Hecho cuando (por lote)

- [x] F0: mapa completo en §5 (Max-1, 12-ago) — **visto bueno del dueño (12-ago) a F1 + las 4 baratas (#2-#5)**; el resto del mapa pendiente de decisión, apartado por apartado.
- [x] **F1 EN PRODUCCIÓN (12-ago, `ca91422`)**: «Precios» vive como pestaña del Catálogo
      (tab-nav Productos · Listas de precios); cero funcionalidad perdida (3 rutas y
      permiso intactos); gate R-31 aprobado con observaciones; mini-candado
      `MenuConsolidacionesTest` instalado y heredable por los lotes siguientes.
      **Menú 47 → 46.** Falta solo el QA del dueño en celular.
- [x] **Lote 2 EN PRODUCCIÓN (13-ago, `ee1a72c`)**: retiro COMPLETO del boceto
      «Seguimiento» — ítem, ruta, acción + helper, vista, componente `st/seguimiento-timeline`
      y su test. El único lote de la cola que RESTA código: **+3 / −244** en 11 archivos.
      El test ajeno que asserteaba contra la maqueta (`TrasladoServicioTest::…paso_en_camino`)
      se retiró CON él y quedó declarado; la decisión de negocio sobrevive en
      `docs/reglas/traslado-maquinas-a-reparar.md` y su candado real («Va en camino desde
      Coquimbo» en la ficha) sigue verde. Suite 2005/14.067, delta −4 exacto.
      **Menú 46 → 45.** Falta solo el QA del dueño en celular.
- [x] **Lote 3 EN PRODUCCIÓN (13-ago, `47785ad`)**: «Estado» dejó de ser ítem y vive como
      pestaña «Estado de la conexión» de Documentos. **Mudanza, no retiro**: ruta, permiso
      y controller intactos. Suite 2005/14.113 — delta 0 tests exacto, +46 aserciones del
      candado extendido. Nace **`<x-tab-nav>`**, extraído al 3er uso como prometió el Lote 1
      (el `_tabs` del Catálogo migró; el de ST queda fuera a propósito: vive en páginas de
      detalle y ahí `aria-current="page"` es correcto). El mapa `CONSOLIDADAS` ahora acepta
      ruta hoja exacta además de `{prefijo}index`. Decisión declarada: el acordeón de
      Facturación se conserva con 1 ítem (su `activo_extra` lo necesita y la aritmética
      aprobada de la cola lo asume). **Menú 45 → 44.** Falta solo el QA del dueño en celular.
- [x] **Lote 4 EN PRODUCCIÓN (13-ago, `6d6a2ce`)**: «Códigos QR» dejó de ser ítem y entra
      por la cabecera del Listado de ST como **BOTÓN** (no pestaña). Decisión delegada,
      resuelta con criterio: los permisos NO son idénticos (Listado `view|manage`, QR solo
      `manage`) → una pestaña se la mostraría al vendedor con `view` que recibe 403; el
      botón se gatea con `@can('manage')`. Desviación de la LETRA del mapa declarada (va en
      la cabecera permanente, no en el bloque doblemente condicional). QR pasa a hija con su
      «Volver». Amolde de NavigationTest correcto (mueve la aserción + agrega el gateo del
      vendedor). Suite 2005/14.152, delta 0 exacto. **Menú 44 → 43.** Falta QA del dueño.
- [x] **Lote 5 EN PRODUCCIÓN (13-ago, `c5f5b47`)**: «Servicios de terreno» (tarifario UF)
      vive como pestaña de la Agenda de terreno. Matiz de permisos que el forjador corrigió
      contra el dictado con evidencia (seeder L172): NO es permiso 100 % idéntico — la
      Agenda la ve el técnico industrial con solo `ver agenda terreno`; el tarifario exige
      `agendar servicio terreno`. La pestaña se gatea con `@can` y con una sola pestaña el
      nav no se dibuja (idioma del `_tabs` de ST) → el técnico ve su Agenda sin la que le
      daría 403. Link «Catálogo de servicios» retirado de la cabecera (lo absorbe la
      pestaña). Suite 2005/14.193, delta 0 exacto. **Menú 43 → 42.** Falta QA del dueño.
- [x] **FASE «EN VUELO» COMPLETA (47 → 42 en cinco lotes)**: cero pérdida de pantallas ni
      permisos; mini-candado con las 4 consolidaciones fijadas por test + mutación.
- [x] **Bloque A · A1 EN PRODUCCIÓN (13-ago, `0e9feaa`)**: «Costos generales de reparación»
      entra al Listado de ST. La «sección Configuración» del mapa F0 se materializó como
      desplegable «Configuración» (QR + Costos, cada uno por su permiso; trigger `@canany`).
      Cambio de UX declarado por el forjador (el botón QR del Lote 4 se muda adentro, 1→2
      clics) → **decisión del dueño: se mantiene el desplegable**. Permisos verificados
      (jefe_ventas + admin, ambos ven el Listado). Re-merge tras drift de 4 commits ajenos
      (auto-merge sin conflicto). Suite 2032/14.396. **Menú 42 → 41.**
- [x] **Bloque A · A2 EN PRODUCCIÓN (14-ago, `6a35329`)**: «Traslados al taller» pasa a
      pestaña del Listado (primera pestaña de FLUJO; la config va en el desplegable). Permisos
      verificados (los 4 roles con despachar|recibir traslado ven el Listado). Prefijo
      `admin.traslados.*` no pisa `admin.bodegas.traslados.*`. Suite 2110/14.723. **Menú 41 → 40.**
      Nota: 1 rojo de ENTORNO durante la verificación (AutorizacionCitaTest del PR #9, ajeno;
      vendor `symfony/error-handler` incompleto → `composer reinstall` lo curó; CI de main verde
      lo confirmó). 4ª incidencia de entorno de la semana.
- [x] **Bloque B · B1 EN PRODUCCIÓN (14-ago, `c67d882`)**: «Cargas reales» pasa a pestaña
      del Simulador. Permiso IDÉNTICO por construcción (grupo `permission:simular carga`) —
      sin nudo. Comentario del ítem-aparte reescrito dejando rastro (estándar del QR).
      Re-merge tras drift (Sucursales+plazos, territorio disjunto). Suite 2182/15.096.
      **Menú 40 → 39.** Con B2 Conductores vive solo, el **Bloque B queda CERRADO en un lote**.
- [ ] Cada F2+: ídem F1 para su consolidación.
- [ ] Métrica simple del proyecto: nº de ítems del menú ANTES (47) vs DESPUÉS de cada
      lote — la densidad ganada se ve en un número. **Hoy: 39** (Bloques A y B cerrados; falta
      C, D, E). **QA del dueño del Bloque B ✅ (14-ago). Bloque C ABIERTO: C1 Roles→Usuarios
      dictado (v59); cruce del Director limpio en ambos lotes (C1: manage roles={admin} ⊂
      view users; C2: los 3 permisos solo-admin, audiencia idéntica por construcción).**
- [ ] **Deuda anotada del `<x-tab-nav>`**: resuelve el ancho con
      `count($tabs) === 3 ? 'grid-cols-3' : 'grid-cols-2'`. Con 4 pestañas cae a 2 columnas
      sin avisar — justo el caso de «Configuración de producción» (Máquinas/Tipos/Recetas/
      Moldes), ahora aprobada. Se salda EN el lote que trae la 4ª pestaña (Bloque E), no
      antes (hoy los dos usos son de 2).

## 4.1 Cola de bloques — el dueño aprobó TODO el mapa, en bloques por módulo (13-ago)

> Decisión del dueño (13-ago): **todas las consolidaciones restantes aprobadas**, con la
> condición de ejecutarlas **en bloques, no todas juntas** (directiva raíz: lento y seguro).
> Criterio de agrupación: **por módulo**. La grande #6 (Config de producción, −3): **al
> final y sola**. El ritmo de seguridad NO cambia: **un lote = un merge = una doble llave**.
> El bloque agrupa el visto bueno y el QA: el dueño aprueba el bloque una vez, Max-1 lo
> forja lote a lote (cada uno con su doble llave), y al cerrarlo el dueño hace QA del
> módulo completo antes de abrir el siguiente. **Nunca dos bloques abiertos a la vez.**

| Bloque | Lotes (en orden) | Δ | Nota |
|---|---|---|---|
| (en vuelo) ✅ | Lote 4 QR→Listado ST · Lote 5 Servicios→Agenda | −2 | HECHO; 44→42; QA del dueño OK (13-ago) |
| **A · Servicio Técnico** ✅ CERRADA (14-ago) | ✅A1 Costos (`0e9feaa`) · ✅A2 Traslados (`6a35329`) · ❌A3 Informe → **VIVE SOLO** (decisión del dueño 14-ago) | **−2** | A1 y A2 en producción. El Informe NO se consolida: su audiencia cruza dos dominios sin anfitrión común (ver «SEGUNDO NUDO» abajo). Bloque cierra en menú 40 |
| **B · Logística** ✅ CERRADA (14-ago) | ✅B1 Cargas reales→Simulador (`c67d882`) · B2 Conductores → **VIVE SOLO** | **−1** | B1 en producción (pestaña; permiso idéntico; nota vieja del código reescrita con rastro). B2 Conductores no se consolida: mismo nudo del Informe → vive solo. Bloque cierra en menú 39 |
| **C · Administración** | C1 Roles→Usuarios · C2 «Registro del sistema» (3→1) | −3 | C2 junta Auditoría+Notif+Aprobaciones; 2 cards del Inicio se reapuntan |
| **D · Producción (barata)** | D1 Kardex→Producción | −1 | ex-huérfana (candado duro); el panel ya la enlaza |
| **E · Cierre (SOLA)** | E1 Configuración de producción (Máquinas·Tipos·Recetas·Moldes 4→1) | −3 | la mayor densidad y más riesgo; **aquí se salda la deuda del `<x-tab-nav>`** (4ª pestaña) |

**Aritmética (actualizada 14-ago tras «Informe vive solo» + «Conductores vive solo»):**
44 → (L4) 43 → (L5) 42 → **A** 40 (A1+A2; Informe vive solo) → **B** 39 (solo B1 Cargas
reales; Conductores vive solo) → **C** 36 → **D** 35 → **E** 32. El mapa completo llega a
**32**, no 30: dos ítems (Informe, Conductores) son casos de audiencia partida entre dos
dominios sin anfitrión común — el propio criterio del proyecto («¿puede integrarse o vive
solo?») los deja fuera. Cero accesos rotos. Honestidad del proyecto por encima del número.

**Regla de apertura:** no se emite dictado de un bloque nuevo hasta que el anterior esté
cerrado (todos sus lotes en producción + QA del dueño). El Bloque A abre cuando Lote 4 y 5
estén en producción con QA. **Cumplido: QA del dueño OK el 13-ago → Bloque A ABIERTO.**

### Hallazgo del Director al abrir el Bloque A — «Informe» NO puede ir tal cual al Listado
El mapa F0 proponía «Informe → pestaña del Listado». Verificación de permisos por
ejecución (seeder + grupos de `routes/web.php`): las rutas del informe viven en su PROPIO
grupo `permission:ver informe dispensadores|ver informe industrial` (web.php L243),
**separado** del grupo del Listado (`view|manage servicio tecnico`, L224). El **técnico
industrial** (`tecnico_industrial`) tiene `ver informe industrial` pero **NO**
`view servicio tecnico`: hoy entra al informe industrial por su ítem del menú, sin tocar
el Listado. Meterlo tras el Listado le quitaría su ÚNICO acceso (no puede ver el anfitrión).
Esto viola el invariante del proyecto (ninguna pantalla/permiso se pierde) — el mapa no lo
detectó. Costos y Traslados NO tienen este problema (verificado: todos sus roles ven el
Listado).

**Decisión del dueño (13-ago): PARTIR el Informe por dominio** (no ampliar permisos, no
dejarlo solo):
- **Informe industrial → pestaña de la Agenda de terreno** (el técnico industrial SÍ la
  ve; es su dominio de terreno). Pestaña gateada por `ver informe industrial` (idioma del
  `_tabs` calculado por permiso, ya usado en lotes 4-5). Sería la 3ª pestaña de la Agenda
  (Agenda · Servicios de terreno · Informe industrial) — `grid-cols-3`, aún no toca la
  deuda de 4 pestañas.
- **Informe dispensadores → pestaña/sección del Listado** (gateada por `ver informe
  dispensadores`).
- **El landing `admin.servicio-tecnico.informe`** (que hoy bifurca): su destino lo decide
  y declara el lote ejecutor (retirarlo si cada cara ya tiene entrada propia, o mantenerlo
  reapuntado). Verificar que nadie pierda acceso tras el cambio.
- Aritmética intacta: el ítem «Informe» se retira igual (−1); sus dos caras son navegación
  interna, no rótulos del menú. Bloque A sigue −3 → 39.

### SEGUNDO NUDO (Director, 14-ago, al preparar A3) — «partir por dominio» rompe a 2 jefaturas
La decisión del 13-ago se tomó sin este dato. Cruce de permisos por ejecución (seeder):
quién porta `ver informe industrial` vs quién entra a la Agenda de terreno
(`ver agenda terreno`/`agendar servicio terreno`):

| Rol | ve informe industrial | entra a la Agenda | ve el Listado |
|---|---|---|---|
| vendedor, jefe_ventas, admin | ✓ | ✓ | ✓ |
| tecnico_industrial | ✓ | ✓ | **✗** |
| **jefe_bodega** (seeder L150) | ✓ | **✗** | ✓ |
| **jefe_sucursal** (seeder L196) | ✓ | **✗** | ✓ |

El informe industrial tiene una audiencia que cruza DOS dominios disjuntos: el técnico
industrial solo ve la Agenda (no el Listado); jefe_bodega y jefe_sucursal solo ven el
Listado (no la Agenda). **No existe un anfitrión único que cubra a todos**:
- Industrial → Agenda: **jefe_bodega y jefe_sucursal pierden acceso** (nudo 2).
- Industrial → Listado: **el técnico industrial pierde acceso** (nudo 1, el original).
- (Dispensadores → Listado sí es limpio: todos los que lo ven, ven el Listado.)

**A3 FRENADO — vuelve a decisión del dueño (14-ago).** Opciones sobre la mesa:
1. **Informe vive solo** (no consolidar) — el caso especial del Bloque A: su audiencia no
   cabe en un anfitrión sin romper a alguien o duplicar. Nadie pierde. Densidad −2 en vez
   de −3 (Bloque A cierra en 40→39 solo con dispensadores... en realidad el ítem se
   quedaría; ver nota). La respuesta honesta a la lente del dueño: «necesita vivir solo».
2. **Solo dispensadores → Listado; industrial se queda como su propia entrada** — resta
   parcial y compleja (el landing bifurca hoy).
3. **Ampliar permisos** (dar acceso a la Agenda a bodega/sucursal, o al Listado al técnico)
   — cambio de accesos de negocio, decisión de gerencia.
4. **Duplicar** (industrial en Agenda Y Listado) — PROHIBIDO por el proyecto.
Recomendación del Director: **opción 1** (vive solo) — es el ítem que el propio criterio
del proyecto deja fuera. Menú final 31 en vez de 30; una resta menos, cero accesos rotos.

**→ DECISIÓN DEL DUEÑO (14-ago): opción 1, el Informe VIVE SOLO.** A3 no se construye; el
ítem «Informe» se queda como está. El Bloque A cierra con A1 (Costos) + A2 (Traslados) en
producción, menú en **40**. En el mapa (§5.1) el veredicto del Informe pasa de «integrable»
a **vive-solo** — audiencia partida entre taller y terreno, sin anfitrión común.

## 5. Anexo — auditoría y mapa (F0, Max-1, 2026-08-12)

> Levantamiento verificado contra `MenuPrincipal::MODULOS`/`CUENTA`, `routes/web.php`,
> `RolesAndPermissionsSeeder` y las vistas reales (workflow de 7 lectores + síntesis).
> **Conteo exacto HOY: 47 rótulos** = 11 entradas de primer nivel (6 acordeones + 5
> links directos) + 35 subítems + 1 en el área de cuenta. Los veredictos son PROPUESTA;
> la decisión es del dueño, apartado por apartado. Todo permiso citado existe en el
> seeder (verificado). «Frec.» = frecuencia estimada de uso: D diaria · S semanal · R rara.

### 5.1 Inventario y veredicto por ítem

**Convención del veredicto:** `integrable → X` lleva SIEMPRE la propuesta concreta
(pestaña/sección/botón y bajo qué permiso). Las pantallas NUNCA se pierden: solo su
entrada en el menú. Los permisos de las rutas no se tocan en ninguna propuesta.

#### Comercial (3 ítems)

| Ítem | Qué es (rutas) | Permiso → roles | Frec. | Veredicto |
|---|---|---|---|---|
| Catálogo | Hub del catálogo espejo Bsale: medidas, categoría interna, import/export (12 rutas, `admin.productos.*`) | `manage productos` → solo admin | S | **vive-solo** — hub de datos maestros; ANFITRIÓN del piloto F1 |
| Precios | Listas de precios espejo, solo-lectura salvo `canal` (3 rutas) | `manage productos` → solo admin | R | **integrable → Catálogo (F1, DECIDIDO por el dueño)** — mismo permiso exacto; el edit de producto YA muestra sus precios por lista y la lista YA enlaza a cada producto (links bidireccionales hoy). Pestaña «Listas de precios» en Catálogo |
| Clientes | Ficha local sincronizada con Bsale | `manage clientes` → admin, vendedor, jefe_ventas, jefe_sucursal | D | **vive-solo** — dominio y permiso propios, uso diario de ventas |

#### Operación (8 ítems)

| Ítem | Qué es (rutas) | Permiso → roles | Frec. | Veredicto |
|---|---|---|---|---|
| Inventario | Stock por bodega (espejo) + clasificación/baja de bodegas | lectura `manage productos` (solo admin); gestión `manage sucursales` | S | **vive-solo** — única puerta al stock espejo; bipermiso ya resuelto por rutas |
| Producción | El panel del jefe: cola de aprobación, alertas, hoy en vivo, OEE, drill-downs (20+ rutas) · badge «:n reporte(s) por aprobar» | `manage production` → admin, jefe_bodega | D | **vive-solo** — hub diario con badge accionable; ANFITRIÓN de Kardex |
| Kardex | Ledger solo-lectura de movimientos (1 ruta GET) | `manage production` | S | **integrable → Producción** — la cabecera del panel YA lo enlaza y la ficha de reporte también («Ver kardex completo»); su ruta entra a la enumeración `activo` del ítem Producción. Deja de ser ítem; la pantalla no cambia |
| Recetas | Componentes por botellón + ciclo ideal (3 rutas) | `manage production` | R | **integrable → «Configuración de producción»** (ver propuesta C abajo) |
| Máquinas | CRUD de sopladoras (6 rutas) | `manage production` | R | **integrable → «Configuración de producción»** |
| Moldes | Ficha con contador de ciclos + mantenciones (7 rutas) | `manage production` | S | **integrable → «Configuración de producción»** — la ficha y sus avisos M15 quedan intactos; solo la entrada se consolida |
| Tipos de botellón | CRUD de formatos (6 rutas) | `manage production` | R | **integrable → «Configuración de producción»** |
| Devoluciones | Flujo M13: recibir → evaluar → resolver · badge «:n devolución(es) por recibir» | `view\|manage devoluciones` → admin, jefe_ventas, jefe_bodega | D/S | **vive-solo** — dominio, permiso y badge propios; audiencia doble (ventas+bodega) |

> **Propuesta C — «Configuración de producción» (4 → 1, la mayor densidad del mapa):**
> una superficie con pestañas **Máquinas · Tipos de botellón · Recetas · Moldes**. Los
> 4 comparten `manage production` (nadie gana ni pierde acceso), son catálogos de
> frecuencia rara del MISMO flujo y ya están encadenados por datos y links (tipo →
> receta → molde; la ficha del molde enlaza a su receta; máquinas/tipos enlazan al
> drill-down del panel). Nota de autocrítica asumida: Recetas y Moldes los agregué yo
> en M11 sin este filtro — este veredicto los reabsorbe sin apego.

#### Logística (6 ítems)

| Ítem | Qué es (rutas) | Permiso → roles | Frec. | Veredicto |
|---|---|---|---|---|
| Despachos | Salida de mercadería: crear desde documento, cola, QR, retiro, entrega | `manage despachos` → admin, jefe_bodega | D | **vive-solo** — inicio del flujo diario |
| Hojas de ruta | La hoja del camión con la cadena R11 de 3 llaves (pagos → ruta → carga) | OR de 4 permisos → admin, jefe_logistica, jefe_despacho, jefe_ventas, jefe_bodega | D | **vive-solo** — flujo diario con 3 audiencias autorizadoras distintas |
| Vehículos | Tablero de flota M18: semáforo de vencimientos, fichas, respaldos | `ver\|manage vehiculos` → admin, jefe_logistica, conductor (ver) | S | **vive-solo** — ANFITRIÓN de Conductores |
| Simulador de carga | Calculadora «¿cuánto entra?» con visor 3D y Excel (GET-only) | `simular carga` → admin, vendedor, jefe_ventas, jefe_bodega, jefe_logistica | D | **vive-solo** — herramienta diaria de ventas; ANFITRIÓN de Cargas reales |
| Cargas reales | Anotar lo que entró de verdad; calibra el factor del simulador (3 rutas) | `simular carga` (los mismos 5 roles) | S/R | **integrable → Simulador** — mismo permiso y grupo de middleware; el simulador la CONSUME (factor «medido en terreno») y la enlaza dos veces («anotá una en Cargas reales»). Pestaña «Cargas reales». Contra-argumento honesto (comentario del código): se usa DESPUÉS de cargar y el simulador ANTES — una pestaña conserva ambos momentos sin costar un ítem, pero el dueño decide si ese matiz pesa |
| Conductores | CRUD mínimo de nombres (5 rutas, sin destroy) | `manage servicio tecnico\|manage vehiculos` → admin, jefe_ventas, tecnico, jefe_logistica | R | **integrable → Vehículos** — «quien administra la flota administra quién la maneja» (la razón del propio traslado del 04-08); pestaña «Conductores» que conserva SU permiso OR (el técnico no la pierde: la pestaña se gatea por el canAny del catálogo, no por el permiso de vehículos). Hecho: sus consumidores de código están todos FUERA de Logística (ingreso por lote, traslados ST, devoluciones) |

#### Facturación (2 ítems)

| Ítem | Qué es (rutas) | Permiso → roles | Frec. | Veredicto |
|---|---|---|---|---|
| Documentos | DTEs emitidos + orígenes de documento + órdenes listas para facturar | `emitir documentos tributarios` → solo admin | R hoy, D al emitir | **vive-solo** — será el hub del mostrador; ANFITRIÓN de Estado |
| Estado | Checklist de preparación de la conexión (1 ruta GET, 100 % lectura) | `emitir documentos tributarios` (idéntico) | R y decreciente | **integrable → Documentos** — mismo permiso/controller/grupo; el index ya la enlaza dos veces; su valor cae a casi cero tras la primera emisión. Pestaña «Estado de la conexión». El módulo queda de 1 ítem (se conserva el acordeón por el `activo_extra` del documento de ST, o Documentos pasa a link directo — decisión menor del lote ejecutor) |

#### Administración (6 ítems)

| Ítem | Qué es (rutas) | Permiso → roles | Frec. | Veredicto |
|---|---|---|---|---|
| Usuarios | Cuentas con roles y sucursal (6 rutas, permisos granulares view/create/edit/delete) | `view users` → admin + 3 jefaturas (solo-lectura) | R | **vive-solo** — entrada de la gestión de accesos; ANFITRIÓN de Roles |
| Roles | Matriz de permisos por rol (6 rutas) | `manage roles` → solo admin | R | **integrable → Usuarios** — mismo dominio (en Usuarios se ASIGNAN los roles que acá se definen; `config/permissions.php` ya los agrupa como «Usuarios y accesos»). Pestaña «Roles» gateada por `manage roles` |
| Sucursales | Catálogo casi estático (4 sucursales) | `manage sucursales` → solo admin | MUY R | **vive-solo** — transversal (usuarios/bodegas/facturación la consumen); sin casa mejor; su permiso además gatea la estructura de bodegas en Inventario, mover el ítem no densifica nada más |
| Auditoría | Historial de cambios owen-it con filtros (1 ruta GET) | `view audit` → solo admin | R | **vive-solo COMO ANFITRIÓN** de la propuesta R (abajo) |
| Notificaciones | Panel de envíos/reintentos/fallas + prueba (2 rutas) | `view notificaciones` → solo admin | R | **integrable → «Registro del sistema»** (propuesta R) |
| Historial de aprobaciones | Solo-lectura del motor M14 con filtros (1 ruta GET) | `view aprobaciones` → solo admin | R | **integrable → «Registro del sistema»** — NO junto a la bandeja: el QA 15-07 ya mostró que mezclar bandeja/historial confunde, y las audiencias son disjuntas (jefes actúan / admin audita) |

> **Propuesta R — «Registro del sistema» (3 → 1):** Auditoría como anfitriona con
> pestañas **Cambios · Notificaciones · Aprobaciones** (cada una conserva su permiso).
> Los tres son visores solo-lectura forenses, solo-admin, de 1-2 rutas — y la campanita
> YA los agrupa como hub «Funciones»: la consolidación formaliza un agrupamiento que la
> práctica ya inventó.

#### Servicio Técnico (10 ítems — el módulo más cargado)

| Ítem | Qué es (rutas) | Permiso → roles | Frec. | Veredicto |
|---|---|---|---|---|
| Listado | El tablero del taller: órdenes, filtros, por-confirmar, todo el ciclo · badge «:n ingreso(s) por confirmar» | `view\|manage servicio tecnico` → 6 roles | D | **vive-solo** — hub diario; ANFITRIÓN de QR, Informe, Costos (y Traslados si el dueño aprieta) |
| Ingreso por lote | Form del conductor en ruta: N máquinas de una empresa → N órdenes | `crear lote servicio` → admin, conductor, tecnico | S | **vive-solo** — HECHO decisivo: el conductor porta `crear lote servicio` pero NO `view servicio tecnico`; esconderlo tras el Listado le quitaría su única entrada (el candado del árbol podado fija que el conductor ve ST con EXACTAMENTE `['lote']`). Doctrina 1-clic del operario |
| Traslados al taller | Flujo sucursal → casa matriz con dos puntas de permiso (cadena de custodia) | `despachar\|recibir traslado servicio` → jefe_sucursal / jefe_ventas, jefe_bodega, tecnico | S | **integrable → Listado** (pestaña «Traslados», conserva su OR) — todos sus roles ya ven el Listado; links bidireccionales orden↔traslado ya existen. **Prioridad baja a propósito**: es un flujo activo, no un catálogo — la de menor urgencia del mapa |
| Códigos QR | Genera el afiche imprimible por sucursal (1 ruta GET) | `manage servicio tecnico` | R | **integrable → Listado** — botón/sección «Códigos QR» junto al bloque por-confirmar que esos QR alimentan |
| Informe | Landing dispensadores/industrial con KPIs por período | `ver informe dispensadores\|industrial` → 6-7 roles | S/M | **integrable → Listado** (pestaña «Informes» visible por su OR) — coherente con la casa: los informes de producción ya viven DENTRO del panel, no como ítems |
| Seguimiento (boceto) | Maqueta estática sin datos (estilo tracking Blue Express) | `view\|manage servicio tecnico` | NULA | **RETIRAR del menú** (caso especial: ni integrable ni vive-solo — es un boceto sin usuarios operativos; si se quiere mostrar, link temporal desde el Listado; vuelve como pantalla real cuando exista el seguimiento público por folio) |
| Agenda de terreno | Calendario + coordinación de visitas · badge «:n visita(s) por coordinar» | `ver\|agendar servicio terreno` → tecnico_industrial, vendedor, jefe_ventas | D | **vive-solo** — LA pantalla del técnico industrial (la prioridad por rol ya la sube al tope); ANFITRIONA de Servicios de terreno |
| Servicios de terreno | Tarifario UF de servicios (5 rutas) | `agendar servicio terreno` (idéntico a la mitad de escritura de la agenda) | R | **integrable → Agenda de terreno** — permiso idéntico, la agenda ya lo enlaza desde su cabecera («Catálogo de servicios»); pestaña/sección |
| Instalaciones | Ledger de instalaciones en terreno (el Excel de Tablante, con historial año→mes) | `gestionar instalaciones` → jefe_ventas, tecnico_industrial | S | **vive-solo** — registro operativo con volumen y navegación propios (2º ítem prioritario del técnico industrial), no un satélite de configuración. Candidata de segunda ola solo si el dueño quiere apretar más |
| Costos generales de reparación | Tiempos estándar + valor hora (la mano de obra se calcula sola) | `gestionar tiempos reparacion` → jefe_ventas | R | **integrable → Listado** — sección «Configuración» del taller junto a Códigos QR (ambos raros, ambos config; cada uno conserva su permiso) |

#### Links directos + cuenta (6 ítems)

| Ítem | Qué es | Permiso → roles | Frec. | Veredicto |
|---|---|---|---|---|
| Dashboard | Aterrizaje post-login, `start_url` de la PWA | null (todos) | D | **vive-solo** |
| Mi producción | La pantalla del soplador (PWA, cola offline) · badge devueltos | `report production` → soplador | D | **vive-solo** — doctrina 1-clic del operario (dueño 24-07) |
| Mis entregas | La hoja del día del conductor (firma+foto, offline) | `confirmar entrega` → conductor | D | **vive-solo** — ídem |
| Aprobaciones | Bandeja del aprobador · badge «:n solicitud(es) por aprobar» | `aprobar solicitudes` → 3 jefaturas | D (por evento) | **vive-solo** — bandeja accionable; su historial se consolida en «Registro del sistema», no acá |
| Plan del proyecto | Carta Gantt del PROYECTO (fuente: el repo) | `ver plan proyecto` → solo admin | R | **vive-solo con fecha de muerte natural** — transicional por diseño: se retira solo cuando la app se termine, no se consolida |
| Configuración (cuenta) | Parámetros globales tipados | `manage settings` → solo admin | R | **vive-solo donde está** — ya está fuera del árbol (dropdown del pie) por pedido del dueño 24-07, con candado propio |

### 5.2 Mapa objetivo (si TODAS las propuestas se aprobaran)

```
Dashboard
COMERCIAL          Catálogo (+ Listas de precios)          · Clientes
OPERACIÓN          Inventario · Producción (+ Kardex)
                   · Configuración de producción (Máquinas · Tipos · Recetas · Moldes)
                   · Devoluciones
LOGÍSTICA          Despachos · Hojas de ruta
                   · Vehículos (+ Conductores) · Simulador de carga (+ Cargas reales)
FACTURACIÓN        Documentos (+ Estado de la conexión)
ADMINISTRACIÓN     Usuarios (+ Roles) · Sucursales
                   · Registro del sistema (Cambios · Notificaciones · Aprobaciones)
SERVICIO TÉCNICO   Listado (+ QR + Informes + Costos + Traslados) · Ingreso por lote
                   · Agenda de terreno (+ Servicios de terreno) · Instalaciones
MI PRODUCCIÓN · MIS ENTREGAS · APROBACIONES · Plan del proyecto      (sin cambios)
Cuenta: Configuración                                                 (sin cambio)
```

**El número del proyecto: 47 rótulos → 30** (subítems **35 → 18**; primer nivel y
cuenta intactos). Operación 8→4 · ST 10→4 · Administración 6→3 · Logística 6→4 ·
Comercial 3→2 · Facturación 2→1. Ninguna pantalla ni permiso se pierde: 16
consolidaciones + 1 retiro de boceto.

### 5.3 Priorización (densidad ganada × esfuerzo × riesgo)

| # | Consolidación | Densidad | Esfuerzo | Riesgo | Nota |
|---|---|---|---|---|---|
| 1 | **Precios → Catálogo (F1, DECIDIDO)** | −1 | Bajo | Bajo | El piloto; links bidireccionales ya existen; card «Precios» del Inicio se reapunta/borra (candado 2f) |
| 2 | Seguimiento (boceto): retiro | −1 | Trivial | Nulo | Sin usuarios operativos; densidad gratis |
| 3 | Estado → Documentos | −1 | Muy bajo | Muy bajo | 1 ruta GET, solo-admin, 2 links que ya existen |
| 4 | QR → Listado ST | −1 | Bajo | Bajo | 1 ruta GET rara; VolverTest (era ítem, vuelve el Volver) |
| 5 | Servicios de terreno → Agenda | −1 | Bajo | Bajo | Permiso idéntico; link en cabecera ya existe |
| 6 | **Configuración de producción (4→1)** | **−3** | Medio | Medio | La mayor densidad; permiso único simplifica; toca el candado duro de ex-huérfanas (abajo) |
| 7 | Registro del sistema (3→1) | −2 | Medio | Bajo | Todo solo-admin y solo-lectura; 2 cards del Inicio se reapuntan |
| 8 | Roles → Usuarios | −1 | Bajo-medio | Bajo | Pestaña gateada; card «Roles» se reapunta |
| 9 | Cargas reales → Simulador | −1 | Bajo | Bajo | Contra-argumento del momento de uso anotado para el dueño |
| 10 | Kardex → Producción | −1 | Bajo-medio | Bajo | Ex-huérfana (candado duro) + patrón `activo` del ítem Producción |
| 11 | Conductores → Vehículos | −1 | Medio | Medio | Permisos OR cruzados (el técnico no puede perderlo); ex-huérfana |
| 12 | Informe → Listado ST | −1 | Medio | Bajo-medio | Pestaña por permiso; audiencia jefatura |
| 13 | Costos → Listado ST | −1 | Medio | Bajo | Sección config del taller junto a QR |
| 14 | Traslados → Listado ST | −1 | Medio-alto | Medio | Flujo activo con dos puntas — la última, y opcional |

Ritmo: **un lote = una consolidación = una doble llave** (§2.5). Los números 2-5 son
tan baratos que podrían acompañar al lote vecino si el dueño lo prefiere — pero la
doctrina de calma manda.

### 5.4 Candados tocados por consolidación (insumo del lote ejecutor)

Los candados del menú y qué operación los toca (verificado test por test):

| Candado | Qué fija | Lo tocan |
|---|---|---|
| `SidebarTest::test_cada_ruta_del_menu_resalta_exactamente_un_item` | Cada ruta de ítem = exactamente UN aria-current (0 = sin dueño; 2+ = comodín pisa hermano) | TODAS las fusiones: el patrón `activo` del anfitrión debe absorber las rutas del absorbido SIN pisar hermanos; prohibido duplicar ruta en dos ítems |
| `VolverTest::test_las_ex_huerfanas_estan_en_el_menu` | **Candado DURO**: Máquinas, Tipos, Kardex y Conductores DEBEN estar en el menú | #6, #10 y #11 lo ponen rojo A PROPÓSITO — la salida exige editar la lista conscientemente + devolverles `<x-volver>` + sumarlas a `test_pantalla_hija_tiene_exactamente_un_volver` |
| `VolverTest::test_ningun_item_del_menu_lleva_volver` | Ítem del menú sin Volver | Toda pantalla que SALE del menú necesita su Volver de vuelta (QR, Informe, Estado, Cargas reales, Roles, Notificaciones, Historial, Servicios de terreno, Costos, Traslados, Precios) |
| `MenuPrincipalTest::test_cards_del_dashboard_son_subconjunto_del_menu` | Cada card del Inicio apunta a un ítem del menú con su mismo route/permiso | #1 (card Precios), #7 (cards Notificaciones y Aprobaciones-historial), #8 (card Roles): reapuntar o borrar la card EN EL MISMO push |
| `MenuPrincipalTest::test_todo_patron_activo_matchea_rutas_registradas` | Cero patrones muertos | Toda fusión que herede patrones: limpiar la enumeración (Producción declara 10) |
| `MenuPrincipalTest::test_labels_de_menu_unicos` + `test_badges_cuentan_pendientes…` | Labels únicos; keys y gating de badges | Renombres; los badges viajan con su KEY (el gating vive en el resolver, no en el ítem — puede ser MÁS angosto que el permiso del ítem y eso es diseño) |
| `SidebarTest::test_badges_de_pendientes_se_ven_en_el_menu` + `DashboardTest` (3 tests) | Los title-contrato literales (`'1 ingreso(s) por confirmar'`…) y la pill de suma por categoría | #12-14 (Listado ST conserva su badge `st_por_confirmar`); jamás un contador de ESTADO al menú (`assertDontSee('equipo(s) por atender')`) |
| `SidebarTest::test_el_documento_tributario_abre_facturacion_y_no_servicio_tecnico` | El arbitraje `activo_extra` vs comodín es POR ORDEN de declaración de MODULOS | #3 (si Facturación cambia de forma) y cualquier REORDEN de módulos |
| `HigienePermisosTest::test_ningun_permiso_queda_sin_usar` | Todo permiso sembrado se usa en app/rutas/vistas | Si al sacar un ítem el menú era el último uso textual del permiso → limpiar o declarar en el mismo push |
| `MenuPrincipalTest` 2g (árbol podado / prioridad por rol) | Fixtures con keys literales de ST (`lote`, `agenda-terreno`, `instalaciones`, `listado`) y del conductor (`['lote']`) | #12-14 y cualquier cambio de keys dentro de ST |

**Regla operativa que el levantamiento confirmó** (para el lote ejecutor): mover una
ruta a pestaña = (1) sumar la ruta al `activo` del anfitrión, (2) devolver el Volver a
la pantalla, (3) reapuntar su card del Inicio si tiene, (4) verificar el permiso del
badge si viaja, (5) limpiar el seeder si el permiso quedaba solo en el menú. Los
candados existentes cazan 4 de los 5 — el (1) a medias: si la ruta sigue registrada
pero ningún patrón la cubre, la página queda sin resaltado EN SILENCIO (ningún test
lo caza hoy; anotado como mini-candado sugerido para el lote F1).
