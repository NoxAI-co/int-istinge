<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DianAuditLog extends Model
{
    protected $table = 'dian_audit_logs';

    protected $fillable = [
        'audit_record_id',
        'session_id',
        'factura_id',
        'folio',
        'cufe',
        'nit_anterior',
        'nombre_anterior',
        'cliente_id_anterior',
        'nit_nuevo',
        'nombre_nuevo',
        'cliente_id_nuevo',
        'motivo',
        'usuario_id',
        'ip',
        'user_agent'
    ];

    public function record()
    {
        return $this->belongsTo(DianAuditRecord::class, 'audit_record_id');
    }

    public function session()
    {
        return $this->belongsTo(DianAuditSession::class, 'session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function clienteAnterior()
    {
        return $this->belongsTo(Contacto::class, 'cliente_id_anterior');
    }

    public function clienteNuevo()
    {
        return $this->belongsTo(Contacto::class, 'cliente_id_nuevo');
    }
}
