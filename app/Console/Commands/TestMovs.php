<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Movimiento;
use Illuminate\Support\Facades\DB;

class TestMovs extends Command {
    protected $signature = 'test:movs';
    public function handle() {
        $dates = ['inicio' => '2026-02-01', 'fin' => '2026-02-28'];
        $movimientos = Movimiento::leftjoin('contactos as c', 'movimientos.contacto', '=', 'c.id')
            ->leftjoin('ingresos as i', function($join) {
                $join->on('i.id', '=', 'movimientos.id_modulo')
                     ->on('movimientos.modulo', '=', DB::raw('1'));
            })
            ->leftjoin('ingresos_factura as if', 'if.ingreso', '=', 'i.id')
            ->leftjoin('factura as f', 'f.id', '=', 'if.factura')
            ->select('movimientos.*', DB::raw('if(movimientos.contacto,c.nombre,"") as nombrecliente'), 'f.id as facturaId')
            ->where('movimientos.fecha', '>=', $dates['inicio'])
            ->where('movimientos.fecha', '<=', $dates['fin'])
            ->where('movimientos.estatus','<>',2)
            ->where('movimientos.banco', 13)
            ->where('movimientos.empresa', 1)
            ->groupBy('movimientos.id')
            ->orderBy('movimientos.fecha', 'DESC');
            
        $this->info("SQL: " . $movimientos->toSql());
        
        foreach($movimientos->get() as $m) {
            $this->info("ID: {$m->id} Tipo: {$m->tipo} Fact: " . ($m->facturaId ?? 'NULL') . " Saldo: {$m->saldo}");
        }
    }
}
