<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MikrotikService;
use App\Mikrotik;
use Auth;

class MorososController extends Controller
{
    protected $mikrotikService;

    public function __construct(MikrotikService $mikrotikService)
    {
        $this->middleware('auth');
        $this->mikrotikService = $mikrotikService;
        view()->share(['seccion' => 'contratos', 'subseccion' => 'clientes', 'title' => 'Lista de Morosos', 'icon' => 'fas fa-users']);
    }

    public function index()
    {
        $this->getAllPermissions(Auth::user()->id);
        $mikrotiks = Mikrotik::where('empresa', Auth::user()->empresa)->get();
        return view('mikrotik.morosos', compact('mikrotiks'));
    }

    public function listar(Request $request)
    {
        if (!$request->has('mikrotik_id')) {
            return response()->json([
                'success' => false,
                'message' => 'Seleccione una Mikrotik'
            ]);
        }

        $result = $this->mikrotikService->getMorosos($request->mikrotik_id);
        return response()->json($result);
    }

    public function sacarMoroso(Request $request)
    {
        $request->validate([
            'mikrotik_id' => 'required',
            'ip' => 'required'
        ]);

        $contrato = null;
        if ($request->filled('contrato_id')) {
            $contrato = \App\Contrato::find($request->contrato_id);
        }

        // 1. Eliminar de Mikrotik
        $removed = $this->mikrotikService->removerMoroso($request->mikrotik_id, $request->ip);

        if ($removed) {
            if ($contrato) {
                // 2. Activar contrato
                $contrato->state = 'enabled';
                $contrato->save();

                // 3. Registrar Log
                $movimiento = new \App\MovimientoLOG;
                $movimiento->contrato    = $contrato->id;
                $movimiento->modulo      = 5; // Modulo 5 indicado por el usuario
                $movimiento->descripcion = "Se sacó de morosos manualmente y se reactivó el contrato";
                $movimiento->created_by  = Auth::user()->id;
                $movimiento->empresa     = Auth::user()->empresa;
                $movimiento->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Contrato removido de morosos y activado correctamente'
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'IP removida de morosos correctamente'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No se pudo remover del Mikrotik. Verifique la conexión.'
        ]);
    }

