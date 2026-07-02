# Plantilla · Investigación para destrabar una decisión

> Uso: levantar información (del servidor, de Bsale, del mercado) para resolver una ficha D-0NN de
> `docs/DECISIONES.md`. A diferencia de las otras plantillas, aquí SÍ se piden opciones y
> recomendación. Ver [`../PROTOCOLO-DELEGACION.md`](../PROTOCOLO-DELEGACION.md).

---

```markdown
CONTEXTO
Trabajas para DaliGo, sistema de gestión de una purificadora de agua chilena (Laravel, HostGator,
complementa a Bsale que es el ERP/facturador). Necesitamos decidir: {{decisión D-0NN, 1 línea}}.

TU ROL
Investigador. Levantas datos verificables, comparas opciones y recomiendas con fundamento. Si un dato
no lo puedes verificar, lo dices ("no verificado") en vez de darlo por cierto.

ACCESO / FUENTES
{{qué puede usar: cPanel, phpMyAdmin, web pública, documentación de Bsale, cotizaciones online...}}
{{credenciales si aplican: «PEGAR...»}}

TAREA
{{Pregunta concreta a responder, ej.: "¿Qué modelos de impresora térmica 10x15 compatibles con
etiquetas de Mercado Libre se consiguen en Chile, a qué precio, y cuál conviene?"}}

PASOS EXACTOS
{{Numerados. Ejemplo del patrón:
1. Busca 3 modelos disponibles en Chile con soporte de etiquetas 10x15 térmicas directas.
2. Por cada uno anota: precio (CLP, con fuente y fecha), conexión (USB/red/WiFi), compatibilidad
   con Windows, disponibilidad de repuestos.
3. Verifica si alguno aparece recomendado oficialmente por Mercado Libre Chile.}}

## FORMATO DE RESPUESTA (OBLIGATORIO — responde EXACTAMENTE así)

Primero la tabla de hallazgos por paso:

| # | Paso | Resultado | Detalle (datos textuales, con fuente) |
|---|------|-----------|---------------------------------------|

Después la tabla de opciones:

| Opción | Pros | Contras | Costo/plazo | Fuente |
|---|---|---|---|---|

Y al final este bloque, completando TODOS los campos:

RESUMEN
RECOMENDACION: (una opción, con la razón principal en 1 línea)
CONFIANZA: ALTA | MEDIA | BAJA (y por qué)
DATOS_NO_VERIFICADOS: (lista o "ninguno")
OBSERVACIONES: (o "ninguna")

Reglas: distingue SIEMPRE dato verificado de estimación. Si la pregunta no se puede responder con
las fuentes disponibles, dilo explícitamente y lista qué haría falta.
```
