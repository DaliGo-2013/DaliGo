# Parte — Max-2 · P-DSP-00 arrancado: explorador de documentos listo + delegación al server · 2026-07-13

CUENTA: Max-2 · Forjador B (stream 2) · Opus 4.8 · high
TAREA: P-DSP-00 (primer paso DESPACHOS-v1) — fijar el SHAPE real de `documents.json` antes de congelar la migración de P-DSP-01
ESTADO: PARCIAL — código del explorador HECHO y en la rama; **la EJECUCIÓN se delega** (el token Bsale NO existe en el clon local, vive solo en el `.env` del server tras I-03). No inventé el shape.

QUÉ HICE:
- Extendí `app/Console/Commands/BsaleExplore.php` (read-only, **sin migración** — dentro del gate) con una §7 que consulta `documents.json?limit=3&expand=[details,client,office,references]` y vuelca el **SHAPE** (claves de cabecera + de una línea de `details` + nodos `client`/`office` + tipos), con **valores REDACTADOS** (los documentos reales traen nombre/RUT de cliente y montos de facturas → datos sensibles que NO deben quedar en el log ni en el repo público). Confirma explícitamente si `details` viene en la respuesta GET.
- `php -l` OK; **suite 530 verde**; commit a `feature/despachos-v1`.

POR QUÉ DELEGO LA EJECUCIÓN:
- `BSALE_ACCESS_TOKEN` local = vacío (verificado, longitud 0). El token renovado (I-03) está en el `.env` del server. Llamar la API desde local daría 401. La regla es no alucinar el shape → lo lee quien tiene el token: la IA-cPanel en el server.

⬇️ DELEGACIÓN PARA IA-cPanel (Mauricio despacha; a revisión del Director si aplica):

```
CONTEXTO
Administras el hosting de DaliGo (Laravel) en HostGator/cPanel. App en /home4/impdali/daligo,
staging.impdali.cl. Hoy: correr un comando artisan de SOLO LECTURA ya desplegado que explora la
forma de los documentos de venta en Bsale (no escribe nada, ni en Bsale ni en la BD).

TU ROL
Operador de cPanel. Ejecutas y devuelves la salida TEXTUAL. No cambias nada (no hay [CAMBIO]).

PASOS
1. Terminal cPanel. Asegura que el código de la rama está desplegado O corre directo el comando
   (ya existe en main tras el merge del plan): 
   /opt/cpanel/ea-php83/root/usr/bin/php /home4/impdali/daligo/artisan bsale:explore
2. Copia ÍNTEGRA la salida de la sección "== Documentos de venta (shape para DESPACHOS-v1 ...) ==".
   Interesa: ¿aparece "✅ details PRESENTE"? Las claves de la CABECERA, las claves de una LÍNEA de
   detalle, las claves de client/office, y la lista de tipos (ya vienen redactados por el comando).
3. Si dice "⚠️ details NO vino como lista utilizable", cópialo igual (es un hallazgo).

FORMATO: pega la salida del paso 2 tal cual (ya está redactada — no trae valores reales). Si el
comando falla (401/token), copia el error exacto. VEREDICTO: OK / FALLO.
```

TESTS: 530 verde (el explorador no rompe nada; sin test propio — es un comando de diagnóstico).
/usage: ← Mauricio completa. Sesión en Opus 4.8 ✓.

SIGUIENTE: con la salida del shape → congelo la migración de P-DSP-01 (espejo `documentos_venta`+`_detalles`) ajustada a los campos REALES. Hasta entonces NO escribo esa migración (gate de mi propio plan §4, riesgo #1). En paralelo puedo adelantar P-DSP-02 (zonas) que NO depende de Bsale — incluye el ajuste del dueño: `clientes.zona_id` explícito que GANA sobre la zona heredada del vendedor.
