# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-07-14 (v6 — PLAN-M16-V0 validado, GO a construir). Manda sobre lo anterior.

MODELO: Fable 5 disponible hasta el 19-07 (decisión del dueño); si no, Opus 4.8 · high.

✅ PLAN-M16-V0 VALIDADO por el Director (`dd20ee8`, sello contra c86e341): x-stat-card existe ✓,
dashboard semilla intacta ✓, scopePorConfirmar (OrdenServicio:358) ✓, filtro notif fallida ✓,
cards/rutas/matriz verificadas por tus 3 refutadores. Alcance limpio (5 cards nuevas + semilla
de Marcos intacta, cero permisos/migraciones/componentes nuevos, stock omitido con evidencia).
Buen método de sellado.

✅ Tu verificación post-deploy de M14: aceptada — Actions 4/4, migración+seeders exit 0, rutas
M14 vivas (302), bundle correcto sirviéndose. Buen catch del guion QA (el ajuste lo pide un
NO-admin, si no se auto-aprueba y no se ve el flujo). Ese guion se lo paso a Mauricio.

## GO: construir M16-v0 (3 pasos del plan)
1. **P-M16-01** — DashboardController con los conteos de las 5 cards nuevas (queries de LECTURA
   sin N+1; `whereDate` para "hoy") + 9 tests: cada card cuenta bien Y un rol sin el `@can`
   correspondiente NO la ve.
2. **P-M16-02** — vista: `/dashboard` reagrupado por módulo, cards semilla de Marcos INTACTAS
   (mismos queries/labels/permisos) + las 5 nuevas con `<x-stat-card>`, cada una clickeable a
   su módulo. Responsive 375/768/1024. npm run build + grep bundle (npm install por qrcode).
3. **P-M16-03** — cierre: gate /pre-merge + parte con el plan de merge (doble llave como M14).
Suite verde por commit. Rama `feature/m16-v0-dashboard`. RUTA al día en el mismo push.

RECORDATORIO: E2·M14 cierra formalmente cuando Mauricio corra el QA de celular (su guion está
en tu parte). Cuando Mauricio te confirme que salió OK, marca P-M14-07 [x] + sello 01→07 en
RUTA-MAESTRA (docs, un push) → E2·M14 CERRADA. No bloquea M16.

CIERRE por paso: parte a docs/fleet/buzon/partes/ + push.
