# QA · I-04 — alerta GitGuardian: barrido de secretos + deploy key SSH + falso positivo · 2026-07-08

> Expediente completo de la incidencia I-04. Tres partes: **T1** barrido de secretos de la
> historia git completa (trabajo interno del stream 2, no delegado), **T2** delegación a la
> IA-cPanel para autenticar el `git pull` del servidor con deploy key SSH read-only, y el
> **cierre**: la alerta resultó falso positivo y el repo queda público por decisión del dueño
> (ficha D-012 en `docs/DECISIONES.md`).

## 0. Cronología

1. GitGuardian alertó "secreto expuesto" en el repo (que era público). Director verificó diffs
   del día limpios y dictó I-04 con máxima prioridad + gate de pushes a main.
2. **T1**: barrido de la historia completa (233 commits, 14 ramas) → **0 credenciales reales**.
3. **T2**: deploy key SSH generada en el server, registrada por Mauricio (read-only), remote a
   SSH, fetch autenticado verificado 12/12.
4. Detalle de la alerta revisado por el dueño: el "secreto" era el **placeholder «PEGAR» junto a
   `MAIL_USERNAME`** en un doc de delegación → **FALSO POSITIVO**. Rotaciones de claves hechas
   por el dueño de todos modos. Decisión: **el repo queda PÚBLICO por política del dueño**
   (D-012). Gate de pushes LIBERADO. I-04 CERRADA.

## 1. T1 · Barrido de secretos (interno, stream 2)

**Método:** workflow de 7 agentes — 6 finders en paralelo (familias: `.env` commiteados,
tokens/API keys, DSN con credenciales, llaves privadas, docs con huecos rellenados,
config/código) + verificación adversarial por hallazgo; ~563k tokens, 159 comandos git
read-only sobre `--all` (233 commits, 14 ramas). Spot-check independiente del autor sobre los
3 vectores críticos (.env en historia, `BEGIN PRIVATE KEY`, token Bsale con valor).

**Resultado: historia LIMPIA — 0 credenciales reales.**

| # | Archivo | Tipo | Severidad | Detalle |
|---|---------|------|-----------|---------|
| 1 | `docs/AUDITORIA-M01-M02.md` + `docs/qa/INFRA/2026-07-07--INFRA--i01-cierre-modo-compatibilidad.md` | Endpoint SSH `usuario@host:puerto` SIN contraseña | bajo (no-credencial) | El server autentica solo por llave ed25519 (jamás commiteada). **Redactado en ambos docs en este mismo push** (higiene). |

Negativas clave: ningún `.env` real commiteado jamás; ninguna llave privada; token Bsale jamás
con valor; cero DSN con credenciales; cero huecos «PEGAR» rellenados en `docs/qa/` (la
disciplina `[CLAVE OCULTA]`/«REDACTADO» se cumplió en todo el archivo histórico).
**Conclusión T1:** nada que purgar (`git filter-repo` innecesario); la alerta no apuntaba a una
credencial en la historia — consistente con el falso positivo confirmado después.

## 2. T2 · Delegación deploy key SSH — prompt enviado

El prompt de 12 pasos (plantilla VERIFICACION-CPANEL): bloque 1 estado (remote/-v, `ls ~/.ssh`,
fetch), bloque 2 llave (`ssh-keygen -t ed25519` sin passphrase, pública textual, `~/.ssh/config`
con `IdentitiesOnly`), **checkpoint humano** (Mauricio registra la deploy key read-only en
GitHub), bloque 3 remote a `git@github.com:DaliGo-2013/DaliGo.git` con URL de rollback, bloque 4
verificación (`ssh -T`, `git fetch origin`, `git fetch origin main` — sin reset), nota KEX
(entrante≠saliente, no improvisar), regla privada-jamás/pública-sí, formato tabla+RESUMEN con
campos extra `CLAVE_PUBLICA` y `URL_ROLLBACK`. Texto íntegro en la conversación del despacho;
revisado por el Director antes de enviar.

## 3. T2 · Respuesta recibida (íntegra, final 12/12)

