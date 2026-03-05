<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\PlanesVelocidad;
use App\Mikrotik;
use App\Nodo;
use App\GrupoCorte;
use App\AP;
use App\CajaNap;
use App\Oficina;
use App\Canal;

class ExternalApiController extends Controller
{
    public function getPlanes($mikrotik = null)
    {
        $planes = PlanesVelocidad::where('status', 1)->get()->map(function($plan) {
            return [
                'id' => $plan->id,
                'nombre' => $plan->name,
                'precio' => $plan->price,
                'velocidad_bajada' => $plan->download,
                'velocidad_subida' => $plan->upload,
                'tipo' => $plan->type == 1 ? 'PCQ' : 'Simple Queue',
                'mikrotik_id' => $plan->mikrotik
            ];
        });

        return response()->json(['data' => $planes]);
    }

    public function getMikrotiks()
    {
        $mikrotiks = Mikrotik::where('status', 1)->get()->map(function($mk) {
            return [
                'id' => $mk->id,
                'nombre' => $mk->nombre,
                'ip' => $mk->ip,
                'puertos' => [
                    'api' => $mk->puerto_api,
                    'web' => $mk->puerto_web,
                    'winbox' => $mk->puerto_winbox
                ]
            ];
        });

        return response()->json(['data' => $mikrotiks]);
    }

    public function getNodos()
    {
        $nodos = Nodo::where('status', 1)->get()->map(function($nodo) {
            return [
                'id' => $nodo->id,
                'nombre' => $nodo->nombre,
                'descripcion' => $nodo->descripcion
            ];
        });

        return response()->json(['data' => $nodos]);
    }

    public function getGruposCorte()
    {
        $grupos = GrupoCorte::where('status', 1)->get()->map(function($grupo) {
            return [
                'id' => $grupo->id,
                'nombre' => $grupo->nombre,
                'fecha_corte' => $grupo->fecha_corte,
                'fecha_suspension' => $grupo->fecha_suspension
            ];
        });

        return response()->json(['data' => $grupos]);
    }

    public function getAccessPoints()
    {
        $aps = AP::where('status', 1)->get()->map(function($ap) {
            return [
                'id' => $ap->id,
                'nombre' => $ap->nombre,
                'modo_red' => $ap->modo_red == '1' ? 'Bridge' : 'Enrutador',
                'nodo_id' => $ap->nodo
            ];
        });

        return response()->json(['data' => $aps]);
    }

    public function getCajasNap()
    {
        $cajas = CajaNap::where('status', 1)->get()->map(function($caja) {
            return [
                'id' => $caja->id,
                'nombre' => $caja->nombre,
                'puertos_totales' => $caja->cant_puertos,
                'ubicacion' => $caja->ubicacion,
                'coordenadas' => $caja->coordenadas
            ];
        });

        return response()->json(['data' => $cajas]);
    }

    public function getOficinas()
    {
        $oficinas = Oficina::where('status', 1)->get()->map(function($oficina) {
            return [
                'id' => $oficina->id,
                'nombre' => $oficina->nombre,
                'direccion' => $oficina->direccion,
                'telefono' => $oficina->telefono
            ];
        });

        return response()->json(['data' => $oficinas]);
    }

    public function getCanales()
    {
        // El modelo Canal no tiene columna status visible en el archivo visto, asumo que puede no tenerla o ser diferente.
        // Revisando Canal.php, tiene metodo status() que chequea $this->status, asi que debe tener la columna.
        $canales = Canal::all()->map(function($canal) {
            return [
                'id' => $canal->id,
                'nombre' => $canal->nombre,
                'observaciones' => $canal->observaciones
            ];
        });

        return response()->json(['data' => $canales]);
    }
}
