# DaliGo — Brief de diseño (contexto para iterar en claude.ai/design)

DaliGo es la app interna de gestión de **Industrias Dali** (Chile): producción de
botellones, inventario, comercial y servicio técnico de dispensadores. **UI 100% en
español**, tema claro y sobrio, **mobile-first** (los operarios usan celular en planta,
a veces con guantes). Breakpoints de referencia: **375 / 768 / 1024 px** — nada puede
tener scroll horizontal en móvil.

Este proyecto contiene el design system real de la app (grupo *Fundaciones*), tres
pantallas actuales capturadas del servidor (grupo *Pantallas actuales*) y **variantes
de navegación estilo Talana** para evaluar (grupo *Menu Talana*). El objetivo del
trabajo aquí es **solo la vista** — prototipos estáticos, nada de lógica profunda.

---

## 1. Paleta ESTRICTA de 4 colores

Naranjo de marca + negro/gris + blanco. El **rojo** existe SOLO para lo
destructivo/negativo (eliminar, errores, estado *devuelto*). **Prohibido**
verde, ámbar, azul, degradados o cualquier otro matiz.

| Token | Hex | Uso |
|---|---|---|
| `brand-50` | `#fff7ed` | fondos suaves, item de menú activo |
| `brand-100` | `#ffedd5` | rings/bordes de badges brand |
| `brand-200` | `#fed7aa` | bordes suaves |
| `brand-300` | `#fdba74` | bordes con más presencia |
| `brand-500` | `#f97316` | focos (`ring-brand-500/30`) |
| `brand-600` | `#ea580c` | **primario**: botones, badge contador, subrayado activo |
| `brand-700` | `#c2410c` | hover/énfasis fuerte, texto sobre `brand-50` |
| `neutral-*` | escala Tailwind | texto 900/700/600/500/400 · fondos 50/100 · bordes 200/300 · sólidos 800/900 |
| `red-600/500/50` | — | SOLO destructivo/error |

**La intención se expresa por relleno/peso/tono, no con más colores:**
producido / foco / requiere acción → `brand`; secundario / merma / de-enfatizado →
`neutral` atenuado; en reposo / final → `neutral`. Estados por relleno:
*Enviado* = `bg-brand-600 text-white` (requiere acción), *Aprobado* =
`bg-neutral-800 text-white` (final), *Borrador* = `bg-neutral-100 text-neutral-600`,
*Devuelto* = `bg-red-50 text-red-700`.

## 2. Tipografía, radios, sombra

- Fuente **Instrument Sans** (Bunny Fonts). Títulos `text-xl font-semibold
  text-neutral-900`; encabezados de sección `text-xs font-medium uppercase
  tracking-wide text-neutral-500`; cuerpo `text-sm`; badges/ayudas `text-xs`.
- Radios: `rounded-lg` (botones/inputs), `rounded-2xl` (tarjetas), `rounded-full`
  (badges/pills/avatares).
- Tarjeta canónica: `rounded-2xl border border-neutral-200 bg-white shadow-sm`;
  cabecera `border-b border-neutral-100 px-6 py-3`; filas `divide-y divide-neutral-100`.
- Motion sutil: `transition duration-150`, `active:scale-[0.98]`, animación de
  entrada `dg-enter` (sin rebotes ni loops; respeta `prefers-reduced-motion`).

## 3. Componentes canónicos (clases exactas en `11-componentes.html`)

Botón primario (`bg-brand-600 hover:bg-brand-700`), secundario (borde
`neutral-300`, fondo blanco), peligro (`bg-red-600`, solo destructivo); inputs
`rounded-lg border-neutral-300` con foco `focus:border-brand-500 focus:ring-2
focus:ring-brand-500/30`; badge pill `rounded-full … ring-1 ring-inset`; stat-card
(número grande clickeable); list-card + list-row (fila responsive que apila en
móvil); avatar `h-10 w-10 rounded-full bg-neutral-100`; nav-link activo = subrayado
`border-b-2 border-brand-600`.

## 4. Navegación ACTUAL (la que se quiere reemplazar)

Top-bar horizontal (patrón Laravel Breeze), altura `h-16`, dropdowns por grupo en
desktop (≥1024px) y hamburguesa con panel plano en móvil. **El menú se poda por
permisos** (spatie/laravel-permission): cada rol ve solo sus grupos — toda variante
nueva debe verse bien con 2 módulos o con 6.

