# QA · I-05 — servidor comprometido (malware era-2022) · 2026-07-08 · REDACTADO (repo público)

> Inspección de SOLO LECTURA de `~/.ssh` y el home del servidor, derivada de I-04 (T2 detectó
> archivos `.php` anómalos dentro de `~/.ssh`). Delegada a la IA-cPanel. Único `[CAMBIO]`: borrado
> de 3 backups `.env` con claves ya rotadas (autorizado).
>
> **REDACTADO por decisión de seguridad del Director (2026-07-13):** el repo es PÚBLICO (D-012),
> así que los detalles útiles a un atacante — rutas exactas de los archivos maliciosos, patrón de
> ofuscación, volcado del crontab, blobs de llaves — **NO se documentan aquí**. El informe
> operativo COMPLETO se entregó a **Víctor** (sysadmin interno) por canal privado, fuera del repo.

## Veredicto

Inspección de solo lectura del servidor (HostGator, cuenta impdali) el 2026-07-08.
**Hallazgo: servidor comprometido** — múltiples archivos PHP maliciosos (webshells + backdoors)
fuera del docroot, en directorios ocultos del sistema. Cantidad del orden de varios cientos de
copias. **APROBADO CON OBSERVACIONES**, 11/11 pasos, único cambio autorizado (borrado de 3
`.env.bak`) ejecutado.

## Datación

Infección **era Nov-2022** (anterior al proyecto DaliGo) con retoque de metadata en marzo-2026.
No hay evidencia de que la app DaliGo, sus tablas ni sus datos estén afectados.

## Estado de nuestra superficie (limpio)

- **Crontab:** limpio, solo las 2 tareas legítimas de Laravel en grilla `*/15`. Sin entradas maliciosas.
- **Llaves SSH:** la única llave autorizada es la de deploy legítima (GitHub Actions→server),
  verificada por fingerprint contra la bitácora 2026-06-05. `authorized_keys` correcto — **NO tocar**.
  La "llave nueva" que alertó al operador era la deploy key de I-04·T2 de la propia flota.
- **Claves de la app:** ya rotadas (R-04, 2026-07-08). `.env.bak` viejos borrados (único `[CAMBIO]`
  autorizado de la inspección).

## Remediación

FUERA del alcance de la flota (no es código de la app). **Asignada a Víctor** (sysadmin interno de
DALI) con el informe operativo completo por canal privado: opciones de limpieza/scan (ticket
HostGator o manual), rotar clave del cPanel, revisar cuentas FTP/correo desconocidas. La app en
`/home4/impdali/daligo` y sus crons no se tocan.

## Nota de propagación (aprendizaje)

El detalle operativo completo (con rutas) estuvo brevemente en un anexo público del buzón antes de
esta redacción. Riesgo real bajo: los archivos están fuera del docroot (no alcanzables por web) y
un atacante externo no puede usarlos sin acceso al servidor. La mitigación definitiva es la
limpieza de Víctor: una vez removidos, las rutas dejan de existir. **Lección (a la bitácora de
CLAUDE.md):** en repo público, un incidente de seguridad se documenta REDACTADO desde el primer
commit; el detalle viaja por canal privado.

## Acciones derivadas

- Remediación de los webshells → **Víctor** (informe privado). La app y sus 2 crons NO se tocan.
- 3 backups `.env` muertos borrados — higiene cerrada.
- **NO tocar** `~/.ssh/authorized_keys` ni la `.pub` de deploy (legítimos y necesarios para el deploy).
- Grilla `*/15` de I-01 reconfirmada viva de paso.
- I-05 fuera del alcance de stream 2 una vez entregado a Víctor; no bloquea DESPACHOS-v1.
