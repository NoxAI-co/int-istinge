<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\CronController;
use App\Http\Controllers\CronDianController;

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

        // Actualizar status de WhatsApp cada 15 minutos
        $schedule->command('whatsapp:update-status')->everyFifteenMinutes();

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
        $schedule->call($this->cronLogueado('CortarFacturas', [CronController::class, 'CortarFacturas']))
            ->name('cron-cortar-facturas')
            ->everyFifteenMinutes()
            ->timezone('America/Bogota')
            ->withoutOverlapping();

        // Generar las facturas recurrentes del día. Cada 15 min.
        $schedule->call($this->cronLogueado('CrearFactura', [CronController::class, 'CrearFactura']))
            ->name('cron-crear-factura')
            ->everyFifteenMinutes()
            ->timezone('America/Bogota')
            ->withoutOverlapping();

        // Aviso de pago oportuno (facturas con pago_oportuno = hoy). Cada 15 min.
        $schedule->call($this->cronLogueado('PagoOportuno', [CronController::class, 'PagoOportuno']))
            ->name('cron-pago-oportuno')
            ->everyFifteenMinutes()
            ->timezone('America/Bogota')
            ->withoutOverlapping();

        // Aviso de vencimiento (facturas con vencimiento = hoy). Cada 15 min.
        $schedule->call($this->cronLogueado('PagoVencimiento', [CronController::class, 'PagoVencimiento']))
            ->name('cron-pago-vencimiento')
            ->everyFifteenMinutes()
            ->timezone('America/Bogota')
            ->withoutOverlapping();

        // Cortar contratos con promesas de pago vencidas. Cada 15 min.
        $schedule->call($this->cronLogueado('CortarPromesas', [CronController::class, 'CortarPromesas']))
            ->name('cron-cortar-promesas')
            ->everyFifteenMinutes()
            ->timezone('America/Bogota')
            ->withoutOverlapping();

        // Cortar servicios de televisión con facturas vencidas. Cada 15 min.
        // Estos 5 jobs usan instance methods que internamente referencian $this
        // (helpers, $this->dianLog, etc). Los llamamos vía app() para que el
        // container instancie el controller correctamente, en vez de pasar
        // [Class::class, 'method'] (que PHP trataría como static call y volaría
        // con "Using $this when not in object context").
        $schedule->call($this->cronLogueado('CortarTelevision', function () {
            return app(CronController::class)->cortarTelevision();
        }))
            ->name('cron-cortar-television')
            ->everyFifteenMinutes()
            ->timezone('America/Bogota')
            ->withoutOverlapping();

        // Envío de facturas por WhatsApp. Cada 15 min.
        $schedule->call($this->cronLogueado('EnvioFacturaWpp', function () {
            return app(CronController::class)->envioFacturaWpp();
        }))
            ->name('cron-envio-factura-wpp')
            ->everyFifteenMinutes()
            ->timezone('America/Bogota')
            ->withoutOverlapping();

        // Sincronización con IntegraPay. Cada 15 min.
        $schedule->call($this->cronLogueado('SyncIntegraPay', function () {
            return app(CronController::class)->syncIntegraPay();
        }))
            ->name('cron-sync-integrapay')
            ->everyFifteenMinutes()
            ->timezone('America/Bogota')
            ->withoutOverlapping();

        // Emisión de facturas electrónicas DIAN. Cada 15 min.
        $schedule->call($this->cronLogueado('EmisionFacturaDian', function () {
            return app(CronDianController::class)->ejecutar();
        }))
            ->name('cron-emision-factura-dian')
            ->everyFifteenMinutes()
            ->timezone('America/Bogota')
            ->withoutOverlapping();

        // Sincronización de logs WhatsApp Meta — versión controller (replica /sync-whatsapp-meta-logs).
        // Convive con el command whatsapp:sync-meta-logs que usa otro code path (WhatsAppMessageSyncService).
        $schedule->call($this->cronLogueado('SyncWhatsAppMetaLogsCtrl', function () {
            return app(CronController::class)->syncWhatsAppMetaLogs();
        }))
            ->name('cron-sync-whatsapp-meta-logs-ctrl')
            ->everyFifteenMinutes()
            ->timezone('America/Bogota')
            ->withoutOverlapping();
    }

    /**
     * Envuelve un callable de cron para dejar traza en storage/logs/cron.log:
     * registra inicio, fin (con resultado y duración) y cualquier excepción.
     * El error se relanza para que withoutOverlapping/scheduler lo manejen igual.
     *
     * @param  string    $nombre  Etiqueta legible del job.
     * @param  callable  $fn      La lógica a ejecutar (ej. [CronController::class, 'CortarFacturas']).
     * @return \Closure
     */
    private function cronLogueado($nombre, callable $fn)
    {
        return function () use ($nombre, $fn) {
            $t0 = microtime(true);
            Log::channel('cron')->info("[$nombre] inicio");

            try {
                $resultado = $fn();
                Log::channel('cron')->info("[$nombre] fin", [
                    'resultado' => is_scalar($resultado) ? (string) $resultado : 'ok',
                    'segundos'  => round(microtime(true) - $t0, 1),
                ]);
            } catch (\Throwable $e) {
                Log::channel('cron')->error("[$nombre] ERROR: " . $e->getMessage(), [
                    'archivo'  => $e->getFile() . ':' . $e->getLine(),
                    'segundos' => round(microtime(true) - $t0, 1),
                ]);
                throw $e;
            }
        };
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
