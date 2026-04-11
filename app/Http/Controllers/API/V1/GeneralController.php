<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Contacto;
use App\Contrato;
use App\Empresa;
use App\Servicio;
use App\Radicado;
use App\User;
use App\MovimientoLOG;
use App\RadicadoLOG;
use DB;
use Illuminate\Support\Facades\Log;

class GeneralController extends Controller
{
    /**
     * /deudacontrato
     */
    public function deudacontrato(Request $request)
    {
        if (isset($request->contrato_nro)) {
            $contrato = Contrato::where('nro', $request->contrato_nro)->first();
            if ($contrato) {
                $deuda = "$" . \App\Funcion::Parsear($contrato->deudaFacturas());
                $contrato->deuda = $deuda;

                return response()->json(['data' => $contrato, 'status' => 200, 'multicontratos' => false]);
            } else {
                return response()->json(['status' => 400, 'message' => 'No se encontraron datos']);
            }
        }

        if (isset($request->identificacion)) {
            $cliente = Contacto::where('nit', $request->identificacion)->first();
            if ($cliente) {
                $contratos = Contrato::where('client_id', $cliente->id)->get();

                if (count($contratos) == 0) {
                    return response()->json(['status' => 400, 'message' => 'No se encontraron datos']);
                }

                if (count($contratos) > 1) {
                    return response()->json(['data' => $contratos, 'status' => 200, 'multicontratos' => true]);
                } else {
                    $contrato = $contratos->first();
                    $deuda = "$" . \App\Funcion::Parsear($contrato->deudaFacturas());
                    $contrato->deuda = $deuda;

                    return response()->json(['data' => $contrato, 'status' => 200, 'multicontratos' => false]);
                }
            } else {
                return response()->json(['status' => 400, 'message' => 'No se encontraron clientes con esa cédula']);
            }
        }
        
        return response()->json(['status' => 400, 'message' => 'Parámetros insuficientes']);
    }

    /**
     * /medios-pago
     */
    public function mediosPago()
    {
        $empresa = Empresa::Find(1); // O tomar del usuario autenticado si es dinámico
        $medios_pago = DB::table('metodos_pago')->get();
        return response()->json(['data' => $empresa ? $medios_pago : null, 'status' => 200]);
    }

    /**
     * /tipos-servicio
     */
    public function tiposServicio()
    {
        $servicios = Servicio::where('estatus', 1)->get();
        return response()->json(['data' => $servicios, 'status' => 200]);
    }

    /**
     * /create-radicado
     */
    public function createRadicado(Request $request)
    {
        // Registrar toda la data recibida para diagnóstico
        $data = $request->json()->all() ?: $request->all();
        Log::info('Request JSON Radicado (API V1):', $data);

        // Verificar que se recibieron los datos esperados
        if (
            !isset($data['servicio']) ||
            !isset($data['identificacion']) ||
            !isset($data['contrato']) ||
            !isset($data['observaciones'])
        ) {
            return response()->json([
                'status'  => 400,
                'message' => 'Formato de solicitud inválido. Faltan datos.'
            ], 400);
        }

        // Buscar registros relacionados
        $cliente = Contacto::where('nit', $data['identificacion'])->first();
        $servicio = Servicio::find($data['servicio']);
        $contrato = Contrato::where('nro', $data['contrato'])->first();
        $tecnico = User::where('empresa', 1)->where('rol', 4)->first();

        try {
            if ($servicio && $cliente && $contrato) {
                // Validación para servicios distintos
                if (!isset($data['contrato']) && isset($data['servicio']) && $data['servicio'] != 4) {
                    $nombreServicio = trim(strtolower($servicio->nombre));
                    if (
                        $nombreServicio != 'notificacion de data creditos' &&
                        $nombreServicio != 'notificacion de datacreditos' &&
                        $nombreServicio != 'notificacion datacredito' &&
                        $nombreServicio != 'notificacion de datacredito'
                    ) {
                        $mensaje = 'El cliente no posee contrato asignado y no puede hacer uso de un servicio distinto a instalaciones o notificacion de datacredito';
                        return response()->json(['status' => 400, 'message' => $mensaje]);
                    }
                }
            } else {
                $mensaje = "No se encontró el servicio solicitado o el cliente o el contrato";
                return response()->json(['status' => 400, 'message' => $mensaje]);
            }

            $radicado = new Radicado();
            $radicado->fecha = \Carbon\Carbon::now()->format('Y-m-d');
            $radicado->identificacion = $data['identificacion'];
            $radicado->cliente = $cliente->id;
            $radicado->nombre = $cliente->nombre . " " . $cliente->apellido1 . " " . $cliente->apellido2;
            $radicado->telefono = $cliente->celular;
            $radicado->correo = $cliente->email;
            $radicado->direccion = $cliente->direccion;
            $radicado->contrato = $contrato->nro;
            $radicado->desconocido = $data['observaciones'];
            $radicado->servicio = $servicio->id;
            $radicado->tecnico = $tecnico ? $tecnico->id : null;
            $radicado->estatus = 0;
            $radicado->codigo = Radicado::getNextConsecutiveCodeNumber();
            $radicado->prioridad = 2;
            $radicado->mac_address = $contrato->mac_address;
            $radicado->ip = $contrato->ip;
            $radicado->empresa = 1;
            $radicado->valor = null;
            $radicado->barrio = $cliente->barrio;
            $radicado->save();

            if (isset($data['contrato'])) {
                $movimiento = new MovimientoLOG();
                $movimiento->contrato = $contrato->nro;
                $movimiento->modulo = 5;
                $movimiento->descripcion = '<i class="fas fa-check text-success"></i> <b>Generación de Radicado (API)</b> Servicio ' . ($radicado->servicio() ? $radicado->servicio()->nombre : '') . ' N° ' . $radicado->codigo;
                $movimiento->empresa = 1;
                $movimiento->save();

                if (isset($data['deshabilitar_contrato']) && $data['deshabilitar_contrato'] == 1) {
                    $contrato->update(["status" => 0]);
                }
            }

            $log = new RadicadoLOG();
            $log->id_radicado = $radicado->id;
            $log->accion = 'Creación del radicado bajo el código #' . $radicado->codigo;
            $log->save();

            $mensaje = 'Se ha creado satisfactoriamente el radicado bajo el código #' . $radicado->codigo;
            return response()->json(['status' => 200, 'data' => $radicado, 'message' => $mensaje]);

        } catch (\Throwable $th) {
            Log::error('Error creando radicado API: ' . $th->getMessage());
            return response()->json(['status' => 500, 'message' => 'Error interno en el servidor'], 500);
        }
    }
}
