# Protocolo de delegación — IA de cPanel/QA

> DaliGo se construye con un stream dueño+IA de desarrollo, más una **IA externa** que tiene acceso a
> cPanel, al servidor y a `staging.impdali.cl`. Este documento define cómo se le delega trabajo y cómo
> se procesan sus respuestas para tomar decisiones confiables.

---

## 1. Los tres actores y el ciclo (6 pasos)

```
IA de desarrollo            Mauricio (dueño)              IA externa (cPanel/QA)
────────────────            ────────────────              ──────────────────────
1. Redacta el prompt   →    2. Copia/pega el prompt  →    3. Ejecuta los pasos
   (con plantilla,           (rellena credenciales         y responde en el
   pasos exactos)            en los huecos «PEGAR»)        formato obligatorio
                                                                 │
6. Procesa: veredicto  ←    5. Pega la respuesta     ←    4. Entrega respuesta
   → acción en               de vuelta en la sesión
   RUTA-MAESTRA              (se archiva en docs/qa/)
```

## 2. Reglas de oro del prompt delegado

1. **Autocontenido.** La IA externa NO tiene el repo ni esta conversación: el prompt incluye todo el
   contexto necesario (URL, qué es la app, qué módulo se prueba) en 3 líneas máximo.
2. **CERO secretos en el repo.** Los prompts llevan huecos `«PEGAR CREDENCIAL AQUÍ»` que Mauricio
   rellena a mano al copiar. Jamás commitear contraseñas/tokens en estas plantillas ni en las evidencias.
3. **Formato de respuesta OBLIGATORIO y cerrado** (sección 3). Si la respuesta no lo respeta,
   se reenvía el prompt subrayando la sección FORMATO — no se interpreta una respuesta ambigua.
4. **Pasos numerados y atómicos:** un paso = una acción = un resultado observable, con el resultado
   esperado explícito.
5. **Anti-alucinación explícita:** el prompt siempre dice que `NO SE PUDO` es una respuesta válida y
   que no proponga soluciones — solo reporte.

## 3. El formato de respuesta obligatorio

Todo prompt termina con este bloque **literal** (las plantillas ya lo traen):

````markdown
## FORMATO DE RESPUESTA (OBLIGATORIO — responde EXACTAMENTE así)

Primero la tabla, un renglón por paso, sin omitir ninguno:

| # | Paso | Resultado | Detalle |
|---|------|-----------|---------|
| 1 | (nombre del paso) | OK / FALLO / NO SE PUDO | qué viste, texto exacto de errores |

Después este bloque, completando TODOS los campos:

```
RESUMEN
VEREDICTO: APROBADO | APROBADO CON OBSERVACIONES | RECHAZADO
PASOS_OK: n de m
FALLOS: (lista breve o "ninguno")
OBSERVACIONES: (cosas raras aunque no fallen, o "ninguna")
CAPTURAS: (sí adjunto n / no aplica)
```

Reglas: no inventes resultados; si un paso no se puede hacer, marca NO SE PUDO, explica por qué y
CONTINÚA con el siguiente. No propongas soluciones: solo reporta lo que observaste.
````

## 4. Plantillas disponibles

| Plantilla | Cuándo usarla |
|---|---|
| [`plantillas/QA-FUNCIONAL-STAGING.md`](plantillas/QA-FUNCIONAL-STAGING.md) | Probar un módulo en `staging.impdali.cl` como usuario final (post-deploy, siempre). |
| [`plantillas/VERIFICACION-CPANEL.md`](plantillas/VERIFICACION-CPANEL.md) | Verificar/ajustar infraestructura: crons, PHP, logs, BD, SSL, espacio, límites. |
| [`plantillas/INVESTIGACION-DECISION.md`](plantillas/INVESTIGACION-DECISION.md) | Levantar información para destrabar una decisión D-0NN de `docs/DECISIONES.md`. |

Los campos `{{...}}` se rellenan al redactar cada prompt; los huecos `«PEGAR...»` los rellena Mauricio.

## 5. Archivo de evidencia

La respuesta pegada se archiva **íntegra y sin editar** según la convención de
[`../qa/README.md`](../qa/README.md) (un archivo por delegación, 4 bloques: prompt, respuesta,
veredicto, acciones derivadas).

## 6. Matriz veredicto → acción

| Veredicto | Acción en RUTA-MAESTRA |
|---|---|
| `APROBADO` | Marcar el paso `[x]` con la evidencia enlazada. |
| `APROBADO CON OBSERVACIONES` | Paso `[x]` + crear un paso nuevo por CADA observación relevante. |
| `RECHAZADO` | El paso queda `[ ] [EN CURSO]`; cada fallo se convierte en paso de corrección. |
| Respuesta que no respeta el formato | No interpretar: reenviar el prompt (regla 2.3). No archiva evidencia. |
