<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Model\Ingresos\Factura;

/**
 * Reconcilia el estatus de las facturas con su saldo real, reimponiendo la
 * invariante del sistema:  estatus=0 (Cerrada)  <=>  porpagar() <= 0.
 *
 * Detecta y corrige facturas que quedaron "Cerradas" sin pago real (Pagado $0 /
 * Por Pagar > 0) — el síntoma del bug del cierre en bloque en IngresosController.
 * Nunca toca facturas anuladas (estatus=2).
 *
 * Corre en el contexto de UN cliente (usa su .env/BD). El .sh lo lanza por cliente.
 *
 * Uso:
 *   php artisan facturas:reconciliar-estatus                 # dry-run (solo reporta)
 *   php artisan facturas:reconciliar-estatus --run           # reabre las mal cerradas
 *   php artisan facturas:reconciliar-estatus --run --cerrar  # además cierra pagadas abiertas
 *   php artisan facturas:reconciliar-estatus --desde=2026-01-01   # limita por fecha
 *   php artisan facturas:reconciliar-estatus --empresa=1     # limita a una empresa
 */
class ReconciliarEstatusFacturas extends Command
{
    protected $signature = 'facturas:reconciliar-estatus
                            {--run : Aplica los cambios (sin este flag es dry-run)}
                            {--cerrar : Además de reabrir mal-cerradas, cierra las pagadas que siguen abiertas}
                            {--desde= : Solo facturas con fecha >= YYYY-MM-DD}
                            {--empresa= : Limita a una empresa (id)}
                            {--tolerancia=1 : Saldo por pagar <= este valor se considera saldado (redondeos). No se reabre}';

    protected $description = 'Reconcilia estatus de facturas vs saldo real (invariante estatus=0 <=> porpagar<=0)';

    public function handle()
    {
        $run    = (bool) $this->option('run');
        $cerrar = (bool) $this->option('cerrar');
        $desde  = $this->option('desde');
        $emp    = $this->option('empresa');
        $tol    = (float) $this->option('tolerancia');

        $this->info('Reconciliando estatus de facturas ' . ($run ? '(APLICANDO)' : '(dry-run)') . " | tolerancia=\${$tol}");

        // Solo miramos facturas NO anuladas.
        $query = Factura::where('estatus', '<>', 2);
        if ($desde) { $query->whereDate('fecha', '>=', $desde); }
        if ($emp)   { $query->where('empresa', $emp); }

        $reabiertas = 0; $cerradas = 0; $revisadas = 0;

        // chunk para no cargar toda la tabla en memoria.
        $query->orderBy('id')->chunk(300, function ($facturas) use ($run, $cerrar, $tol, &$reabiertas, &$cerradas, &$revisadas) {
            foreach ($facturas as $factura) {
                $revisadas++;
                $porpagar = \App\Funcion::precision($factura->porpagar());

                // Caso 1 (el bug): Cerrada (0) pero todavía debe -> hay que REABRIR.
                // Ignoramos saldos <= tolerancia (diferencias de redondeo, p.ej. $1).
                if ((int)$factura->estatus === 0 && $porpagar > $tol) {
                    $this->warn("  REABRIR  {$factura->codigo} (id {$factura->id}) estatus=0 pero porpagar=\${$porpagar}");
                    if ($run) {
                        $factura->estatus = 1;
                        $factura->save();
                    }
                    $reabiertas++;
                    continue;
                }

                // Caso 2 (opcional): Abierta (1) pero ya está saldada -> CERRAR.
                if ($cerrar && (int)$factura->estatus === 1 && $porpagar <= 0) {
                    $this->line("  CERRAR   {$factura->codigo} (id {$factura->id}) estatus=1 pero porpagar=\${$porpagar}");
                    if ($run) {
                        $factura->estatus = 0;
                        $factura->save();
                    }
                    $cerradas++;
                }
            }
        });

        $this->info("Listo. Revisadas={$revisadas}  Reabiertas={$reabiertas}" . ($cerrar ? "  Cerradas={$cerradas}" : ''));
        return 0;
    }
}
