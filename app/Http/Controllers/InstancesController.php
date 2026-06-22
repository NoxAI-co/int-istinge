<?php

namespace App\Http\Controllers;

use App\Instance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class InstancesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        view()->share(['seccion' => 'configuracion', 'title' => 'Instancias WhatsApp Meta', 'icon' => 'fab fa-whatsapp']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->getAllPermissions(Auth::user()->id);
        $instances = Instance::where('company_id', Auth::user()->empresa)
            ->where('meta', 0)
            ->orderBy('created_at', 'desc')
            ->get();
        
        return view('configuracion.instances.index')->with(compact('instances'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Validar campos requeridos
        $request->validate([
            'phone_number_id' => 'required|string|max:255',
            'waba_id' => 'required|string|max:255',
            'access_token' => 'nullable|string',
        ]);

        // Validar que ACCESS_TOKEN_META esté en .env (opcional, pero buena práctica)
        $accessTokenMeta = env('ACCESS_TOKEN_META');
        
        if (empty($accessTokenMeta)) {
             Log::warning('ACCESS_TOKEN_META no está configurado en .env al crear instancia Meta.');
             // No bloqueamos la creación, pero avisamos en log
        }

        try {
            // Verificar si ya existe una instancia con este phone_number_id
            $existingInstance = Instance::where('phone_number_id', $request->phone_number_id)
                ->where('company_id', Auth::user()->empresa)
                ->first();

            if ($existingInstance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe una instancia registrada con este Phone Number ID.'
                ], 400);
            }

            // Solo se permite UNA instancia activa por empresa (la que usa todo el
            // sistema para Meta). Si ya hay una activa, la nueva nace inactiva para
            // no interrumpir el envío actual; el operador la activa cuando quiera.
            $yaHayActiva = Instance::where('company_id', Auth::user()->empresa)
                ->where('meta', 0)->where('activo', 1)->exists();

            // Crear la instancia en la base de datos DIRECTAMENTE (Sin VibioCRM)
            // Usamos phone_number_id como identificador único (uuid/api_key) para consistencia
            $instance = Instance::create([
                'uuid' => $request->phone_number_id,
                'api_key' => $request->phone_number_id, // Usamos el ID como key
                'uuid_whatsapp' => $request->phone_number_id,
                'company_id' => Auth::user()->empresa,
                'status' => 'PAIRED', // Meta Direct se asume "conectado" si tiene las credenciales
                'type' => 1, // 1 = Meta Direct
                'meta' => 0, // 0 = Usamos integración directa (sin Wapi middleware)
                'activo' => $yaHayActiva ? 0 : 1,
                'phone_number_id' => $request->phone_number_id,
                'waba_id' => $request->waba_id,
                'access_token' => $request->access_token,
                'addr' => url(''), // Dirección base del sistema
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Instancia Meta creada correctamente.',
                'instance' => $instance
            ]);

        } catch (\Exception $e) {
            Log::error('Error inesperado al crear instancia WhatsApp Meta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la instancia: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $instance = Instance::where('id', $id)
                ->where('company_id', Auth::user()->empresa)
                ->where('meta', 0)
                ->first();

            if (!$instance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Instancia no encontrada'
                ], 404);
            }

            $instance->delete();

            return response()->json([
                'success' => true,
                'message' => 'Instancia eliminada correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar instancia: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la instancia: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'phone_number_id' => 'required|string|max:255',
            'waba_id' => 'required|string|max:255',
            'access_token' => 'nullable|string',
        ]);

        try {
            $instance = Instance::where('id', $id)
                ->where('company_id', Auth::user()->empresa)
                ->where('meta', 0)
                ->first();

            if (!$instance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Instancia no encontrada'
                ], 404);
            }

            // Verificar si el phone_number_id se cambió y si ya existe
            if ($instance->phone_number_id !== $request->phone_number_id) {
                $existingInstance = Instance::where('phone_number_id', $request->phone_number_id)
                    ->where('company_id', Auth::user()->empresa)
                    ->where('id', '!=', $id)
                    ->first();

                if ($existingInstance) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ya existe otra instancia registrada con este Phone Number ID.'
                    ], 400);
                }
            }

            $instance->phone_number_id = $request->phone_number_id;
            $instance->waba_id = $request->waba_id;
            $instance->access_token = $request->access_token;
            $instance->uuid = $request->phone_number_id;
            $instance->api_key = $request->phone_number_id;
            $instance->uuid_whatsapp = $request->phone_number_id;
            
            $instance->save();

            return response()->json([
                'success' => true,
                'message' => 'Instancia Meta actualizada correctamente.',
                'instance' => $instance
            ]);

        } catch (\Exception $e) {
            Log::error('Error inesperado al actualizar instancia WhatsApp Meta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la instancia: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Habilita o deshabilita una instancia Meta. Solo puede haber UNA activa por
     * empresa: al activar una, las demás quedan inactivas. Todo el sistema usa la
     * instancia activa para sus consultas/envíos de Meta (filtran where activo=1).
     */
    public function toggle($id)
    {
        try {
            $empresaId = Auth::user()->empresa;

            $instance = Instance::where('id', $id)
                ->where('company_id', $empresaId)
                ->where('meta', 0)
                ->first();

            if (!$instance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Instancia no encontrada'
                ], 404);
            }

            $activar = ! $instance->activo;

            DB::transaction(function () use ($instance, $empresaId, $activar) {
                if ($activar) {
                    // Exclusividad: desactivar el resto de instancias Meta de la empresa.
                    Instance::where('company_id', $empresaId)
                        ->where('meta', 0)
                        ->where('id', '!=', $instance->id)
                        ->update(['activo' => 0]);
                }
                $instance->activo = $activar ? 1 : 0;
                $instance->save();
            });

            return response()->json([
                'success' => true,
                'activo'  => (bool) $instance->activo,
                'message' => $activar
                    ? 'Instancia habilitada. El sistema usará esta instancia para WhatsApp Meta.'
                    : 'Instancia deshabilitada.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error al cambiar estado de instancia WhatsApp Meta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar el estado de la instancia: ' . $e->getMessage()
            ], 500);
        }
    }
}

