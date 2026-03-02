<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\PucMovimiento;

$nro = 14180;
$moves = PucMovimiento::where('nro', $nro)->get();
echo "Found " . count($moves) . " moves for nro $nro\n";
foreach($moves as $m) {
    echo "ID: {$m->id}, Empresa: {$m->empresa}, EnlaceA: {$m->enlace_a}, TipoComp: {$m->tipo_comprobante}\n";
}
