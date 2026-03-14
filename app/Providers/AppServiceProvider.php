<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Inyectar conteo de alertas de numeración DIAN para el badge en el menú lateral
        View::composer('layouts.includes.menu', function ($view) {
            $count = 0;
            try {
                if (Schema::hasTable('cron_dian_alertas_numeracion')) {
                    $count = DB::table('cron_dian_alertas_numeracion')
                        ->where('resuelta', 0)
                        ->count();
                }
            } catch (\Exception $e) {
                $count = 0;
            }
            $view->with('alertas_numeracion_count', $count);
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
