# Parte — Max-1: `feature/notif-especificas-v2` REFRESCADA contra main y verde · sigue esperando doble llave · 2026-07-27 (2º del día)

> De Max-1 (Forjador A) al Director. Sin dictado nuevo (el v25 quedó cumplido esta mañana);
> esto es mantenimiento defensivo de la rama para que no vuelva a ponerse rancia.

## Por qué este parte

Main se movió otra vez tras mi entrega: entró la **familia de páginas de error**
(`3e03a9a`, doble llave) y tu **corrección post-mortem del anexo** (`a2d911d`). El lote de
errores toca `ConfiguracionSeeder.php` y `CLAUDE.md` — dos archivos que mi rama también
modifica. Refresqué antes de que el conflicto te tocara a ti al mergear.

**Resultado: automerge LIMPIO, cero conflictos** (los cambios de main caen en zonas
distintas: sus claves `documentos_sync_*` van aguas abajo de mis plantillas, y su entrada de
bitácora no colisiona con mi ampliación de la del 20-07).

## Verificación post-refresh (todos los gates del v25, re-corridos)

| Gate | Resultado |
|---|---|
| Ancla `id="aprobacion-` en `resources/views` | **2** (1 + 1) ✅ |
| `ir=1` con `urlDestinoPara($request->user())` | presente ✅ |
| `whitespace-pre-line` en la bandeja | presente ✅ |
| Encoding de todo lo tocado | **UTF-8 sin BOM** ✅ (lección de ayer aplicada: `git checkout origin/main --`, nunca `>`) |
| Bundle | rebuild tras `view:clear` → **idéntico al de main**; `public/build` **ni aparece** en el diff → el merge no toca artefactos ✅ |
| Candado nuevo `ConfiguracionSeedLongitudTest` | mis descripciones miden **185/191**; los cuerpos van en `valor` (TEXT, fuera del candado) ✅ |
| **Suite COMPLETA** | **989 verdes / 5.394 aserciones** (main traía 976 tras el lote de errores; +13 del lote) ✅ |

Diff contra main: **15 archivos, todos del lote**. Cero contaminación del refresh.

## Matiz nuevo sobre el defecto que corregí (lo aclara el lote de errores de main)

El handler de `bootstrap/app.php` ahora convierte un 403 de navegación en **redirect al
Inicio con aviso**, no en pantalla cruda. O sea: sin mi fix, el escenario no era «403 feo»
sino «te consume la notificación, no llegas a destino y apareces en el Inicio con un aviso
genérico». **Menos feo, igual de malo en lo que importa** — el gate `urlDestinoPara` sigue
siendo la corrección correcta: no gasta la notificación en un viaje que no lleva a ninguna
parte. Lo anoto para que el veredicto no se lea con la severidad equivocada.

## Estado

`feature/notif-especificas-v2` @ **`d52e741`** — al día con main, verde, diff acotado, sin
artefactos en conflicto. **Espera doble llave.** La rama vieja `feature/notif-especificas`
la borro cuando la v2 esté mergeada, como ordenaste.

Adopto como rutina el refresh por paso mientras una rama mía espere llave: es barato y le
quita el trabajo sucio a quien mergea.
