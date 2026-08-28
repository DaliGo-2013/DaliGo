# PLAN-AVISOS · La tabla de avisos y la Configuración amable

> **Estado: v1 EN PRODUCCIÓN (`f991cd4`, doble llave del dueño 28-08-2026).**
> Tarea directa del dueño al Director, ejecutada por el Director (no un
> forjador). Pendientes: QA del dueño sobre v1 + fases 2 y 3 (abajo).

## 0. El pedido textual (28-08, al Director)

«las notificaciones actualmente le llegan a cualquier usuario o por lo bajo es
super confuso saber que se le esta mostrando a quien. es por eso que necesito
una vista mas amigable con el usuario y fácil de usar en la cual sea mas claro
saber que informacion se esta entregando y a quien lo hace. me imagino una
tabla tipo excel en donde se muestre que notificacion es de la que estamos
hablando y los roles a los cuales les llegara esa notificacion con check box.
tenia la intencion de modificar el apartado de configuracion ya que ahora
maneja un formato super tecnico cuando deberia estar pensado para usuarios no
informaticos y creo que este apartado seria una buena partida para esta tarea»

## 1. Decisiones del dueño (AskUserQuestion, 28-08)

1. **Todos editables**: también los avisos que iban por PERMISO (flota,
   producción, moldes, bodega nueva) pasaron a listas de roles editables.
   Cambio semántico aceptado: un rol custom con el permiso deja de recibir
   salvo que se marque en la tabla.
2. **Tabla primero**: humanizar rótulos y el editor de plantillas quedaron
   como fases 2 y 3.
3. **Dentro de Configuración**: sin ítem de menú nuevo (doctrina de densidad).
4. **Se permite silenciar un aviso** desmarcando todos sus roles — sin mínimo;
   la fila lo declara («Nadie recibe este aviso»).

## 2. Lo entregado (v1, dos lotes en `f991cd4`)

- **Lote A (`35a6d7a`)** — `App\Support\AudienciasNotificacion`: fuente única
  de destinatarios. 25 eventos editables (`DEFAULTS` = histórico, BD virgen
  byte-idéntica; clave `notif_roles_{evento}`, grupo `notif_destinatarios`),
  12 con destinatario fijo (`NO_EDITABLES`, regla en español). Regla
  vacío-deliberado (silencio se respeta) vs vacío-por-descomposición (cae al
  default). Los 12 emisores derivan del registry; filtros de 2º nivel
  (cartera, anti-autoaviso, vendedor del cliente, fallback técnico industrial)
  intactos. Un hito de vehículos silenciado NO se reclama (la novedad espera).
  Candados: `AudienciasNotificacionTest` (8, mutados ×3 con rojo exacto).
- **Lote B (`2015310`)** — pantalla Configuración → «Avisos y destinatarios»
  (`admin/configuracion/avisos`, permiso `manage settings`): matriz evento ×
  rol por familia, grilla 2/3/4/6 según ancho (sin scroll horizontal), ⓘ por
  fila reusando la descripción de su plantilla, filas informativas para los
  fijos, badge «Nadie recibe este aviso». `App\Support\RolesDelSistema` =
  mapa rol→etiqueta español (hueco real; antes `Str::headline`). El index
  técnico OCULTA el grupo y ofrece la entrada; editar una clave por URL
  redirige a la matriz. PUT persiste solo lo cambiado (audit legible).
  Candados: `AvisosNotificacionScreenTest` (10, mutado el del silencio).
- Suite final 2377/17.001 cero rojos; responsive verificado 375/768/1024/1280.

## 3. Fases futuras (aprobadas en concepto, SIN GO)

- **Fase 2 — rótulos humanos de Configuración**: mapa clave→rótulo español en
  vez de `Str::headline(clave)` («Umbral Aprobacion Clp» → «Monto que exige
  aprobación»). Toca el candado `assertSee('Umbral Aprobacion Clp')` de
  `ConfiguracionManagementTest` — reescribirlo en el mismo lote.
- **Fase 3 — editor amable de plantillas**: Asunto/Cuerpo en campos separados
  (hoy JSON crudo con `\n` escapados en textarea) + placeholders visibles.
  OJO: `OneShotPlantillasCandadoTest` ata la forma JSON del seeder — la fase
  debe decidir si cambia la forma del valor o solo la UI de edición.
- **Coordinación PARAMETRICOS**: la perilla de retención del chat (#8 de
  PLAN-MENSAJES fase 2) y el hallazgo #13 de LOG-4 («correo por permisos»)
  tocan este territorio — citar este plan en sus dictados.
