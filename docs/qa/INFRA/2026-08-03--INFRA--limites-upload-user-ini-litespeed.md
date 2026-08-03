# Evidencia INFRA · Límites de upload PHP en contexto web — LiteSpeed honra el `.user.ini`

- **Fecha:** 2026-08-03 · **Origen:** delegación a IA-cPanel (plantilla VERIFICACION-CPANEL,
  redactada por el Director a partir del §5 de PLAN-M13, despachada por Mauricio)
- **Motivo:** el «Hecho cuando» de E6 (M13 Devoluciones) exige límites de upload verificados
  antes de fijar el validador — si el techo real fuera menor que lo validado, un envío con
  varias fotos se perdería en silencio (PHP descarta el body entero, incluido el `_token`).
- **Veredicto:** APROBADO CON OBSERVACIONES · 5/5 pasos OK · cambios: solo el archivo
  temporal de `phpinfo()` (creado y borrado; quedó en la Papelera de cPanel, no borrado
  permanente).

## Resultado que importa (contexto WEB, `https://staging.impdali.cl`, Server API LiteSpeed V8.3)

| Directiva | Local Value (efectivo web) | Master Value | Esperado | ¿Coincide? |
|---|---|---|---|---|
| `upload_max_filesize` | **12M** | 512M | 12M | ✅ |
| `post_max_size` | **30M** | 516M | 30M | ✅ |
| `max_file_uploads` | **20** | 20 | 20 (default, no fijado) | ✅ |
| `memory_limit` | **256M** | 512M | 256M | ✅ |

- `user_ini.filename` = `.user.ini` (mecanismo activo). La ruta literal del archivo no
  aparece en phpinfo (phpinfo no lista los `.user.ini` escaneados); la evidencia es
  indirecta pero concluyente: **los Local Values web sobrescriben a los Master justo con
  los valores del `public/.user.ini` commiteado**.
- Contraste CLI (ea-php83): 512M/516M/512M/20 — el `.user.ini` NO aplica en CLI (SAPI web
  solamente), lo que confirma que la diferencia web↔CLI proviene del archivo.
- PHP del dominio: 8.3 (ea-php83, "Inherited" del default del sistema).

## Consecuencia para M13 (comunicada a Max-1 en el dictado v33, sección cPanel)

El validador se fija con certeza, ya no como asunción conservadora:
- máx **12M por archivo** (la compresión en navegador sigue obligatoria — GD necesita
  ~100 MB para decodificar 12MP);
- el conjunto de un request (fotos + campos + token) debe caber con holgura en **30M**;
- **20 archivos** por request es el techo real del hosting (default no fijado — si algún
  día se quiere fijar, va en `public/.user.ini`, no en config de vhost).

## Pendiente menor (no bloquea)
El archivo `dg-limites-x7q2.php` quedó en la Papelera de cPanel (borrado reversible). La URL
ya devuelve 404 de Laravel. Vaciar la papelera es opcional y decisión del dueño.

## Detalle textual de la delegación
Tabla 5/5 OK; capturas en poder de IA-cPanel (phpinfo con Server API LiteSpeed V8.3 y salida
de Terminal), disponibles si se piden. Respuesta completa archivada en el hilo del dueño con
el Director (03-ago-2026).
