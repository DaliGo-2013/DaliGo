# Anexo I-05 — expediente REDACTADO (repo público)
> Recopilada por el Director 13-07. **REDACTADA 13-07** por decisión de seguridad: el repo es
> público (D-012), así que los detalles útiles a un atacante (rutas exactas de los archivos
> maliciosos, patrón de ofuscación, detalle del crontab, blob de llaves) NO se documentan aquí.
> El informe operativo COMPLETO se entrega a Víctor por canal privado (fuera del repo).
> Max-2: archiva ESTA versión redactada en docs/qa/INFRA/2026-07-08--INFRA--servidor-comprometido-i05.md.

## Veredicto
Inspección de solo lectura del servidor (HostGator, cuenta impdali) el 08-07. **Hallazgo:
servidor comprometido** — múltiples archivos PHP maliciosos (webshells + backdoors) fuera del
docroot, en directorios ocultos del sistema. Cantidad del orden de varios cientos de copias.

## Datación
Infección **era Nov-2022** (anterior al proyecto DaliGo) con retoque de metadata en marzo-2026.
No hay evidencia de que la app DaliGo, sus tablas ni sus datos estén afectados.

## Estado de nuestra superficie (limpio)
- **Crontab:** limpio, solo las 2 tareas legítimas de Laravel en grilla `*/15`. Sin entradas
  maliciosas.
- **Llaves SSH:** la única llave autorizada es la de deploy legítima (GitHub Actions→server),
  verificada por fingerprint contra la bitácora 2026-06-05. `authorized_keys` correcto — NO
  tocar. La "llave nueva" que alertó al operador era la deploy key T2 de la propia flota.
- **Claves de la app:** ya rotadas (R-04, 08-07). `.env.bak` viejos borrados (único [CAMBIO]
  autorizado de la inspección).

## Remediación
FUERA del alcance de la flota (no es código de la app). **Asignada a Víctor** (sysadmin interno
de DALI) con el informe operativo completo por canal privado: opciones de limpieza/scan (ticket
HostGator o manual), rotar clave del cPanel, revisar cuentas FTP/correo desconocidas. La app
en `/home4/impdali/daligo` y sus crons no se tocan.

## Nota de propagación (aprendizaje)
El detalle operativo completo (con rutas) estuvo brevemente en un anexo público del buzón antes
de esta redacción. Riesgo real bajo: los archivos están fuera del docroot (no alcanzables por
web) y un atacante externo no puede usarlos sin acceso al servidor. Aun así, la mitigación
definitiva es la limpieza de Víctor: una vez removidos, las rutas dejan de existir. Lección para
la bitácora: en repo público, un incidente de seguridad se documenta REDACTADO desde el primer
commit; el detalle viaja por canal privado.
