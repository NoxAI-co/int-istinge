<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EtiquetaAutomaticaContrato extends Model
{
    protected $table = 'etiquetas_automaticas_modulos';

    protected $fillable = [
        'empresa_id',
        'modulo',
        'accion',
        'etiqueta_id',
    ];

    /**
     * Módulos disponibles para la automatización de etiquetas.
     * Agregar nuevos módulos aquí cuando se requieran.
     */
    const MODULO_CONTRATOS = 'contratos';

    /**
     * Acciones disponibles para el módulo "contratos".
     */
    const CORTE_AUTOMATICO    = 'corte_automatico';       // CronController::CortarFacturas
    const CLIENTE_ELIMINADO   = 'cliente_eliminado';      // ContactosController::destroy
    const DESHABILITAR_MANUAL = 'deshabilitar_manual';    // ContratosController::state / state_lote
    const PAGO_FACTURA        = 'pago_factura';           // IngresosController::funcionesPagoMK

    public function etiqueta()
    {
        return $this->belongsTo(Etiqueta::class);
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }
}
