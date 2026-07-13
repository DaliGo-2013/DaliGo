<?php

namespace App\Providers;

use App\Models\OrdenServicio;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // MySQL 5.7 + utf8mb4: limita la longitud por defecto de las columnas
        // string (191*4 = 764 < 767 bytes) para no exceder el limite de
        // longitud de indice de InnoDB en MySQL 5.7.
        Schema::defaultStringLength(191);

        // En produccion (HTTPS detras de LiteSpeed) forzar https en las URLs
        // generadas, para que formularios y enlaces siempre apunten a https.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Contador de la barra: cuantas ordenes tiene "en mano" el tecnico
        // (recibidas + en cotizacion). Solo se calcula para quien ve servicio
        // tecnico; un COUNT liviano sobre la columna indexada `estado`.
        View::composer('layouts.navigation', function ($view) {
            $user = Auth::user();
            $view->with(
                'pendientesServicioTecnico',
                ($user && $user->canAny(['view servicio tecnico', 'manage servicio tecnico']))
                    ? OrdenServicio::pendientesTecnico()->count()
                    : 0
            );
        });
    }
}
