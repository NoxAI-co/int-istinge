<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Movimiento;
use Illuminate\Support\Facades\DB;

$dates = ['inicio' => '2026-02-01', 'fin' => '2026-02-28'];

$empresa = DB::table('empresas')->first()->id ?? 1;
// we use Auth::loginUsingId(1);
Auth::loginUsingId(1); // Usually ID 1 is the main user or just use a known admin ID, maybe 3.

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
    // ->where('movimientos.empresa', 1)
    ->groupBy('movimientos.id', 'movimientos.tipo', 'movimientos.saldo', 'movimientos.fecha', 'movimientos.banco', 'movimientos.contacto', 'movimientos.estatus', 'movimientos.conciliado', 'movimientos.modulo', 'movimientos.id_modulo', 'movimientos.transferencia', 'movimientos.descripcion', 'movimientos.created_at', 'movimientos.updated_at', 'movimientos.empresa', 'c.nombre', 'f.id')
    ->orderBy('movimientos.fecha', 'DESC');
    
echo "SQL: " . $movimientos->toSql() . "\n";
echo "Count total via count(): " . $movimientos->count() . "\n";
echo "Count total collection: " . $movimientos->get()->count() . "\n";
echo "Count tipo 1: " . $movimientos->get()->where('tipo', 1)->count() . "\n";

foreach($movimientos->get() as $m) {
    echo "ID: {$m->id} Tipo: {$m->tipo} Fact: " . ($m->facturaId ?? 'NULL') . " Saldo: {$m->saldo}\n";
}
