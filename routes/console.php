<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// --- Sincronización automática con Bsale (solo lectura) -------------------
// GRILLA */15 OBLIGATORIA (I-01, 2026-07-07): HostGator reescribe los crons
// con frecuencia <15 min (3 reescrituras observadas, todas dejaron intervalos
// >=15 min), así que el cron de cPanel es `*/15 * * * * ... schedule:run`
// (dispara :00/:15/:30/:45) y TODA tarea agendada aquí debe caer EXACTO en
// esos minutos (hourly / hourlyAt(15|30|45)) o no correrá jamás.
// Escalonadas: el catálogo va primero porque los precios matchean por
// bsale_variant_id contra `productos`. Frecuencia horaria (tunable a dailyAt).
// withoutOverlapping(15): evita pila si una corrida se atrasa (lock 15 min).
Schedule::command('bsale:sync-catalog')
    ->hourly()
    ->withoutOverlapping(15)
    ->appendOutputTo(storage_path('logs/bsale-sync.log'));

Schedule::command('bsale:sync-clients')
    ->hourlyAt(15)
    ->withoutOverlapping(15)
    ->appendOutputTo(storage_path('logs/bsale-sync.log'));

Schedule::command('bsale:sync-prices')
    ->hourlyAt(30)
    ->withoutOverlapping(15)
    ->appendOutputTo(storage_path('logs/bsale-sync.log'));

// Stock va último: matchea producto (bsale_variant_id) contra `productos` y
// bodega contra las offices, así que corre tras el catálogo de la misma hora.
Schedule::command('bsale:sync-stock')
    ->hourlyAt(45)
    ->withoutOverlapping(15)
    ->appendOutputTo(storage_path('logs/bsale-sync.log'));

// --- M15 · Reintentos de notificaciones fallidas -------------------------
// Cada 5 min re-encola las fallidas cuyo backoff ya venció. El comando reclama
// por `programada_para <= now()`, no por cadencia: si el scheduler estuviera
// degradado (incidencia I-01), igual procesa todo lo vencido (más latencia).
Schedule::command('notificaciones:reintentar')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->appendOutputTo(storage_path('logs/notificaciones.log'));
