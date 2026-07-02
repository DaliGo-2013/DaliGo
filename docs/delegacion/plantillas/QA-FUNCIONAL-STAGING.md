# Plantilla · QA funcional en staging

> Uso: probar un módulo en `staging.impdali.cl` como usuario final (siempre tras un deploy).
> Rellenar los `{{...}}` al redactar; Mauricio rellena los `«PEGAR...»` al copiar. Ver reglas en
> [`../PROTOCOLO-DELEGACION.md`](../PROTOCOLO-DELEGACION.md).

---

```markdown
CONTEXTO
Estás ayudando a probar DaliGo, una app web Laravel de gestión interna de una purificadora de agua
chilena. Ambiente de pruebas: https://staging.impdali.cl. Hoy probamos: {{módulo y qué hace, 1 línea}}.

TU ROL
Eres un tester manual. Ejecutas los pasos EXACTAMENTE en orden y reportas lo que ves, sin interpretar
ni proponer arreglos. Si un paso no se puede hacer, lo marcas NO SE PUDO y continúas con el siguiente.

ACCESO
- URL: https://staging.impdali.cl/login
- Usuario {{rol 1}}: «PEGAR USUARIO» / Clave: «PEGAR CLAVE»
{{- Usuario {{rol 2}}: «PEGAR USUARIO» / Clave: «PEGAR CLAVE» (si el flujo cruza roles)}}

TAREA
{{Objetivo verificable en 1–2 líneas, ej.: "Verificar el ciclo completo: soplador reporta → jefe
aprueba → el reporte queda aprobado y el soplador ya no puede editarlo".}}

PASOS EXACTOS
{{Numerados, atómicos, con resultado esperado por paso. Ejemplo del patrón:
1. Inicia sesión como soplador. Esperado: llegas al panel y ves la sección "Producción".
2. Crea un reporte de hoy con cantidad 50. Esperado: mensaje de éxito y estado "borrador".
3. Envía el reporte. Esperado: estado cambia a "enviado" y ya no es editable.
4. Cierra sesión e inicia como jefe. Esperado: ves el reporte pendiente del paso 2.
5. Apruébalo. Esperado: cambia a "aprobado" sin error.
6. Vuelve como soplador. Esperado: figura "aprobado" y NO puedes editarlo.}}

PASOS DE SMOKE ESTÁNDAR (agregar SIEMPRE al final)
A. Con el usuario de menos permisos, intenta entrar directo a /admin/usuarios. Esperado: error 403.
B. Recorre el menú completo con el usuario admin. Esperado: ninguna página da error 500.
C. Achica la ventana a ancho de celular (~375px). Esperado: aparece menú hamburguesa y funciona.

## FORMATO DE RESPUESTA (OBLIGATORIO — responde EXACTAMENTE así)

Primero la tabla, un renglón por paso, sin omitir ninguno:

| # | Paso | Resultado | Detalle |
|---|------|-----------|---------|
| 1 | (nombre del paso) | OK / FALLO / NO SE PUDO | qué viste, texto exacto de errores |

Después este bloque, completando TODOS los campos:

RESUMEN
VEREDICTO: APROBADO | APROBADO CON OBSERVACIONES | RECHAZADO
PASOS_OK: n de m
FALLOS: (lista breve o "ninguno")
OBSERVACIONES: (cosas raras aunque no fallen, o "ninguna")
CAPTURAS: (sí adjunto n / no aplica)

Reglas: no inventes resultados; si un paso no se puede hacer, marca NO SE PUDO, explica por qué y
CONTINÚA con el siguiente. No propongas soluciones: solo reporta lo que observaste.
```
