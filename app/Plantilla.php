<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Auth;
use App\User;

class Plantilla extends Model
{
    protected $table = "plantillas";
    protected $primaryKey = 'id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'nro', 'tipo', 'clasificacion', 'title', 'contenido', 'archivo', 'status', 'created_by', 'updated_by', 'created_at', 'updated_at', 'body_text', 'lectura', 'empresa', 'language', 'body_dinamic', 'body_header', 'preferida_cron_factura', 'preferida_tirilla'
    ];

    public function created_by()
    {
        return User::find($this->created_by);
    }

    public function updated_by()
    {
        return User::find($this->update_by);
    }

    public function status($class = false)
    {
        if($class){
            return ($this->status == 1) ? 'success font-weight-bold' : 'danger font-weight-bold';
        }
        return ($this->status == 1) ? 'Activa' : 'Desactivada';
    }

    public function tipo()
    {
        if($this->tipo==0){
            return 'SMS';
        }elseif($this->tipo==1){
            return 'EMAIL';
        }elseif($this->tipo==2){
            return 'WHATSAPP';
        }else if($this->tipo==3){
            return 'META';
        }
    }

    /**
     * Procesa el contenido de la plantilla reemplazando los placeholders {{1}}, {{2}}, etc.
     * con los valores del array body_text. No modifica el valor en base de datos.
     *
     * @return string Contenido procesado con valores reemplazados
     */
    public function procesarContenido()
    {
        $contenido = $this->contenido;

        // Si no hay body_text, retornar contenido sin procesar
        if (empty($this->body_text)) {
            return $contenido;
        }

        // Decodificar body_text si está en formato JSON
        $bodyTextValues = is_string($this->body_text) ? json_decode($this->body_text, true) : $this->body_text;

        // Si body_text es un array, tomar el primer elemento
        if (is_array($bodyTextValues) && isset($bodyTextValues[0]) && is_array($bodyTextValues[0])) {
            $bodyTextValues = $bodyTextValues[0];
        }

        // Si no es un array válido, retornar contenido sin procesar
        if (!is_array($bodyTextValues)) {
            return $contenido;
        }

        // Reemplazar placeholders {{1}}, {{2}}, etc. con los valores del array
        foreach ($bodyTextValues as $index => $value) {
            $placeholder = '{{' . ($index + 1) . '}}';
            $contenido = str_replace($placeholder, $value, $contenido);
        }

        return $contenido;
    }
}
