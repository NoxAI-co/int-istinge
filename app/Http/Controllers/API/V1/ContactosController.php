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

        return response()->json([
            'status' => 200,
            'message' => 'Lista de contactos',
            'data' => $contactos->items(),
            'meta' => [
                'current_page' => $contactos->currentPage(),
                'last_page' => $contactos->lastPage(),
                'per_page' => $contactos->perPage(),
                'total' => $contactos->total(),
            ]
        ], 200);
    }
}
