<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Numeracion;
use App\PucMovimiento;

$empresa = 1; // Assuming 1, but I should probably check
$num = Numeracion::where('empresa', $empresa)->first();
if($num) {
    echo "Numeracion contabilidad: {$num->contabilidad}\n";
} else {
    echo "No Numeracion found for empresa $empresa\n";
}

$last_move = PucMovimiento::where('empresa', $empresa)->orderBy('nro', 'desc')->first();
if($last_move) {
    echo "Last move nro: {$last_move->nro}\n";
}
