# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-07-27 (v25 — RESCATE del lote NOTIF-1: quedó rancio 5 días sin merge). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## Qué pasó con tu lote (no es culpa tuya)
Entregaste NOTIF-1 el 22-07 (`e6fbcc6`, 772 verdes, gate R-31 con sus 2 confirmados
resueltos) y **nadie le dio la doble llave**: el Director no tuvo sesión en 5 días. En ese
hueco main avanzó **122 commits** (menú V4 E-NAV, `x-volver` unificado, ancho único, panel
anclado, colores del Inicio, errores amables, M17 en producción) y dos de ellos te pasaron
por encima:
- **`aprobaciones-categorias` (`6069354`, 26-07)** reescribió `aprobaciones/index.blade.php`
  y `mias.blade.php` — las 2 vistas donde pones tus anclas — agrupando por categoría.
- **main evolucionó el modelo**: nació `urlDestinoPara(?User)` (gate de permisos, por un 403
  REAL de vendedores con cartera ajena) + brazo `taller.ingresado` en el mismo `match` que
  tú tocas, y la bandeja ahora enlaza por `href` directo en vez de tu `?ir=1`.

Resultado: 4 conflictos. **El 92 % de tu lote automerge intacto** (dispatcher, Aprobaciones,
migración, seeder, mail, campanita, tus 286 líneas de tests).

## 🟢 TAREA — rama NUEVA `feature/notif-especificas-v2` desde main fresco (S/M)
NO intentes rescatar la rama vieja con un merge: los dos caminos obvios fallan (ver anexo).
Trae tus archivos limpios y re-injerta los 4 puntos de choque.

**Paquete completo, verificado y refutado, en
[`docs/fleet/buzon/anexo-reaplicacion-notif1.md`](../anexo-reaplicacion-notif1.md)** —
tiene los snippets exactos, la indentación medida (24 espacios en la tarjeta de index, 32 en
el `<li>` de mias, 32 en el span del cuerpo de la bandeja) y el porqué de cada decisión.
Léelo antes de editar; te ahorra el re-descubrimiento completo.

Los 4 puntos, en resumen:
1. **`Notificacion.php`**: injerta tu `$ancla` DENTRO del `urlDestino()` de main
   (`:159-176`), concatenado en los 3 brazos `aprobacion.*`. **PRESERVA el brazo
   `taller.ingresado`** que tú no tienes. NO toques `urlDestinoPara()` — verifiqué que es
   wrapper puro y tu ancla fluye intacta por transitividad.
2. **Las 2 vistas de aprobaciones**: toma el lado main COMPLETO del hunk (secciones por
   categoría) y re-aplica solo `id="aprobacion-{{ $aprobacion->id }}"` / `$solicitud->id`
   + `scroll-mt-6` + `target:ring-*`. Ojo con los nombres de variable: main itera
   `$delTipo as $aprobacion` / `as $solicitud`.
3. **Bandeja** (`notificaciones/index.blade.php`): reemplaza el archivo por la versión de
   main y re-agrega SOLO tu `whitespace-pre-line` al span del cuerpo. **Conserva el
   `urlDestinoPara(auth()->user())` de main** y no reintroduzcas el wrapper
   `mx-auto max-w-xl` (main unificó anchos por layout).
4. **Controlador**: conserva el `->with('notificable')` de main (evita el N+1 que
   `urlDestinoPara` provocaría) **y cambia tu redirect `?ir=1` a
   `urlDestinoPara($request->user())`** con caída a `back()` — si lo dejas en `urlDestino()`,
   la campanita navega SIN el gate y cae en el 403 que la bandeja sí evita. Tus tests
   `NotificacionEspecificaTest:71/:82/:98-100` assertan ese redirect: sus fixtures
   probablemente necesiten el permiso del evento.

