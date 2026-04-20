<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    $persona = DB::table('ne_personas')->where('id', 8)->first();
    echo "PERSONA ID 8:\n";
    print_r($persona);
    
    $nominas = DB::table('ne_nomina')->where('fk_idpersona', 8)->orderBy('id', 'desc')->limit(5)->get();
    echo "\nULTIMAS 5 NOMINAS PERSONA 8:\n";
    print_r($nominas);
    
    $periodos = DB::table('ne_nomina_periodos')->whereIn('fk_idnomina', $nominas->pluck('id'))->get();
    echo "\nPERIODOS DE ESAS NOMINAS:\n";
    print_r($periodos);

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