| # | Paso | Resultado | Detalle |
|---|------|-----------|---------|
| 1 | git remote -v (estado) | OK | `origin  https://github.com/DaliGo-2013/DaliGo.git (fetch)` / `origin  https://github.com/DaliGo-2013/DaliGo.git (push)` |
| 2 | ls -la ~/.ssh/ | OK | Archivos (nombre + permisos, sin mostrar contenido de llaves): `authorized_keys` (-rw-r--r--), `blog.php` (-rw-r--r--), `daligo.pub` (-rw-------), `id_rsa` (-rw-------), `id_rsa.1744051973` (-rw-------), `id_rsa.1744051973.pub` (-rw-r--r--), `id_rsa.pub` (-rw-r--r--), `lufi.php` (-rw-r--r--), `putty/` (drwx------), `./` (drwx------), `../` (drwx--x--x) |
| 3 | git fetch origin | OK (no falló) | Funcionó por HTTPS sin pedir credenciales: `remote: Enumerating objects: 40, done.` … `From https://github.com/DaliGo-2013/DaliGo` `8e952d5..fe862a2  main -> origin/main` + varias ramas nuevas. (Repo aún público a propósito.) |
| 4 | [CAMBIO] ssh-keygen -t ed25519 | OK | Fingerprint: `SHA256:TaLTkrNOFMavqlaXKq0Qbc22sQ2aAGQHvXpUlXhqvEg daligo-deploy-server`. Privada en `~/.ssh/daligo_github_deploy` (NO mostrada) |
| 5 | cat daligo_github_deploy.pub | OK | `ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIDrxEvaldz5z5iy5hbRayefCniEFuEO7eQaAzqGngyoe daligo-deploy-server` |
| 6 | [CAMBIO] ~/.ssh/config + chmod 600 | OK | Contenido final: `Host github.com` / `    IdentityFile ~/.ssh/daligo_github_deploy` / `    IdentitiesOnly yes`. chmod 600 sin error |
| 7 | CHECKPOINT | OK | Mauricio registró la pública como deploy key "daligo-server-pull" READ-ONLY; fingerprint confirmado con captura. Continuación autorizada |
| 8 | [CAMBIO] git remote set-url origin (SSH) | OK | Ejecutado sin error |
| 9 | git remote -v | OK | `origin  git@github.com:DaliGo-2013/DaliGo.git (fetch)` / `origin  git@github.com:DaliGo-2013/DaliGo.git (push)` |
| 10 | ssh -T git@github.com | OK | Aceptado host key de github.com (ECDSA `SHA256:p2QAMXNIC1TJYWeIOttrVc98/R1BUPWu3/LiyKgUfQM`). Resultado: `Hi DaliGo-2013/DaliGo! You've successfully authenticated, but GitHub does not provide shell access.` |
| 11 | git fetch origin && git status | OK | Fetch SSH sin prompt: `From github.com:DaliGo-2013/DaliGo` `fe862a2..03d6d26  main -> origin/main` + 2 ramas nuevas. status: `On branch main` / `Your branch is behind 'origin/main' by 4 commits, and can be fast-forwarded.` / Untracked: `.env.bak.1783436214`, `.env.bak.20260706151430`, `.env.bak_20260708085512` |
| 12 | git fetch origin main | OK | `From github.com:DaliGo-2013/DaliGo` `* branch  main -> FETCH_HEAD`. Sin pedir usuario/contraseña |

RESUMEN

VEREDICTO: APROBADO CON OBSERVACIONES

PASOS_OK: 12 de 12

CAMBIOS_REALIZADOS:
- [Paso 4] Generada deploy key ed25519 sin passphrase: `~/.ssh/daligo_github_deploy` (+ `.pub`)
- [Paso 6] Añadido bloque `Host github.com` a `~/.ssh/config` y aplicado `chmod 600`
- [Paso 8] `git remote set-url origin` cambiado a `git@github.com:DaliGo-2013/DaliGo.git`
- [Paso 10] Añadido host key de github.com a `~/.ssh/known_hosts` (IPs 140.82.112.4, 140.82.112.3, 140.82.113.4)

FALLOS: ninguno

OBSERVACIONES:
- Paso 3: el `git fetch` por HTTPS anónimo AÚN funciona (repo todavía público a propósito; la privatización venía después de esta verificación). No fue un fallo.
- git status muestra la rama local `main` 4 commits por detrás de `origin/main` (fast-forward posible). No corrí reset/pull, según lo indicado; el deploy se encargará.
- 3 archivos sin trackear en el repo: `.env.bak.1783436214`, `.env.bak.20260706151430`, `.env.bak_20260708085512` (backups de .env; no los toqué).
- Existe un `daligo.pub` previo en `~/.ssh/` con permisos inusuales `-rw-------` y sin privada homónima visible; no lo modifiqué.
- No hubo error de KEX en la conexión saliente a GitHub (como estaba previsto).

CLAVE_PUBLICA:
`ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIDrxEvaldz5z5iy5hbRayefCniEFuEO7eQaAzqGngyoe daligo-deploy-server`

URL_ROLLBACK:
`https://github.com/DaliGo-2013/DaliGo.git`

## 4. Veredicto

**APROBADO CON OBSERVACIONES** (T2) + **I-04 CERRADA como FALSO POSITIVO** (Director, 2026-07-08).

Lectura nuestra: (1) la llave registrada es la del server — fingerprint del paso 4 cotejado
contra la captura de GitHub del dueño; el host key aceptado en el paso 10 coincide con el
fingerprint ECDSA **oficial publicado por GitHub** (sin MITM). (2) El pull del deploy quedó
autenticado e **inmune a un cambio futuro de visibilidad del repo** — valor permanente aunque
el repo siga público. (3) La verificación negativa post-privatización (pasos 13-14 preparados)
**no se ejecutó**: quedó sin objeto al decidirse que el repo permanece público (D-012).
(4) La causa de la alerta GitGuardian quedó confirmada por el detalle revisado por el dueño:
placeholder «PEGAR» junto a `MAIL_USERNAME` — coherente con el barrido limpio de T1.

## 5. Acciones derivadas

- **I-04 CERRADA** (falso positivo). Gate de pushes a main LIBERADO por el Director.
- **D-012** creada en `docs/DECISIONES.md`: repo público por política del dueño (TOMADA,
  Mauricio 2026-07-08), con consecuencias y riesgo residual.
- Endpoint SSH `usuario@host:puerto` **redactado** en los 2 docs del hallazgo bajo de T1
  (este mismo push).
- Rotaciones de claves: **hechas por el dueño** (cierra R-04 en lo operativo — registro en
  tablero del Director).
- **I-05 derivada** (dictada por el Director): inspección read-only de `~/.ssh/` del server —
  `blog.php` y `lufi.php` (PHP dentro de `~/.ssh` es anómalo) + `daligo.pub` huérfano + find de
  otros `.php` fuera de docroot/app root + `crontab -l`; y en el mismo despacho, borrar los 3
  `.env.bak.*` del app root (claves ya muertas post-rotación).