Inventario completo (grupo → items, con su permiso):

1. **Dashboard** — visible para todos.
2. **Comercial** — Catálogo (`manage productos`) · Precios (`manage productos`) ·
   Clientes (`manage clientes`).
3. **Operación** — Inventario (`manage productos`) · Producción (`manage production`).
4. **Administración** — Usuarios (`view users`) · Roles (`manage roles`) ·
   Sucursales (`manage sucursales`) · Configuración (`manage settings`) ·
   Auditoría (`view audit`) · Notificaciones (`view notificaciones`) ·
   Historial de aprobaciones (`view aprobaciones`).
5. **Mi producción** (`report production`) — de PRIMER nivel a propósito: es LA
   pantalla del operario (soplador); no debe esconderse tras un menú.
6. **Aprobaciones** (`aprobar solicitudes`) — bandeja del jefe, primer nivel.
7. **Servicio Técnico** — lleva **badge contador** naranjo (equipos activos en
   taller) siempre visible en la barra. Items: Listado (`view|manage servicio
   tecnico`) · Ingreso por lote (`crear lote servicio`) · Códigos QR (`manage
   servicio tecnico`) · Informe · Seguimiento (boceto) · Agenda de terreno
   (`ver agenda terreno|agendar servicio terreno`) · Servicios de terreno
   (`agendar servicio terreno`) · Instalaciones (`gestionar instalaciones`).

Lado derecho: **campanita** de notificaciones con contador + dropdown de usuario
(Perfil / Cerrar sesión). Regla móvil aprendida por QA: la campana va SIEMPRE
visible en la barra, nunca dentro del hamburguesa.

## 5. Referencia Talana y mapeo

Talana (software RRHH chileno que el equipo usa a diario) navega así:
1. **Selector de módulos "nine-box" (waffle)** arriba a la derecha → cambia entre
   módulos grandes de la suite.
2. Dentro de cada módulo, **sidebar izquierdo** con las opciones de ese módulo.
3. **Topbar delgada** con empresa/usuario.

Mapeo a DaliGo: módulos = Inicio · Comercial · Operación · Administración ·
Servicio Técnico (con badge) · **Mi trabajo** (agrupa Mi producción + Aprobaciones).
El sidebar contextual muestra los items del módulo activo.

**Decisión abierta que las variantes comparan:** dónde queda el acceso directo del
operario — V2 lo agrupa como módulo "Mi trabajo" en el waffle (fiel a Talana);
V3 deja "Mi producción" y "Aprobaciones" FIJAS en la topbar fuera de módulos
(preserva el acceso de 1 clic que hoy existe a propósito).

## 6. Las tres variantes (grupo "Menu Talana")

- **V1 — Sidebar único con grupos colapsables** (`30` desktop / `31` móvil):
  evolución conservadora; sidebar fijo 264px con los 7 grupos como acordeones,
  topbar delgada. Móvil: drawer off-canvas.
- **V2 — Talana fiel: waffle + sidebar contextual** (`32` / `33`): topbar con
  waffle 3×3 que abre la grilla de módulos; sidebar muestra SOLO el módulo activo.
  Móvil: waffle full-screen → lista del módulo.
- **V3 — Híbrido: tabs de módulo en topbar + sidebar contextual** (`34` / `35`):
  sin waffle; los módulos son tabs horizontales (subrayado brand) y el sidebar
  cambia según el tab. Mi producción/Aprobaciones fijas en la topbar.

Los previews son **CSS-only** (`<details>`, radios ocultos con `:checked`,
checkbox para el drawer) — al iterar, mantener esa restricción: nada de JS.

## 7. Reglas al proponer diseños nuevos

1. Respetar la paleta de 4 SIN excepciones (rojo solo destructivo).
2. Reutilizar los componentes canónicos; no inventar botones/inputs nuevos.
3. Todo debe funcionar a 375px sin scroll horizontal; objetivos táctiles ≥48px
   para pantallas de operario.
4. El badge contador de Servicio Técnico y la campanita deben quedar visibles
   en TODA variante (desktop y móvil).
5. El menú se poda por permisos: diseñar para N módulos variables, no para 6 fijos.
6. Texto de UI en español de Chile, tono sobrio (sin exclamaciones de marketing).
