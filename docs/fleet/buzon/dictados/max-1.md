# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-20 (v82 — veredictos del dueño al mapa
> §5.4: los 4 nivel 1 + higiene ENTERA aprobados, 3 funciones nuevas a la cola.
> GO LOG-1: los textos-que-mienten — el fix urgente primero). Manda sobre lo
> anterior.

MODELO: Opus 4.8 · high.

## ✅ Veredictos del dueño al mapa §5.4 (20-ago) — cuarto mapa que sale entero

Aprobados los 4 nivel 1 (#1 franja flota, #2 métodos de cobro, #3 relación del
receptor, #4 tarjetas del monitor), la higiene COMPLETA («sí a todo») y los
nivel 3 confirmados con tus porqués. Al pie del anexo. **Funciones nuevas que
el dueño ELIGIÓ para la cola post-fase-B** (lotes de función, se dictan
después): buscador de folios · caducidad del QR · topes de monto en R11. La
pantalla CRUD de camiones NO fue seleccionada esta ronda. Tu método de censo
por fleet con verificación sed quedó aceptado (el mapa salió entero — el
resultado avala).

Fase B de Logística = 4 lotes: **LOG-1** (este dictado) → **LOG-2** (franja
flota) → **LOG-3** (listas del conductor + TV) → **LOG-4** (higiene restante).

## 🔨 GO — Lote LOG-1: los textos-que-mienten (S) — el fix urgente PRIMERO

La fila #9 completa, doctrina DASH-2 (la aritmética exacta, no números a mano):

1. **EL FIX ACTIVO** — `bodegas/traslados/show.blade.php:57` promete «el espejo
   se refresca cada 15 minutos» y el sync corre `hourlyAt(45)`
   (`routes/console.php:38`): el texto pasa a decir la VERDAD derivándola de
   donde el idioma lo permita (si el schedule no es derivable limpio, texto
   correcto + comentario apuntando al schedule con el porqué — decláralo).
   OJO: NO cambies el schedule (la grilla */15 de HostGator es doctrina I-01;
   el texto miente, el cron está bien).
2. **La familia entera deriva**: «folio 1000» (`hojas-ruta/index.blade.php:67`)
   ← `FOLIO_PISO`; «15 MB» ×2 (`VehiculoController.php:390`) ← el `max:15360`
   real; «llave N de 3» ×3 (`HojaRutaController.php:124…`) ← el count del
   flujo; medidas del pallet en prosa (`carga/index.blade.php:1331`) ← las
   constantes del motor. Los «30 días» de la flota NO van aquí — derivan en
   LOG-2 con su perilla (doctrina: el rótulo deriva en el lote de su clave).
3. **Candados**: cada rótulo derivado con su assert de cifra (mover la fuente
   mueve el texto — con `config()`/constante en runtime donde aplique) + el
   texto del traslado ya no promete 15 minutos (assert negativo del literal
   viejo, forma contigua si hay gemelos) + mutación tuya declarada.
4. **Regla de oro**: cero cambio de conducta funcional — solo textos pasando a
   decir la verdad y a derivar. Delta chico declarado.

### Verificación (invariante)
Rama `feature/param-log-1-textos` desde main FRESCO (baseline: 2303/16.048 en
`ab0a8d1` + PR #20 + docs de Marcos encima — recuenta tú; Marcos activo con PR
y pushes docs). Suite COMPLETA antes. Batería: Despachos*/HojaRuta*/Vehiculo*/
Traslado*/Carga* + lo que toque. Parte al buzón; espera doble llave. NO
arranques LOG-2.

## 📡 Radar LOG-2/3/4 (NO arranques)
- **LOG-2**: `vehiculos_dias_aviso` default 30 [RANGOS 7-90 o lo que el código
  pida], rótulos ×3 derivando; la `DIAS_VENTANA_VENCIDO=30` del comando es OTRO
  concepto — clave hermana o nivel 3: TU propuesta con el código a la vista,
  contra-evidencia declarada (doctrina OPE-1).
- **LOG-3**: `despachos_metodos_cobro` + `despachos_relaciones_receptor`
  (LISTAS_SIMPLES, molde OPE-2 — sin par-subconjunto aquí) +
  `despachos_tarjetas_monitor` default 12 [RANGOS chico].
- **LOG-4**: 188 ×6 → constante con porqué (191−«…») · POR_PAGINA ×2 ·
  `Despacho::yaSalioDeBodega()` (o el nombre que el idioma mande) ×4 · correo
  del rechazo por PERMISOS (lección 14-08) · topes cubicar UI←servidor · 90 %
  ×2 unificado.
- **Cola post-fase-B (funciones, dictados aparte)**: buscador de folios ·
  caducidad QR · topes de monto R11.

## Estado
Max-2: F0-MENSAJES-2 forjando (catálogo fase 2 del chat — territorio
disjunto). Marcos: PR #20 + docs ST. Trello espejando (Logística En Curso).

CIERRE: GO LOG-1. Primero que la app deje de mentir; después, las perillas.
