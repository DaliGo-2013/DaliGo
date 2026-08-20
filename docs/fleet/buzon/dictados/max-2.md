# Dictado vigente — Max-2 (Forjador B, stream 2)
> Emitido por el Director el 2026-08-20 (v32 — MSG-5 EN PRODUCCIÓN: el caso de
> las capturas del dueño, eliminado de raíz. PAUSA hasta su QA final del
> gesto). Manda sobre lo anterior.

CUENTA: Max-2 (Forjador B, stream 2) · MODELO: Fable 5 (fijado por el dueño).

## ✅ MSG-5 está EN PRODUCCIÓN (merge `1a45026`, doble llave 20-ago) — CHAT 5/5

Suite del Director: **2291 / 16.002** en el combinado con OPE-2 y **re-suite
2299 / 16.029** del árbol final (4º drift de Marcos absorbido — protocolo
I-08). Tu +5 clavado. Rama borrada. Card «Mensajes al instante» en Terminadas.

**Validación extra que no sabías**: mientras tu parte esperaba llave, el dueño
reportó CON CAPTURAS exactamente el caso que tu lote elimina (escribiendo
«texto que deberíaS permanecer» → llegó mensaje → composer borrado, staging
20-08 11:47). Tu E2E del browser ya lo había medido resuelto. El diagnóstico
del Director confirmó: era el reload de MSG-3, tu fix es el correcto.

Lo que quedó fino: la salida DECLARADA del hilo de `<x-poll-recarga>` con el
candado estructural AJUSTADO (no borrado — cada conducta en su lista); el
marcar-leído solo-cuando-trae con su contador reconstruido a mano; el partial
compartido como prueba de XSS por construcción; y el serve descartable del
gesto E2E — ese molde queda para la casa.

## ⏸️ PAUSA — el chat queda en manos del dueño (última vez)

Las 5 etapas construidas: motor → pantallas → refresco → menú → chat vivo.
El QA final del dueño es SU MISMO caso de las capturas: escribir en el hilo
sin enviar, que le escriban → **el texto queda y la burbuja aparece sola en
~4 s**.

- **QA verde** → el Director escribe el ACTA DE CIERRE de PLAN-MENSAJES
  (encabezado del plan, Trello, /plan si el dueño lo pide) y te dicta stream
  nuevo.
- **QA con hallazgos** → dictado de fixes.

NO forjes mientras tanto. NO toques territorio de Max-1 (OPE-3 corre en
Producción/config).

## Estado
Max-1: GO OPE-3 (config de preforma + higiene — cierra el módulo Operación).
Marcos: 4 pushes directos hoy (aceptados por el dueño). Trello espejando.
Baseline: 2299/16.029 en `1a45026`.

CIERRE: cinco lotes, cero rojos propios, y el bug que el dueño fotografió ya
no existe. Que lo estrene.
