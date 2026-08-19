# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-19 (v72 — QA del dueño del módulo Dashboard ✅: PRIMER MÓDULO DEL PROYECTO SALDADO. GO F0-COMERCIAL: auditoría del apartado Comercial, SOLO DOCS). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ QA del dueño del módulo Dashboard (19-ago) — primer módulo SALDADO de punta a punta

El dueño verificó en celular: Inicio idéntico sin tocar nada, las 4 perillas moviendo
pantalla y rótulos juntos, rechazos de valores inválidos con mensaje, y la sucursal de
prueba apareciendo/desapareciendo de la card en vivo. **El molde del proyecto quedó
probado entero: F0 → veredictos → 3 lotes → QA.** Tu ejecución: cero amoldes, cero
cifras viejas cambiadas, dos contra-evidencias aceptadas. De manual.

Novedad de la casa: el avance ahora se espeja en Trello (tablero del dueño) — lo mueve
el Director, tú sigues igual que siempre: dictado → forja → parte al buzón.

## 🔍 GO — F0-COMERCIAL: auditoría del apartado Comercial (SOLO DOCS, cero código)

Mismo formato del mapa F0-DASH (plan §2, anexo §5.1 como modelo). Barre el módulo
Comercial completo:
- **Clientes**: `ClienteController` + vistas + el modelo y lo que consuma.
- **Catálogo**: `ProductoController` + `ListaPrecioController` + vistas (pestañas
  Productos · Listas de precios) + importar/exportar si existe + categorías.
- Aguas-arriba mínima de lo que esas pantallas calculan (precios, stock mostrado).

Semillas del Director (confírmalas y complétalas):
1. **`paginate(25)`** en `ClienteController:21` y `ProductoController:54` — ¿tamaño de
   página como perilla o convención nivel 3? Propón con criterio (y si hay MÁS
   paginate en el módulo, únelos en un solo hallazgo con sus repeticiones).
2. **`daligo.lista_precios_ventas` ya existe** (nivel 2, GENERAL) — NO es hallazgo
   nuevo, pero revisa si algún rincón del módulo ignora esa clave y elige lista por
   su cuenta (eso SÍ sería hallazgo: duplicado de decisión).
3. La clave `cotizacion_vigencia_dias` existente pertenece a ST
   (`OrdenServicioCotizacion`) — si Comercial tiene cotizaciones propias con plazos,
   son OTRO hallazgo; no los mezcles.

Reglas vigentes del mapa: qué-controla EN PALABRAS DEL NEGOCIO · veredicto propuesto
1/2/3 con la vara de daligo.php · duplicados marcados aunque el veredicto sea 3 ·
hallazgos de OTROS módulos se anotan para su auditoría, no te desparramas.

### Entregable
Anexo **§5.2 Comercial** en `docs/planes/PLAN-PARAMETRICOS.md` + parte al buzón
(resumen por niveles + lo que más te llamó la atención). **Cero código** — los lotes
llegan tras los veredictos del dueño.

### Arranque operativo
Re-fetch de main FRESCO (baseline: **2208 verdes** en `75cce08`; después entraron solo
docs y el fix del workflow de Trello — recuenta igual). Barrido read-only: cero riesgo
de choque.

## Estado
- Max-2 forjando MSG-1 (motor del chat) — toca `Notificacion::EVENTOS` y
  `ConfiguracionSeeder`; tu barrido es read-only así que cero colisión, pero si tu
  FASE B llegara a tocar esos archivos, el Director secuencia.
- Marcos activo. Trello espejando (asunto del Director).

CIERRE: GO F0-COMERCIAL. Segundo módulo del orden del dueño — mismo mapa, misma vara.
