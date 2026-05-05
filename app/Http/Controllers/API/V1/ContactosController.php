<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Contacto;

class ContactosController extends Controller
{
    /**
     * API paginada de contactos con filtros por identificación.
     */
    public function index(Request $request)
    {
        $query = Contacto::query();
        
        // Filtro por identificación
        if ($request->has('identificacion') && !empty($request->identificacion)) {
            $query->where('nit', 'like', '%' . $request->identificacion . '%');
        }

        // Otros filtros opcionales
        if ($request->has('nombre') && !empty($request->nombre)) {
            $query->where('nombre', 'like', '%' . $request->nombre . '%');
        }
        
        if ($request->has('celular') && !empty($request->celular)) {
            $query->where('celular', 'like', '%' . $request->celular . '%');
        }

        // Se usa la paginación dinámica, por defecto 15 por página si no se especifica 'per_page'
        $perPage = $request->input('per_page', 15);
        $contactos = $query->paginate($perPage);

        $mappedItems = [];
        foreach ($contactos->items() as $contacto) {
            $arr = $contacto->toArray();
            if (isset($arr['contracts']) && is_array($arr['contracts'])) {
                foreach ($arr['contracts'] as &$contratoArr) {
                    if (isset($contratoArr['id'])) {
                        $contratoModel = \App\Contrato::find($contratoArr['id']);
                        if ($contratoModel) {
                            $contratoArr['plan_detalles'] = $contratoModel->plan_detalles_api;
                        }
                    }
                }
            }
            $mappedItems[] = $arr;
        }

        return response()->json([
            'status' => 200,
            'message' => 'Lista de contactos',
            'data' => $mappedItems,
            'meta' => [
                'current_page' => $contactos->currentPage(),
                'last_page' => $contactos->lastPage(),
                'per_page' => $contactos->perPage(),
                'total' => $contactos->total(),
            ]
        ], 200);
    }
}
