<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SensibilizacionLog extends Model
{
    protected $table = 'sensibilizacion_logs';

    protected $fillable = [
        'celular',
        'contacto_id',
        'contacto_nombre',
        'template',
        'batch_id',
        'batch_number',
        'idempotency_key',
        'status',
        'api_response',
        'error_message',
        'image_url',
        'optional_1',
        'campaign_date',
        'created_by'
    ];

    public function contacto()
    {
        return $this->belongsTo(Contacto::class, 'contacto_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope a query to only include logs of a specific batch.
     */
    public function scopeBatch($query, $batchId)
    {
        return $query->where('batch_id', $batchId);
    }

    /**
     * Scope a query to only include logs with a specific status.
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
