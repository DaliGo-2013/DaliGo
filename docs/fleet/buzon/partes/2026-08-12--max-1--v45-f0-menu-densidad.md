# Parte de Max-1 — 2026-08-12 · Dictado v45: F0 de PLAN-MENU-DENSIDAD HECHO (auditoría + mapa)

> Forjador A, stream 1 · rama `feature/menu-densidad-f0` (commit `80cfc83`, **SOLO DOCS** — un archivo: `docs/planes/PLAN-MENU-DENSIDAD.md` §5) — espera revisión del Director → **el mapa va al dueño para visto bueno apartado por apartado**.

## El número

| | |
|---|---|
| Rótulos HOY (conteo exacto, verificado contra `MenuPrincipal`) | **47** = 11 primer nivel + 35 subítems + 1 cuenta |
| Mapa objetivo (si TODO se aprueba) | **30** — subítems 35 → 18 |
| Cómo | 16 consolidaciones + 1 retiro de boceto · **cero pérdida de pantallas ni permisos** |
| Método | Workflow de 7 lectores (rutas reales, permisos contra el seeder, roles, badges, links cruzados existentes) + veredictos firmados por mí con el lente del dueño |

## Los veredictos, en una mirada

- **Viven solos (14):** los hubs diarios con badge o flujo (Producción, Listado ST, Despachos, Hojas de ruta, Agenda de terreno, Devoluciones, Clientes, Inventario, Vehículos, Simulador, Documentos, Usuarios) + los 1-clic de operario (Mi producción, Mis entregas, Aprobaciones, Ingreso por lote — el conductor NO ve el Listado: esconderlo le quitaría su única entrada) + los transversales sin casa mejor (Dashboard, Sucursales, Plan «con fecha de muerte natural», Configuración de cuenta).
- **Integrables (16):** cada uno con propuesta concreta (pestaña de qué pantalla, bajo qué permiso). Las dos superficies nuevas absorben 7 entre ambas:
  - **«Configuración de producción»** (Máquinas · Tipos · Recetas · Moldes → 1): permiso único `manage production`, frecuencia rara, cadena de datos ya cableada. Incluye sin apego los 2 ítems que yo mismo agregué en M11 sin este filtro.
  - **«Registro del sistema»** (Auditoría · Notificaciones · Historial de aprobaciones → 1): tres visores solo-admin solo-lectura que **la campanita ya agrupa como hub** — la consolidación formaliza lo que la práctica inventó. El historial NO va junto a la bandeja (el QA 15-07 ya mostró que eso confunde).
- **Retiro (1):** «Seguimiento (boceto)» — maqueta sin datos ni usuarios; densidad gratis.
- **El contra-argumento más honesto anotado:** Cargas reales → Simulador (mismo permiso, el simulador la consume y la enlaza 2 veces) tiene en contra el comentario del propio código («se abre en otro momento») — las dos caras van en la tabla para que el dueño decida.

## Priorización (§5.3)

F1 Precios→Catálogo (decidido, sin re-litigar) → 4 baratas casi gratis (retiro del boceto, Estado→Documentos, QR→Listado, Servicios de terreno→Agenda) → **la grande** (Configuración de producción, −3) → Registro del sistema (−2) → el resto de a una. Traslados→Listado al final y opcional (flujo activo, la de menor urgencia).

## Candados (§5.4 — insumo del lote ejecutor, test por test)

El hallazgo operativo: **`VolverTest::test_las_ex_huerfanas_estan_en_el_menu` es un candado DURO** que se pone rojo A PROPÓSITO si Máquinas/Tipos/Kardex/Conductores salen del menú — 3 de mis consolidaciones lo tocan y exigen editar la lista conscientemente + devolver el `<x-volver>`. Además: 4 cards del Inicio se reapuntan (Precios, Roles, Notificaciones, Historial), los badges viajan con su KEY (el gating vive en el resolver), y el arbitraje de módulos compartidos es POR ORDEN de declaración. **Hueco detectado y anotado:** mover una ruta a pestaña sin sumarla al `activo` del anfitrión deja la página sin resaltado EN SILENCIO — ningún test lo caza hoy; mini-candado sugerido para F1.

## Verificación

`git diff --stat` = 1 archivo bajo docs/ (docs-only de verdad) · los 41 ítems tienen veredicto con propuesta o porqué · todos los permisos citados existen en el seeder · el conteo 47 cuadra contra `MenuPrincipal::MODULOS`+`CUENTA` · cero código, cero MenuPrincipal, cero RUTA-MAESTRA (PlanProyectoTest no aplica).

## Para el radar del Director

- Discrepancia fáctica encontrada de pasada (no es de este lote): `vehiculos/_form.blade.php:209` promete que el vehículo con caja cargada «aparece en el Simulador» — el simulador lee SOLO `camiones_simulacion` (decisión del dueño 05-08). Texto de vista desactualizado.
- `aprobaciones.mias` (auth sin permiso) cae dentro del patrón `activo` del ítem Aprobaciones (gateado por `aprobar solicitudes`) — sin efecto visible hoy, anotado por higiene.
- El módulo Facturación quedaría de 1 ítem tras Estado→Documentos: mantener el acordeón (por el `activo_extra` del documento de ST) o pasar a link directo es decisión menor del lote F2 correspondiente.

## Fuera de alcance (declarado)

Ni una línea de código · MenuPrincipal intacto · el piloto F1 NO arrancado (espera el visto bueno del mapa) · las decisiones (son del dueño, apartado por apartado).
