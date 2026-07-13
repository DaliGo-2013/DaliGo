# Parte de cierre — Max-1 (Forjador A, stream 1) · HOTFIX main rojo (TAREA 0)

> Emitido 2026-07-13. Modelo: Fable 5.

**TAREA:** TAREA 0 del dictado — main ROJO por `PwaTest` desalineado con el nuevo `start_url`.
**ESTADO:** HECHA — main verde.

**DIAGNÓSTICO (verificado contra `origin/main`):**
- Única aserción stale: `PwaTest.php:41` (`test_manifest_es_valido_e_instalable`) asertaba
  `start_url === '/produccion/mi-reporte'`; el manifest ya decía `/dashboard` (fix M12
  `fix/pwa-start-url-403`, `2c396b7` → main rojo).
- La línea 93 (`->get('/produccion/mi-reporte')`) es un GET a la pantalla del soplador para
  comprobar que declara el manifest — sigue válida, NO se tocó.
- El test golden-hash de `offline.blade.php` quedó VERDE (ese Blade no cambió desde `ee01204`).
- NO toqué el `manifest.json` de Marcos ni implementé redirect por rol (pregunta de producto,
  Mauricio).

**EVIDENCIA:** commit `<hash>` en main (se completa al pushear):
- `tests/Feature/PwaTest.php` — aserción del start_url → `/dashboard` + comentario del porqué.
- `CLAUDE.md` — entrada de bitácora [2026-07-13]: "cambiar `manifest.json` desalinea `PwaTest`
  → la CI de main es la red; tocar manifest/sw ⇒ revisar PwaTest en el mismo push".
- Sin build (test + `.md`; nada de Blade/CSS/JS).

**TESTS:** `PwaTest` 5/5; **suite completa de main 507 verdes** local. Señal de cierre real:
la CI de main (Actions "Tests") debe volver a verde tras el push.

**SIGUIENTE (con main verde):** P-M14-07 — rebase de `feature/m14-aprobaciones` sobre main,
re-sellado de PLAN-M14 (guard `class_exists` omitido + eventos ya en 02), gate `/pre-merge`
R-31, suite + grep; parte con el hash del merge EN LA RAMA + conteo ANTES de pushear; el push
a main va SOLO con doble llave (Director + Mauricio) → deploy → QA staging desde el celular de
Mauricio. **No arranco P-M14-07 en este turno** (gate humano del merge coordinado).

**/usage:** lo corre Mauricio; lo anoto si me lo pasa.
