<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Mail;
use Validator;
use Illuminate\Validation\Rule;
use Auth;
use DB;
use Session;

use App\User;
use App\Integracion;
use App\Services\OnePayService;

class IntegracionPasarelaController extends Controller
{
    public function __construct(){
        $this->middleware('auth');
        set_time_limit(300);
        view()->share(['seccion' => 'configuracion', 'title' => 'Integración Pasarelas de Pago', 'icon' =>'far fa-credit-card']);
    }

    public function index(Request $request){
        $this->getAllPermissions(Auth::user()->id);
        $servicios = Integracion::where('empresa', Auth::user()->empresa)->where('tipo', 'PASARELA')->where('lectura', 1)->get();
        return view('configuracion.integracion_pasarela.index')->with(compact('servicios'));
    }

    public function show($id){
        $this->getAllPermissions(Auth::user()->id);
        $servicio = Integracion::where('empresa', Auth::user()->empresa)->where('tipo', 'PASARELA')->where('lectura', 1)->where('id', $id)->first();

        if ($servicio) {
            view()->share(['title' => 'Servicio: '.$servicio->nombre, 'precice' => true]);
            return view('configuracion.integracion_pasarela.show')->with(compact('servicio'));
        }
        return redirect('empresa/configuracion/integracion-pasarelas')->with('danger', 'SERVICIO NO ENCONTRADO, INTENTE NUEVAMENTE');
    }

    public function edit($id){
        $this->getAllPermissions(Auth::user()->id);
        $servicio = Integracion::where('empresa', Auth::user()->empresa)->where('tipo', 'PASARELA')->where('lectura', 1)->where('id', $id)->first();

        if ($servicio) {
            view()->share(['title' => $servicio->nombre, 'middel' => true]);
            return view('configuracion.integracion_pasarela.edit')->with(compact('servicio'));
        }
        return redirect('empresa/configuracion/integracion-pasarelas')->with('danger', 'SERVICIO NO ENCONTRADO, INTENTE NUEVAMENTE');
    }

    public function update(Request $request, $id){
        $servicio = Integracion::where('empresa', Auth::user()->empresa)->where('tipo', 'PASARELA')->where('lectura', 1)->where('id', $id)->first();

        if ($servicio) {
            $servicio->api_key    = $request->api_key;
            $servicio->api_event  = $request->api_event;

            // Para ONEPAY solo guardamos api_key (appkey), no merchantId ni accountId
            if($servicio->nombre != 'ONEPAY'){
                $servicio->accountId  = $request->accountId;
                $servicio->merchantId = $request->merchantId;
            }

            $servicio->integrity  = isset($request->integrity) ? $request->integrity : null;

            if($servicio->nombre=='ePayco'){
                $servicio->p_cust_id_cliente = $request->p_cust_id_cliente;
                $servicio->p_key = $request->p_key;
            }

            if($servicio->nombre=='ComboPay'){
                $servicio->user = $request->user;
                $servicio->pass = $request->pass;
            }

            $servicio->web        = $request->web;
            $servicio->app        = $request->app;
            $servicio->cobro_extra = isset($request->cobro_extra) ? $request->cobro_extra : 0;
            $servicio->updated_by = Auth::user()->id;
            $servicio->save();

            $mensaje='SE HA MODIFICADO SATISFACTORIAMENTE EL SERVICIO';
            return redirect('empresa/configuracion/integracion-pasarelas')->with('success', $mensaje)->with('id', $servicio->id);
        }
        return redirect('empresa/configuracion/integracion-pasarelas')->with('danger', 'SERVICIO NO ENCONTRADO, INTENTE NUEVAMENTE');
    }

    public function act_desc(Request $request, $id){
        $servicio = Integracion::where('empresa', Auth::user()->empresa)->where('tipo', 'PASARELA')->where('lectura', 1)->where('id', $id)->first();

        if($servicio){
            if($servicio->status == 0){
                $servicio->status = 1;
                $mensaje = 'SE HA HABILITADO EL SERVICIO CORRECTAMENTE';
            }else{
                $servicio->status = 0;
                $mensaje = 'SE HA DESHABILITADO EL SERVICIO CORRECTAMENTE';
            }
            $servicio->save();
            return back()->with('success', $mensaje)->with('id', $servicio->id);
            return redirect('empresa/configuracion/integracion-pasarelas')->with('success', $mensaje)->with('id', $servicio->id);
        }else{
            return redirect('empresa/configuracion/integracion-pasarelas')->with('danger', 'SERVICIO NO ENCONTRADO, INTENTE NUEVAMENTE');
        }
    }

    public function onePayInvoices(Request $request, $id)
    {
        $this->getAllPermissions(Auth::user()->id);

        $servicio = Integracion::where('empresa', Auth::user()->empresa)
            ->where('tipo', 'PASARELA')
            ->where('lectura', 1)
            ->where('id', $id)
            ->first();

        if (!$servicio || $servicio->nombre !== 'ONEPAY') {
            return redirect(route('integracion-pasarelas.index'))
                ->with('danger', 'SERVICIO NO ENCONTRADO O NO ES ONEPAY');
        }

        view()->share(['title' => 'Facturas OnePay', 'precice' => true]);

        $filters = [
            'page'              => $request->get('page', 1),
            'filter_id'         => $request->get('filter_id'),
            'filter_status'     => $request->get('filter_status'),
            'filter_reference'  => $request->get('filter_reference'),
            'filter_provider_id'=> $request->get('filter_provider_id'),
            'sort'              => $request->get('sort', '-created_at'),
        ];

        $invoices   = [];
        $meta       = [];
        $error      = null;

        try {
            $onePayService = new OnePayService();
            $response      = $onePayService->getInvoices($filters);
            $invoices      = $response['data']  ?? [];
            $meta          = $response['meta']  ?? [];
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        return view('configuracion.integracion_pasarela.onepay_invoices')
            ->with(compact('servicio', 'invoices', 'meta', 'filters', 'error'));
    }
}
