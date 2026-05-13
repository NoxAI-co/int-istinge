<?php

namespace App;
use Auth;

class Funcion
{
    public static function Parsear($valor){
        if(!Auth::user()){
            $empresa = Empresa::Find(1);
        }
        else{
            $empresa = Auth::user()->empresa();
        }

        if (!is_numeric($valor)) {
            // Intentar extraer valor si viene en objeto tipo { total: X }
            if (is_object($valor)) {
                // Si el objeto solo tiene 1 propiedad numérica, úsala
                $props = get_object_vars($valor);
                $first = reset($props);
                if (is_numeric($first)) {
                    $valor = (float) $first;
                } else {
                    // Caso extremo: no podemos formatear
                    return 0;
                }
            } else {
                return 0;
            }
        }

        $valor = (float) $valor;
        $precision = $empresa->precision;
        $sep_dec = $empresa->sep_dec;
        $sep_miles = ($sep_dec == '.' ? ',' : '.');

        // Si sep_dec esta vacio, usar valores por defecto
        if (empty($sep_dec)) {
            $sep_dec = ',';
            $sep_miles = '.';
        }

        // Si el numero es entero, no mostrar decimales
        // Si el numero es entero, no mostrar decimales
        if (fmod($valor, 1) == 0) {
            $precision = 0;
        }

        return number_format($valor, $precision, $sep_dec, $sep_miles);

    }

    public static function ParsearAPI($valor, $id){
        $empresa = Empresa::find($id);
        return number_format($valor, $empresa->precision, $empresa->sep_dec, ($empresa->sep_dec=='.'?',':'.'));

    }

    public static function precision($valor){

        if(!Auth::user()){
            $empresa = Empresa::Find(1);
        }
        else{
            $empresa = Auth::user()->empresa();
        }
        return round($valor, $empresa->precision);
    }

    public static function precisionAPI($valor, $id){
        $empresa = Empresa::find($id);
        return round($valor, $empresa->precision);
    }

    /**
     * Metodo para la resta de fechas
     *
     */
    public static function diffDates($date1, $date2){
        $dateTime1 = new \DateTime($date1);
        $dateTime2 = new \DateTime($date2);

        $interval = $dateTime1->diff($dateTime2);

        $plus = $interval ->format('%R%');

        if($plus == "+"){
            return 0;
        }

        return $interval->days;
    }

    public static function generateRandomString($length = 10){
        return substr(str_shuffle("0123456789"), 0, $length);
    }

    public static function parseSpeed($speed) {
        if (empty($speed)) return 0;
        
        $speed = strtoupper(trim($speed));
        
        // Si contiene K, es Kbps, dividimos por 1024 para pasar a Mbps
        if (strpos($speed, 'K') !== false) {
            $val = floatval(str_replace('K', '', $speed));
            return $val / 1024;
        }
        
        // Si contiene M, es Mbps, dejamos el valor numérico
        if (strpos($speed, 'M') !== false) {
            return floatval(str_replace('M', '', $speed));
        }
        
        return floatval($speed);
    }
}
