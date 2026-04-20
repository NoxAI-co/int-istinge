<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$request = Illuminate\Http\Request::create('/empresa/contratos/rowitem', 'GET', ['contrato_id' => [2219], 'cliente_id' => 2238]);
App::instance('request', $request);

$controller = new App\Http\Controllers\ContratosController();
$response = $controller->rowItem($request);

header('Content-Type: application/json');
echo $response->getContent();
exit;
