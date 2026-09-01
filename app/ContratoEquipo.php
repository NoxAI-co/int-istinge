<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Equipo entregado al cliente con el plan de servicios fijos.
 *
 * Es tabla y no columnas en `contracts` porque el Contrato Único Convergente
 * (CRC 7811 de 2025) imprime una FILA por equipo, con su condición de entrega
 * y su precio: un router, un decodificador y una ONU son tres renglones.
 */
class ContratoEquipo extends Model
{
    protected $table = 'contratos_equipos';

    protected $fillable = ['contrato_id', 'equipo', 'condicion', 'precio'];

    public function contrato()
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }
}
