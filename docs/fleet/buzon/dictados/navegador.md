# Dictados vigentes — asientos de NAVEGADOR (claude.ai, sin git)
> Mauricio copia el paquete desde aquí a la conversación del navegador y trae la respuesta
> de vuelta (al Director o pegándola él mismo en buzon/partes/). Fuente única.

## N-Investigador — pendiente: nada nuevo
Entregados y aceptados: ficha D-007 ✓ · matriz D-002 ✓ (archivada como referencia) · prompt
P-S0-10 ✓ (aprobado para despacho a la IA de cPanel — ¿se despachó? confirmar con Mauricio).
Próximo paquete probable: evaluación de opciones para el dashboard M16-v0 (lo emite el
Director cuando arranque esa unidad).

## N-Escriba — pendiente: BORRADOR-soplador
El paquete 4 (manual del soplador, 1 página) fue entregado el 08-07 en el hilo del Director
y quedó congelado por la licencia. Mauricio: pégalo en una conversación nueva de claude.ai
con la cuenta Escriba. Al volver el texto: el Director lo verifica y Max-2 lo commitea como
docs/manuales/BORRADOR-soplador.md.

## IA de Chrome (Gemini u otra, del dueño) — 2 investigaciones lanzables
1. D-005 ruta A: preguntas sobre docs oficiales API Bsale (prompt del 08-07, hilo Director).
   PRIORIDAD SUBIÓ con el pivote a despachos: agregar la pregunta "¿el endpoint de
   DOCUMENTOS (documents) permite filtrar por fecha de emisión/modificación para
   sincronización incremental, y trae los ítems/detalles del documento?"
2. D-008: impresoras térmicas Chile con links para Luis (prompt del 08-07, hilo Director).

## Textos reutilizables (fuente única — Mauricio copia desde aquí)

### Informe corto para VÍCTOR (I-05, malware)
> Víctor: en una revisión de seguridad del hosting HostGator (cuenta impdali) encontramos
> malware: 405 archivos PHP maliciosos fuera del docroot, sembrados en directorios ocultos
> (~/.ssh, ~/.cpanel, ~/etc, ~/mail, entre otros). Son webshells/backdoors con fecha de
> infección Nov 2022 (anterior a nuestra app) y retoques de metadata en marzo 2026.
> Identificados: blog.php, lufi.php, xxx.php, about.php. NO los ejecutamos ni borramos.
> El crontab está limpio y la única llave SSH autorizada es la legítima del deploy
> (verificada por fingerprint) — no tocar ~/.ssh/authorized_keys ni daligo.pub.
> Esto está fuera del alcance de nuestra app (no la afecta hoy: sus claves ya fueron
> rotadas), así que te lo dejamos a ti: opciones típicas son ticket a HostGator con esta
> evidencia (tienen scanner de malware) o limpieza manual. Sugerencias adicionales: rotar
> la clave del cPanel y revisar cuentas FTP/correo que nadie reconozca. La app en
> /home4/impdali/daligo y sus 2 crons no se tocan. Cualquier duda, avísanos.

### Correo a SOPORTE BSALE (D-005 — prioridad MEDIA, no bloquea despachos-v1)
El texto completo de 12 preguntas está en el hilo del Director (08-07, "Estimado equipo
Bsale:"). Las 3 que importan AHORA por el pivote: sandbox de pruebas (11), bodegas de la
cuenta real (12), y qué invalida el access_token (1). Enviar a ayuda@bsale.app.

### Prompt P-S0-10 para la IA de cPanel (aprobado por el Director el 08-07)
El prompt completo de 6 pasos (investigación de los "44 omitidos/44 errores" de
bsale:sync-clients) está en el hilo del Director — mensaje del Investigador del 08-07,
aprobado con la nota: "donde diga FECHA_ULTIMA_CORRIDA, el operador la reemplaza por la
fecha real que anotó en el paso 1". Copiar tal cual y despachar.
