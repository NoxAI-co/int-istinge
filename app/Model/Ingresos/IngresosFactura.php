<?php

namespace App\Model\Ingresos;

use Illuminate\Database\Eloquent\Model;
use App\Model\Ingresos\Factura;
use App\Model\Ingresos\Ingreso;
use App\Model\Ingresos\IngresosRetenciones;

class IngresosFactura extends Model
{
    protected $table = "ingresos_factura";
    protected $primaryKey = 'id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'ingreso', 'factura', 'pagado', 'pago', 'created_at', 'updated_at'
    ];


    public function factura(){
         return Factura::where('id',$this->factura)->first();
    }


    public function getPagadoAttribute()
    {
        return $this->factura()->total()->total;
    }


    public function ingreso(){
        return Ingreso::where('id',$this->ingreso)->first();
    }

    public function ingresoRelation()
    {
        return $this->belongsTo(Ingreso::class, 'ingreso'); // o 'ingreso_id' si ese es el nombre real de la columna
    }

    public function retencion(){
        return IngresosRetenciones::where('ingreso', $this->ingreso)->where('factura', $this->factura)->sum('valor');
    }
    public function retenciones(){
        return IngresosRetenciones::where('ingreso', $this->ingreso)->where('factura', $this->factura)->get();

    }

    public function pago(){
        return $this->pago;
    }

    public function detalle(){
        $factura=Factura::where('id',$this->factura)->first();
        return 'Factura de Venta: '.$factura->codigo;
    }

    public function fecha($fecha)
    {
        return date('Y-m-d', strtotime($this->created_at)) == $fecha;
}

    /**
     * Devuelve la factura del item relacionado
     */
    public function itemFactura()
    {
        return ItemsFactura::where('factura', $this->factura)->get();
    }

    /**
     * Verifica si el ingreso relacionado existe
     * @return bool
     */
    public function tieneIngreso(){
        return Ingreso::where('id',$this->ingreso)->exists();
    }

    /**
     * Método auxiliar para verificar métodos en el ingreso
     * @param string $method
     * @return mixed
     */
    public function metodoIngreso($method, $default = null){
        $ingreso = $this->ingreso();
        if ($ingreso && is_object($ingreso) && method_exists($ingreso, $method)) {
            return $ingreso->$method();
        }
        return $default;
    }

    /**
     * Obtiene el método de pago de forma segura
     * @return string
     */
    public function metodo_pago_seguro(){
        $ingreso = $this->ingreso();
        if ($ingreso && method_exists($ingreso, 'metodo_pago')) {
            return $ingreso->metodo_pago() ?: 'N/A';
        }
        return 'N/A';
    }

    /**
     * Obtiene las observaciones de forma segura
     * @return string
     */
    public function observaciones_seguras(){
        $ingreso = $this->ingreso();
        if ($ingreso && isset($ingreso->observaciones)) {
            return $ingreso->observaciones ?: '';
        }
        return '';
    }

    /**
     * Verifica si el ingreso es válido y tiene el método especificado
     * @param string $method
     * @return bool
     */
    public function ingreso_tiene_metodo($method){
        $ingreso = $this->ingreso();
        return $ingreso && method_exists($ingreso, $method);
    }
}
