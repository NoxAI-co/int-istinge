<?php

use App\Model\Nomina\Persona;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$columns = DB::select('SHOW COLUMNS FROM ne_nomina');
foreach ($columns as $column) {
    if ($column->Field == 'valor_total') {
        echo "Table: ne_nomina\n";
        echo "Column: " . $column->Field . "\n";
        echo "Type: " . $column->Type . "\n";
    }
}

$columns = DB::select('SHOW COLUMNS FROM ne_nomina_periodos');
foreach ($columns as $column) {
    if ($column->Field == 'valor_total' || $column->Field == 'pago_empleado') {
        echo "Table: ne_nomina_periodos\n";
        echo "Column: " . $column->Field . "\n";
        echo "Type: " . $column->Type . "\n";
    }
}
