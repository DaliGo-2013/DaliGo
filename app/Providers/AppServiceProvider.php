<?php

namespace App\Providers;

use App\Services\Bsale\BsaleEmisor;
use App\Services\Dte\EmisorDte;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Puerto de emisión de DTE → implementación concreta (M05). El resto de
        // la app pide EmisorDte y nunca BsaleEmisor: cambiar de emisor es
        // cambiar esta línea. El match es explícito para que un valor
        // desconocido en config reviente al arrancar y no al emitir.
        $this->app->bind(EmisorDte::class, fn () => match (config('dte.emisor')) {
            'bsale' => $this->app->make(BsaleEmisor::class),
            default => throw new InvalidArgumentException(
                'Emisor de DTE desconocido en config dte.emisor: '.var_export(config('dte.emisor'), true)
            ),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // P-TZ-02 (capa render): pasa un instante UTC del storage a hora
        // chilena SOLO para mostrarlo. Únicamente sobre timestamps con hora
        // (created_at, enviada_at, resuelta_at); JAMÁS sobre casts `date`
        // puros — su medianoche UTC retrocedería al día anterior. Los
        // diffForHumans no lo necesitan (un delta no depende del tz de render).
        Carbon::macro('enChile', function () {
            return $this->copy()->tz(config('daligo.tz_negocio'));
        });

        // MySQL 5.7 + utf8mb4: limita la longitud por defecto de las columnas
        // string (191*4 = 764 < 767 bytes) para no exceder el limite de
        // longitud de indice de InnoDB en MySQL 5.7.
        Schema::defaultStringLength(191);

        // En produccion (HTTPS detras de LiteSpeed) forzar https en las URLs
        // generadas, para que formularios y enlaces siempre apunten a https.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // El contador de Servicio Técnico de la barra vive ahora en
        // App\Support\MenuPrincipal::badges() (fuente única del menú V4):
        // los componentes x-layout.sidebar / x-layout.topbar traen sus datos,
        // sin View::composer atado a un nombre de vista.
    }
}
