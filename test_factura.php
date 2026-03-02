<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$factura = \App\Model\Ingresos\Factura::where('codigo', 'EST5411')->first();
if ($factura) {
    echo "ID Factura: " . $factura->id . "\n";
    echo "Estatus DB: " . $factura->estatus . "\n";
    echo "Estatus func: " . $factura->estatus() . "\n";
    
    $contrato = \App\Contrato::where('id', $factura->contrato_id)->first();
    if(!$contrato){
        $pivot = \Illuminate\Support\Facades\DB::table('facturas_contratos')->where('factura_id', $factura->id)->first();
        if($pivot) {
           $contrato = \App\Contrato::where('nro', $pivot->contrato_nro)->first();
        }
    }
    
    if ($contrato) {
        echo "Contrato ID: " . $contrato->id . " Nro: " . $contrato->nro . "\n";
        $ultima_factura = $contrato->facturas()->orderBy('created_at', 'desc')->first();
        if ($ultima_factura) {
              echo "Ultima Factura por created_at - ID: " . $ultima_factura->id . " Codigo: " . $ultima_factura->codigo . " Estatus: " . $ultima_factura->estatus() . "\n";
        }
        $ultima_factura2 = $contrato->facturas()->orderBy('id', 'desc')->first();
        if ($ultima_factura2) {
              echo "Ultima Factura por id - ID: " . $ultima_factura2->id . " Codigo: " . $ultima_factura2->codigo . " Estatus: " . $ultima_factura2->estatus() . "\n";
        }
    }
} else {
    echo "Factura EST5411 no encontrada.\n";
}
