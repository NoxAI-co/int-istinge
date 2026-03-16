<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Model\Ingresos\Factura;
use App\Contacto;
use App\Empresa;
use App\Plantilla;
use App\User;

class WhatsappMetaLog extends Model
{
    protected $table = "log_meta";
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'remote_id',
        'wamid',
        'incoming_invoice_id',
        'incoming_contract_id',
        'incoming_payment_id',
        'incoming_company_nit',
        'direction',
        'status',
        'response',
        'error_message',
        'metadata',
        'sent_at',
        'delivered_at',
        'read_at',
        'factura_id',
        'contacto_id',
        'empresa',
        'mensaje_enviado',
        'plantilla_id',
        'enviado_por',
        'created_at',
        'updated_at'
    ];

    /**
     * Relación con Factura
     */
    public function factura()
    {
        return $this->belongsTo(Factura::class, 'factura_id');
    }

    /**
     * Relación con Contacto
     */
    public function contacto()
    {
        return $this->belongsTo(Contacto::class, 'contacto_id');
    }

    /**
     * Relación con Empresa
     */
    public function empresaObj()
    {
        return $this->belongsTo(Empresa::class, 'empresa');
    }

    /**
     * Relación con Plantilla
     */
    public function plantilla()
    {
        return $this->belongsTo(Plantilla::class, 'plantilla_id');
    }

    /**
     * Relación con User (quien envió)
     */
    public function usuarioEnvio()
    {
        return $this->belongsTo(User::class, 'enviado_por');
    }

    /**
     * Obtener el estado formateado
     */
    public function estadoFormateado()
    {
        $status = $this->status;

        if (!$status) {
            return '<span class="badge badge-secondary">Desconocido</span>';
        }

        switch ($status) {
            case 'sent':
                return '<span class="badge badge-info">Enviado</span>';
            case 'delivered':
                return '<span class="badge badge-primary">Entregado</span>';
            case 'read':
                return '<span class="badge badge-success">Leído</span>';
            case 'failed':
                return '<span class="badge badge-danger">Fallido</span>';
            case 'pending':
                return '<span class="badge badge-warning">Pendiente</span>';
            case 'success':
                // Compatibilidad hacia atrás con registros antiguos
                return '<span class="badge badge-success">Éxito</span>';
            default:
                return '<span class="badge badge-secondary">' . e(ucfirst($status)) . '</span>';
        }
    }

    /**
     * Obtener el mensaje truncado
     */
    public function mensajeTruncado($length = 100)
    {
        if (strlen($this->mensaje_enviado) > $length) {
            return substr($this->mensaje_enviado, 0, $length) . '...';
        }
        return $this->mensaje_enviado;
    }
}
