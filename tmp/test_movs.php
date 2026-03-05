<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Movimiento;
use Illuminate\Support\Facades\DB;

$dates = [
    'inicio' => '2026-02-01',
    'fin'    => '2026-02-28'
];
$empresa = 1; // Arbitrary, let's try to find an active one or just check count

$movimientos= Movimiento::leftjoin('contactos as c', 'movimientos.contacto', '=', 'c.id')
    ->leftjoin('ingresos as i', function($join) {
        $join->on('i.id', '=', 'movimientos.id_modulo')
             ->where('movimientos.modulo', '=', 1);
    })
    ->leftjoin('ingresos_factura as if', function($join) {
        $join->on('if.ingreso', '=', 'movimientos.id_modulo')
             ->where('movimientos.modulo', '=', 1);
    })
    ->leftjoin('factura as f','f.id','if.factura')
    ->select('movimientos.*', DB::raw('if(movimientos.contacto,c.nombre,"") as nombrecliente'),'f.id as facturaId')
    ->where('movimientos.fecha', '>=', $dates['inicio'])
    ->where('movimientos.fecha', '<=', $dates['fin'])
    ->where('movimientos.estatus','<>',2)
    // ->where('movimientos.empresa',Auth::user()->empresa) // skip this for now
    ->groupBy('movimientos.id');

echo "Count: " . $movimientos->get()->count() . "\n";
echo "Incomes (tipo 1): " . $movimientos->get()->where('tipo', 1)->count() . "\n";
echo "Expenses (tipo 2): " . $movimientos->get()->where('tipo', 2)->count() . "\n";

foreach($movimientos->get()->where('tipo', 1)->take(5) as $m) {
    echo "ID: " . $m->id . " facturaId: " . ($m->facturaId ?? 'NULL') . " saldo: " . $m->saldo . "\n";
}
