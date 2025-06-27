<?php

namespace App\Model\Ingresos;

use Illuminate\Database\Eloquent\Model;
use App\Model\Ingresos\Factura; 
use App\Model\Ingresos\NotaCredito; 

class NotaCreditoFactura extends Model 
{
    protected $table = "notas_factura";
    protected $primaryKey = 'id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'nota', 'factura', 'pago', 'created_at', 'updated_at'
    ];

    
    public function factura(){
         return Factura::where('id',$this->factura)->first();
    }

    public function nota(){
        return NotaCredito::where('id',$this->nota)->first();
    }

    public function pago(){
        return $this->pago+$this->retencion();
    }

    /**
     * Obtiene las observaciones de la nota de forma segura
     * @return string
     */
    public function observaciones_nota_seguras(){
        $nota = $this->nota();
        if ($nota && isset($nota->observaciones)) {
            return $nota->observaciones ?: '';
        }
        return '';
    }
    
    public function nota_nro_seguro() {
        $nota = $this->nota();
        return $nota ? $nota->nro : 'N/A';
    }
    
    public function nota_fecha_segura() {
        $nota = $this->nota();
        return $nota && $nota->fecha ? date('d-m-Y', strtotime($nota->fecha)) : 'N/A';
    }
}