    public function sacarMorososMasivo(Request $request)
    {
        $request->validate([
            'mikrotik_id' => 'required'
        ]);

        // 1. Obtener la lista de morosos para esta Mikrotik
        $result = $this->mikrotikService->getMorosos($request->mikrotik_id);
        
        if (!$result['success'] || empty($result['data'])) {
            return response()->json(['success' => false, 'message' => 'No se encontraron morosos o hubo un error al consultar.']);
        }

        $exitosos = 0;
        $fallidos = 0;

        foreach ($result['data'] as $item) {
            // Solo procesar los que tienen discrepancia (PAGADA) y tienen contrato asociado
            if ($item['tiene_discrepancia'] && isset($item['contrato']['id'])) {
                $removed = $this->mikrotikService->removerMoroso($request->mikrotik_id, $item['ip']);

                if ($removed) {
                    $contrato = \App\Contrato::find($item['contrato']['id']);
                    if ($contrato) {
                        $contrato->state = 'enabled';
                        $contrato->save();

                        // Registrar Log
                        $movimiento = new \App\MovimientoLOG;
                        $movimiento->contrato    = $contrato->id;
                        $movimiento->modulo      = 5;
                        $movimiento->descripcion = "Se sacó de morosos por la acción en lote (Discrepancia resuelta)";
                        $movimiento->created_by  = Auth::user()->id;
                        $movimiento->empresa     = Auth::user()->empresa;
                        $movimiento->save();
                        
                        $exitosos++;
                    } else {
                        $fallidos++;
                    }
                } else {
                    $fallidos++;
                }
            }
        }

        if ($exitosos > 0) {
            return response()->json([
                'success' => true,
                'message' => "Se procesaron $exitosos discrepancias con éxito." . ($fallidos > 0 ? " Errores: $fallidos" : "")
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No se encontraron discrepancias para solucionar o hubo un error en todos los intentos.'
        ]);
    }


    // --- NUEVAS FUNCIONES PARA DISCREPANCIAS DE DISABLED ---

    /**
     * Compara contratos 'disabled' en BD con la lista de morosos en Mikrotik.
     * Retorna conteo y lista de contratos que NO estan en Mikrotik.
     */
    public function checkDisabledButNotListed(Request $request)
    {
        if (!$request->has('mikrotik_id')) {
            return response()->json(['success' => false, 'message' => 'Falta mikrotik_id']);
        }

        $mikrotikId = $request->mikrotik_id;
        
        // 1. Obtener lista de morosos actual (Solo IPs)
        $morosos = $this->mikrotikService->getMorosos($mikrotikId);
        $morososIps = [];
        if ($morosos['success']) {
            foreach ($morosos['data'] as $m) {
                if ($m['ip']) $morososIps[] = $m['ip'];
            }
        }

        // 2. Obtener contratos disabled asociados a esta Mikrotik
        // Asumiendo que state='disabled' es lo que buscamos
        $contratosDisabled = \App\Contrato::where('server_configuration_id', $mikrotikId)
            ->where('state', 'disabled')
            ->where('status', 1)
            ->whereNotNull('ip')
            ->get();

        $discrepancias = [];

        foreach ($contratosDisabled as $contrato) {
            if (!in_array($contrato->ip, $morososIps)) {
                $cliente = $contrato->cliente();
                $discrepancias[] = [
                    'id' => $contrato->id,
                    'nro' => $contrato->nro,
                    'cliente_nombre' => $cliente->nombre ?? 'Desconocido',
                    'apellido1' => $cliente->apellido1 ?? '',
                    'ip' => $contrato->ip,
                    'mikrotik_id' => $mikrotikId
                ];
            }
        }

        return response()->json([
            'success' => true,
            'count' => count($discrepancias),
            'data' => $discrepancias
        ]);
    }

    public function indexDisabledDiscrepancy(Request $request)
    {
        $this->getAllPermissions(Auth::user()->id);
        $mikrotikId = $request->mikrotik_id;
        $mikrotik = Mikrotik::find($mikrotikId);
        
        if (!$mikrotik) {
            return redirect()->back()->with('danger', 'Mikrotik no encontrada');
        }

        // Reutilizamos la lógica de checkDisabledButNotListed pero internamente
        // para pasar los datos a la vista
        $req = new Request(['mikrotik_id' => $mikrotikId]);
        $data = $this->checkDisabledButNotListed($req)->getData(true);
        $discrepancias = $data['data'];

        view()->share(['seccion' => 'contratos', 'subseccion' => 'clientes', 'title' => 'Contratos Deshabilitados sin Bloqueo - ' . $mikrotik->nombre, 'icon' => 'fas fa-user-slash']);
        
        return view('mikrotik.discrepancias_disabled', compact('discrepancias', 'mikrotik'));
    }

    public function deshabilitadosNavegando()
    {
        $this->getAllPermissions(Auth::user()->id);
        $mikrotiks = Mikrotik::where('empresa', Auth::user()->empresa)->get();
        
        view()->share([
            'seccion' => 'contratos',
            'subseccion' => 'clientes',
            'title' => 'Deshabilitados Y Navegando',
            'icon' => 'fas fa-unlink'
        ]);
        
        return view('mikrotik.deshabilitados_navegando', compact('mikrotiks'));
    }

    public function fixDisabledDiscrepancy(Request $request)
    {
        $request->validate([
            'mikrotik_id' => 'required',
            'contrato_id' => 'required',
            'ip' => 'required'
        ]);

        $contrato = \App\Contrato::find($request->contrato_id);
        if (!$contrato) {
            return response()->json(['success' => false, 'message' => 'Contrato no encontrado']);
        }
        
        // Lógica solicitada: comment = servicio
        $servicio = $contrato->plan()->name;

        // Agregar a Morosos DIRECTO
        $added = $this->mikrotikService->agregarMorosoDirecto($request->mikrotik_id, $request->ip, $servicio);

        if ($added) {
            // Log
            $movimiento = new \App\MovimientoLOG;
            $movimiento->contrato    = $contrato->id;
            $movimiento->modulo      = 5;
            $movimiento->descripcion = "Se agregó a morosos por corrección de discrepancia (Disabled en BD pero no en Mikrotik)";
            $movimiento->created_by  = Auth::user()->id;
            $movimiento->empresa     = Auth::user()->empresa;
            $movimiento->save();

            return response()->json(['success' => true, 'message' => 'Agregado a morosos correctamente']);
        }

        return response()->json(['success' => false, 'message' => 'Error al agregar a Mikrotik']);
    }

    public function fixDisabledDiscrepancyBatch(Request $request)
    {
        $request->validate([
            'mikrotik_id' => 'required'
        ]);

        // Obtener lista fresca de discrepancias
        $req = new Request(['mikrotik_id' => $request->mikrotik_id]);
        $data = $this->checkDisabledButNotListed($req)->getData(true);
        $discrepancias = $data['data'];

        if (empty($discrepancias)) {
            return response()->json(['success' => false, 'message' => 'No hay discrepancias para procesar']);
        }

        $exitosos = 0;
        $fallidos = 0;

        foreach ($discrepancias as $item) {
            // Lógica solicitada: comment = servicio
            $servicio = 'N/A';
            // Need to fetch contract again or if $item has enough info?
            // $item in checkDisabledButNotListed returns: id, nro, cliente_nombre, ip...
            // It does NOT return plan/service. 
            // So we must fetch contract to get the plan name.

            $contratoItem = \App\Contrato::find($item['id']);
            if ($contratoItem) {
                $servicio = $contratoItem->plan()->name;
            }

            $added = $this->mikrotikService->agregarMorosoDirecto($request->mikrotik_id, $item['ip'], $servicio);

            if ($added) {
                // Log
                $movimiento = new \App\MovimientoLOG;
                $movimiento->contrato    = $item['id'];
                $movimiento->modulo      = 5;
                $movimiento->descripcion = "Se agregó a morosos por corrección de discrepancia LOTE (Disabled en BD pero no en Mikrotik)";
                $movimiento->created_by  = Auth::user()->id;
                $movimiento->empresa     = Auth::user()->empresa;
                $movimiento->save();
                $exitosos++;
            } else {
                $fallidos++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Procesados: $exitosos. Fallidos: $fallidos"
        ]);
    }
}
