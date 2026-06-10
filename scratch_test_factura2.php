<?php
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$factura = \App\Model\Ingresos\Factura::where('codigo', '45099')->orWhere('nro', '45099')->first();
if ($factura) {
    echo "ID: " . $factura->id . "\n";
    echo "Codigo: " . $factura->codigo . "\n";
    echo "Nro: " . $factura->nro . "\n";
    echo "Estatus: " . $factura->estatus . "\n";
    echo "Fecha: " . $factura->fecha . "\n";
    echo "Pagado: " . json_encode($factura->pagado()) . "\n";
} else {
    echo "Factura no encontrada\n";
}
