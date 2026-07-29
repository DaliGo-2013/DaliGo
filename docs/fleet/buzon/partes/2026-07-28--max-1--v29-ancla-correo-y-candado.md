# Parte — Max-1: dictado v29 COMPLETO — `fix/notif-url-ancla` @ `a24565b` espera doble llave · 2026-07-28 (3º del día)

> De Max-1 (Forjador A) al Director. Las 2 tareas eran las 2 deudas que yo mismo dejé
> anotadas en mis partes; cerradas ambas, con mutación verificada en cada una.

## Tarea 1 — el botón del correo ya aterriza en la tarjeta

La campanita llevaba a `#aprobacion-{id}` pero el correo iba a la lista pelada: con la
bandeja agrupada por categorías, el aprobador tenía que buscar su solicitud a mano.

- **Punto ÚNICO**: `Notificacion::anclaAprobacion(?int)`. Lo usan `urlDestino()` (la fila de
  la campanita) y el `payload['url']` que arma `Aprobaciones`. **Duplicar ese cálculo fue
  justo lo que desalineó las dos superficies**, así que la corrección incluye que no pueda
  volver a pasar — no solo empareja los strings de hoy.
- `notificarRol()` → `aprobaciones.index` + ancla · `notificarSolicitante()` →
  `aprobaciones.mias` + ancla. El guard del mailable (`str_starts_with($url, 'http')`) no se
  tocó: el ancla va al final, la URL sigue empezando con http.
- **Los 3 `assertSame` de `payload['url']` actualizados por marcador**, no silenciados
  (`AprobacionAccionableTest` ×2, `NotificacionEspecificaTest` ×1) — como advertía el dictado.
- **Test E2E del correo** (lo que pediste: «el ancla tiene que sobrevivir hasta el
  `<a href>`»): toma la fila **CANAL_MAIL** del despacho real, renderiza el mailable como lo
  hace el job y exige `href="…#aprobacion-{id}"`, con negativo del href a la lista sin ancla.

**Mutación A** (quitar el ancla del payload): **3 rojos**, incluido el E2E del correo.

## Tarea 2 — candado del patrón de entrega (tu deuda anotada)

El patrón one-shot es correcto pero frágil: editar los textos del seeder sin actualizar el
par viejo/nuevo lo vuelve un **no-op silencioso**. `OneShotPlantillasCandadoTest` ata las dos
puntas para **las DOS one-shot vivas**:

- **Aprobaciones** (`2026_07_22_100000`): reemplaza el JSON completo → compara el arreglo
  entero; lee su `const PLANTILLAS` por `ReflectionObject`.
- **Internas de Marcos** (`2026_07_22_180000`): cambia **solo el `cuerpo`** y conserva el
  asunto → compara esa clave; lee su método privado `plantillas()` por `ReflectionMethod`.
- **Extra que agregué**: exige que el par viejo/nuevo sea un **cambio real** — si son
  idénticos, la migración existe pero no entrega nada y su presencia engaña.
- El mensaje de fallo explica el no-op completo, para quien lo lea en frío dentro de 3 meses.

**Mutación B**: cambié **un solo carácter** (un punto final) en una plantilla del seeder → el
candado falló nombrando la clave exacta con su explicación. La otra one-shot siguió verde
(discrimina, no dispara en bloque).

## Decisiones y disciplina

- **Una sola rama** para ambas tareas (el dictado dejó la llamada a mi criterio): la T2 es
  solo-tests, cero riesgo de conflicto, y simplifica la doble llave.
- **Commité ANTES de mutar** y verifiqué con grep que el fix sobrevivió al `git checkout --`
  — la lección de la bitácora de ayer, aplicada en las dos mutaciones.
- Sin Blade → **sin build**. El candado `MarcoHorizontalTest` que avisaste no aplica: cero
  tarjetas tocadas.

## Verificación

- Batería focalizada **28/28** (`OneShotPlantillasCandadoTest` + `AprobacionAccionableTest`
  + `NotificacionEspecificaTest`).
- Las 2 mutaciones, rojas donde debían y verdes donde no correspondía.
- **Suite completa: corriendo al momento de subir este parte** (baseline main ~1138). La
  reporto en cuanto termine; si algo saliera rojo, lo digo antes de pedir la llave.

## Lo que queda de NOTIF-1

**Nada.** Con esto el lote cierra completo: campanita con cuerpo y navegación, ancla puntual
en las dos superficies, payload con objeto y cambio, fallback nunca-mudo, plantillas ricas
entregadas por one-shot **y ahora blindadas por candado**. El único pendiente del área es el
sublote C (cotización/terreno), que es territorio de Marcos.

## Consumo

Talla **S/M** (2 tareas cortas + 2 mutaciones + batería focalizada + suite). `/usage`:
Mauricio, cuando puedas.
