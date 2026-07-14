# RELEVO DEL DIRECTOR — cómo tomar el mando al nivel actual (no al del kickoff)
> Escrito por el Director saliente el 2026-07-14 al 80% de ventana. El `KICKOFF-DIRECTOR.md`
> te constituye; ESTE archivo te pone al día con TODO lo aprendido desde el 02-07. Léelo
> completo antes de dictar nada. El estado vivo manda: si este doc y el tablero/git discrepan,
> git gana (regla de oro del proyecto).

## 1. Constitución del relevo (en orden, ~15 min)
1. Estás en el clon `C:\Users\HP\Documents\DaliGo-Director` (ya existe; NO clones de nuevo).
   `git fetch origin && git pull --rebase`. Corre `/usage`, anota tu punto de partida en
   `CONSUMO.md`.
2. Lee EN ESTE ORDEN: `docs/delegacion/KICKOFF-DIRECTOR.md` (tu rol base) → ESTE archivo →
   `docs/fleet/TABLERO-3-DIAS.md` (estado vivo + Incidencias) → `docs/fleet/buzon/README.md`
   (el bus actual) → `docs/fleet/buzon/dictados/*` (qué está dictado a cada asiento) →
   `docs/fleet/buzon/partes/` los más recientes (qué reportó cada uno) → `CONSUMO.md`.
3. Modelo: el roster dice Sonnet 5 para el Director; en la práctica corrió en Fable/Opus por
   decisión del dueño (Fable disponible hasta 19-07). El dueño fija el modelo en el selector.

## 2. Lo que CAMBIÓ desde el kickoff del 02-07 (esto no está allá)
- **BUZÓN git (13-07):** dictados y partes ya NO viajan por copy/paste del dueño. Viven en
  `docs/fleet/buzon/`. El dueño solo abre cada sesión y dice **«revisa tu buzón y ejecuta»**.
  Tú escribes `dictados/<asiento>.md`; los Forjadores escriben en `buzon/partes/`. Es su ÚNICA
  excepción de escritura en `docs/fleet/**` (resto = exclusivo del Director). Ver README del buzón.
- **Restricción física:** 1 cuenta Claude Code por computador. Hay 3 computadores: Max-1, Max-2,
  Director. Las cuentas Pro (Investigador/Escriba/QA) NO tienen Code → operan como **asientos de
  NAVEGADOR** (claude.ai) vía "paquete de contexto": tú redactas un prompt 100% autocontenido en
  `buzon/dictados/navegador.md`, el dueño lo pega en claude.ai, la respuesta vuelve. El navegador
  NO toca git.
- **Stream 3 (Marcos/M12 servicio técnico):** el dueño trabaja M12 AUTÓNOMO — no reporta, no está
  en el tablero. Regla: solo vigilas INTERFERENCIAS (si algo de la flota cruza su territorio,
  avisas al dueño ANTES de decidir). M12 empuja a main seguido.
- **Pivote a DESPACHOS (13-07):** M04 inventario POSPUESTO; el foco es DESPACHOS-v1.
- **D-007 WhatsApp APLAZADA** por el dueño (no bloquea; canal stub).

## 3. Cómo verifico (el núcleo del rol — nunca marques [x] sin esto)
- **Regla anti-autoengaño:** verifica contra el REPO, no contra el parte. `git show <hash>`,
  `git diff`, grep dirigido. Un parte dice "hecho"; tú confirmas que el commit existe, toca lo
  que dice y no fuga a otro territorio.
- **Auditoría adversarial con Workflow** para diffs grandes (usé 2-3 "lentes" en paralelo:
  correctness / territorio-seguridad / UI-bundle-tests). Cazó defectos reales que los partes no
  mencionaban. Vale la pena en merges y planes.
- **Sellos de PLAN:** spot-checks de 3-5 afirmaciones del sello contra el código (BsaleClient
  read-only, scope existe, componente existe, etc.). Ojo con greps case-sensitive (me pasó:
  `porConfirmar` vs `scopePorConfirmar`).
- **Deploys previewables:** verifica el servidor real con WebFetch a staging.impdali.cl (rutas
  302 vs 404/500, bundle servido, manifest). Lo hice para M15/M14.
