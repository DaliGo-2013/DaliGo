# Anexo I-05 — respuesta ÍNTEGRA del operador (para docs/qa/INFRA/)
> Recopilada por el Director 13-07 desde el hilo. Max-2: archívala tal cual en
> docs/qa/INFRA/2026-07-08--INFRA--servidor-comprometido-i05.md + entrada de bitácora.

Inspección SOLO LECTURA de ~/.ssh y del home del server (sesión Terminal cPanel como impdali;
hubo reconexión inicial por resize de ventana, sin impacto). NO se ejecutó, decodificó ni
reprodujo ningún payload. Único [CAMBIO]: paso 10 (borrado de 3 .env.bak autorizados).

| # | Paso | Resultado | Detalle |
|---|------|-----------|---------|
| 1 | stat blog.php/lufi.php/daligo.pub (~/.ssh) | OK | **blog.php**: 14397 B, 0644, uid 4131/impdali, Modify 2022-11-22 21:20:49, Change 2026-03-09 01:10:37. **lufi.php**: 53777 B, 0644, mismo dueño, Modify 2022-11-22 21:21:53, Change 2026-03-09 01:10:37. **daligo.pub**: 95 B, 0600, mismo dueño, Modify 2026-06-02 10:40:42, Change 2026-06-02 13:17:34 |
| 2 | cat blog.php (599 líneas) | OK | Web shell tipo file-manager: lee `$_GET["d"]` → `uhex(...)` (hex-decode) y ejecuta funciones ofuscadas vía array `$GNJ[1..4]()`; `$_GET` 40×, `$_POST` 18×. Fuente NO reproducida por ser malware |
| 3 | cat lufi.php (2 líneas) | OK | 53777 B. Backdoor `<?php eval(base64_decode('ZnVuY3Rpb24g...'))`. Payload NO reproducido |
| 4 | cat daligo.pub | OK | `ssh-ed25519 AAAAC3...L6Wf daligo-deploy`. Comentario `daligo-deploy` |
| 5 | grep daligo + conteo authorized_keys | OK | `~/.ssh/authorized_keys` menciona "daligo"; **1** sola llave autorizada, comentario `daligo-deploy` |
| 6 | find otros .php fuera de docroot/app | OK | **Total: 405** archivos. En `~/.cphorde`, `~/.autorespond`, `~/.htpasswds`, `~/xxx.php`, `~/.ssh`, `~/.subaccounts`, `~/perl5`, `~/.pki`, `~/about.php`, `~/etc`, `~/.cpanel`, `~/ssl`, `~/public_ftp`, etc. (blog.php + lufi.php sembrados en múltiples dirs ocultos) |
| 7 | stat + head de prioritarios | OK | `~/xxx.php` 24957 B (2022-11-30) y `~/about.php` 24954 B: idénticos, `<?php`+ofuscación, 1× eval c/u. `~/etc/{blog,lufi}.php` byte-idénticos a los de ~/.ssh. Copias en `~/tmp/` y `~/mail/` |
| 8 | crontab -l | OK | `MAILTO=""` + 2 tareas Laravel legítimas: `*/15 ... schedule:run` y `*/15 ... queue:work --stop-when-empty --max-time=840`. Grilla `*/15` VIVA. Sin entradas maliciosas |
| 9 | ls -la .env* (app root) | OK | `.env` 994 B (Jul 8 08:55); `.env.bak.1783436214` (Jul 7); `.env.bak.20260706151430` (Jul 6); `.env.bak_20260708085512` (Jul 8); `.env.example` (Jul 8) |
| 10 | [CAMBIO] rm 3 backups .env | OK | Borrados: `.env.bak.1783436214`, `.env.bak.20260706151430`, `.env.bak_20260708085512`. Sin errores |
| 11 | ls -la .env* (verificación) | OK | Quedan solo `.env` y `.env.example`. `.env` vigente intacto |

VEREDICTO: APROBADO CON OBSERVACIONES · PASOS_OK: 11/11 · CAMBIOS: solo el borrado de los 3
.env.bak del paso 10 · FALLOS: ninguno.
OBSERVACIONES: blog.php/lufi.php/xxx.php/about.php son MALICIOSOS (webshells+backdoors), no
basura. Infestación masiva (405 .php). Fechas: infección Nov-2022, Change 2026-03-09.
Recomienda tratar como compromiso de cuenta (remediar — fuera del alcance de la tarea de solo
lectura). Crontab limpio, grilla `*/15` viva.

ACLARACIÓN DEL DIRECTOR (post-inspección): `daligo.pub` (`daligo-deploy`) es LEGÍTIMA — es la
llave de GitHub Actions→server (bitácora 2026-06-05). Fingerprint computado por el Director:
`SHA256:m2QhGnkDrl3a2S6JGSLnIiNkpUmZWVrfp2a49/3OhWU` = exacto al de la bitácora. Su privada
vive en GitHub Secrets (por eso parece huérfana). NO tocar authorized_keys. La llave "creada
hoy 10:55" que alertó al operador = deploy key T2 de la propia flota. Remediación de los 405
webshells asignada a VÍCTOR (sysadmin interno), fuera del alcance de la app.
