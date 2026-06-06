<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Http\Controllers\CronController;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\SyncWhatsAppMetaLogs::class,
        \App\Console\Commands\UpdateWhatsAppMessageStatus::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        //$schedule->command('facturas:end')->cron('0 */12 * * *');
        //$schedule->command('pagos:end')->cron('0 */12 * * *');
        //$schedule->command('check:invoices')->everyMinute();everyFiveMinutes

        // Actualizar status de WhatsApp cada 5 minutos
        $schedule->command('whatsapp:update-status')->everyFiveMinutes();

        // Sincronizar logs de WhatsApp Meta (whatsapp_messages -> log_meta) cada 15 minutos para el día actual
        $schedule->command('whatsapp:sync-meta-logs')->everyFifteenMinutes();

        // ──────────────────────────────────────────────────────────────────
        // Cron de facturación (antes eran rutas /cortarfacturas, /generarfactura,
        // etc. que disparaba un cron externo por URL). Ahora corren dentro del
        // contenedor de cada cliente vía `schedule:run` (scheduler-all.sh en el
        // host, cada minuto). Como corren headless usan la BD de ese cliente y
        // Empresa::find(1); no dependen de login. Zona horaria explícita.
        // ──────────────────────────────────────────────────────────────────

        // Suspender contratos con facturas vencidas según grupos de corte.
        // Cada 15 min: el método compara hora_suspension del grupo con la hora actual.
        $schedule->call([CronController::class, 'CortarFacturas'])
            ->name('cron-cortar-facturas')
            ->everyFifteenMinutes()
            ->timezone('America/Bogota')
            ->withoutOverlapping();

        // Generar las facturas recurrentes del día. Una vez al día (00:30 Bogotá).
        $schedule->call([CronController::class, 'CrearFactura'])
            ->name('cron-crear-factura')
            ->dailyAt('00:30')
            ->timezone('America/Bogota')
            ->withoutOverlapping();

        // Aviso de pago oportuno (facturas con pago_oportuno = hoy). 08:00 Bogotá.
        $schedule->call([CronController::class, 'PagoOportuno'])
            ->name('cron-pago-oportuno')
            ->dailyAt('08:00')
            ->timezone('America/Bogota')
            ->withoutOverlapping();

        // Aviso de vencimiento (facturas con vencimiento = hoy). 08:15 Bogotá.
        $schedule->call([CronController::class, 'PagoVencimiento'])
            ->name('cron-pago-vencimiento')
            ->dailyAt('08:15')
            ->timezone('America/Bogota')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
