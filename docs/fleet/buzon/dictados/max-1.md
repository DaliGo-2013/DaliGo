# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-07-14 (v7 — M16-v0 verificado, espera doble llave). Manda sobre lo anterior.

MODELO: Fable 5 disponible hasta el 19-07 (decisión del dueño); si no, Opus 4.8 · high.

✅ **P-M16-01/02/03 VERIFICADOS por el Director** (`f9b0721`). Spot-checks propios (no solo tu
parte): diff contra main acotado a `DashboardController`+vista+tests+docs (cero conflicto
esperado, confirmado) · grep bundle 7/7 confirmado independientemente sobre
`public/build/assets/app-OhPY6b0u.css` (`lg\:flex`, `lg\:hidden`, `sm\:grid-cols-3`,
`lg\:grid-cols-5`, `min-w-\[1.5rem\]`, `bg-white\/60` — las 6 que grepeaste + el selector
`grid-cols-5` base) · manifest apunta al bundle nuevo. Gate R-31 16/16 con tus 3 observaciones
quedan anotadas como deuda menor de v1 (helper compartido de fórmulas, `$indicadores` muerto,
matriz de roles futura) — NO bloquean.

## LLAVE DEL DIRECTOR: DADA. Falta la de Mauricio.
NO mergees ni pushees a main todavía — merge a main = deploy a producción, y esa es la
SEGUNDA llave (la del dueño), que el Director aún no tiene confirmada en este dictado.

**Cuando el Director te confirme "doble llave dada" en un próximo dictado o parte:**
1. `git fetch` + plegar main si avanzó → suite verde → merge a main + push (= deploy) →
   verificar Actions (Deploy Y Tests, son independientes — mira los dos).
2. QA staging de 1 minuto: admin ve el Inicio agrupado con números reales, cards clickeables.
3. P-M16-03 [x] en RUTA-MAESTRA mismo push → **E10-v0 CERRADA**.

Hasta entonces: nada más que hacer en esta rama. Si tienes ventana libre, avisa al buzón y el
Director te asigna algo (no inventes trabajo fuera de tu cola).

RECORDATORIO: E2·M14 cierra formalmente cuando Mauricio corra el QA de celular (tu guion ya
está en su poder). Cuando te confirme que salió OK, marca P-M14-07 [x] + sello 01→07 en
RUTA-MAESTRA (docs, un push). No bloquea M16.

CIERRE por paso: parte a docs/fleet/buzon/partes/ + push.
