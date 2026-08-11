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

- [ ] F0: mapa completo en §5 con veredicto por ítem + visto bueno del dueño.
- [ ] F1: un solo ítem «Catálogo» con los precios adentro; cero funcionalidad perdida;
      gate R-31 aprobado; QA del dueño en celular.
- [ ] Cada F2+: ídem F1 para su consolidación.
- [ ] Métrica simple del proyecto: nº de ítems del menú ANTES (47) vs DESPUÉS de cada
      lote — la densidad ganada se ve en un número.

## 5. Anexo — auditoría y mapa (lo llena la F0)

*(pendiente: lo entrega Max-1 como parte de su lote F0)*
