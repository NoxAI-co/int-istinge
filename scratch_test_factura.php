<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$factura = \App\Model\Ingresos\Factura::where('codigo', '45099')->orWhere('nro', '45099')->first();
if ($factura) {
    echo "ID: " . $factura->id . "\n";
    echo "Codigo: " . $factura->codigo . "\n";
    echo "Nro: " . $factura->nro . "\n";
    echo "Estatus: " . $factura->estatus . "\n";
    echo "Fecha: " . $factura->fecha . "\n";
    echo "Pagado: " . json_encode($factura->pagado()) . "\n";
    echo "Notas Credito: " . json_encode($factura->notas_credito() ? $factura->notas_credito()->count() : 0) . "\n";
    echo "Total: " . json_encode($factura->total()->total) . "\n";
} else {
    echo "Factura no encontrada\n";
}
