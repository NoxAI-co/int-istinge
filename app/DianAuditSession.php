<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DianAuditSession extends Model
{
    protected $table = 'dian_audit_sessions';

    protected $fillable = [
        'filename',
        'original_filename',
        'uploaded_by',
        'periodo',
        'total_records',
        'matched',
        'discrepancies',
        'not_found',
        'corrected',
        'monto_total_discrepancia',
        'status',
        'error_message'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function records()
    {
        return $this->hasMany(DianAuditRecord::class, 'session_id');
    }

    public function getPorcentajeOkAttribute()
    {
        if ($this->total_records == 0) return 0;
        return round((($this->matched + $this->corrected) / $this->total_records) * 100, 2);
    }

    public function getStatusBadgeAttribute()
    {
        $badges = [
            'processing' => '<span class="badge badge-warning">Procesando</span>',
            'completed' => '<span class="badge badge-success">Completado</span>',
            'error' => '<span class="badge badge-danger">Error</span>',
        ];

        return $badges[$this->status] ?? $this->status;
    }
}
