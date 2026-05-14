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
     *
     * Devuelve el contrato vigente del cliente con: plan, usuario, ubicación,
     * deuda, contacto, historial de pagos y casos (radicados) abiertos.
     *
     * Parámetros (uno requerido):
     *   - contrato_nro: número del contrato.
     *   - identificacion: NIT / cédula del cliente.
     */
    public function deudacontrato(Request $request)
    {
        if (isset($request->contrato_nro)) {
            $contrato = Contrato::where('nro', $request->contrato_nro)->first();
            if (!$contrato) {
                return response()->json(['status' => 400, 'message' => 'No se encontraron datos']);
            }

            $this->attachContratoData($contrato);

            return response()->json([
                'data'           => $contrato,
                'status'         => 200,
                'multicontratos' => false,
            ]);
        }

        if (isset($request->identificacion)) {
            $clientes = Contacto::where('nit', $request->identificacion)->get();
            if ($clientes->count() === 0) {
                return response()->json(['status' => 400, 'message' => 'No se encontraron clientes con esa cédula']);
            }

            $ids = $clientes->pluck('id');
            $contratosAll = Contrato::whereIn('client_id', $ids)->get();

            if ($contratosAll->count() === 0) {
                return response()->json(['status' => 400, 'message' => 'No se encontraron datos']);
            }

            // Vigente = el habilitado más reciente; si no hay habilitados, el más reciente de todos.
            $habilitados = $contratosAll->where('state', 'enabled');
            $candidatos  = $habilitados->count() > 0 ? $habilitados : $contratosAll;

            $contratoVigente = $candidatos
                ->sortByDesc(function ($c) {
                    return $c->created_at ? strtotime($c->created_at) : 0;
                })
                ->first();

            $this->attachContratoData($contratoVigente);

            $tieneOtros = $contratosAll->count() > 1;
            $response = [
                'data'           => $contratoVigente,
                'status'         => 200,
                'multicontratos' => $tieneOtros,
            ];

            if ($tieneOtros) {
                $response['contratos_alternativos'] = $contratosAll
                    ->reject(function ($c) use ($contratoVigente) {
                        return $c->id === $contratoVigente->id;
                    })
                    ->map(function ($c) {
                        return [
                            'id'         => $c->id,
                            'nro'        => $c->nro,
                            'state'      => $c->state,
                            'created_at' => $c->created_at,
                        ];
                    })
                    ->values();
            }

            return response()->json($response);
        }

        return response()->json(['status' => 400, 'message' => 'Parámetros insuficientes']);
    }

    /**
     * Enriquece el contrato con la información requerida por el consumidor:
     * plan, deuda, cliente, ubicación, historial de pagos y casos abiertos.
     */
    private function attachContratoData(Contrato $contrato)
    {
        $contrato->setAttribute('plan_detalles', $contrato->plan_detalles_api);

        $deudaValor = $contrato->deudaFacturas();
        $contrato->setAttribute('deuda', '$' . \App\Funcion::Parsear($deudaValor));
        $contrato->setAttribute('deuda_valor', (float) $deudaValor);
        $contrato->setAttribute('tiene_deuda', $deudaValor > 0);

        $cliente = Contacto::find($contrato->client_id);
        $contrato->setAttribute('cliente', $this->buildClienteInfo($cliente));
        $contrato->setAttribute('ubicacion', $this->buildUbicacion($cliente));
        $contrato->setAttribute('historial_pagos', $this->buildHistorialPagos($contrato));
        $contrato->setAttribute('casos_abiertos', $this->buildCasosAbiertos($contrato));
    }

    private function buildClienteInfo($cliente)
    {
        if (!$cliente) {
            return null;
        }

        $nombreCompleto = trim(implode(' ', array_filter([
            $cliente->nombre,
            $cliente->apellido1,
            $cliente->apellido2,
        ])));

        return [
            'id'                  => $cliente->id,
            'identificacion'      => $cliente->nit,
            'tipo_identificacion' => $cliente->tip_iden,
            'nombre'              => $cliente->nombre,
            'apellido1'           => $cliente->apellido1,
            'apellido2'           => $cliente->apellido2,
            'nombre_completo'     => $nombreCompleto,
            'email'               => $cliente->email,
            'telefono1'           => $cliente->telefono1,
            'telefono2'           => $cliente->telefono2,
            'celular'             => $cliente->celular,
        ];
    }

    private function buildUbicacion($cliente)
    {
        if (!$cliente) {
            return null;
        }

        $barrio = null;
        if ($cliente->barrio_id) {
            $row = DB::table('barrios')->where('id', $cliente->barrio_id)->first();
            $barrio = $row ? $row->nombre : null;
        }

        $municipio = null;
        if (!empty($cliente->fk_idmunicipio)) {
            $row = DB::table('municipios')->where('id', $cliente->fk_idmunicipio)->first();
            $municipio = $row ? $row->nombre : $cliente->ciudad;
        } else {
            $municipio = $cliente->ciudad;
        }

        $departamento = null;
        if (!empty($cliente->fk_iddepartamento)) {
            $row = DB::table('departamentos')->where('id', $cliente->fk_iddepartamento)->first();
            $departamento = $row ? $row->nombre : null;
        }

        $pais = null;
        if (!empty($cliente->fk_idpais)) {
            $row = DB::table('pais')->where('codigo', $cliente->fk_idpais)->first();
            $pais = $row ? $row->nombre : null;
        }

        return [
            'direccion'    => $cliente->direccion,
            'barrio'       => $barrio,
            'municipio'    => $municipio,
            'departamento' => $departamento,
            'pais'         => $pais,
            'estrato'      => $cliente->estrato,
        ];
    }

    /**
     * Últimos 10 pagos asociados al contrato vía facturas_contratos -> factura
     * -> ingresos_factura -> ingresos. Sólo pagos efectivos (tipo=1, estatus=1).
     */
    private function buildHistorialPagos(Contrato $contrato)
    {
        $pagos = DB::table('ingresos as i')
            ->join('ingresos_factura as inf', 'inf.ingreso', '=', 'i.id')
            ->join('factura as f', 'f.id', '=', 'inf.factura')
            ->join('facturas_contratos as fc', 'fc.factura_id', '=', 'f.id')
            ->leftJoin('metodos_pago as mp', 'mp.id', '=', 'i.metodo_pago')
            ->where('fc.contrato_nro', $contrato->nro)
            ->where('i.tipo', 1)
            ->where('i.estatus', 1)
            ->select(
                'i.id',
                'i.nro',
                'i.fecha',
                'inf.pago as monto',
                'mp.metodo as metodo_pago',
                'f.codigo as factura_codigo',
                'f.id as factura_id'
            )
            ->orderBy('i.fecha', 'desc')
            ->orderBy('i.id', 'desc')
            ->limit(10)
            ->get();

        return $pagos->map(function ($p) {
            return [
                'id'             => $p->id,
                'nro'            => $p->nro,
                'fecha'          => $p->fecha,
                'monto'          => (float) $p->monto,
                'metodo_pago'    => $p->metodo_pago,
                'factura_id'     => $p->factura_id,
                'factura_codigo' => $p->factura_codigo,
            ];
        })->values();
    }

    /**
     * Radicados abiertos del contrato. Según Radicado::estatus(), 0 = Pendiente
     * y 2 = Escalado/Pendiente; 1 y 3 son solventados.
     */
    private function buildCasosAbiertos(Contrato $contrato)
    {
        $radicados = Radicado::where('contrato', $contrato->nro)
            ->whereIn('estatus', [0, 2])
            ->whereNotNull('codigo')
            ->where('codigo', '!=', '')
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return $radicados->map(function ($r) {
            $servicio = $r->servicio();
            return [
                'id'             => $r->id,
                'codigo'         => $r->codigo,
                'fecha'          => $r->fecha,
                'servicio_id'    => $r->servicio,
                'servicio'       => $servicio ? $servicio->nombre : null,
                'prioridad'      => $r->prioridad(),
                'estatus'        => $r->estatus(),
                'observaciones'  => $r->desconocido,
            ];
        })->values();
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
