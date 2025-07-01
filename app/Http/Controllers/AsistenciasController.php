<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use App\Asistencia;
use Carbon\Carbon;
use Auth;
use DB;
use Illuminate\Support\Facades\Response;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Maatwebsite\Excel\Facades\Excel;

class AsistenciasController extends Controller
{
    public function __construct()
    {
        // Aplicar middleware auth excepto para los métodos públicos del QR
        $this->middleware('auth')->except(['paginaMarcar', 'marcar']);
        view()->share([
            'seccion' => 'asistencias',
            'title' => 'Control de Asistencias', 
            'icon' => 'fas fa-clock'
        ]);
    }

    /**
     * Mostrar lista de empleados para control de asistencia
     */
    public function index()
    {
        $this->getAllPermissions(Auth::user()->id);
        view()->share(['subseccion' => 'asistencias-empleados']);
        
        // Obtener empleados de la empresa
        $empleados = User::where('empresa', Auth::user()->empresa)
            ->where('user_status', 1)
            ->where('rol', '>', 1) // Excluir super admin
            ->with(['roles'])
            ->get();

        // Obtener registros de hoy para cada empleado
        $hoy = Carbon::today();
        foreach ($empleados as $empleado) {
            $empleado->ultimo_registro_hoy = Asistencia::ultimoRegistroDelDia($empleado->id, $hoy);
            $empleado->marco_ingreso_hoy = Asistencia::yaMarcoIngresoHoy($empleado->id, $hoy);
            $empleado->horas_trabajadas_hoy = Asistencia::horasTrabajadasDelDia($empleado->id, $hoy);
            
            // Obtener el nombre del rol manualmente si la relación no funciona
            if (!$empleado->roles || !isset($empleado->roles->rol)) {
                $empleado->nombre_rol = \App\Roles::where('id', $empleado->rol)->value('rol') ?? 'Sin rol';
            } else {
                $empleado->nombre_rol = $empleado->roles->rol;
            }
        }

        return view('asistencias.index', compact('empleados'));
    }

    /**
     * Mostrar QR del empleado para marcar asistencia
     */
    public function mostrarQR($usuarioId)
    {
        $this->getAllPermissions(Auth::user()->id);
        view()->share(['subseccion' => 'asistencias-empleados']);
        
        $usuario = User::where('id', $usuarioId)
            ->where('empresa', Auth::user()->empresa)
            ->firstOrFail();

        // Generar URL única para el usuario
        $url = route('asistencias.marcar', ['token' => $this->generarToken($usuarioId)]);
        
        // Generar código QR
        $qrCode = QrCode::size(300)->generate($url);
        
        // Indicar que vino de la lista (no del navbar)
        $desdeNavbar = false;

        return view('asistencias.qr', compact('usuario', 'qrCode', 'url', 'desdeNavbar'));
    }

    /**
     * Mostrar QR del usuario autenticado
     */
    public function miQR()
    {
        $this->getAllPermissions(Auth::user()->id);
        view()->share(['subseccion' => 'asistencias-empleados']);
        
        $usuario = Auth::user();

        // Generar URL única para el usuario
        $url = route('asistencias.marcar', ['token' => $this->generarToken($usuario->id)]);
        
        // Generar código QR
        $qrCode = QrCode::size(300)->generate($url);
        
        // Indicar que vino del navbar (no de la lista)
        $desdeNavbar = true;

        return view('asistencias.qr', compact('usuario', 'qrCode', 'url', 'desdeNavbar'));
    }

    /**
     * Obtener el estado actual de asistencia del usuario autenticado
     */
    public function estadoActual()
    {
        $usuarioId = Auth::user()->id;
        $ultimoRegistro = Asistencia::ultimoRegistroDelDia($usuarioId);
        
        $response = [
            'usuario_id' => $usuarioId,
            'ultimo_registro' => $ultimoRegistro ? [
                'tipo' => $ultimoRegistro->tipo,
                'fecha_hora' => $ultimoRegistro->fecha_hora_formateada,
                'hora' => $ultimoRegistro->hora,
                'fecha' => $ultimoRegistro->fecha
            ] : null,
            'ya_marco_ingreso' => Asistencia::yaMarcoIngresoHoy($usuarioId),
            'horas_trabajadas' => Asistencia::horasTrabajadasDelDia($usuarioId)
        ];
        
        return response()->json($response);
    }

