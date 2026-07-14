# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-07-14 (v13 — tweak campanita móvil, aprobado por el dueño). Manda sobre lo anterior.

MODELO: Opus 4.8 · high (talla S).

## Contexto (hallazgo del QA de celular del dueño, 14-07)
El dueño intentó el QA de M14 desde el teléfono y **no encontró la campana**: en móvil no
hay ícono en la cabecera — la sección «Notificaciones» vive al FONDO del menú hamburguesa
(`navigation.blade.php` ~L295). El usuario objetivo del flujo aprobar-desde-el-teléfono no
la descubrió → discoverability insuficiente. (Su test además quedó bajo el umbral — Δ 20 <
50 — así que no había notificación que ver; va a repetir con Δ ≥ 50.)

## TWEAK-CAMPANITA-MOVIL (S, rama nueva `feature/campanita-movil` desde main fresco)
1. **Ícono de campana SIEMPRE VISIBLE en la cabecera móvil**, junto a la hamburguesa
   (zona `lg:hidden`), con el badge de no-leídas (`$dgConteo` ya calculado al tope del nav —
   reúsalo, no dupliques la query). Heroicon outline `bell` como componente
   `<x-icon.bell />` (formato del catálogo: fill=none, stroke-width 1.5, currentColor,
   aria-hidden + sr-only).
2. **Destino:** al tocarla → `notificaciones.index` (mismo destino que la sección del menú).
   Dropdown completo en móvil NO (pantalla chica, la página personal ya existe) — salvo que
   veas trivial reusar el partial; decide tú y documenta.
3. La sección del fondo del hamburguesa SE QUEDA (no romper `CampanitaTest` — extiéndelo:
   assert del ícono visible en móvil con badge).
4. Es superficie compartida (navigation.blade.php, M12 la toca seguido): bloque mínimo,
   fetch de main fresco antes, npm run build + grep bundle (6 de M16 + `lg\:*` + las del
   badge). Responsive 375/768/1024: a 375 la cabecera no debe desbordar (logo + campana +
   hamburguesa caben).
5. Suite verde. Parte al buzón → merge con doble llave exprés (el dueño ya lo aprobó
   conceptualmente; falta solo el endoso del Director al resultado).

## Housekeeping pendiente del dictado v12 (si no lo hiciste): P-M16V1-03 [x] en RUTA con
hash `6caf1f9`.

Pendiente vivo: el dueño repite el QA M14 con Δ ≥ 50 — al confirmarlo, P-M14-07 [x] + sello.

CIERRE: parte a docs/fleet/buzon/partes/ + push.
