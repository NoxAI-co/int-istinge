<?php

namespace App\Traits;

use App\EtiquetaAutomaticaContrato;
use App\Contrato;
use Illuminate\Support\Facades\Log;

trait AplicaEtiquetaAutomatica
{
    /**
     * Aplica automáticamente una etiqueta a un contrato según el módulo y la acción configurados.
     *
     * Este método es genérico y está diseñado para escalar a múltiples módulos en el futuro.
     * Por ahora soporta el módulo 'contratos'.
     *
     * @param  int    $contrato_id  ID del contrato al que se le aplicará la etiqueta
     * @param  int    $empresa_id   ID de la empresa (para buscar la configuración)
     * @param  string $modulo       Módulo al que pertenece la acción (ej: 'contratos')
     * @param  string $accion       Acción dentro del módulo
     *                              (corte_automatico | cliente_eliminado | deshabilitar_manual | pago_factura)
     * @return void
     */
    public static function aplicarEtiquetaAutomatica(int $contrato_id, int $empresa_id, string $modulo, string $accion): void
    {
        try {
            $config = EtiquetaAutomaticaContrato::where('empresa_id', $empresa_id)
                ->where('modulo', $modulo)
                ->where('accion', $accion)
                ->first();

            if ($config && $config->etiqueta_id) {
                Contrato::where('id', $contrato_id)
                    ->update(['etiqueta_id' => $config->etiqueta_id]);
            }
        } catch (\Throwable $e) {
            Log::error('[EtiquetaAutomatica] Error al aplicar etiqueta en contrato #' . $contrato_id
                . ' | modulo: ' . $modulo
                . ' | accion: ' . $accion
                . ' | ' . $e->getMessage());
        }
    }
}