## ⚠️ Los 3 que te van a morder (2ª ronda del workflow, PARTE 2 del anexo)
- **`manifest.json` TAMBIÉN conflictúa** (son 5 conflictos, no 4): si queda un marcador de
  merge dentro del JSON, **Vite revienta en runtime**. Manifest de main + borrar tu
  `app-DLrdfNJe.css` huérfano + `npm run build` al final.
- **Un candado nuevo de main pone la suite ROJA**: `AnchoDePaginaTest:80-107` tiene el regex
  `/class="mx-auto max-w-[0-9a-z]+ px-/` y tu versión de `notificaciones/index.blade.php:5`
  lo matchea. Es otra razón para tomar main completo ahí. (Tus otras 2 vistas NO lo trippean:
  el `space-y-4` intercalado rompe la adyacencia — verificado.)
- **NO edites `campanita.blade.php` ni `ConfiguracionSeeder.php` a mano**: automergean y el
  resultado ya es el correcto. Aplicar la receta encima DUPLICA el input `ir` y el span del
  cuerpo. Solo verifica el resultado.

Y el ajuste de tests con una corrección: el caso espejo del usuario sin permiso **debe crear
su propia notificación** (`leer()` tiene `abort_unless($notificacion->user_id === ...)`, así
que reusar la del jefe da 403, no `back()`). Para el caso feliz, `assignRole('jefe_bodega')`.

## Gates de este lote (dos son nuevos y NO los cubre PHPUnit)
- **Gate del ancla**: tras resolver, `git grep -c 'id="aprobacion-' resources/views` debe dar
  2. Si da 0, el ancla se perdió en silencio y la suite igual queda verde.
- **Gate de CSS, con matiz importante**: `target:` es la primera vez que el proyecto lo usa en
  `resources/`, PERO el bundle comiteado de main ya trae esas clases — se filtraron por el
  `@source '../../storage/framework/views/*.php'` de `app.css:4`, o sea desde un caché de
  blades compilados de un árbol que tenía tu lote. **Un grep verde NO prueba que compilaron
  desde tus `.blade`**: corre `npm run build` igual y luego grep de `:target` en
  `public/build`, para que se regeneren legítimamente. Riesgo de clase faltante: nulo hoy.
- Cobertura que falta y te pido agregar: **nadie asserta el `whitespace-pre-line`** (0 hits en
  tests de ambas ramas) — un `assertSee('whitespace-pre-line', false)` sobre
  `route('notificaciones.index')` cierra el hueco.
- Suite COMPLETA: **la baseline de main hoy es 920 verdes / 4.418 aserciones** (la verifiqué
  en worktree limpio). Tu lote debe sumar sobre eso, no sobre las 772 de tu árbol viejo.
- Filtro útil mientras iteras: `--filter='NotificacionEspecificaTest|AprobacionCategoriaTest|AprobacionBandejaTest|AprobacionHistorialTest|AprobacionAccionableTest|CampanitaTest|NotificacionPanelTest'`.

## Cierra la rama vieja
Borra `feature/notif-especificas` del remoto cuando la v2 esté mergeada (no antes). La
migración one-shot viaja tal cual: verifiqué que su premisa sigue en pie (las plantillas de
aprobación no cambiaron y la one-shot de Marcos toca claves disjuntas).

## Aviso de entorno (te va a pasar)
El clon `C:\Users\maatr\Documents\DaliGo` lo está usando OTRA sesión (rediseño de menú, rama
`design/menu-talana` + worktree con cambios sin commitear). Si trabajas ahí, **worktree
propio con su `composer install`**: montar el `vendor` del clon por junction hace que PSR-4
resuelva `App\` al OTRO clon y correrías la suite contra el árbol equivocado (me pasó hoy;
síntoma: `Invalid route action: [DashboardColoresController]`).

## Pendientes que NO son tuyos
- P-TZ-03 QA de borde · #6 chips · decisión de producto del ciclo de la factura: dueño.
- DESPACHOS (P-DSP-04): Max-2, asiento congelado 13 días.

CIERRE: parte a docs/fleet/buzon/partes/ + push.
