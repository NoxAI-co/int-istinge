<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Model\Ingresos\Factura;

class DianAuditRecord extends Model
{
    protected $table = 'dian_audit_records';

    protected $fillable = [
        'session_id',
        'tipo_documento',
        'cufe',
        'folio',
        'prefijo',
        'nit_receptor_dian',
        'nombre_receptor_dian',
        'nit_emisor',
        'nombre_emisor',
        'fecha_emision',
        'total',
        'status',
        'factura_id',
        'nit_receptor_sistema',
        'nombre_receptor_sistema',
        'cliente_id_sistema'
    ];

    protected $appends = ['status_badge', 'total_formatted', 'folio_completo'];

    public function session()
    {
        return $this->belongsTo(DianAuditSession::class, 'session_id');
    }

    public function factura()
    {
        return $this->belongsTo(Factura::class, 'factura_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Contacto::class, 'cliente_id_sistema');
    }

    public function logs()
    {
        return $this->hasMany(DianAuditLog::class, 'audit_record_id');
    }

    public function getStatusBadgeAttribute()
    {
        $badges = [
            'matched' => '<span class="badge badge-success">Correcto</span>',
            'discrepancy' => '<span class="badge badge-danger">Discrepancia</span>',
            'not_found' => '<span class="badge badge-secondary">No encontrado</span>',
            'corrected' => '<span class="badge badge-primary">Corregido</span>',
        ];

        return $badges[$this->status] ?? $this->status;
    }

    public function getTotalFormattedAttribute()
    {
        return number_format($this->total, 0, ',', '.');
    }

    public function getFolioCompletoAttribute()
    {
        return ($this->prefijo ?: '') . ($this->folio ?: '');
    }
}