- **CI:** `gh run list --repo DaliGo-2013/DaliGo`. TRAMPA: los workflows Deploy y Tests son
  INDEPENDIENTES → un deploy verde NO garantiza tests verdes (M12 pusheó main rojo con deploy
  ok). Mira SIEMPRE el workflow Tests aparte.

## 4. Lecciones de proceso duras (ganadas con dolor)
- **Doble llave para deploys:** push a main de CÓDIGO = deploy a producción. Nunca sin OK del
  dueño + tu verificación. Docs a main = inocuo, adelante.
- **Integración selectiva de ramas:** cuando integres una rama de Forjador a main, si trae
  versiones VIEJAS de archivos del Director (tablero/dictados), NO mergees la rama entera —
  `git checkout <rama> -- <solo-sus-archivos>` y deja los tuyos. Pisar tu propio hotfix es fácil.
- **Vistos buenos al momento (R-03):** si el dueño aprueba algo por canal directo, que te lo
  confirme A TI en el momento — o abres reconciliaciones fantasma después.
- **Repo público + seguridad:** el repo es PÚBLICO (D-012, decisión del dueño). Un incidente de
  seguridad se documenta REDACTADO desde el primer commit (veredicto sí; rutas/credenciales/mapa
  de atacante no). NO reescribir historia (rompe clones). Detalle sensible → canal privado.
- **Placeholders disparan escáneres:** GitGuardian alertó por un `«PEGAR»` junto a MAIL_USERNAME
  (falso positivo). Redacta también los placeholders en docs de infra.
- **git push a main:** el clasificador de permisos a veces lo bloquea (default branch de repo
  público). Con OK del dueño pasa; si no, el dueño aprueba el prompt o pusheas tras su palabra.
- **Modelo del roster ANTES de abrir cada sesión:** Fable consume ~3× vs Opus para el mismo
  trabajo (dato del ledger). El dueño fija el modelo en el selector.
- **Rama nueva por unidad:** cada Forjador arranca su unidad en rama nueva desde main fresco
  (`feature/<unidad>`), no reusa ramas viejas ya mergeadas.

## 5. Estado vivo al 14-07 (verifícalo, no lo asumas — puede haber avanzado)
- **En producción:** M11 producción · M12 servicio técnico (Marcos) · M15 notificaciones ·
  **M14 aprobaciones (merge 69a93a2, deploy+tests success)** · spike PWA. Avance global ~28%.
- **Max-1:** M16-v0 Dashboard ejecutivo — PLAN sellado y validado (`dd20ee8`), GO a construir
  (P-M16-01 controller+tests → 02 vista → 03 merge). Rama `feature/m16-v0-dashboard`.
- **Max-2:** DESPACHOS-v1 — PLAN sellado y validado + visto bueno del dueño (alcance QR
  anti-fraude + PWA conductor; zonas con excepción explícita cliente>vendedor). Rama
  `feature/despachos-v1`, arrancando por P-DSP-00 (explorar documents.json de Bsale).
- **Pendiente del dueño:** QA de celular de M14 (guion en
  `buzon/partes/2026-07-14--max-1--verificacion-post-deploy-m14.md`) → al confirmar, Max-1 marca
  P-M14-07 [x] y E2·M14 CERRADA. Rotaciones R-04 (¿hechas?). Malware I-05 → Víctor.
- **Decisiones:** D-001/002/009/010/011/012 cerradas; D-003 (Ricardo respondió, falta Luis);
  D-004 (Melisa respondió, faltan Scarlett/Héctor); D-005 redefinida (Víctor=sysadmin interno);
  D-006 zonas (modelo definido); D-007 aplazada; D-008 investigación impresoras (navegador).
- **Incidencias:** I-01 (cron */15 HostGator) cerrada · I-03 (token Bsale) cerrada · I-04
  (GitGuardian falso positivo) cerrada · I-05 (servidor comprometido era-2022) → Víctor ·
  I-06 (test flaky M12) cerrada. Ver TABLERO §Incidencias.
- **Meta:** reunión ~28-07 con M14 en vivo + dashboard + despachos en camino.

## 6. Tu primer acto como relevo
`git fetch` → lee partes nuevos del buzón → verifica con evidencia → actualiza tablero/ledger →
reescribe los dictados que correspondan → informa al dueño. Si el dueño te saluda sin contexto,
resúmele el §5 en 6 líneas y pregúntale qué priorizar. Sé conciso, verifica todo, protege
producción. Suerte. 🫡
