# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-07-29 (v30 — NOTIF-1 cerrado y en producción; GO P-NAV-05: gate R-31 formal del menú V4 sobre main). Manda sobre lo anterior.

MODELO: Opus 4.8 · high (auditoría; sube a xhigh solo si un hallazgo se resiste).

## ✅ Tu lote del v29 EN PRODUCCIÓN (merge `0e92076`, Deploy success)
El botón del correo ya aterriza en la tarjeta puntual: **NOTIF-1 queda cerrado del todo**.
Verificación del Director: punto único `anclaAprobacion()` reusado por `urlDestino()` y por los
dos productores —sin duplicar, como se pidió—, bundle 98/98, y **suite con tu lote + el QR de
Max-2 integrados: 1191 verdes / 7.715 aserciones**. Rama borrada tras confirmar ancestría.

El candado `OneShotPlantillasCandadoTest` era la deuda que más me preocupaba de todo el
territorio de notificaciones: sin él, cambiar un texto del seeder sin tocar la migración la
volvía un **no-op silencioso** y las plantillas nunca llegaban a producción. Que cubra las DOS
one-shot (la tuya y la de Marcos) es lo correcto.

## 🟢 GO P-NAV-05 — gate R-31 formal del menú V4, sobre main (M)
Rama `audit/nav-gate-r31` desde main fresco. **Es el único paso que le falta a E-NAV para
cerrarse** (va 9 de 10) y hoy es lo de mayor valor que puedes tomar sin colisionar.

**Por qué ahora y por qué tú:** la revisión pre-gate se corrió el 24-07 (13 confirmados,
corregidos en `2557043`), pero **eso fue sobre la rama, no sobre main** — y desde entonces el
menú recibió acordeón, badges, hub de funciones, campanita en cabecera, drawer móvil táctil,
panel anclado, ancho único, botón único de volver, y las 4 ex-huérfanas que tú mismo agregaste.
El R-31 formal sobre el árbol integrado nunca se corrió. Tus gates propios de esta semana
(`fix/nav-huerfanas`, `#6 chips`) cazaron cosas reales que a mí se me pasaron; esto es lo mismo
a escala de unidad.

**Qué audita el gate** (usa tu criterio, esto es guía no camisa de fuerza):
- **Permisos ↔ visibilidad**: cada ítem del menú aparece solo para quien puede entrar a su
  ruta. Un ítem visible que lleva a 403 es peor que no tenerlo. Cruza `MenuPrincipal` contra
  los middleware reales de `routes/web.php`, no contra lo que digan los comentarios.
- **Cobertura**: ¿queda alguna pantalla huérfana que no listamos? ¿Algún ítem apunta a una ruta
  que ya no existe? El candado que dejaste (`test_cada_ruta_del_menu_resalta_exactamente_un_item`)
  cubre el resaltado, no la existencia.
- **Móvil**: el drawer, los objetivos táctiles, el panel anclado y la campanita en cabecera son
  de otras manos y entraron por separado — verifica que juntos no se pisen.
- **Accesibilidad**: `aria-current`, foco al abrir/cerrar el drawer, navegación por teclado en
  el acordeón. Ya hubo un hallazgo de doble `aria-current`; puede haber hermanos.
- **Los 4 contratos nuevos** de la semana aplicados al menú: ancho por layout, botón único de
  volver, errores amables, marco mobile-first.

**Entrega:** un parte con los hallazgos clasificados (alto/medio/bajo) **y los fixes de los que
tú consideres seguros aplicados en la misma rama**. Los que impliquen decisión de producto,
déjalos anotados sin tocar. Si el gate sale limpio, dilo — un gate sin hallazgos es un
resultado válido y vale más que inventar observaciones.

**Lo que NO cubre este paso:** el QA del dueño en celular real. Eso es suyo y cierra E-NAV
después de tu gate.

## Territorio: cuidado, hay dos manos activas cerca
- **Marcos está construyendo M05 Facturación/DTE AHORA** (módulo Documentos + Estado, puerto
  emisor, credenciales). No lo toques ni de refilón.
- El **bundle de diseño** sigue bloqueado y en manos del dueño.
- **Max-2** arranca P-DSP-05 (PWA del conductor) desde main.

## Recordatorios
Suite COMPLETA (baseline hoy **1191**). Si tocas Blade → build + grep superset. Conflictos con
`git checkout origin/main -- <archivo>`, nunca con `>`. Parte al buzón → doble llave.

CIERRE: parte a docs/fleet/buzon/partes/ + push.
