<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Instance;
use App\Services\WhatsAppMessageSyncService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncWhatsAppMetaLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:sync-meta-logs {--date= : Fecha a sincronizar (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza los logs de WhatsApp (whatsapp_messages) con la tabla log_meta y actualiza factura/ingreso.whatsapp';

    /**
     * @var WhatsAppMessageSyncService
     */
    protected $syncService;

    public function __construct(WhatsAppMessageSyncService $syncService)
    {
        parent::__construct();
        $this->syncService = $syncService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $dateOption = $this->option('date');
        $date = $dateOption ? Carbon::parse($dateOption) : Carbon::today();

        $dateFrom = $date->copy()->startOfDay()->format('Y-m-d');
        $dateTo = $date->copy()->endOfDay()->format('Y-m-d');

        $this->info("Iniciando sincronización de WhatsApp Meta Logs para la fecha: {$date->toDateString()}");

        // Obtener todas las instancias activas de Meta
        $instances = Instance::active()
            ->whereNotNull('phone_number_id')
            ->get();

        if ($instances->isEmpty()) {
            $this->warn('No se encontraron instancias activas con phone_number_id configurado.');
            return 0;
        }

        $totalLogsCreated = 0;
        $totalLogsUpdated = 0;
        $totalFacturasActualizadas = 0;
        $totalIngresosActualizados = 0;

        foreach ($instances as $instance) {
            $company = $instance->company;
            if (!$company || !$company->nit) {
                $this->warn("Instancia {$instance->id} sin empresa/nit asociado, se omite.");
                continue;
            }

            $companyNit = (int) $company->nit;

            $this->info("Procesando instancia {$instance->id} ({$instance->phone_number_id}) para empresa NIT {$companyNit}...");

            try {
                $result = $this->syncService->syncForInstanceAndCompany(
                    $instance,
                    $companyNit,
                    $dateFrom,
                    $dateTo
                );

                $totalLogsCreated += $result['logsCreated'] ?? 0;
                $totalLogsUpdated += $result['logsUpdated'] ?? 0;
                $totalFacturasActualizadas += $result['facturasActualizadas'] ?? 0;
                $totalIngresosActualizados += $result['ingresosActualizados'] ?? 0;

                $this->line("  Logs creados: {$result['logsCreated']}, logs actualizados: {$result['logsUpdated']}, facturas: {$result['facturasActualizadas']}, ingresos: {$result['ingresosActualizados']}");
            } catch (\Exception $e) {
                $this->error("Error procesando instancia {$instance->id}: {$e->getMessage()}");
                Log::error('Error en SyncWhatsAppMetaLogs', [
                    'instance_id' => $instance->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info("Sincronización completada. Logs creados: {$totalLogsCreated}, logs actualizados: {$totalLogsUpdated}, facturas: {$totalFacturasActualizadas}, ingresos: {$totalIngresosActualizados}");

        return 0;
    }
}

