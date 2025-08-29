<?php

namespace App;

use App\Model\Gastos\Gastos;
use App\Model\Gastos\GastosCategoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ContratoDigital extends Model
{
    protected $table = "contratos_digitales";
    protected $primaryKey = 'id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'cliente_id', 'fecha_firma', 'estado_firma', 'firma', 'imgA', 'imgB', 'imgC',
        'imgD', 'imgE', 'imgF', 'imgG', 'imgH', 'documento', 'adjunto_audio', 'created_at',
        'updated_at', 'nro', 'contrato_id'
    ];

    public function cliente(){
        return $this->belongsTo(Contacto::class, 'cliente_id');
    }

    public function contrato(){
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }

    public function asignacion($opt = false, $class = false){
        if($opt == 'firma'){
            if($class){
                return ($this->firma) ? 'success' : 'danger';
            }
            return ($this->firma) ? 'Firmado' : 'Pendiente por firmar';
        }

    }

}