    /**
     * Página para marcar asistencia desde QR
     */
    public function paginaMarcar($token)
    {
        // No compartir subsección para evitar errores con navbar cuando no hay usuario autenticado
        view()->share(['title' => 'Marcar Asistencia']);
        
        $usuarioId = $this->decodificarToken($token);
        
        if (!$usuarioId) {
            abort(404, 'Token inválido');
        }

        $usuario = User::findOrFail($usuarioId);
        $ultimoRegistro = Asistencia::ultimoRegistroDelDia($usuarioId);
        $yaMarcoIngreso = Asistencia::yaMarcoIngresoHoy($usuarioId);

        // Determinar qué tipo de marcación corresponde
        $proximoTipo = 'ingreso';
        if ($yaMarcoIngreso) {
            // Si ya marcó ingreso, verificar si el último registro fue ingreso o salida
            if ($ultimoRegistro && $ultimoRegistro->tipo == 'ingreso') {
                $proximoTipo = 'salida';
            } else {
                $proximoTipo = 'ingreso';
            }
        }

        return view('asistencias.marcar', compact('usuario', 'token', 'proximoTipo', 'ultimoRegistro'));
    }

    /**
     * Marcar asistencia (ingreso o salida)
     */
    public function marcar(Request $request, $token)
    {
        $usuarioId = $this->decodificarToken($token);
        
        if (!$usuarioId) {
            return response()->json(['error' => 'Token inválido'], 400);
        }

        $usuario = User::findOrFail($usuarioId);
        $tipo = $request->input('tipo', 'ingreso');

        // Validar que el tipo sea válido
        if (!in_array($tipo, ['ingreso', 'salida'])) {
            return response()->json(['error' => 'Tipo de marcación inválido'], 400);
        }

        // Obtener información del dispositivo
        $ipAddress = $request->ip();
        $userAgent = $request->header('User-Agent');
        $dispositivoInfo = $this->obtenerInfoDispositivo($request);

        try {
            // Crear el registro de asistencia
            $asistencia = new Asistencia();
            $asistencia->usuario_id = $usuarioId;
            $asistencia->tipo = $tipo;
            $asistencia->fecha_hora = Carbon::now();
            $asistencia->ip_address = $ipAddress;
            $asistencia->user_agent = $userAgent;
            $asistencia->dispositivo_info = $dispositivoInfo;
            $asistencia->save();

            $mensaje = $tipo == 'ingreso' ? 
                'Ingreso registrado exitosamente' : 
                'Salida registrada exitosamente';

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'mensaje' => $mensaje,
                    'asistencia' => $asistencia
                ]);
            }

            return redirect()->back()->with('success', $mensaje);

        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json(['error' => 'Error al registrar asistencia'], 500);
            }
            
            return redirect()->back()->with('error', 'Error al registrar asistencia');
        }
    }

    /**
     * Marcar asistencia desde el panel administrativo
     */
    public function marcarAdmin(Request $request)
    {
        $request->validate([
            'usuario_id' => 'required|exists:usuarios,id',
            'tipo' => 'required|in:ingreso,salida'
        ]);

        $usuario = User::where('id', $request->usuario_id)
            ->where('empresa', Auth::user()->empresa)
            ->firstOrFail();

        // Obtener información del dispositivo
        $ipAddress = $request->ip();
        $userAgent = $request->header('User-Agent');
        $dispositivoInfo = $this->obtenerInfoDispositivo($request);

        try {
            // Crear el registro de asistencia
            $asistencia = new Asistencia();
            $asistencia->usuario_id = $request->usuario_id;
            $asistencia->tipo = $request->tipo;
            $asistencia->fecha_hora = Carbon::now();
            $asistencia->ip_address = $ipAddress;
            $asistencia->user_agent = $userAgent;
            $asistencia->dispositivo_info = $dispositivoInfo . ' (Marcado por admin: ' . Auth::user()->nombres . ')';
            $asistencia->save();

            $mensaje = $request->tipo == 'ingreso' ? 
                'Ingreso registrado exitosamente para ' . $usuario->nombres : 
                'Salida registrada exitosamente para ' . $usuario->nombres;

            return response()->json([
                'success' => true,
                'mensaje' => $mensaje
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al registrar asistencia'], 500);
        }
    }

    /**
     * Mostrar reportes de asistencias
     */
    public function reportes(Request $request)
    {
        $this->getAllPermissions(Auth::user()->id);
        view()->share(['subseccion' => 'asistencias-reportes']);
        
        // Obtener empleados de la empresa
        $empleados = User::where('empresa', Auth::user()->empresa)
            ->where('user_status', 1)
            ->where('rol', '>', 1)
            ->get();
            
        // Agregar nombre del rol a cada empleado
        foreach ($empleados as $empleado) {
            $empleado->nombre_rol = \App\Roles::where('id', $empleado->rol)->value('rol') ?? 'Sin rol';
        }

        // Filtros
        $usuarioId = $request->get('usuario_id');
        $fechaInicio = $request->get('fecha_inicio', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $fechaFin = $request->get('fecha_fin', Carbon::now()->format('Y-m-d'));

        // Query base
        $query = Asistencia::with('usuario')
            ->whereHas('usuario', function($q) {
                $q->where('empresa', Auth::user()->empresa);
            });

        // Aplicar filtros
        if ($usuarioId) {
            $query->where('usuario_id', $usuarioId);
        }

        if ($fechaInicio) {
            $query->whereDate('fecha_hora', '>=', $fechaInicio);
        }

        if ($fechaFin) {
            $query->whereDate('fecha_hora', '<=', $fechaFin);
        }

        $asistencias = $query->orderBy('fecha_hora', 'desc')->paginate(50);

        view()->share(['title' => 'Reportes de Asistencias']);
        
        return view('asistencias.reportes', compact(
            'asistencias', 
            'empleados', 
            'usuarioId', 
            'fechaInicio', 
            'fechaFin'
        ));
    }

    /**
     * Exportar reporte a Excel
     */
    public function exportarExcel(Request $request)
    {
        $usuarioId = $request->get('usuario_id');
        $fechaInicio = $request->get('fecha_inicio', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $fechaFin = $request->get('fecha_fin', Carbon::now()->format('Y-m-d'));

        // Query base
        $query = Asistencia::with('usuario')
            ->whereHas('usuario', function($q) {
                $q->where('empresa', Auth::user()->empresa);
            });

        // Aplicar filtros
        if ($usuarioId) {
            $query->where('usuario_id', $usuarioId);
        }

        if ($fechaInicio) {
            $query->whereDate('fecha_hora', '>=', $fechaInicio);
        }

        if ($fechaFin) {
            $query->whereDate('fecha_hora', '<=', $fechaFin);
        }

        $asistencias = $query->orderBy('fecha_hora', 'desc')->get();

        // Crear archivo CSV
        $fileName = 'reporte_asistencias_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = function() use ($asistencias) {
            $file = fopen('php://output', 'w');
            
            // Agregar BOM para UTF-8
            fputs($file, $bom = chr(0xEF) . chr(0xBB) . chr(0xBF));
            
            // Encabezados
            fputcsv($file, [
                'Empleado',
                'Fecha',
                'Hora',
                'Tipo',
                'IP',
                'Dispositivo'
            ], ';');

            // Datos
            foreach ($asistencias as $asistencia) {
                fputcsv($file, [
                    $asistencia->usuario->nombres,
                    $asistencia->fecha,
                    $asistencia->hora,
                    ucfirst($asistencia->tipo),
                    $asistencia->ip_address,
                    $asistencia->dispositivo_info
                ], ';');
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * Generar token único para el usuario
     */
    private function generarToken($usuarioId)
    {
        return base64_encode($usuarioId . '|' . time() . '|' . config('app.key'));
    }

    /**
     * Decodificar token y verificar validez
     */
    private function decodificarToken($token)
    {
        try {
            $decoded = base64_decode($token);
            $parts = explode('|', $decoded);
            
            if (count($parts) !== 3) {
                return false;
            }

            $usuarioId = $parts[0];
            $timestamp = $parts[1];
            $key = $parts[2];

            // Verificar que el key coincida
            if ($key !== config('app.key')) {
                return false;
            }

            // Verificar que el token no sea muy viejo (24 horas)
            if (time() - $timestamp > 86400) {
                return false;
            }

            return $usuarioId;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Obtener información del dispositivo
     */
    private function obtenerInfoDispositivo(Request $request)
    {
        $userAgent = $request->header('User-Agent');
        $info = [];

        // Detectar sistema operativo
        if (strpos($userAgent, 'Windows') !== false) {
            $info[] = 'Windows';
        } elseif (strpos($userAgent, 'Mac') !== false) {
            $info[] = 'Mac';
        } elseif (strpos($userAgent, 'Linux') !== false) {
            $info[] = 'Linux';
        } elseif (strpos($userAgent, 'Android') !== false) {
            $info[] = 'Android';
        } elseif (strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false) {
            $info[] = 'iOS';
        }

        // Detectar navegador
        if (strpos($userAgent, 'Chrome') !== false) {
            $info[] = 'Chrome';
        } elseif (strpos($userAgent, 'Firefox') !== false) {
            $info[] = 'Firefox';
        } elseif (strpos($userAgent, 'Safari') !== false) {
            $info[] = 'Safari';
        } elseif (strpos($userAgent, 'Edge') !== false) {
            $info[] = 'Edge';
        }

        return implode(', ', $info);
    }
}
