# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-19 (v73 — veredictos del dueño al mapa F0-COMERCIAL: los 2 nivel 1 APROBADOS + mini-lote de higiene APROBADO + los 7 nivel 3 confirmados. GO COM-1: las dos listas del negocio editables). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ Veredictos del dueño al mapa F0-COMERCIAL (19-ago) — otro mapa que salió entero

Aprobados #1 (segmentos de cliente) y #2 (categorías internas sugeridas) como nivel 1;
el mini-lote de higiene que ofreciste, APROBADO como COM-2; los 7 nivel 3 confirmados
con tus porqués. Quedaron escritos al pie del anexo §5.2. Tu resolución de las semillas
(el paginate ×3 con el criterio de UNA-clave-global-o-ninguna, y el barrido limpio de
lista_precios_ventas) — fina.

Fase B de Comercial = 2 lotes: **COM-1** (este dictado) → **COM-2** (higiene, v74).

## 🔨 GO — Lote COM-1: los segmentos de cliente y las categorías sugeridas se editan sin programador

### El mecanismo (verificado por el Director: la casa YA soporta listas)
`Configuracion::TIPO_JSON` existe (`Configuracion.php:30`, decode a array en `:99`).
Las dos listas van como **claves JSON de configuracion** — NO se inventa CRUD nuevo:
- `clientes_segmentos` — default `["mayorista","retail","recurrente"]` (los de hoy).
- `catalogo_categorias_sugeridas` — default `["Repuestos industriales", …]` (las de hoy
  en `PRESETS_CATEGORIA_INTERNA`).
Si al abrir el código la UI de Configuración no edita bien claves JSON de lista (mírala
con ojos de QA: ¿un textarea JSON crudo es digno del dueño?), tu criterio decide entre
mejorar ESA edición para listas simples (p. ej. una-por-línea → JSON) o proponer otra
forma con contra-evidencia ANTES de forjar distinto. La vara: el dueño edita solo, sin
sintaxis de programador.

### La forma
1. **`Cliente::SEGMENTOS` deriva de la clave** (constante pasa a default del fallback):
   filtro, validación y formularios ya leen la fuente única (`:78`, `:120`, `:151` del
   mapa) — el cambio es el origen del dato, no los consumidores.
2. **La regla de seguridad del mapa (dictada)**: AGREGAR segmento = libre. QUITAR un
   segmento que tenga clientes asignados → la UI de Configuración lo RECHAZA con
   mensaje que nombre cuántos clientes lo usan («No puedes quitar “retail”: 12 clientes
   lo tienen asignado»). Mecanismo de validación por clave: propón dónde vive (primo de
   RANGOS/PARES_ORDENADOS — la casa ya valida por-clave; una validación por-clave-JSON
   es el tercer hermano).
3. **`PRESETS_CATEGORIA_INTERNA` deriva de su clave**; el placeholder «Ej. Repuestos
   industriales» (`productos/index:123`) DERIVA del primer elemento de la lista — el
   duplicado del mapa muere aquí.
4. **Normalización**: trim, sin vacíos, sin duplicados (case-insensitive), tope sano de
   elementos (propón) — una lista rota por fuera de la UI no puede tumbar el selector
   (clamp de la casa, cinturón y tirantes).
5. **Seeder**: las 2 claves con ayuda en español del negocio (una explica la regla de
   quitar-segmento). Grupo `comercial`.
6. **Candados (molde DASH adaptado a listas)**:
   - Default idéntico: BD virgen → los 3 segmentos y las categorías de hoy, byte a byte.
   - Agregar «horeca» a la clave → aparece en selector, filtro y validación del cliente
     SIN tocar código (el candado estrella del lote).
   - Quitar con clientes adentro → rechazado con el mensaje y la cifra.
   - Placeholder deriva (cambiar el primer elemento → el «Ej. …» cambia).
   - Normalización: lista con duplicados/vacíos por fuera de la UI → selector sano.
   - **Mutación**: romper la derivación (volver a la constante) → rojo exacto el
     candado de agregar-sin-código → restaurar → verde.
7. **Regla de oro**: cero tests existentes con cifra cambiada; con BD virgen todo
   idéntico a hoy.

### Verificación (invariante)
Rama `feature/param-com-1-listas` desde main FRESCO. Suite COMPLETA de main fresco
ANTES (baseline Director: **2208 verdes** en `75cce08` + solo docs después — recuenta).
Batería dirigida: Cliente* + Producto* + ListaPrecio* + ConfiguracionManagement +
ConfiguracionSeedLongitud. Parte al buzón; espera doble llave. NO arranques COM-2.

## 📡 Radar COM-2 (higiene, llega como v74)
Los 4 duplicados nivel 3 a constante única (25/pág ×3, 50 errores ×3, chunk 500 ×2,
topes de peso/medidas ×2). Cero cambio de conducta — delta 0 tests exacto, sin candados
nuevos salvo que un duplicado unificado ya tenga test que lo fije.

## ⚠️ Coordinación
Max-2 (MSG-1) toca `ConfiguracionSeeder` — TU lote también lo toca (2 claves nuevas).
El Director secuencia los merges; forja tranquilo, el que llegue segundo se re-mergea
sobre fresco con la suite entera (protocolo I-08 de siempre).

## Estado
Marcos activo. Trello espejando (asunto del Director). Baseline 2208 en `75cce08`.

CIERRE: GO COM-1. Las dos listas del negocio a manos del dueño. Fierro.
