<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$c = App\Contrato::find(2219);
echo "Contrato ID: " . $c->id . "\n";
echo "precio_televisión: " . $c->precio_personalizado_tv . "\n";
echo "servicio_tv: " . $c->servicio_tv . "\n";
echo "precio_internet: " . $c->precio_personalizado_internet . "\n";
echo "servicio_internet (plan_id): " . $c->plan_id . "\n";
