# Parte — Max-1: dictado v25 CUMPLIDO — `feature/notif-especificas-v2` @ `7cd5aff` lista para doble llave · 2026-07-27

> De Max-1 (Forjador A) al Director. Rescate del lote NOTIF-1 sobre el main de hoy, siguiendo
> el paquete del anexo instrucción por instrucción. **Sin sorpresas: los 5 conflictos que
> predijiste fueron exactamente 5**, y las indentaciones/variables que mediste calzaron al
> milímetro. El anexo ahorró el re-descubrimiento completo — gracias.

## Los 5 conflictos, resueltos como manda el paquete

| Archivo | Resolución |
|---|---|
| `app/Models/Notificacion.php` | `$ancla` injertada en los 3 brazos `aprobacion.*`; **brazo `taller.ingresado` de main PRESERVADO**; `urlDestinoPara()` intacto (el ancla fluye por transitividad, como verificaste) |
| `aprobaciones/index.blade.php` | Lado main COMPLETO (secciones por categoría) + `id="aprobacion-{{ $aprobacion->id }}"` + `scroll-mt-6` + `target:ring-2 target:ring-brand-300` |
| `aprobaciones/mias.blade.php` | Ídem con `$solicitud->id` y `target:ring-inset` (el `<li>` vive dentro del `rounded-2xl`) |
| `notificaciones/index.blade.php` | Archivo de main completo (conserva `urlDestinoPara` + `->with('notificable')` + esquiva el candado de `AnchoDePaginaTest`) + **una sola palabra**: `whitespace-pre-line` |
| `public/build/manifest.json` | Manifest de main + CSS huérfano borrado + rebuild |

**El defecto real corregido:** el redirect `ir=1` pasa de `urlDestino()` a
`urlDestinoPara($request->user())` con caída a `back()`. Sin eso la campanita mandaba al 403
que la bandeja evita, y con la notificación **ya marcada leída** — el peor combo.

`campanita.blade.php` y `ConfiguracionSeeder.php`: **solo verificados**, como ordenaste. El
automerge dejó el hidden `ir` ×1 y el span del cuerpo ×1 (sin duplicar), y las 3 plantillas
del lote conviven con el `taller_ingresado` de main.

## Tests (los 3 ajustes del anexo, aplicados con su corrección)

- Caso feliz: `assignRole('jefe_bodega')` — porta `aprobar solicitudes`, que es lo que exige
  el gate nuevo.
- **Caso espejo NUEVO** con tu corrección: el soplador crea **SU PROPIA** notificación (una
  ajena daría 403 por el `abort_unless` y probaría otra cosa) → `back()`, no 403.
- **Cobertura del hueco que detectaste**: `assertSee('whitespace-pre-line', false)` sobre la
  bandeja. El token era la única edición del lote ahí y no tenía un solo assert en ninguna
  rama.

## Gates

- **Ancla**: `git grep -c 'id="aprobacion-' resources/views` = **2** (1 + 1). ✅
- **CSS con tu matiz resuelto de forma limpia**: `view:clear` + `npm run build` → las 3
  clases `:target` presentes… y el bundle regenerado salió **byte-idéntico** al de main
  (`app-DcH-lDk3.css`). O sea: lo que main tenía por el accidente del `@source` de
  `storage/framework/views` ahora lo produce el `.blade` **por derecho propio**, y el merge
  no mueve ni un byte de `public/build`. ✅
- **Suite COMPLETA: 973 verdes / 4.777 aserciones** (baseline de main con DESPACHOS-v1 ya
  dentro; +13 tests del lote). Corrida previa al refresh: 933/4.458 sobre la baseline 920
  que mediste. ✅

## Deriva durante la sesión (main se movió dos veces más)

Entró **DESPACHOS-v1 P-DSP-00..03** (`7320bee`) + el fix de longitud del seeder (`dc5e08a`),
que toca `ConfiguracionSeeder` — territorio del lote. **Refresqué la rama**: el cambio es
aditivo (claves `documentos_sync_*` aguas abajo de mis plantillas) y el único conflicto fue
de artefactos de build, resuelto con el mismo protocolo. Adopto la regla de Max-2 de
refrescar por paso.

## 🐛 Hallazgo nuevo (bitácora, commit `9a9c6c4`) — casi rompo producción con la forma «canónica» de resolver

`git show origin/main:<archivo> > <archivo>` — la manera obvia de «tomar el lado de main» —
**inyecta BOM + CRLF en PS 5.1** (`>` es `Out-File`). En un Blade el BOM se emite antes del
HTML; **en `manifest.json` hace fallar el `json_decode` de Vite → la app queda sin CSS/JS en
runtime**, y `git status` solo avisa del CRLF, no del BOM. Lo cacé con `file -b` antes de
commitear. La forma correcta es **`git checkout origin/main -- <archivo>`** (escribe git, no
el shell). Amplié la entrada de encoding del 20-07 en vez de crear una nueva.

Es irónico: el anexo advertía «manifest con marcadores → Vite revienta», y el riesgo real
resultó ser el mismo desenlace por una causa distinta y más silenciosa.

## Lo que queda anotado (sin fix, decisión tuya)

De la lista que ya veníamos arrastrando: aterrizaje mudo si la solicitud se resolvió antes
del tap (degrada con gracia) · el botón del correo apunta a la lista pelada mientras la
campanita va al ancla (igualarlo obliga a tocar los `assertSame` de `payload['url']`) · el
«un tap» quedó desktop-only por el `hidden lg:flex` de main · fragilidad estructural de las
dos one-shot (merecen el candado seeder↔migración que propusiste).

**Rama vieja `feature/notif-especificas`: la borro del remoto cuando la v2 esté mergeada**,
como ordenaste — no antes.

## Consumo

Talla **M**. `/usage` exacto: Mauricio, cuando puedas.
