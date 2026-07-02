# Plantilla · Verificación / ajuste de infraestructura (cPanel + servidor)

> Uso: tareas de cPanel, crons, BD, logs, límites PHP, SSL. Pide VALORES TEXTUALES, no juicios
> ("anota el valor exacto", no "verifica que esté bien"). Ver [`../PROTOCOLO-DELEGACION.md`](../PROTOCOLO-DELEGACION.md).
> ⚠️ Si el prompt incluye acciones que CAMBIAN el servidor (borrar cron, rotar clave), separarlas en
> pasos propios con la palabra **[CAMBIO]** para que quede evidencia explícita de qué se modificó.

---

```markdown
CONTEXTO
Administras el hosting de DaliGo (app Laravel) en HostGator compartido con cPanel. La app vive en
/home4/impdali/daligo y el sitio de pruebas es https://staging.impdali.cl. Hoy: {{tema, 1 línea}}.

TU ROL
Eres el operador de cPanel/servidor. Ejecutas los pasos en orden, anotas VALORES EXACTOS (copia el
texto tal cual, no lo resumas) y no cambias nada que no esté marcado como [CAMBIO].

ACCESO
- cPanel: «PEGAR URL/USUARIO/CLAVE O INDICAR QUE YA TIENES SESIÓN»
- {{otros accesos si aplican (phpMyAdmin, Terminal, FTP)}}

TAREA
{{Objetivo en 1–2 líneas.}}

PASOS EXACTOS
{{Numerados; para cada uno: dónde clicar / qué comando correr, y qué valor anotar. Ejemplo del patrón:
1. cPanel → Cron Jobs. Anota TODAS las líneas de cron existentes, textuales.
2. [CAMBIO] Elimina SOLO la línea que empieza con "*/20 * * * *" y contiene "schedule:run".
   Esperado: queda una única línea de schedule:run (la de "* * * * *").
3. cPanel → Terminal → ejecuta: /opt/cpanel/ea-php83/root/usr/bin/php /home4/impdali/daligo/artisan schedule:list
   Copia la salida completa.
4. Abre /home4/impdali/daligo/storage/logs/laravel.log → copia las últimas 20 líneas.}}

## FORMATO DE RESPUESTA (OBLIGATORIO — responde EXACTAMENTE así)

Primero la tabla, un renglón por paso, sin omitir ninguno:

| # | Paso | Resultado | Detalle |
|---|------|-----------|---------|
| 1 | (nombre del paso) | OK / FALLO / NO SE PUDO | valores textuales anotados / error exacto |

Después este bloque, completando TODOS los campos:

RESUMEN
VEREDICTO: APROBADO | APROBADO CON OBSERVACIONES | RECHAZADO
PASOS_OK: n de m
CAMBIOS_REALIZADOS: (lista exacta de lo que se modificó, o "ninguno")
FALLOS: (lista breve o "ninguno")
OBSERVACIONES: (cosas raras aunque no fallen, o "ninguna")
CAPTURAS: (sí adjunto n / no aplica)

Reglas: no inventes resultados; si un paso no se puede hacer, marca NO SE PUDO, explica por qué y
CONTINÚA con el siguiente. No hagas ningún cambio no marcado [CAMBIO]. No propongas soluciones.
```
