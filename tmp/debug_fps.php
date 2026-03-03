<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$fps = \DB::table('forma_pago')->get();
foreach($fps as $fp) {
    echo $fp->id . ': ' . $fp->nombre . PHP_EOL;
}
