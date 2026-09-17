# DaliGo — Expediente de ingeniería de software

> **Qué es esto.** El conjunto de documentos que un proyecto de software lleva **por etapa** —requisitos, análisis, diseño, construcción, pruebas, operación y gestión— aplicado a DaliGo. Casi todo el contenido **ya existía** repartido en la biblia, la ruta maestra, el handoff, la bitácora y los tests; este expediente lo **nombra, lo ordena y le pone la forma estándar** (historias de usuario, casos de uso, documento de arquitectura, ADRs), para que sirva a tres lectores: quien entra al proyecto, quien lo audita (jefe de proyecto) y quien lo muestra como portafolio.
>
> **Regla de mantención:** cada documento de acá dice de qué fuente se deriva. Cuando la fuente cambia, el documento cambia **en el mismo push** (misma regla que RUTA-MAESTRA). Un documento que no se puede mantener no entra al expediente.

## Mapa: etapa → documento → estado

| Etapa | Qué contiene | Documento | Estado |
|---|---|---|---|
| **1. Levantamiento y requisitos** | Procesos actuales de las 3 sucursales (29), problemas y oportunidades | `PROYECTO_DALIGO.md` §8 | ✅ existía |
| | Reglas de negocio estructurales (las 18 de Luis) y las 9 reglas contables del DTE | biblia §3, §10 | ✅ existía |
| | **Historias de usuario** por rol con criterios de aceptación | [`HISTORIAS-DE-USUARIO.md`](HISTORIAS-DE-USUARIO.md) | ✅ **nuevo (v1)** · 58 HU |
| | **Casos de uso**: diagrama, índice y 7 detallados | [`CASOS-DE-USO.md`](CASOS-DE-USO.md) | ✅ **nuevo (v1)** |
| | Requisitos **no funcionales** con número y forma de verificación | `../arquitectura/ARQUITECTURA.md` §10 | ◐ propuesta — números por acordar con el dueño |
| **2. Análisis** | Glosario del dominio (botellón, tanda, kardex, folio…) | `ARQUITECTURA.md` §12 | ✅ **nuevo** |
| | Máquinas de estado (orden de servicio, reporte, agenda, hoja de ruta, despacho, aprobación) | — | ⚪ sesión 2: derivadas de las constantes `ESTADOS` |
| | Modelo de datos (ERD por módulo, 71 tablas) | — | ⚪ sesión 2: **generado** desde las migraciones |
| | Matriz de trazabilidad proceso → paso → pantalla → test | — | ⚪ sesión 3 |
| **3. Diseño y arquitectura** | Documento de arquitectura arc42: objetivos, restricciones, contexto, bloques, ejecución, despliegue, transversales | [`../arquitectura/ARQUITECTURA.md`](../arquitectura/ARQUITECTURA.md) | ✅ **nuevo (v1)** |
| | Registros de decisiones (ADR) | [`../arquitectura/adr/`](../arquitectura/adr/README.md) | ✅ **nuevo** · 10 ADR |
| | Decisiones de negocio y producto | `../DECISIONES.md` | ✅ existía |
| **4. Construcción** | Convenciones, reglas de diseño, catálogo de componentes, bitácora de errores | `CLAUDE.md`, `HANDOFF.md` §6 | ✅ existía |
| | Recetario de prompts y protocolo de delegación (cómo se trabaja con IA) | `../delegacion/` | ✅ existía |
| **5. Pruebas** | Suite (2.351 tests, PHPUnit), doctrina de mutación, candados estructurales | `tests/`, `CLAUDE.md` | ✅ existía |
| | Gate adversarial pre-merge (R-31) | `../delegacion/RECETARIO-PROMPTS.md` §4 | ✅ existía |
| | QA del dueño por módulo (actas) | `../qa/` | ✅ existía |
| | Plan de pruebas no funcionales (carga, seguridad) | — | ⚪ tras la migración |
| **6. Despliegue y operación** | CI/CD, cron, entornos, restricciones del hosting | `.github/workflows/`, `HANDOFF.md` §4–5, `ARQUITECTURA.md` §7 | ✅ existía + dibujado |
| | Incidentes de infraestructura (I-0x) | `../qa/INFRA/` | ✅ existía |
| | Respaldo con restauración probada | — | ⚪ pendiente (o se verifica el de cPanel) |
| **7. Gestión** | Ruta maestra (fases, pasos, tracker), protocolo de sesión, bitácora de sesiones | `../RUTA-MAESTRA.md`, `../PROTOCOLO-SESION.md`, `../BITACORA-SESIONES.md` | ✅ existía |
| | Registro de riesgos | — | ◐ propuestos 4 (RG-001…004), archivo por crear |
| | Informe semanal abreviado para gerencia | — | ⚪ por crear con el punto 7 del cierre |
| | Checklist de aceptación previa por feature | — | ⚪ entra en la ficha R-10 |

## Cómo se derivan (y por qué no se dibujan a mano)

| Documento | Fuente en el código | Se regenera cuando… |
|---|---|---|
| Roles y actores (HU, CU) | `database/seeders/RolesAndPermissionsSeeder.php` | se agrega un permiso o un rol |
| Pantallas por rol | `app/Support/MenuPrincipal.php` | cambia el menú |
| Estados de los casos de uso | `const ESTADOS` de cada modelo | cambia una máquina de estado |
| Módulos y bloques (§5) | carpetas de `Controllers/Admin`, `Services/`, `Models/` | entra un módulo |
| Interfaces externas (§3) | `routes/console.php`, `Services/Bsale/*`, grupo público de `routes/web.php` | cambia una integración |
| ERD (sesión 2) | `database/migrations/` | corre una migración nueva |

Un diagrama de 71 tablas dibujado a mano está mal a los dos meses. Uno generado, no. Por eso los de contexto, despliegue y secuencia —que cambian una vez al año— se dibujan, y el resto se deriva.

## Para el portafolio: qué mostrar y en qué orden

1. **`ARQUITECTURA.md` §3 y §7** — contexto y despliegue: en dos figuras se entiende qué es, con quién habla y dónde corre.
2. **`adr/`** — diez decisiones con contexto, alternativas y consecuencias: demuestra criterio, no solo código.
3. **`CASOS-DE-USO.md`** — CU-03, CU-07 y CU-19: concurrencia, terceros y dinero, con sus candados.
4. **`HISTORIAS-DE-USUARIO.md`** — 58 historias con criterio verificable y su test: requisitos que se pueden probar.
5. **`CLAUDE.md` (bitácora)** — el registro de errores y soluciones: la parte que ningún portafolio suele tener y la que más dice de cómo se trabaja.

## Próximas sesiones

| # | Entrega | Cómo |
|---|---|---|
| 2 | ERD por módulo (6 figuras) + 6 máquinas de estado | script que lee `Schema::create`/`foreignId` → `erDiagram`; estados desde `ESTADOS` → `stateDiagram-v2` |
| 3 | Matriz de trazabilidad | 29 procesos × pasos × rutas × tests; las filas vacías son la agenda |
| 4 | NFR acordados + candado del peso del CSS + línea en la ficha R-31 | una tarde con el dueño para los números |
| 5 | `RIESGOS.md` (4 fichas) + `INFORME-SEMANAL.md` + punto 7 del cierre | media hora; después 10 min por viernes |
