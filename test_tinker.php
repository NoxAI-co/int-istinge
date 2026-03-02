<?php

use App\Movimiento;
use Illuminate\Support\Facades\DB;

$dates = ['inicio' => '2026-02-01', 'fin' => '2026-02-28'];

$empresa = 1;

$movimientos = Movimiento::leftjoin('contactos as c', 'movimientos.contacto', '=', 'c.id')
    ->leftjoin('ingresos as i', function($join) {
        $join->on('i.id', '=', 'movimientos.id_modulo')
             ->on('movimientos.modulo', '=', DB::raw('1'));
    })
    ->leftjoin('ingresos_factura as if', 'if.ingreso', '=', 'i.id')
    ->leftjoin('factura as f', 'f.id', '=', 'if.factura')
    ->select('movimientos.*', DB::raw('if(movimientos.contacto,c.nombre,"") as nombrecliente'), 'f.id as facturaId')
    ->where('movimientos.fecha', '>=', $dates['inicio'])
    ->where('movimientos.fecha', '<=', $dates['fin'])
    ->where('movimientos.estatus','<>',2)
    ->where('movimientos.banco', 13)
    ->where('movimientos.empresa', 1)
    ->groupBy('movimientos.id')
    ->orderBy('movimientos.fecha', 'DESC');
    
echo "SQL: " . $movimientos->toSql() . "\n";
echo "Count total collection: " . $movimientos->get()->count() . "\n";
echo "Count tipo 1: " . $movimientos->get()->where('tipo', 1)->count() . "\n";
echo "Count tipo 2: " . $movimientos->get()->where('tipo', 2)->count() . "\n";

