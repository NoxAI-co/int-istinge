<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Notificación enviada desde el Integra Portal (portal.integracolombia.co)
 * para mostrarse en el dashboard. La tabla se auto-crea en el primer POST
 * del portal (ver MasterApiController::asegurarTabla) — en el legado no hay
 * pipeline de migraciones hacia las BDs de clientes.
 */
class PortalNotificacion extends Model
{
    protected $table = 'portal_notificaciones';

    protected $fillable = [
        'portal_id', 'portal_empresa_id', 'titulo', 'cuerpo', 'tipo',
        'vigente_desde', 'vigente_hasta', 'vista_at',
    ];

    protected $casts = [
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
        'vista_at'      => 'datetime',
    ];

    /** Vigentes hoy (sin límite cuenta como vigente). */
    public function scopeVigentes($query)
    {
        $hoy = now()->toDateString();

        return $query
            ->where(function ($q) use ($hoy) {
                $q->whereNull('vigente_desde')->orWhere('vigente_desde', '<=', $hoy);
            })
            ->where(function ($q) use ($hoy) {
                $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $hoy);
            });
    }
}
