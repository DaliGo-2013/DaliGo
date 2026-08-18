# PLAN-PARAMETRICOS · Cacería de hardcodes — lo que el dueño debería poder cambiar sin programador

> **Estado: VIGENTE (definido por el dueño, 2026-08-18)** — sucesor directo del
> PLAN-MENU-DENSIDAD (cerrado el mismo día, 47→32). Mismo ritmo por directriz raíz:
> «poco a poco, lento pero seguro, decisiones con calma». Módulo por módulo en el orden
> de la sidebar. Autor: Director, sobre el pedido textual del dueño.

## 0. El pedido en una frase

Buscar valores **hardcodeados que deberían ser paramétricos**, en orden por apartado —
primero Dashboard, después Comercial, y así por toda la app — con Max-1 ejecutando.

## 1. Los tres niveles (la casa ya los tiene — reusar, no inventar)

| Nivel | Mecanismo | Cuándo | Ejemplos vivos |
|---|---|---|---|
| **1 · Editable en caliente** | tabla `configuracion` + `Configuracion::get(clave, default)` + UI (`Admin/ConfiguracionController`) | parámetro de NEGOCIO que el dueño quiera mover sin deploy | umbrales de notificaciones, retención de auditoría, devoluciones |
| **2 · Config de despliegue** | `config/*.php` + env | decisión que NO debe moverse en caliente | `daligo.tz_negocio` (moverla desplazaría el día de negocio), `lista_precios_ventas`, `servicio_tecnico.dias_reparacion` por sucursal, `feriados.php` |
| **3 · Se queda hardcodeado** | código + comentario con el porqué | invariantes, doctrina, aritmética que no es parámetro | `aria-current="true"`, grid del tab-nav |

**El veredicto por hallazgo = elegir nivel.** La propuesta la hace el forjador en el
mapa; **la decisión es del dueño**, hallazgo por hallazgo (mismo esquema que los
veredictos del mapa F0 del menú). El criterio de daligo.php es la vara: si moverlo en
caliente puede romper la operación, es nivel 2, no 1.

## 2. Protocolo (heredado del PLAN-MENU-DENSIDAD, cerrado con cero rojos)

Por cada módulo, DOS fases:

**Fase A — Auditoría (SOLO DOCS, un dictado):**
Max-1 barre controllers + vistas + support/models del módulo buscando:
- **Números mágicos**: plazos, ventanas de días, umbrales, límites, tamaños de página,
  montos, porcentajes, capacidades.
- **Strings de negocio fijos**: nombres de empresa/sucursal/lista, correos, teléfonos,
  textos que cambian con el negocio (no labels de UI).
- **Listas que crecen**: sucursales, categorías, horarios, todo array literal que la
  operación pueda ampliar.
- **Duplicados**: el mismo valor repetido en N sitios (peor que hardcodeado: drifteable).

Entregable: **mapa en el anexo §5 de ESTE archivo** — tabla con: valor · dónde vive
(file:line) · qué controla en pantalla (en palabras del negocio, para que el dueño
decida sin leer código) · repeticiones · veredicto propuesto (nivel 1/2/3 con una línea
de porqué) · esfuerzo (S/M/L). **Cero código en fase A.**

**Fase B — Lotes (tras veredictos del dueño):**
- El dueño marca qué se parametriza y a qué nivel; el Director dicta lotes.
- Un lote = un merge = una doble llave; parte al buzón; verificación invariante del
  Director (suite completa local, cifra contrastada, CI verde, ancestría).
- **Regla de oro: parametrizar NO cambia comportamiento** — el valor actual queda como
  default; delta 0 tests salvo los candados nuevos del parámetro (candado mínimo: el
  default rinde idéntico + mover el parámetro mueve la pantalla — mutación de siempre).
- Nivel 1 exige además: la clave aparece en la UI de Configuración con label y ayuda en
  español del negocio, y validación de rango (un 0 o un negativo no puede romper la
  operación).

Nunca dos módulos abiertos a la vez. QA del dueño al cierre de cada módulo.

## 3. Orden de módulos (el de la sidebar, pedido del dueño)

1. **Dashboard** (F0-DASH — dictado v67, EN CURSO)
2. Comercial
3. Operación
4. Logística
5. Facturación
6. Administración
7. Mi producción
8. Mis entregas
9. Aprobaciones
10. Servicio Técnico (el más grande — al final de los operativos, con lo aprendido)
11. Plan del proyecto

Pantallas públicas (QR, visita industrial, devoluciones) se auditan CON el módulo dueño
de su flujo. Los módulos avanzan de a uno: auditoría → veredictos → lotes → QA → siguiente.

## 4. Registro

- Avance por módulo se anota aquí (§5 anexos) y en el bloque `E-PARAM` de RUTA-MAESTRA
  (se crea cuando el Dashboard tenga su primer avance real — así la página /plan lo
  muestra con pasos de verdad, no un mapa vacío).
- Partes al buzón como siempre; veredictos del dueño quedan escritos en el anexo del
  módulo.

## 5. Anexos por módulo (los llena la fase A de cada uno)

### §5.1 Dashboard — mapa F0-DASH (pendiente: dictado v67 en manos de Max-1)
Semillas ya vistas por el Director (el mapa debe confirmarlas y completarlas):
- `DashboardController`: serie de producción de **7 días**; referencia de merma de los
  **7 días anteriores**; cortes de antigüedad de órdenes **0-7 / 8-30 / 30+**.
- `AccesosDashboard` / `DashboardColores`: revisar si hay valores de negocio (no de UI).
