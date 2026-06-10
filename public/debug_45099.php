<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Http\Kernel::class)->handle(Illuminate\Http\Request::capture());

$f = \App\Model\Ingresos\Factura::where('codigo', '45099')->orWhere('nro', '45099')->first();
if ($f) {
    echo json_encode([
        'id' => $f->id,
        'codigo' => $f->codigo,
        'nro' => $f->nro,
        'estatus' => $f->estatus,
        'fecha' => $f->fecha,
        'created_at' => $f->created_at,
        'pagado' => $f->pagado(),
        'total' => $f->total()->total,
        'notas' => $f->notas_credito()->count()
    ]);
} else {
    echo "Not found";
}
