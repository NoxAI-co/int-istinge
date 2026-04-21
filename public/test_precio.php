<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);
$c = App\Contrato::find(2219);
header('Content-Type: application/json');
echo json_encode([
    'id' => $c->id,
    'servicio_tv' => $c->servicio_tv,
    'precio_personalizado_tv' => $c->precio_personalizado_tv,
    'plan_id' => $c->plan_id,
    'precio_personalizado_internet' => $c->precio_personalizado_internet,
]);
exit;
