<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Comprobante de pago de la suscripción Integra adjuntado desde
 * "Mi suscripción". Copia local; el original viaja al Integra Portal.
 * La tabla se auto-crea (ver MiSuscripcionController::asegurarTabla).
 */
class PortalComprobante extends Model
{
    protected $table = 'portal_comprobantes';

    protected $fillable = [
        'portal_comprobante_id', 'periodo', 'archivo', 'valor', 'referencia',
        'observaciones', 'estado', 'motivo_rechazo', 'created_by',
    ];

    protected $casts = [
        'periodo' => 'date',
    ];
}
