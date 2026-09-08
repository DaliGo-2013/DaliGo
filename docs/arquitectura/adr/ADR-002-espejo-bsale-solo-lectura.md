# ADR-002 · Espejo de Bsale de solo lectura por polling `*/15`

- **Estado:** vigente (webhooks quedan condicionados a D-005)
- **Fecha:** junio 2026 (espejo) · 2026-07-07 (grilla `*/15`, I-01)

## Contexto
Bsale es el sistema de registro de catálogo, precios, clientes, stock y documentos, y sigue siendo el emisor ante el SII. DaliGo necesita esos datos para cotizar, asignar producción y validar retiros, pero **no puede haber dos dueños del stock**. Bsale no ofrece webhooks confirmados (D-005 abierta) y el hosting reescribe cualquier cron menor a 15 minutos.

## Decisión
**DaliGo espeja Bsale por polling y nunca escribe el espejo.**
- Cinco syncs idempotentes en la grilla del cron: catálogo `:00`, clientes `:15`, precios `:30`, documentos `:30`, stock `:45` (`routes/console.php`).
- Las tablas espejo (`productos`, `precios`, `listas_precios`, `stocks`, `bodegas`, `clientes`, `documentos_venta`) **no tienen escritura desde la app**; el formulario de producto no acepta `bsale_variant_id`/`bsale_product_id`.
- Guardas: el borrado «espejo fiel» se **salta** si el conjunto visto quedó vacío habiendo recorrido elementos (`whereNotIn([])` compila a `1=1`); `BsaleClient::get()` reintenta 2 veces, `post()` no.
- Lista de precios de venta oficial: `GENERAL`; sin precio ahí → `null`, no otra lista.

## Alternativas consideradas
- **Webhooks de Bsale** — no confirmados; se reevalúa cuando D-005 se resuelva.
- **Escritura bidireccional** — rechazada: dos verdades del stock es el problema que DaliGo viene a eliminar.
- **Cron por minuto** — HostGator lo reescribe (I-01, tres veces observado).

## Consecuencias
Latencia del espejo ≤ 1 h por entidad y de la cola ≤ 15 min, aceptadas. La **frescura del espejo no se vigila**: un token vencido congela los datos sin síntoma (I-03) — es el riesgo RG-002 y su mitigación es un aviso de frescura, no un reintento. Toda tarea nueva en `routes/console.php` cae en `:00/:15/:30/:45` o no corre jamás.

## Evidencia
`CLAUDE.md` bitácora [2026-07-07], [2026-07-02], [2026-06-12], [2026-07-28] · `docs/BSALE_API.md` · `ScheduleBsaleTest`, `BsalePreciosSyncTest`, `PrecioListaOficialTest`.
