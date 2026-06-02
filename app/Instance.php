<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Instance extends Model
{
    protected $fillable = ['uuid', 'company_id', 'api_key', 'addr', 'uuid_whatsapp', 'status', 'type', 'meta', 'activo', 'phone_number_id', 'waba_id', 'access_token'];

    protected $casts = [
        'meta' => 'array',
        'activo' => 'boolean',
    ];

    /**
     * Relación con conversaciones de WhatsApp
     */
    public function whatsappConversations()
    {
        return $this->hasMany(WhatsAppConversation::class, 'instance_id');
    }

    /**
     * Relación con la empresa
     */
    public function company()
    {
        return $this->belongsTo(Empresa::class, 'company_id'); // Project uses Empresa, check if Company exists? Prompt said Company.
    }

    /**
     * Scope para instancias activas
     */
    public function scopeActive($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para instancias de tipo Meta
     */
    public function scopeMeta($query)
    {
        return $query->where('type', 1)->where('meta', 0); // Based on previous logic: type=0, meta=0 is Meta Direct
    }

    /**
     * Obtener el phone_number_id de Meta
     */
    public function getPhoneNumberIdAttribute($value)
    {
        return $value;
    }

    /**
     * Verificar si la instancia está configurada para Meta
     */
    public function isMetaConfigured()
    {
        return !empty($this->phone_number_id) && $this->type == 1 && $this->meta == 0;
    }

    public function isPaired(): bool
    {
        return $this->status === 'PAIRED';
    }
}
