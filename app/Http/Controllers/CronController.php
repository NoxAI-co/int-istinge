<?php

namespace App\Http\Controllers;

use StdClass;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Funcion;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Model\Ingresos\Factura;
use App\NumeracionFactura;
use App\Model\Ingresos\ItemsFactura;
use App\Model\Inventario\Inventario;
use App\Contrato;
use App\Contacto;
use App\TerminosPago;
use App\Empresa;
use App\GrupoCorte;
use App\Mikrotik;
use App\CRM;
use App\Blacklist;
use App\Mail\BlacklistMailable;
use App\ServidorCorreo;
use App\Integracion;
use App\PlanesVelocidad;
use App\Model\Ingresos\FacturaRetencion;
use App\Producto;
use App\Plantilla;
use Auth;
use App\Services\EmisionesService;

include_once(app_path() .'/../public/routeros_api.class.php');
use RouterosAPI;

use App\Numeracion;
use App\Model\Ingresos\Ingreso;
use App\Model\Ingresos\IngresosFactura;
use App\Banco;
use App\Instance;
use App\Model\Gastos\FacturaProveedores;
use App\Model\Gastos\NotaDebito;
use App\Model\Ingresos\NotaCredito;
use App\Model\Nomina\Nomina;
use App\Movimiento;
use App\MovimientoLOG;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\WhatsappMetaLog;
use App\Helpers\CamposDinamicosHelper;
use App\Traits\CentralizedWhatsApp;

class CronController extends Controller
{
    use CentralizedWhatsApp;
    public static function precisionAPI($valor, $id){
        $empresa = Empresa::find($id);
        return round($valor, $empresa->precision);
    }

    public function tokenComboPay(Request $request)
    {
        $strUser = $request->query('user');
        $strPass = $request->query('pass');
        $strClientID = $request->query('client_id');
        $strClientSecret = $request->query('client_secret');

        if (!$strUser || !$strPass || !$strClientID || !$strClientSecret) {
            return response()->json([
                'message' => 'Missing credentials',
            ], 422);
        }

        try {
            $response = Http::asForm()->post('https://api.combopay.co/api/oauth/token', [
                'grant_type'    => 'password',
                'username'      => $strUser,
                'password'      => $strPass,
                'client_id'     => $strClientID,
                'client_secret' => $strClientSecret,
            ]);

            if ($response->successful()) {
                return response()->json($response->json(), 200);
            }

            return response()->json([
                'message' => 'Token request failed',
                'error' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Exception occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function crearLinkPago(Request $request)
    {
        $accessToken = $request->input('access_token');
        $data = $request->input('data');

        try {
            $response = Http::withToken($accessToken)
                ->asForm()
                ->post('https://api.combopay.co/api/invoice-company-customer', [
                    'value' => $data['value'],
                    'description' => $data['description'],
                    'invoice' => $data['invoice'],
                    'url_data_return' => $data['url_data_return'],
                    'url_client_redirect' => $data['url_client_redirect'],
                    'name' => $data['name'],
                    'document_type' => $data['document_type'],
                    'customer_phone_number' => $data['customer_phone_number'],
                    'document' => $data['document'],
                    'customer_address' => $data['customer_address'],
                ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'error' => 'Error al generar el link',
                'response' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Exception: ' . $e->getMessage()
            ], 500);
        }
    }

    public static function up_transaccion_($modulo, $id, $banco, $contacto, $tipo, $saldo, $fecha, $descripcion, $generoSaldoFavor=null,$empresa=null){
        $movimiento=new Movimiento;
        $probableMovimiento = Movimiento::where('modulo', 7)->where('id_modulo', $id)->where('estatus',1)->first();

        //Caso1: Cuando cambiamos de un saldo a favor a un pago normal, necesitamos buscarlo por el modulo.
        $regis=Movimiento::where('modulo', $modulo)->where('id_modulo', $id)->where('estatus',1)->first();

        if(!$regis && $probableMovimiento && $generoSaldoFavor == null){
            $movimiento=$probableMovimiento;
        }

        if ($regis) {
            $movimiento=$regis;
        }

        //Caso1: Se esta pasando de un saldo a favor a un movimiento normal, se devuelve el dinero al cliente de saldo a favor.
        if($probableMovimiento && $probableMovimiento->tipo == 2 && $modulo != 7){
            $conta = Contacto::Find($probableMovimiento->contacto);
            $conta->saldo_favor = $conta->saldo_favor + $probableMovimiento->saldo;
            $conta->save();
        }

        if($modulo == 7){
            $banco = Banco::where('empresa',$empresa)->where('nombre','like','Saldos a favor')->first()->id;
        }

        $movimiento->empresa=$empresa;
        $movimiento->banco=$banco;
        $movimiento->contacto=$contacto;
        $movimiento->tipo=$tipo;
        $movimiento->saldo=$saldo;
        $movimiento->fecha=$fecha;
        $movimiento->modulo=$modulo;
        $movimiento->id_modulo=$id;
        $movimiento->descripcion=$id . " " . $descripcion;
        $movimiento->save();
    }

    //ELIMINAR DUPLICADOS DE FACTURA CONTRATOS
    public static function limpiarDuplicadosFacturaContratos()
    {
        // 1. Obtener grupos duplicados
        $duplicados = DB::table('facturas_contratos')
            ->select('factura_id', 'contrato_nro', 'client_id', DB::raw('COUNT(*) as total'))
            ->groupBy('factura_id', 'contrato_nro', 'client_id')
            ->having('total', '>', 1)
            ->get();

        $eliminados = 0;

        foreach ($duplicados as $dup) {

            // 2. Obtener todos los registros duplicados del grupo
            $registros = DB::table('facturas_contratos')
                ->where('factura_id', $dup->factura_id)
                ->where('contrato_nro', $dup->contrato_nro)
                ->where('client_id', $dup->client_id)
                ->orderBy('id', 'asc') // dejamos el más pequeño
                ->get();

            // 3. Conservar el primero y eliminar los otros
            $idConservar = $registros->first()->id;

            $idsEliminar = $registros->pluck('id')->filter(function ($id) use ($idConservar) {
                return $id != $idConservar;
            });

            if ($idsEliminar->count() > 0) {
                DB::table('facturas_contratos')
                    ->whereIn('id', $idsEliminar)
                    ->delete();

                $eliminados += $idsEliminar->count();
            }
        }

    return "Duplicados eliminados: " . $eliminados;
    }

    //***** Metodo para agregar el saldo a favor a las facturas de un dia de creacion especifico ***** //
    public static function pagarFacturasSaldoFavor(){

        $facturas = Factura::
        leftJoin('contactos as c','c.id','factura.cliente')
        ->select('factura.*')
        ->where('factura.created_at','lIKE','%2026-01-07%')
        ->where('factura.facturacion_automatica',1)
        ->where('factura.estatus',1)
        ->where('c.saldo_favor','>',0)
        ->get();

        $empresa = Empresa::Find(1);

        if($empresa->aplicar_saldofavor == 1){
            foreach($facturas as $factura){
                self::pagoFacturaAutomatico($factura);
            }
        }else{
            return "empresa no tiene la opcion habilitada.";
        }

        return "pagos generados correctamente.";

    }

    public static function CrearFactura($fechaRef = null, $idGrupo = null){

        $fecha = $fechaRef ? $fechaRef : Carbon::now()->format('Y-m-d');
        $horaActual = $fechaRef ? "23:59" : date('H:i');

        ini_set('max_execution_time', 500);
        setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'spanish');
        self::limpiarDuplicadosFacturaContratos();
        self::validateFacturasDuplicadas($fecha);

        $empresa = Empresa::find(1);

        if($empresa->factura_auto == 1){
            $i=0;
            $date = $fechaRef ? (int)Carbon::parse($fechaRef)->format('d') : getdate()['mday'] * 1;
            $numeros = [];
            $bulk = '';
            $query = GrupoCorte::query();
            
            if ($idGrupo) {
                $query->where('id', $idGrupo);
            } else {
                $query->where('fecha_factura', $date)
                      ->whereRaw("STR_TO_DATE(hora_creacion_factura, '%H:%i') <= STR_TO_DATE(?, '%H:%i')", [$horaActual])
                      ->where('status', 1);
            }
            
            $grupos_corte = $query->get();


            $state = ['enabled'];
            if ($empresa->factura_contrato_off == 1) {
                $state[] = 'disabled';
            }

            foreach($grupos_corte as $grupo_corte){
                $contratos = Contrato::join('contactos as c', 'c.id', '=', 'contracts.client_id')->
                join('empresas as e', 'e.id', '=', 'contracts.empresa')
                ->select('contracts.id', 'contracts.iva_factura', 'contracts.public_id', 'c.id as cliente',
                'contracts.state', 'contracts.fecha_corte', 'contracts.fecha_suspension', 'contracts.facturacion',
                'contracts.plan_id', 'contracts.descuento', 'c.nombre', 'c.nit', 'c.celular', 'c.telefono1',
                'c.saldo_favor','contracts.created_at','contracts.fact_primer_mes',
                'e.terminos_cond', 'e.notas_fact', 'contracts.servicio_tv', 
                'contracts.factura_individual','contracts.nro','contracts.prorrateo')
                ->where('contracts.grupo_corte',$grupo_corte->id)->
                where('contracts.status',1)->
                // whereIn('contracts.client_id',[645])->
                // where('c.saldo_favor','>',80000)->//rc
                whereIn('contracts.state',$state)
                ->get();

                $num = Factura::where('empresa',1)->orderby('id','asc')->get()->last();
                if($num){
                    $numero = $num->nro;
                }else{
                    $numero = 0;
                }

                //Calculo fecha pago oportuno.
                $y = Carbon::parse($fecha)->format('Y');
                $m = Carbon::parse($fecha)->format('m');
                $d = substr(str_repeat(0, 2).$grupo_corte->fecha_pago, - 2);
                if($d == 0){
                    $d = 30;
                }

                if($grupo_corte->fecha_factura > $grupo_corte->fecha_pago && $m!=12){
                    $m=$m+1;
                }

                if($m == 12 && $grupo_corte->fecha_factura > $grupo_corte->fecha_pago){
                    $y = $y+1;
                    $m = 01;
                }
                $date_pagooportuno = self::validarFechaUltimoDiaMes($y, $m, $d);
                //Fin calculo fecha de pago oportuno

                //calculo fecha suspension
                $y = Carbon::parse($fecha)->format('Y');
                $m = Carbon::parse($fecha)->format('m');
                $ds = substr(str_repeat(0, 2).$grupo_corte->fecha_suspension, - 2);
                $da = Carbon::parse($fecha)->format('d')*1;

                // Si mes_siguiente está activo, forzar el mes siguiente para la suspensión
                if($grupo_corte->mes_siguiente == 1){
                    $m = $m + 1;
                    if($m > 12){
                        $m = 1;
                        $y = $y + 1;
                    }
                } else {
                    // Lógica original: solo avanzar mes si el día actual es mayor al día de suspensión
                    if($da > $grupo_corte->fecha_suspension && $m!=12){
                        $m=$m+1;
                    }

                    if($m == 12){
                        if($da > $grupo_corte->fecha_suspension){

                            if(Carbon::now()->format('m') != 11){
                                $m = 01;
                                $y = $y+1;
                            }
                        }
                    }
                }
                $date_suspension = self::validarFechaUltimoDiaMes($y, $m, $ds);
                //Fin calculo fecha suspension

                foreach ($contratos as $contrato) {

                    //validacion primer factura del contrato
                    $creacion_contrato = Carbon::parse($contrato->created_at);
                    $dia_creacion_contrato = $creacion_contrato->day;
                    $dia_creacion_factura = $grupo_corte->fecha_factura;

                    // Determinar el mes y año para la primera factura
                    if ($dia_creacion_contrato <= $dia_creacion_factura) {
                        // Si el contrato se creó antes o el mismo día del corte, la factura es en el mismo mes
                        $primer_fecha_factura = $creacion_contrato->copy()->day($dia_creacion_factura);
                        $primer_fecha_factura = Carbon::parse($primer_fecha_factura)->format("Y-m-d");
                    } else {
                        // Si el contrato se creó después del corte, la factura es en el siguiente mes
                        $primer_fecha_factura = $creacion_contrato->copy()->addMonth()->day($dia_creacion_factura);
                        $primer_fecha_factura = Carbon::parse($primer_fecha_factura)->format("Y-m-d");
                    }

                    //** Si no existe ninguna factura en esa tabla es por que es la primer fac y entra a la validacion*
                    if(!DB::table('facturas_contratos as fc')->where('contrato_nro',$contrato->nro)->first()){
                        if(isset($primer_fecha_factura) &&
                        Carbon::parse($fecha)->format("Y-m-d") == $primer_fecha_factura &&
                        $contrato->fact_primer_mes == 0){
                            continue; //este continue salta la actual iteracion
                        }
                    }
                    //Fin validacion primer factura del contrato

                    $ultimaFactura = DB::table('facturas_contratos')
                    ->join('factura', 'facturas_contratos.factura_id', '=', 'factura.id')
                    ->where('facturas_contratos.contrato_nro', $contrato->nro)
                    ->where('factura.estatus','!=',2)
                    ->select('factura.*')
                    ->orderBy('factura.fecha', 'desc')
                    ->first();

                    $mesUltimaFactura = false;
                    $mesActualFactura = date('Y-m',strtotime($fecha));

                    if($ultimaFactura){

                        //Validamos que solo vamos a evaluar por created_at a las f. electronicas, por que las pudieron emitir despues.
                        if($ultimaFactura->tipo == 2){
                            $mesUltimaFactura = date('Y-m',strtotime($ultimaFactura->created_at));
                        }else{
                            $mesUltimaFactura = date('Y-m',strtotime($ultimaFactura->fecha));
                        }
                        //Validacion nueva: mirar si la ultima factura generada tiene la opcion de factura del mes actual.
                        if($mesActualFactura == $mesUltimaFactura){
                            if($ultimaFactura->factura_mes_manual == 1){
                                continue; //salte esta iteracion entonces por que es la factura del mes manual.
                            }

                            //Esto lo hacemos por que si estoy ejecutando un periodo de 2 de enero y la factura manual es del 4 pues lo mas logico es
                            //que esa factura seal periodo ya que esto nos esta trayendo demasiadas fallas.
                            elseif(date('d',strtotime($fecha)) <= date('d',strtotime($ultimaFactura->fecha))){
                                DB::table('factura')->where('id',$ultimaFactura->id)->update(['factura_mes_manual'=>1]);
                                continue;
                            }

                        }

                        if($ultimaFactura->factura_mes_manual == null){
                            $ultimaFactura->factura_mes_manual = 0;
                            DB::table('factura')->where('id',$ultimaFactura->id)->update(['factura_mes_manual'=>0]);
                        }
                        
                    }

                    /* ** Validacion: si la actual es dif a la ultima fac pasa o sino
                    si son iguales y no tiene fact manual == 1(la ultima) y es manual y no automatica pasa */
                    if($mesActualFactura != $mesUltimaFactura ||
                       $mesActualFactura == $mesUltimaFactura && $ultimaFactura->factura_mes_manual == 0
                       && $ultimaFactura->facturacion_automatica == 0)
                    {
                        ## Verificamos que el cliente no posea la ultima factura automática abierta, de tenerla no se le genera la nueva factura
                        if(isset($ultimaFactura->fecha)){
                            $fac = $ultimaFactura;
                        }else{$fac=false;}

                        //Primer filtro de la validación, que la factura esté cerrada o que no exista una factura.
                        if(isset($fac->estatus) || !$fac || $empresa->cron_fact_abiertas == 1){

                            //Segundo filtro, que la fecha de vencimiento de la factura abierta sea mayor a la fecha actual
                            if(isset($fac->vencimiento) && $fac->vencimiento > $fecha ||
                            isset($fac->estatus) && $fac->estatus == 0 || !$fac ||
                            isset($fac->estatus) && $fac->estatus == 2 ||
                            $empresa->cron_fact_abiertas == 1
                            ){

                                if(!$fac || isset($fac) && $fecha != $fac->fecha){
                                    $numero=round(floatval($numero));+1;

                                    //Obtenemos el número depende del contrato que tenga asignado (con fact electrpinica o estandar).
                                    $nro = NumeracionFactura::tipoNumeracion($contrato);

                                    if(is_null($nro)){
                                    }else{ //aca empieza la verdadera creacion de la factura despues de pasar las validaciones.

                                    $hoy = $fecha;

                                    if(!DB::table('facturas_contratos')
                                    ->whereDate('created_at',$hoy)
                                    ->where('contrato_nro',$contrato->nro)->where('is_cron',1)->first())
                                    {

                                        if($contrato->fecha_suspension){
                                                $fecha_suspension = $contrato->fecha_suspension;
                                        }else{
                                                $fecha_suspension = $grupo_corte->fecha_suspension;
                                        }

                                        $plazo=TerminosPago::where('dias', Funcion::diffDates($date_suspension, Carbon::now())+1)->first();
                                        $tipo = 1; //1= normal, 2=Electrónica.
                                        $electronica = Factura::booleanFacturaElectronica($contrato->cliente);

                                        if($contrato->facturacion == 3 && !$electronica){
                                            $tipo = 1;
                                            // return redirect('empresa/facturas')->with('success', "La Factura Electrónica no pudo ser creada por que no ha pasado el tiempo suficiente desde la ultima factura");
                                        }elseif($contrato->facturacion == 3 && $electronica){
                                            $tipo = 2;
                                        }

                                        $inicio = $nro->inicio;

                                        // Validacion para que solo asigne numero consecutivo si no existe.
                                        while (Factura::where('codigo',$nro->prefijo.$inicio)->first()) {
                                            $nro = $nro->fresh();
                                            $inicio=$nro->inicio;
                                            $nro->inicio += 1;
                                            $nro->save();
                                        }

                                        $factura = new Factura;
                                        $factura->nro           = $numero;
                                        $factura->codigo        = $nro->prefijo.$inicio;
                                        $factura->numeracion    = $nro->id;
                                        $factura->plazo         = isset($plazo->id) ? $plazo->id : '';
                                        $factura->term_cond     = $contrato->terminos_cond;
                                        $factura->facnotas      = $contrato->notas_fact;
                                        $factura->empresa       = 1;
                                        $factura->cliente       = $contrato->cliente;
                                        $factura->fecha         = $fecha;
                                        $factura->tipo          = $tipo;
                                        $factura->vencimiento   = $date_suspension;
                                        $factura->suspension    = $date_suspension;
                                        $factura->pago_oportuno = $date_pagooportuno;
                                        $factura->observaciones = 'Facturación Automática - Corte '.$grupo_corte->fecha_corte;
                                        $factura->bodega        = 1;
                                        $factura->vendedor      = 1;
                                        $factura->prorrateo_aplicado = 0;
                                        $factura->facturacion_automatica = 1;
                                        $factura->factura_mes_manual = 1;

                                        if($contrato){
                                            $factura->contrato_id = $contrato->id;
                                        }

                                        //validacion extra antes de guardar que no haya ningun mismo codigo.
                                        if(Factura::where('codigo',$factura->codigo)->count() <= 1){
                                            $factura->save();

                                        // *** Actualizacion importante contratos multiples en una sola factura **** //
                                        if($contrato->factura_individual == 0){
                                            $contratos_multiples = Contrato::where('client_id',$factura->cliente)->where('factura_individual', 0)->get();
                                        }else {
                                            $contratos_multiples = Contrato::where('nro',$contrato->nro)->where('client_id',$factura->cliente)->get();
                                        }

                                        foreach($contratos_multiples as $cm){

                                            $descuentoPesos = 0;
                                            $descuentoHasta = isset($cm->fecha_hasta_desc) ? $cm->fecha_hasta_desc : null;
                                            $fechaActual = Carbon::now()->format('Y-m-d');

                                            ## Se carga el item a la factura (Plan de Internet) ##
                                            if($contrato->plan_id){
                                                $plan = PlanesVelocidad::find($cm->plan_id);
                                                $item = Inventario::find($plan->item);
                                                $item_reg = new ItemsFactura;
                                                $item_reg->factura     = $factura->id;
                                                $item_reg->producto    = $item->id;
                                                $item_reg->ref         = $item->ref;
                                                $item_reg->precio      = $item->precio;

                                                // Precio personalizado internet
                                                if(isset($cm->precio_personalizado_internet) && $cm->precio_personalizado_internet > 0){
                                                    $item_reg->precio = $cm->precio_personalizado_internet;
                                                }

                                                $item_reg->descripcion = $plan->name;
                                                $item_reg->id_impuesto = $item->id_impuesto;
                                                $item_reg->impuesto    = $item->impuesto;
                                                if($cm->iva_factura == 1){
                                                    $item_reg->id_impuesto = 1;
                                                    $item_reg->impuesto = 19;
                                                }
                                                $item_reg->cant        = 1;

                                                if($descuentoHasta != null && $fechaActual <= $descuentoHasta){
                                                    $item_reg->desc        = $cm->descuento;

                                                    if($cm->descuento_pesos != null && $descuentoPesos == 0){
                                                        $item_reg->precio      = $item_reg->precio - $cm->descuento_pesos;
                                                        $descuentoPesos = 1;
                                                    }
                                                }else if($descuentoHasta == null || $descuentoHasta == ""){
                                                    $item_reg->desc        = $cm->descuento;

                                                    if($cm->descuento_pesos != null && $descuentoPesos == 0){
                                                        $item_reg->precio      = $item_reg->precio - $cm->descuento_pesos;
                                                        $descuentoPesos = 1;
                                                    }
                                                }

                                                $item_reg->save();
                                            }

                                            ## Se carga el item a la factura (Plan de Televisión) ##
                                            if($cm->servicio_tv){
                                                $item = Inventario::find($cm->servicio_tv);
                                                $item_reg = new ItemsFactura;
                                                $item_reg->factura     = $factura->id;
                                                $item_reg->producto    = $item->id;
                                                $item_reg->ref         = $item->ref;
                                                $item_reg->precio      = $item->precio;

                                                // Precio personalizado TV
                                                if(isset($cm->precio_personalizado_tv) && $cm->precio_personalizado_tv > 0){
                                                    $item_reg->precio = $cm->precio_personalizado_tv;
                                                }

                                                $item_reg->descripcion = $item->producto;
                                                $item_reg->id_impuesto = $item->id_impuesto;
                                                $item_reg->impuesto    = $item->impuesto;
                                                $item_reg->cant        = 1;

                                                if($descuentoHasta != null && $fechaActual <= $descuentoHasta){
                                                    $item_reg->desc        = $cm->descuento;
                                                    if($cm->descuento_pesos != null && $descuentoPesos == 0){
                                                        $item_reg->precio      = $item_reg->precio - $cm->descuento_pesos;
                                                        $descuentoPesos = 1;
                                                    }
                                                }elseif($descuentoHasta == null || $descuentoHasta == ""){
                                                    $item_reg->desc        = $cm->descuento;
                                                    if($cm->descuento_pesos != null && $descuentoPesos == 0){
                                                        $item_reg->precio      = $item_reg->precio - $cm->descuento_pesos;
                                                        $descuentoPesos = 1;
                                                    }
                                                }

                                                $item_reg->save();
                                            }

                                            ## Se carga el item de otro tipo de servicio ##
                                            if($cm->servicio_otro){
                                                $item = Inventario::find($cm->servicio_otro);
                                                $item_reg = new ItemsFactura;
                                                $item_reg->factura     = $factura->id;
                                                $item_reg->producto    = $item->id;
                                                $item_reg->ref         = $item->ref;
                                                $item_reg->precio      = $item->precio;
                                                $item_reg->descripcion = $item->producto;
                                                $item_reg->id_impuesto = $item->id_impuesto;
                                                $item_reg->impuesto    = $item->impuesto;
                                                $item_reg->cant        = 1;

                                                if($descuentoHasta != null && $fechaActual <= $descuentoHasta){
                                                    $item_reg->desc        = $cm->descuento;
                                                    if($cm->descuento_pesos != null && $descuentoPesos == 0){
                                                        $item_reg->precio      = $item_reg->precio - $cm->descuento_pesos;
                                                        $descuentoPesos = 1;
                                                    }
                                                }elseif($descuentoHasta == null || $descuentoHasta == ""){
                                                    $item_reg->desc        = $cm->descuento;
                                                    if($cm->descuento_pesos != null && $descuentoPesos == 0){
                                                        $item_reg->precio      = $item_reg->precio - $cm->descuento_pesos;
                                                        $descuentoPesos = 1;
                                                    }
                                                }


                                                if($cm->rd_item_vencimiento == 1){

                                                    if($cm->dt_item_hasta >= now()){
                                                        $item_reg->save();
                                                    }
                                                }else{
                                                    $item_reg->save();
                                                }
                                            }

                                            ## REGISTRAMOS EL ITEM SI TIENE PAGO PENDIENTE DE ASIGNACIÓN DE PRODUCTO
                                            $asignacion = Producto::where('contrato', $cm->id)->where('venta', 1)->where('status', 2)->where('cuotas_pendientes', '>', 0)->get()->last();

                                            if($asignacion){
                                                $item = Inventario::find($asignacion->producto);
                                                $item_reg = new ItemsFactura;
                                                $item_reg->factura     = $factura->id;
                                                $item_reg->producto    = $item->id;
                                                $item_reg->ref         = $item->ref;
                                                $item_reg->precio      = ($asignacion->precio/$asignacion->cuotas);
                                                $item_reg->descripcion = $item->producto;
                                                $item_reg->id_impuesto = $item->id_impuesto;
                                                $item_reg->impuesto    = $item->impuesto;
                                                $item_reg->cant        = 1;

                                                if($descuentoHasta != null && $fechaActual <= $descuentoHasta){
                                                    $item_reg->desc        = $cm->descuento;
                                                    if($cm->descuento_pesos != null && $descuentoPesos == 0){
                                                        $item_reg->precio      = $item_reg->precio - $cm->descuento_pesos;
                                                        $descuentoPesos = 1;
                                                    }
                                                }elseif($descuentoHasta == null || $descuentoHasta == ""){
                                                    $item_reg->desc        = $cm->descuento;
                                                    if($cm->descuento_pesos != null && $descuentoPesos == 0){
                                                        $item_reg->precio      = $item_reg->precio - $cm->descuento_pesos;
                                                        $descuentoPesos = 1;
                                                    }
                                                }

                                                $item_reg->save();
                                            }

                                            //guardamos en la tabla detalle para saber que esa factura tiene n contratos
                                            DB::table('facturas_contratos')->insert([
                                                'factura_id' => $factura->id,
                                                'contrato_nro' => $cm->nro,
                                                'created_by' => 0,
                                                'client_id' => $factura->cliente,
                                                'is_cron' => 1,
                                                'created_at' => Carbon::now()
                                            ]);
                                        }

                                    // Integración con OnePay si está habilitado
                                    if(\App\Services\OnePayService::isEnabled($empresa->id)){
                                        try {
                                            $onePayService = new \App\Services\OnePayService($empresa->id);
                                            $onePayService->createInvoice($factura, $empresa->id);
                                        } catch (\Exception $e) {
                                            // Log del error pero no interrumpir el flujo
                                            \Illuminate\Support\Facades\Log::error('Error al crear factura en OnePay: ' . $e->getMessage(), [
                                                'factura_id' => $factura->id,
                                                'empresa_id' => $empresa->id
                                            ]);
                                        }
                                    }

                                        $nro->save();
                                        $i++;

                                        $numero = str_replace('+','',$factura->cliente()->celular);
                                        $numero = str_replace(' ','',$numero);

                                        array_push($numeros, '57'.$numero);

                                        if($empresa->sms_factura_generada){

                                        $nombreCliente = $factura->cliente()->nombre.' '.$factura->cliente()->apellidos();
                                        $nombreEmpresa = $empresa->nombre;
                                        $codigoFactura = $factura->codigo ?? $factura->nro;
                                        $valorFactura =  $factura->totalAPI($empresa->id)->total;
                                        $fechaVencimiento = Carbon::parse($date_suspension)->format('d-m-Y');

                                        $bulksms = $empresa->sms_factura_generada;
                                        $bulksms = str_replace("{cliente}", $nombreCliente, $bulksms);
                                        $bulksms = str_replace("{empresa}", $nombreEmpresa, $bulksms);
                                        $bulksms = str_replace("{factura}", $codigoFactura, $bulksms);
                                        $bulksms = str_replace("{valor}", $valorFactura, $bulksms);
                                        $bulksms = str_replace("{vencimiento}", $fechaVencimiento, $bulksms);

                                        $bulk .= '{"numero": "57'.$numero.'", "sms": "'.$bulksms.'"},';

                                        }else if($empresa->nombre == 'FIBRACONEXION S.A.S.' || $empresa->nit == '900822955' || $empresa->nombre == 'Almeidas Comunicaciones S.A.S' ||  $empresa->nit == '901044772' || $empresa->nombre == 'Telecomunicaciones Por Redes Pon Tele Pon S.A.S' ||  $empresa->nit == '901346829' ){
                                            $fullname = $factura->cliente()->nombre.' '.$factura->cliente()->apellidos();
                                            $bulksms = ''.trim($fullname).'. '.$empresa->nombre.' le informa que su factura de servicio de internet. Tiene como fecha de vencimiento: '.$date->format('d-m-Y').' Total a pagar '.$factura->totalAPI($empresa->id)->total;
                                            $bulk .= '{"numero": "57'.$numero.'", "sms": "'.$bulksms.'"},';
                                        }else{
                                            // Array con los nombres de los meses en español
                                            $meses = [
                                                1 => 'enero',
                                                2 => 'febrero',
                                                3 => 'marzo',
                                                4 => 'abril',
                                                5 => 'mayo',
                                                6 => 'junio',
                                                7 => 'julio',
                                                8 => 'agosto',
                                                9 => 'septiembre',
                                                10 => 'octubre',
                                                11 => 'noviembre',
                                                12 => 'diciembre',
                                            ];
                                            $numeroMes = date('n', strtotime($factura->fecha));
                                            $mes = ucfirst($meses[$numeroMes]);

                                            $bulksms = $empresa->nombre.' informa, su factura del mes de ' .$mes.  ' fue generada por un total de ' .$factura->total()->total .  ' en el contrato nro ' . $contrato->nro . ' . Cuenta para pago en Coopenessa convenio Telepon ' . $contrato->contrato_nro;
                                            $bulk .= '{"numero": "57'.$numero.'", "sms": "'.$bulksms.'"},';
                                        }

                                        //>>>>Posible aplicación de Prorrateo al total<<<<//
                                        if($contrato->prorrateo == 1){
                                            $dias = $factura->diasCobradosProrrateo();
                                            //si es diferente de 30 es por que se cobraron menos dias y hay prorrateo
                                            //Se agrego la solucion de que sea menor.
                                            if($dias < 30){

                                                    DB::table('factura')->where('id',$factura->id)->update([
                                                    'prorrateo_aplicado' => 1
                                                    ]);
                                                    //si no se nombra la variable en la primer guardada se genera una copia

                                                foreach($factura->itemsFactura as $item){
                                                    //dividimos el precio del item en 30 para saber cuanto vamos a cobrar en total restando los dias
                                                    $precioItemProrrateo = round($item->precio * $dias / 30, $empresa->precision);
                                                    DB::table('items_factura')->where('id',$item->id)->update([
                                                        'precio' => $precioItemProrrateo
                                                        ]);
                                                }
                                            }
                                        }
                                        //>>>>Fin posible aplicación prorrateo al total<<<<//

                                        /* Creacion de pagos automaticamente */
                                        if($contrato->saldo_favor >= $factura->totalAPI($empresa->id)->total && $empresa->aplicar_saldofavor == 1){
                                            self::pagoFacturaAutomatico($factura);
                                        }

                                    }// fin de validacion factura doble.

                                } //Validacion facturas_contratos

                            }
                            }//validacion que no se creen dos el mismo dia
                        }
                    } //Comentando factura abierta del mes pasado

                 }


                }// fin foreach contratos.
            }

            if(isset($nro)){
                $nro->inicio = $nro->inicio+1;
                $nro->save();
            }

             /* Enviar correo funcional y Limpiar Caché (Re-aplicado) */
             $periodoCache = date('Y-m', strtotime($fecha));
             $analyzer = new \App\Services\BillingCycleAnalyzer();

             foreach($grupos_corte as $grupo_corte){
                // Limpiar caché del ciclo para este grupo
                $analyzer->clearCycleCache($grupo_corte->id, $periodoCache);
                $fechaInvoice = Carbon::now()->format('Y-m').'-'.substr(str_repeat(0, 2).$grupo_corte->fecha_factura, - 2);
                self::sendInvoices($fechaInvoice);
            }

            ## ENVIO SMS ##
            if($empresa->factura_sms_auto){
                $servicio = Integracion::where('empresa', 1)->where('tipo', 'SMS')->where('status', 1)->first();
                if($servicio){
                    if(isset($bulksms) && $bulksms != ''){
                        $mensaje = $bulksms;
                    }else{
                        $mensaje = 'Hola, '.$empresa->nombre.' le informa que su factura de internet ha sido generada. '.$empresa->slogan;
                    }
                    if($servicio->nombre == 'Hablame SMS'){
                        if($servicio->api_key && $servicio->user && $servicio->pass){
                            $curl = curl_init();
                            curl_setopt_array($curl, [
                                CURLOPT_URL => "https://api103.hablame.co/api/sms/v3/send/marketing/bulk",
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_ENCODING => "",
                                CURLOPT_MAXREDIRS => 10,
                                CURLOPT_TIMEOUT => 30,
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                CURLOPT_CUSTOMREQUEST => "POST",
                                CURLOPT_POSTFIELDS => "{\n  \"bulk\": [\n    ".substr($bulk, 0, -1)."\n  ]\n}",
                                CURLOPT_HTTPHEADER => [
                                    'Content-Type: application/json',
                                    'account: '.$servicio->user,
                                    'apiKey: '.$servicio->api_key,
                                    'token: '.$servicio->pass,
                                    ],
                            ]);

                            $response = curl_exec($curl);
                            $err = curl_error($curl);
                            curl_close($curl);
                        }
                    }elseif($servicio->nombre == 'SmsEasySms'){
                        if($servicio->user && $servicio->pass){
                            $post['to'] = $numeros;
                            $post['text'] = $mensaje;
                            $post['from'] = "SMS";
                            $login = $servicio->user;
                            $password = $servicio->pass;

                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, "https://sms.istsas.com/Api/rest/message");
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                            curl_setopt($ch, CURLOPT_POST, 1);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
                            curl_setopt($ch, CURLOPT_HTTPHEADER,
                                array(
                                    "Accept: application/json",
                                    "Authorization: Basic ".base64_encode($login.":".$password)));
                            $result = curl_exec ($ch);
                            $err  = curl_error($ch);
                            curl_close($ch);
                        }
                    }else{
                        if($servicio->user && $servicio->pass){
                            $post['to'] = $numeros;
                            $post['text'] = $mensaje;
                            $post['from'] = "";
                            $login = $servicio->user;
                            $password = $servicio->pass;

                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, "https://masivos.colombiared.com.co/Api/rest/message");
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                            curl_setopt($ch, CURLOPT_POST, 1);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
                            curl_setopt($ch, CURLOPT_HTTPHEADER,
                                array(
                                    "Accept: application/json",
                                    "Authorization: Basic ".base64_encode($login.":".$password)));
                            $result = curl_exec ($ch);
                            $err  = curl_error($ch);
                            curl_close($ch);
                        }
                    }
                }
            }
            ## ENVIO SMS ##
        }
    }

    //Pago automatico que se genera cuando el cliente tiene saldo a favor.
    public static function pagoFacturaAutomatico($factura){

        $empresa = $factura->empresa;
        $precio = $factura->totalAPI($empresa)->total;
        $contacto = Contacto::Find($factura->cliente);

        if($contacto->saldo_favor >= $factura->totalAPI($empresa)->total){

        //obtencion de numeración de el recibo de caja.
        $nro = Numeracion::where('empresa', $empresa)->first();
        $caja = $nro->caja;

        while (true) {
            $numero = Ingreso::where('empresa', $empresa)->where('nro', $caja)->count();
            if ($numero == 0) {
                break;
            }
            $caja++;
        }

        $request = new StdClass;
        $request->cuenta = Banco::where('empresa',$empresa)->where('nombre','like','Saldos a favor')->first()->id;
        $request->metodo_pago = 1;
        $request->notas = "Recibo de caja generado automáticamente por saldo a favor.";
        $request->observaciones = "Recibo de caja generado automáticamente por saldo a favor desde cronjob. Antes de aplicar el saldo a favor tenia: " . round($contacto->saldo_favor);
        $request->tipo = 1;
        $request->fecha = Carbon::now()->format('Y-m-d');

        $ingreso = new Ingreso;
        $ingreso->nro = $caja;
        $ingreso->empresa = $empresa;
        $ingreso->cliente = $factura->cliente;
        $ingreso->cuenta = $request->cuenta;
        $ingreso->metodo_pago = $request->metodo_pago;
        $ingreso->notas = $request->notas;
        $ingreso->tipo = $request->tipo;
        $ingreso->fecha = $request->fecha;
        $ingreso->observaciones = mb_strtolower($request->observaciones);
        $ingreso->save();

        $items = new IngresosFactura;
        $items->ingreso = $ingreso->id;
        $items->factura = $factura->id;
        $items->puc_factura = $factura->cuenta_id;

        $saldoAntes = $contacto->saldo_favor;

        if($contacto->saldo_favor >= $precio){
            $items->pagado = $precio; //asi exista mas dinero del  pagado ese se debe usar.
            $items->pago = self::precisionAPI($precio, $empresa);
            $items->save();

            $factura->estatus = 0;
            $factura->save();

            $contacto->saldo_favor-=$precio;
            $contacto->save();
        }
        else{

            $items->pagado = $contacto->saldo_favor; //asi exista mas dinero del  pagado ese se debe usar.
            $items->pago = self::precisionAPI($contacto->saldo_favor, $empresa);
            $items->save();

            $factura->estatus = 1;
            $factura->save();

            $contacto->saldo_favor-=$contacto->saldo_favor;
            $contacto->save();
        }

        $descripcion = 'Se creo un ingreso de factura con el recibo de caja nro ' . $ingreso->nro . ' por un total de $' . number_format($precio, 0, ',', '.');
        $descripcion .= ' <br>Antes de aplicar el saldo a favor tenia: ' . round($saldoAntes);
        $descripcion .= ' <br>Despues de aplicar el saldo a favor tiene: ' . round($contacto->saldo_favor);

        //registro de que se creo un ingreso de factura
        $movimiento = new MovimientoLOG();
        $movimiento->contrato    = $factura->id;
        $movimiento->modulo      = 8;
        $movimiento->descripcion = $descripcion;
        $movimiento->created_by  = 1;
        $movimiento->empresa     = $factura->empresa;
        $movimiento->save();


        //No vamos a regisrtrar por el momento un movimiento del puc ya que no sabemos esta informacion.
        // $ingreso->puc_banco = $request->forma_pago; //cuenta de forma de pago genérico del ingreso. (en memoria)
        // PucMovimiento::ingreso($ingreso,1,2,$request);

        self::up_transaccion_(7, $ingreso->id, $ingreso->cuenta, $ingreso->cliente, 2, $precio, $ingreso->fecha, "Uso de saldo a favor automatico.",null,$empresa);
     }
    }

    public static function cortarFacturasDiaEspecifico(){

        $i=0;
        $fecha = date('Y-m-d');

        if(request()->fechaCorte){
            $fecha = request()->fechaCorte;
        }
        $swGrupo = 1; //masivo
        $horaActual = date('H:i');

        $contactos = Contacto::join('factura as f','f.cliente','=','contactos.id')->
        join('contracts as cs','cs.client_id','=','contactos.id')->
        select('contactos.id', 'contactos.nombre', 'contactos.nit', 'f.id as factura', 'f.estatus', 'f.suspension', 'cs.state', 'f.contrato_id','cs.grupo_corte')->
        where('f.estatus',1)->
        whereIn('f.tipo', [1,2])->
        where('contactos.status',1)->
        where('cs.state','enabled')->
        where('f.fecha',$fecha)->
        where('cs.fecha_suspension','!=', null)->
        take(20)->
        get();

        if($contactos){
            $empresa = Empresa::find(1);
            foreach ($contactos as $contacto) {

                //** Desarrollo nuevo:
                //** Analizar la cantidad de facturas abiertas del contrato y el grupo de corte
                $contacto->updated_at = now();
                $contacto->save();

                $grupo_corte = null;
                $cant_fac_grupo_corte = 1;
                $cantFacturasVencidas = 1;
                if(isset($contacto->grupo_corte) && $contacto->grupo_corte != ""){
                    $grupo_corte = GrupoCorte::Find($contacto->grupo_corte);
                    $cant_fac_grupo_corte = $grupo_corte->nro_factura_vencida;
                }

                if(isset($grupo_corte->nro_factura_vencida) && $grupo_corte->nro_factura_vencida > 1){
                    $contrato = Contrato::Find($contacto->contrato_id);
                    if($contrato){
                        $cantFacturasVencidas = $contrato->cantidadFacturasVencidas();
                    }else{
                        continue;
                    }
                }
                //** Fin desarrollo nuevo

                $factura = Factura::find($contacto->factura);

                //ESto es lo que hay que refactorizar.
                $facturaContratos = DB::table('facturas_contratos')
                ->where('factura_id',$factura->id)
                ->where('client_id',$factura->cliente)
                ->pluck('contrato_nro');

                if(!DB::table('facturas_contratos')
                ->where('factura_id',$factura->id)->first()){

                    $contratoVerificar = Contrato::where('id',$factura->contrato_id)->first();
                    //Validando que si se trate de el contrato del verdadero cliente
                    if($factura->cliente != $contratoVerificar->client_id){
                        $contrato = Contrato::where('client_id',$factura->cliente)->first();
                        if(!$contrato){
                            $factura->contrato_id = null;
                        }else{
                            $factura->contrato_id = $contrato->id;
                        }
                        $factura->save();
                    }
                    $facturaContratos = Contrato::where('id',$factura->contrato_id)->pluck('nro');
                }

                $contratosId = Contrato::whereIn('nro',$facturaContratos)
                ->pluck('id');

                $ultimaFacturaRegistrada = Factura::
                where('cliente',$factura->cliente)
                ->where('estatus','<>',2)
                ->whereIn('contrato_id',$contratosId)
                ->orderBy('created_at', 'desc')
                ->value('id');

                //manera antigua de buscar el contrato.
                if(!$ultimaFacturaRegistrada){
                      $ultimaFacturaRegistrada = Factura::
                        where('cliente',$factura->cliente)
                        ->where('contrato_id',$factura->contrato_id)
                        ->orderBy('created_at', 'desc')
                        ->value('id');
                }

                //** Validacion nueva:
                ///** validamos que segun el grupo_corte la cantidad de facturas vencidas si sea igual
                if($factura->id == $ultimaFacturaRegistrada && $cantFacturasVencidas >= $cant_fac_grupo_corte){

                    //1. debemos primero mirar si los contrsatos existen en la tabla detalle, si no hacemos el proceso antiguo
                    $contratos = Contrato::whereIn('nro',$facturaContratos)->get();
                    if(!$contratos){
                        if($factura->contrato_id != null){
                            $contratos = Contrato::where('id',$factura->contrato_id)->get();
                        }else{
                            $contratos = Contrato::where('id',$contacto->contrato_id)->get();
                        }
                    }

                    $promesaExtendida = DB::table('promesa_pago')->where('factura', $contacto->factura)->where('vencimiento', '>=', $fecha)->count();

                    //2. Debemos recorrer el o los contratos para que haga el disabled.
                    foreach($contratos as $contrato){
                        $crm = CRM::where('cliente', $contacto->id)->whereIn('estado', [0, 3])->delete();
                        $crm = new CRM();
                        $crm->cliente = $contacto->id;
                        $crm->factura = $contacto->factura;
                        $crm->estado = 0;
                        $crm->servidor = isset($contrato->server_configuration_id) ? $contrato->server_configuration_id : '';
                        $crm->grupo_corte = isset($contrato->grupo_corte) ? $contrato->grupo_corte : '';
                        $crm->save();

                        if($promesaExtendida > 0){

                            if($contrato->state != 'enabled' && $empresa->consultas_mk ==1){

                                if(isset($contrato->server_configuration_id) && $factura->estatus != 0){

                                    $mikrotik = Mikrotik::where('id', $contrato->server_configuration_id)->first();
                                    $API = new RouterosAPI();
                                    $API->port = $mikrotik->puerto_api;

                                    if ($API->connect($mikrotik->ip,$mikrotik->usuario,$mikrotik->clave)) {
                                        $API->write('/ip/firewall/address-list/print', TRUE);
                                        $ARRAYS = $API->read();


                                    #ELIMINAMOS DE MOROSOS#
                                    $API->write('/ip/firewall/address-list/print', false);
                                    $API->write('?address='.$contrato->ip, false);
                                    $API->write("?list=morosos",false);
                                    $API->write('=.proplist=.id');
                                    $ARRAYS = $API->read();

                                    if(count($ARRAYS)>0){
                                        $API->write('/ip/firewall/address-list/remove', false);
                                        $API->write('=.id='.$ARRAYS[0]['.id']);
                                        $READ = $API->read();

                                        $contrato->state = 'enabled';
                                        $contrato->update();
                                    }
                                    #ELIMINAMOS DE MOROSOS#

                                    #AGREGAMOS A IP_AUTORIZADAS#
                                    $API->comm("/ip/firewall/address-list/add", array(
                                        "address" => $contrato->ip,
                                        "list" => 'ips_autorizadas'
                                        )
                                    );
                                    #AGREGAMOS A IP_AUTORIZADAS#
                                    $API->disconnect();
                                    }
                                }
                            }

                            continue;
                        }

                        //por aca entra cuando estamos deshbilitando de un grupo de corte sus contratos.
                        if (($contrato && $swGrupo == 1) ||
                        ($contrato && $swGrupo == 0 && $contrato->fecha_suspension == getdate()['mday'])) {

                        //segundo filtro de validacion, validando por rango de fechas
                        $diasHabilesNocobro = 0;
                        if($contrato->tipo_nosuspension == 1 &&  $contrato->fecha_desde_nosuspension <= $fecha && $contrato->fecha_hasta_nosuspension >= $fecha){
                            $diasHabilesNocobro = 1;
                        }

                        if($diasHabilesNocobro == 0){
                            if(isset($contrato->server_configuration_id) || $promesaExtendida == 0){

                                $mikrotik = Mikrotik::where('id', $contrato->server_configuration_id)->first();
                                $API = new RouterosAPI();
                                $API->port = $mikrotik->puerto_api;

                                if($empresa->consultas_mk ==1){
                                if ($API->connect($mikrotik->ip,$mikrotik->usuario,$mikrotik->clave)) {
                                    $API->write('/ip/firewall/address-list/print', TRUE);
                                    $ARRAYS = $API->read();
                                    if($contrato->state == 'enabled'){
                                        if($contrato->ip){
                                            $API->comm("/ip/firewall/address-list/add", array(
                                                "address" => $contrato->ip,
                                                "comment" => $contrato->servicio,
                                                "list" => 'morosos'
                                                )
                                            );

                                            #ELIMINAMOS DE IP_AUTORIZADAS#
                                            $API->write('/ip/firewall/address-list/print', false);
                                            $API->write('?address='.$contrato->ip, false);
                                            $API->write("?list=ips_autorizadas",false);
                                            $API->write('=.proplist=.id');
                                            $ARRAYS = $API->read();
                                            if(count($ARRAYS)>0){
                                                $API->write('/ip/firewall/address-list/remove', false);
                                                $API->write('=.id='.$ARRAYS[0]['.id']);
                                                $READ = $API->read();
                                            }
                                            #ELIMINAMOS DE IP_AUTORIZADAS#

                                            if(isset($empresa->activeconn_secret) && $empresa->activeconn_secret == 1){

                                                #DESHABILITACION DEL PPPoE#
                                                if ($contrato->conexion == 1 && $contrato->usuario != null) {

                                                    // Buscar el ID interno del secret con ese nombre
                                                    $API->write('/ppp/secret/print', false);
                                                    $API->write('?name=' . $contrato->usuario, true);
                                                    $ARRAYS = $API->read();

                                                    if (count($ARRAYS) > 0) {
                                                        $id = $ARRAYS[0]['.id']; // obtenemos el .id interno

                                                        // Deshabilitar el secret
                                                        $API->write('/ppp/secret/disable', false);
                                                        $API->write('=numbers=' . $id, true);
                                                        $response = $API->read();

                                                    }
                                                }
                                                #DESHABILITACION DEL PPPoE#

                                                #SE SACA DE LA ACTIVE CONNECTIONS
                                                if($contrato->conexion == 1 && $contrato->usuario != null){

                                                    $API->write('/ppp/active/print', false);
                                                    $API->write('?name=' . $contrato->usuario);
                                                    $response = $API->read();

                                                    if(isset($response['0']['.id'])){
                                                        $API->comm("/ppp/active/remove", [
                                                            ".id" => $response['0']['.id']
                                                        ]);
                                                    }
                                                    else{ //NUEVO CODIGO

                                                        //HACEMOS EL MISMO PROCESO PERO ENTONCES POR EL NRO CONTRARTO.
                                                        $API->write('/ppp/active/print', false);
                                                        $API->write('?name=' . $contrato->nro);
                                                        $response = $API->read();

                                                        if(isset($response['0']['.id'])){
                                                            $API->comm("/ppp/active/remove", [
                                                                ".id" => $response['0']['.id']
                                                            ]);
                                                        }
                                                    }

                                                }
                                                #SE SACA DE LA ACTIVE CONNECTIONS
                                            }


                                        }
                                        $i++;
                                    }
                                    $API->disconnect();
                                }
                                }

                                $contrato->state = 'disabled';
                                $contrato->observaciones = $contrato->observaciones. " - Contrato deshabilitado automaticamente";
                                $contrato->save();

                                $descripcion = '<i class="fas fa-check text-success"></i> <b>Cambio de Status</b> de habilitado a deshabilitado por cronjob de corte facturas<br>';
                                $movimiento = new MovimientoLOG();
                                $movimiento->contrato    = $contrato->id;
                                $movimiento->modulo      = 5;
                                $movimiento->descripcion = $descripcion;
                                $movimiento->created_by  = 1;
                                $movimiento->empresa     = $contrato->empresa;
                                $movimiento->save();
                            }
                        }
                        }
                    }
                }
            }

            if (file_exists("CorteFacturas.txt")){
                $file = fopen("CorteFacturas.txt", "a");
                fputs($file, "-----------------".PHP_EOL);
                fputs($file, "Fecha de Corte: ".date('Y-m-d').''. PHP_EOL);
                fputs($file, "Contratos Deshabilitados: ".$i.''. PHP_EOL);
                fputs($file, "-----------------".PHP_EOL);
                fclose($file);
            }else{
                $file = fopen("CorteFacturas.txt", "w");
                fputs($file, "-----------------".PHP_EOL);
                fputs($file, "Fecha de Corte: ".date('Y-m-d').''. PHP_EOL);
                fputs($file, "Contratos Deshabilitados: ".$i.''. PHP_EOL);
                fputs($file, "-----------------".PHP_EOL);
                fclose($file);
            }

            if(request()->fechaCorte){
                return back();
            }
        }

    }


    public static function CortarFacturas(){
        $i=0;
        $fecha = date('Y-m-d');

        if(request()->fechaCorte){
            $fecha = request()->fechaCorte;
        }
        $swGrupo = 1; //masivo
        $horaActual = date('H:i');

        $grupos_corte = DB::table('grupos_corte')
        ->where('status', 1)
        ->whereTime('hora_suspension', '<=', $horaActual)
        ->where('fecha_suspension','!=',0)
        ->orderby('nro_factura_vencida','asc')
        ->get();

        if($grupos_corte->count() > 0){

            $grupos_corte_array = array();

            foreach($grupos_corte as $grupo){
                array_push($grupos_corte_array,$grupo->id);
            }

            $whereOrder = implode(',', $grupos_corte_array);

            //Estamos tomando la ultima factura siempre del cliente con el orderby y el groupby, despues analizamos si esta ultima ya vencio
            $contactos = Contacto::join('factura as f','f.cliente','=','contactos.id')->
                leftJoin('facturas_contratos as fcs', 'fcs.factura_id', '=', 'f.id')
                ->leftJoin('contracts as cs', function ($join) {
                    $join->on('cs.nro', '=', 'fcs.contrato_nro');
                })->
                select('contactos.id', 'contactos.nombre', 'contactos.nit', 'f.id as factura', 'f.estatus',
                 'f.suspension', 'cs.state', 'f.contrato_id','cs.grupo_corte')->
                where('f.estatus',1)->
                whereIn('f.tipo', [1,2])->
                where('contactos.status',1)->
                where('cs.state','enabled')->
                whereIn('cs.grupo_corte',$grupos_corte_array)->
                where(function($sub){
                    $sub->whereNull('cs.fecha_suspension')
                    ->orWhere('cs.fecha_suspension',0);
                })->
                where('cs.server_configuration_id','!=',null)->
                whereDate('f.vencimiento', '<=', now())->
                where('f.id', function ($subquery) {
                    $subquery->selectRaw('MAX(f2.id)')
                        ->from('factura as f2')
                        ->whereColumn('f2.cliente', 'contactos.id')
                        ->where('f2.estatus', 1)
                        ->whereIn('f2.tipo', [1, 2])
                        ->whereDate('f2.vencimiento', '<=', now())
                        ;
                        //Habilitar cuando no se desee cortar por la ultima factura si no por otra fecha.
                        // ->
                        //     whereBetween('f2.created_at', [
                        //         '2025-12-01 00:00:00',
                        //         '2025-12-31 23:59:59'
                        // ]);
                })->
                orderByRaw("FIELD(cs.grupo_corte, $whereOrder)")->
                orderBy('contactos.updated_at', 'asc')->
                get();

        }else{
            $contactos = Contacto::join('factura as f','f.cliente','=','contactos.id')->
            join('contracts as cs','cs.client_id','=','contactos.id')->
            select('contactos.id', 'contactos.nombre', 'contactos.nit', 'f.id as factura', 'f.estatus', 'f.suspension', 'cs.state', 'f.contrato_id','cs.grupo_corte')->
            where('f.estatus',1)->
            whereIn('f.tipo', [1,2])->
            where('contactos.status',1)->
            where('cs.state','enabled')->
            where('cs.fecha_suspension','!=', null)->
            take(20)->
            get();
            $swGrupo = 0; // personalizado
        }

            if($contactos){
            $empresa = Empresa::find(1);
            foreach ($contactos as $contacto) {

                //** Desarrollo nuevo:
                //** Analizar la cantidad de facturas abiertas del contrato y el grupo de corte
                $contacto->updated_at = now();
                $contacto->save();

                $grupo_corte = null;
                $cant_fac_grupo_corte = 1;
                $cantFacturasVencidas = 1;
                if(isset($contacto->grupo_corte) && $contacto->grupo_corte != ""){
                    $grupo_corte = GrupoCorte::Find($contacto->grupo_corte);
                    $cant_fac_grupo_corte = $grupo_corte->nro_factura_vencida;
                }

                if(isset($grupo_corte->nro_factura_vencida) && $grupo_corte->nro_factura_vencida > 1){
                    $contrato = Contrato::Find($contacto->contrato_id);
                    if($contrato){
                        $cantFacturasVencidas = $contrato->cantidadFacturasVencidas();
                    }else{
                        continue;
                    }
                }
                //** Fin desarrollo nuevo

                $factura = Factura::find($contacto->factura);

                //ESto es lo que hay que refactorizar.
                $facturaContratos = DB::table('facturas_contratos')
                ->where('factura_id',$factura->id)
                ->where('client_id',$factura->cliente)
                ->pluck('contrato_nro');

                if(!DB::table('facturas_contratos')
                ->where('factura_id',$factura->id)->first()){

                    $contratoVerificar = Contrato::where('id',$factura->contrato_id)->first();
                    //Validando que si se trate de el contrato del verdadero cliente
                    
                    if($contratoVerificar){
                        if($factura->cliente != $contratoVerificar->client_id){
                            $contrato = Contrato::where('client_id',$factura->cliente)->first();
                            if(!$contrato){
                            $factura->contrato_id = null;
                            }else{
                            $factura->contrato_id = $contrato->id;
                            }
                            $factura->save();
                        }
                        $facturaContratos = Contrato::where('id',$factura->contrato_id)->pluck('nro');
                    }
                }

                $contratosNro = Contrato::whereIn('nro',$facturaContratos)
                ->pluck('nro');

                $ultimaFacturaRegistrada = DB::table('facturas_contratos')
                    ->join('factura', 'facturas_contratos.factura_id', '=', 'factura.id')
                    ->whereIn('facturas_contratos.contrato_nro', $contratosNro)
                    ->where('factura.estatus','!=',2)
                    ->select('factura.*')
                    ->orderBy('factura.created_at', 'desc')
                ->value('id');

                //manera antigua de buscar el contrato.
                if(!$ultimaFacturaRegistrada){
                      $ultimaFacturaRegistrada = Factura::
                        where('cliente',$factura->cliente)
                        ->where('contrato_id',$factura->contrato_id)
                        ->orderBy('created_at', 'desc')
                        ->value('id');
                }

                //** Validacion nueva:
                ///** validamos que segun el grupo_corte la cantidad de facturas vencidas si sea igual
                if($factura->id == $ultimaFacturaRegistrada && $cantFacturasVencidas >= $cant_fac_grupo_corte){

                    //1. debemos primero mirar si los contrsatos existen en la tabla detalle, si no hacemos el proceso antiguo
                    $contratos = Contrato::whereIn('nro',$facturaContratos)->get();
                    if(!$contratos){
                        if($factura->contrato_id != null){
                            $contratos = Contrato::where('id',$factura->contrato_id)->get();
                        }else{
                            $contratos = Contrato::where('id',$contacto->contrato_id)->get();
                        }
                    }

                    $promesaExtendida = DB::table('promesa_pago')->where('factura', $contacto->factura)->where('vencimiento', '>=', $fecha)->count();

                    //2. Debemos recorrer el o los contratos para que haga el disabled.
                    foreach($contratos as $contrato){
                        $crm = CRM::where('cliente', $contacto->id)->whereIn('estado', [0, 3])->delete();
                        $crm = new CRM();
                        $crm->cliente = $contacto->id;
                        $crm->factura = $contacto->factura;
                        $crm->estado = 0;
                        $crm->servidor = isset($contrato->server_configuration_id) ? $contrato->server_configuration_id : '';
                        $crm->grupo_corte = isset($contrato->grupo_corte) ? $contrato->grupo_corte : '';
                        $crm->save();

                        if($promesaExtendida > 0){

                            if($contrato->state != 'enabled' && $empresa->consultas_mk ==1){

                                if(isset($contrato->server_configuration_id) && $factura->estatus != 0){

                                    $mikrotik = Mikrotik::where('id', $contrato->server_configuration_id)->first();
                                    $API = new RouterosAPI();
                                    $API->port = $mikrotik->puerto_api;

                                    if ($API->connect($mikrotik->ip,$mikrotik->usuario,$mikrotik->clave)) {
                                        $API->write('/ip/firewall/address-list/print', TRUE);
                                        $ARRAYS = $API->read();


                                    #ELIMINAMOS DE MOROSOS#
                                    $API->write('/ip/firewall/address-list/print', false);
                                    $API->write('?address='.$contrato->ip, false);
                                    $API->write("?list=morosos",false);
                                    $API->write('=.proplist=.id');
                                    $ARRAYS = $API->read();

                                    if(count($ARRAYS)>0){
                                        $API->write('/ip/firewall/address-list/remove', false);
                                        $API->write('=.id='.$ARRAYS[0]['.id']);
                                        $READ = $API->read();

                                        $contrato->state = 'enabled';
                                        $contrato->update();
                                    }
                                    #ELIMINAMOS DE MOROSOS#

                                    #AGREGAMOS A IP_AUTORIZADAS#
                                    $API->comm("/ip/firewall/address-list/add", array(
                                        "address" => $contrato->ip,
                                        "list" => 'ips_autorizadas'
                                        )
                                    );
                                    #AGREGAMOS A IP_AUTORIZADAS#

                                    // #HABILITACION DEL PPOE#
                                    // if($contrato->conexion == 1 && $contrato->usuario != null){
                                    //     $API->write('/ppp/secret/enable', false);
                                    //     $API->write('=numbers=' . $contrato->usuario);
                                    //     $response = $API->read();
                                    // }
                                    // #HABILITACION DEL PPOE#
                                    $API->disconnect();
                                    }
                                }
                            }

                            continue;
                        }

                        //por aca entra cuando estamos deshbilitando de un grupo de corte sus contratos.
                        if (($contrato && $swGrupo == 1) ||
                        ($contrato && $swGrupo == 0 && $contrato->fecha_suspension == getdate()['mday'])) {

                        //segundo filtro de validacion, validando por rango de fechas
                        $diasHabilesNocobro = 0;
                        if($contrato->tipo_nosuspension == 1 &&  $contrato->fecha_desde_nosuspension <= $fecha && $contrato->fecha_hasta_nosuspension >= $fecha){
                            $diasHabilesNocobro = 1;
                        }

                        if($diasHabilesNocobro == 0){
                            if(isset($contrato->server_configuration_id) || $promesaExtendida == 0){

                                $mikrotik = Mikrotik::where('id', $contrato->server_configuration_id)->first();
                                $API = new RouterosAPI();
                                $API->port = $mikrotik->puerto_api;

                                if($empresa->consultas_mk ==1){
                                if ($API->connect($mikrotik->ip,$mikrotik->usuario,$mikrotik->clave)) {
                                    $API->write('/ip/firewall/address-list/print', TRUE);
                                    $ARRAYS = $API->read();
                                    if($contrato->state == 'enabled'){
                                        if($contrato->ip){
                                            $API->comm("/ip/firewall/address-list/add", array(
                                                "address" => $contrato->ip,
                                                "comment" => $contrato->servicio,
                                                "list" => 'morosos'
                                                )
                                            );

                                            #ELIMINAMOS DE IP_AUTORIZADAS#
                                            $API->write('/ip/firewall/address-list/print', false);
                                            $API->write('?address='.$contrato->ip, false);
                                            $API->write("?list=ips_autorizadas",false);
                                            $API->write('=.proplist=.id');
                                            $ARRAYS = $API->read();
                                            if(count($ARRAYS)>0){
                                                $API->write('/ip/firewall/address-list/remove', false);
                                                $API->write('=.id='.$ARRAYS[0]['.id']);
                                                $READ = $API->read();
                                            }
                                            #ELIMINAMOS DE IP_AUTORIZADAS#

                                            if(isset($empresa->activeconn_secret) && $empresa->activeconn_secret == 1){

                                                #DESHABILITACION DEL PPPoE#
                                                if ($contrato->conexion == 1 && $contrato->usuario != null) {

                                                    // Buscar el ID interno del secret con ese nombre
                                                    $API->write('/ppp/secret/print', false);
                                                    $API->write('?name=' . $contrato->usuario, true);
                                                    $ARRAYS = $API->read();

                                                    if (count($ARRAYS) > 0) {
                                                        $id = $ARRAYS[0]['.id']; // obtenemos el .id interno

                                                        // Deshabilitar el secret
                                                        $API->write('/ppp/secret/disable', false);
                                                        $API->write('=numbers=' . $id, true);
                                                        $response = $API->read();

                                                    }
                                                }
                                                #DESHABILITACION DEL PPPoE#

                                                #SE SACA DE LA ACTIVE CONNECTIONS
                                                if($contrato->conexion == 1 && $contrato->usuario != null){

                                                    $API->write('/ppp/active/print', false);
                                                    $API->write('?name=' . $contrato->usuario);
                                                    $response = $API->read();

                                                    if(isset($response['0']['.id'])){
                                                        $API->comm("/ppp/active/remove", [
                                                            ".id" => $response['0']['.id']
                                                        ]);
                                                    }
                                                    else{ //NUEVO CODIGO

                                                        //HACEMOS EL MISMO PROCESO PERO ENTONCES POR EL NRO CONTRARTO.
                                                        $API->write('/ppp/active/print', false);
                                                        $API->write('?name=' . $contrato->nro);
                                                        $response = $API->read();

                                                        if(isset($response['0']['.id'])){
                                                            $API->comm("/ppp/active/remove", [
                                                                ".id" => $response['0']['.id']
                                                            ]);
                                                        }
                                                    }

                                                }
                                                #SE SACA DE LA ACTIVE CONNECTIONS
                                            }


                                        }
                                        $i++;
                                    }
                                    $API->disconnect();
                                }
                                }

                                $contrato->state = 'disabled';
                                $contrato->observaciones = $contrato->observaciones. " - Contrato deshabilitado automaticamente";
                                $contrato->save();

                                $descripcion = '<i class="fas fa-check text-success"></i> <b>Cambio de Status</b> de habilitado a deshabilitado por cronjob de corte facturas<br>';
                                $movimiento = new MovimientoLOG();
                                $movimiento->contrato    = $contrato->id;
                                $movimiento->modulo      = 5;
                                $movimiento->descripcion = $descripcion;
                                $movimiento->created_by  = 1;
                                $movimiento->empresa     = $contrato->empresa;
                                $movimiento->save();
                            }
                        }
                        }
                    }
                }
            }

            if (file_exists("CorteFacturas.txt")){
                $file = fopen("CorteFacturas.txt", "a");
                fputs($file, "-----------------".PHP_EOL);
                fputs($file, "Fecha de Corte: ".date('Y-m-d').''. PHP_EOL);
                fputs($file, "Contratos Deshabilitados: ".$i.''. PHP_EOL);
                fputs($file, "-----------------".PHP_EOL);
                fclose($file);
            }else{
                $file = fopen("CorteFacturas.txt", "w");
                fputs($file, "-----------------".PHP_EOL);
                fputs($file, "Fecha de Corte: ".date('Y-m-d').''. PHP_EOL);
                fputs($file, "Contratos Deshabilitados: ".$i.''. PHP_EOL);
                fputs($file, "-----------------".PHP_EOL);
                fclose($file);
            }

            if(request()->fechaCorte){
                return back();
            }
        }
    }

    public function cortarTelevision(){
        $i=0;
        $fecha = date('Y-m-d');
        $empresa = Empresa::find(1);

        if(request()->fechaCorte){
            $fecha = request()->fechaCorte;
        }
        $swGrupo = 1; //masivo
        $horaActual = date('H:i');

        $grupos_corte = DB::table('grupos_corte')
        ->where('status', 1)
        ->where('hora_suspension','<=',$horaActual)
        ->where('fecha_suspension','!=',0)
        ->get();

        if($grupos_corte->count() > 0 && $empresa->smartOLT != null){

            $grupos_corte_array = array();

            foreach($grupos_corte as $grupo){
                array_push($grupos_corte_array,$grupo->id);
            }

            $contactos = Contacto::join('factura as f', 'f.cliente', '=', 'contactos.id')
            ->leftJoin('facturas_contratos as fcs', 'fcs.factura_id', '=', 'f.id')
                ->leftJoin('contracts as cs', function ($join) {
                    $join->on('cs.nro', '=', 'fcs.contrato_nro');
                        //  ->orOn('cs.id', '=', 'f.contrato_id');
                })->
            join('grupos_corte as gc', 'gc.id', '=', 'cs.grupo_corte') // Unimos con grupos_corte
            ->select(
                'contactos.id',
                'contactos.nombre',
                'contactos.nit',
                'f.id as factura',
                'f.estatus',
                'f.suspension',
                'cs.state',
                'f.contrato_id',
                'gc.prorroga_tv', // Seleccionamos prorroga_tv
                'gc.id as grupo_corte',
                'contactos.updated_at'
            )
            ->where('f.estatus', 1)
            ->whereIn('f.tipo', [1, 2])
            ->where('contactos.status', 1)
            ->whereDate('f.vencimiento', '<=', now())
            ->whereIn('cs.grupo_corte', $grupos_corte_array)
            ->where('cs.fecha_suspension', null)
            ->where('cs.state_olt_catv', true)
            ->where('f.id', function ($subquery) {
                $subquery->selectRaw('MAX(f2.id)')
                    ->from('factura as f2')
                    ->whereColumn('f2.cliente', 'contactos.id')
                    ->where('f2.estatus', 1)
                    ->whereIn('f2.tipo', [1, 2])
                    ->whereDate('f2.vencimiento', '<=', now());
            })
            ->orderBy('contactos.updated_at', 'asc')
            ->take(50)
            ->get();

            if($contactos){
                $i=0;
                foreach ($contactos as $contacto) {

                    //** Desarrollo nuevo:
                    //** Analizar la cantidad de facturas abiertas del contrato y el grupo de corte
                    $contacto->updated_at = now();
                    $contacto->save();

                    $grupo_corte = null;
                    $cant_fac_grupo_corte = 1;
                    $cantFacturasVencidas = 1;
                    if(isset($contacto->grupo_corte) && $contacto->grupo_corte != ""){
                        $grupo_corte = GrupoCorte::Find($contacto->grupo_corte);
                        $cant_fac_grupo_corte = $grupo_corte->nro_factura_vencida;
                    }

                    if(isset($grupo_corte->nro_factura_vencida) && $grupo_corte->nro_factura_vencida > 1){
                        $contrato = Contrato::Find($contacto->contrato_id);
                        $cantFacturasVencidas = $contrato->cantidadFacturasVencidas();
                    }
                    //** Fin desarrollo nuevo

                    $factura = Factura::find($contacto->factura);

                    //ESto es lo que hay que refactorizar.
                    $facturaContratos = DB::table('facturas_contratos')
                    ->where('factura_id',$factura->id)->pluck('contrato_nro');

                    if(!DB::table('facturas_contratos')
                    ->where('factura_id',$factura->id)->first()){
                        $facturaContratos = Contrato::where('id',$factura->contrato_id)->pluck('nro');
                    }

                    $contratosId = Contrato::whereIn('nro',$facturaContratos)
                    ->pluck('id');

                    $ultimaFacturaRegistrada = Factura::
                    where('cliente',$factura->cliente)
                    ->where('estatus','<>',2)
                    ->whereIn('contrato_id',$contratosId)
                    ->orderBy('created_at', 'desc')
                    ->value('id');

                    //manera antigua de buscar el contrato.
                    if(!$ultimaFacturaRegistrada){
                        $ultimaFacturaRegistrada = Factura::
                        where('cliente',$factura->cliente)
                        ->where('contrato_id',$factura->contrato_id)
                        ->orderBy('created_at', 'desc')
                        ->value('id');
                    }

                    if($factura->id == $ultimaFacturaRegistrada && $cantFacturasVencidas >= $cant_fac_grupo_corte){

                    //1. debemos primero mirar si los contrsatos existen en la tabla detalle, si no hacemos el proceso antiguo
                    $contratos = Contrato::whereIn('nro',$facturaContratos)->get();
                    if(!$contratos){
                        if($factura->contrato_id != null){
                            $contratos = Contrato::where('id',$factura->contrato_id)->get();
                        }else{
                            $contratos = Contrato::where('id',$contacto->contrato_id)->get();
                        }
                    }

                    $promesaExtendida = DB::table('promesa_pago')->where('factura', $factura->id)->where('vencimiento', '>=', $fecha)->count();
                    if($promesaExtendida > 0){
                        continue;
                    }

                    //2. Debemos recorrer el o los contratos para que haga el disabled.
                        foreach($contratos as $contrato){

                                if($contrato->olt_sn_mac != null){
                                    $curl = curl_init();

                                curl_setopt_array($curl, array(
                                CURLOPT_URL => $empresa->adminOLT.'/api/onu/disable_catv/'.$contrato->olt_sn_mac,
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_ENCODING => '',
                                CURLOPT_MAXREDIRS => 10,
                                CURLOPT_TIMEOUT => 0,
                                CURLOPT_FOLLOWLOCATION => true,
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                CURLOPT_CUSTOMREQUEST => 'POST',
                                CURLOPT_HTTPHEADER => array(
                                    'X-token: '.$empresa->smartOLT
                                ),
                                ));

                                $response = curl_exec($curl);
                                $response = json_decode($response);

                                curl_close($curl);

                                if(isset($response->status) && $response->status == true){
                                    $contrato->state_olt_catv = false;
                                    $contrato->save();

                                    $descripcion = '<i class="fas fa-check text-success"></i> <b>Cambio de Status</b> de habilitado a deshabilitado por cronjob de corte facturas (TV)<br>';
                                    $movimiento = new MovimientoLOG();
                                    $movimiento->contrato    = $contrato->id;
                                    $movimiento->modulo      = 5;
                                    $movimiento->descripcion = $descripcion;
                                    $movimiento->created_by  = 1;
                                    $movimiento->empresa     = $contrato->empresa;
                                    $movimiento->save();

                                }

                            }
                        }
                    }
                }
            }
        }
    }

    public static function CortarPromesas(){
        $i=0;
        $fecha = date('Y-m-d');
        $hora = date('G:i');
        $hora_24 = date('H:i', strtotime($hora));

        $contactos = Contacto::join('factura as f','f.cliente','=','contactos.id')->
            join('facturas_contratos as fc','fc.factura_id','f.id')->
            join('contracts as cs','cs.nro','=','fc.contrato_nro')->
            join('promesa_pago as p', 'p.factura', '=', 'f.id')->
            select('contactos.id','p.hora_pago','f.codigo','cs.nro')->
            where('f.estatus',1)->
            whereIn('f.tipo', [1,2])->
            where('f.promesa_pago', $fecha)->
            where('contactos.status',1)->
            where('cs.state','enabled')->
            whereRaw('TIME_FORMAT(p.hora_pago, "%H:%i") < ?', [$hora_24])->
            get();

        $empresa = Empresa::find(1);
        foreach ($contactos as $contacto) {
            $contrato = Contrato::where('nro', $contacto->nro)->first();

            //$crm = CRM::where('cliente', $contacto->id)->whereIn('estado', [0, 3])->delete();
            /*$crm = new CRM();
            $crm->cliente = $contacto->id;
            $crm->factura = $contacto->factura;
            $crm->servidor = $contrato->server_configuration_id;
            $crm->grupo_corte = $contrato->grupo_corte;
            $crm->save();*/

            $mikrotik = Mikrotik::where('id', $contrato->server_configuration_id)->first();

            $API = new RouterosAPI();
            $API->port = $mikrotik->puerto_api;

            if ($contrato) {
                if ($API->connect($mikrotik->ip,$mikrotik->usuario,$mikrotik->clave)) {
                    $API->write('/ip/firewall/address-list/print', TRUE);
                    $ARRAYS = $API->read();
                    if($contrato->state == 'enabled'){
                        if($contrato->ip){
                            $API->comm("/ip/firewall/address-list/add", array(
                                "address" => $contrato->ip,
                                "comment" => $contrato->servicio,
                                "list" => 'morosos'
                                )
                            );

                            #ELIMINAMOS DE IP_AUTORIZADAS#
                            $API->write('/ip/firewall/address-list/print', false);
                            $API->write('?address='.$contrato->ip, false);
                            $API->write("?list=ips_autorizadas",false);
                            $API->write('=.proplist=.id');
                            $ARRAYS = $API->read();
                            if(count($ARRAYS)>0){
                                $API->write('/ip/firewall/address-list/remove', false);
                                $API->write('=.id='.$ARRAYS[0]['.id']);
                                $READ = $API->read();
                            }
                            #ELIMINAMOS DE IP_AUTORIZADAS#
                        }
                        $contrato->state = 'disabled';
                        $i++;

                        $descripcion = '<i class="fas fa-check text-success"></i> <b>Cambio de Status</b> de habilitado a deshabilitado por cronjob de corte promesas<br>';
                        $movimiento = new MovimientoLOG();
                        $movimiento->contrato    = $contrato->id;
                        $movimiento->modulo      = 5;
                        $movimiento->descripcion = $descripcion;
                        $movimiento->created_by  = 1;
                        $movimiento->empresa     = $contrato->empresa;
                        $movimiento->save();

                    }
                    $API->disconnect();
                    $contrato->save();
                }
            }
        }

        if (file_exists("CortePromesas.txt")){
            $file = fopen("CortePromesas.txt", "a");
            fputs($file, "-----------------".PHP_EOL);
            fputs($file, "Fecha de Promesa: ".date('Y-m-d').''. PHP_EOL);
            fputs($file, "Contratos Deshabilitados: ".$i.''. PHP_EOL);
            fputs($file, "-----------------".PHP_EOL);
            fclose($file);
        }else{
            $file = fopen("CortePromesas.txt", "w");
            fputs($file, "-----------------".PHP_EOL);
            fputs($file, "Fecha de Promesa: ".date('Y-m-d').''. PHP_EOL);
            fputs($file, "Contratos Deshabilitados: ".$i.''. PHP_EOL);
            fputs($file, "-----------------".PHP_EOL);
            fclose($file);
        }
    }

    public static function CortarCRM(){
        $fecha = date('d-m-Y');
        $hora = date('G:i');
        $i = 0;
        $notificaciones = CRM::join('factura as f','f.id','=','crm.factura')->where('f.estatus',1)->where('crm.fecha_pago', $fecha)->where('crm.hora_pago', $hora)->select('f.id as factura', 'f.cliente', 'f.estatus', 'crm.id', 'crm.estado', 'crm.fecha_pago')->get();

        foreach($notificaciones as $notificacion){
            $notificacion->estado = 2;
            $notificacion->notificacion = 1;
            $notificacion->save();
            $i++;
        }

        if (file_exists("CortarCRM.txt")){
                $file = fopen("CortarCRM.txt", "a");
                fputs($file, "-----------------".PHP_EOL);
                fputs($file, "Fecha de Corte: ".date('Y-m-d').''. PHP_EOL);
                fputs($file, "CRM: ".$i.''. PHP_EOL);
                fputs($file, "-----------------".PHP_EOL);
                fclose($file);
            }else{
                $file = fopen("CortarCRM.txt", "w");
                fputs($file, "-----------------".PHP_EOL);
                fputs($file, "Fecha de Corte: ".date('Y-m-d').''. PHP_EOL);
                fputs($file, "CRM: ".$i.''. PHP_EOL);
                fputs($file, "-----------------".PHP_EOL);
                fclose($file);
            }

        //Conectando login de siigo cada cierto tiempo
        $siigoLogin = new SiigoController();
        $siigoLogin->configurarSiigo(null,null, true);

    }

    public static function monitorBlacklist(){
        $blacklists = Blacklist::all();
        $empresa    = Empresa::find(1);
        $api_key    = $empresa->api_key_hetrixtools;
        $contact    = $empresa->id_contacto_hetrixtools;
        $respon     = '';
        $datos      = [];

        if($api_key || $contact){
            foreach($blacklists as $blacklist) {
                $url = 'https://api.hetrixtools.com/v2/'.$api_key.'/blacklist-check/ipv4/'.$blacklist->ip.'/';

                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                ));
                $result = curl_exec($curl);
                curl_close($curl);

                $response = json_decode($result, true);
                if($response['status'] == 'ERROR'){
                    $respon .= $blacklist->ip.' - '.$response['error_message'].'<br>';
                }else{
                    $blacklist->blacklisted_count = $response['blacklisted_count'];
                    $blacklist->estado = ($response['blacklisted_count'] == 0) ? 1:2;
                    $blacklist->response = '';
                    $blacklist->save();
                    $respon .= $blacklist->ip.' - '.$response['blacklisted_count'].'<br>';

                    if($blacklist->estado == 2){
                        $var = array(
                            'nombre' => $blacklist->nombre,
                            'ip' => $blacklist->ip,
                            'blacklisted_count' => $blacklist->blacklisted_count,
                            'estado' => $blacklist->estado,
                            'empresa' => $empresa->nombre,
                            'color' => $empresa->color
                        );

                        array_push($datos,$var);
                    }
                }
            }

            if(count($datos)>0){
                $correo = new BlacklistMailable($datos);
                $host = ServidorCorreo::where('estado', 1)->where('empresa', 1)->first();
                if($host){
                    $existing = config('mail');
                    $new =array_merge(
                        $existing, [
                            'host' => $host->servidor,
                            'port' => $host->puerto,
                            'encryption' => $host->seguridad,
                            'username' => $host->usuario,
                            'password' => $host->password,
                        ]
                    );
                    config(['mail'=>$new]);
                }
                // Mail::to($empresa->email)->send($correo);
            }
        }
    }

    public static function PagoOportuno(){
        $empresa = Empresa::find(1);
        $i=0;
        $fecha = date('Y-m-d');
        $numeros = [];
        $bulk = '';
        $fail = 0;
        $succ = 0;

        $contactos = Contacto::join('factura as f','f.cliente','=','contactos.id')->
            join('contracts as cs','cs.client_id','=','contactos.id')->
            select('contactos.celular', 'f.vencimiento', 'contactos.id as idContacto')->
            where('f.estatus',1)->
            whereIn('f.tipo', [1,2])->
            where('f.pago_oportuno', $fecha)->
            where('contactos.status',1)->
            where('cs.status',1)->
            get();

        foreach ($contactos as $contacto) {
            $numero = str_replace('+','',$contacto->celular);
            $numero = str_replace(' ','',$numero);
            array_push($numeros, '57'.$numero);

            if($empresa->sms_factura_generada){

                $facturaDetalle = Factura::where('cliente', $contacto->idContacto)->whereIn('tipo', [1,2])->where('pago_oportuno', $fecha)->get();

                foreach($facturaDetalle as $fd){

                        $nombreCliente = trim($fd->cliente()->nombre.' '.$fd->cliente()->apellidos());
                        $nombreEmpresa = $empresa->nombre;
                        $codigoFactura = $fd->codigo ?? $fd->nro;
                        $valorFactura =  $fd->totalAPI($empresa->id)->total;
                        $fechaVencimiento = date('d-m-Y', strtotime($fd->vencimiento));

                        $bulksms = $empresa->sms_factura_generada;
                        $bulksms = str_replace("{cliente}", $nombreCliente, $bulksms);
                        $bulksms = str_replace("{empresa}", $nombreEmpresa, $bulksms);
                        $bulksms = str_replace("{factura}", $codigoFactura, $bulksms);
                        $bulksms = str_replace("{valor}", $valorFactura, $bulksms);
                        $bulksms = str_replace("{vencimiento}", $fechaVencimiento, $bulksms);

                        $bulk .= '{"numero": "57'.$numero.'", "sms": "'.$bulksms.'"},';
                }

            }else if($empresa->nombre == 'FIBRACONEXION S.A.S.' || $empresa->nit == '900822955' || $empresa->nombre == 'Almeidas Comunicaciones S.A.S' ||  $empresa->nit == '901044772'){
                $facturaDetalle = Factura::where('cliente', $contacto->idContacto)->whereIn('tipo', [1,2])->where('pago_oportuno', $fecha)->get();
                foreach($facturaDetalle as $fd){
                    $fullname = $fd->cliente()->nombre.' '.$fd->cliente()->apellidos();
                    $bulk .= '{"numero": "57'.$numero.'", "sms": "'.trim($fullname).'. '.$empresa->nombre.' le informa que su factura de servicio de internet. Tiene como fecha de vencimiento: '.date('d-m-Y', strtotime($fd->vencimiento)).' Total a pagar '.$fd->totalAPI($empresa->id)->total.'"},';
                }
            }else{
                $bulk .= '{"numero": "57'.$numero.'", "sms": "Estimado cliente, se le informa que su factura de internet ha sido generada. '.$empresa->slogan.'"},';
            }
        }

        $servicio = Integracion::where('empresa', 1)->where('tipo', 'SMS')->where('status', 1)->first();
        if($servicio){
            $mensaje = "Estimado cliente, su fecha limite de pago es el ".date('d-m-Y').", recuerde pagar su factura y evite la suspension del servicio. ".$empresa->slogan;

            if($servicio->nombre == 'Hablame SMS'){
                if($servicio->api_key && $servicio->user && $servicio->pass){
                    $curl = curl_init();
                    curl_setopt_array($curl, [
                        CURLOPT_URL => "https://api103.hablame.co/api/sms/v3/send/marketing/bulk",
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 30,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "POST",
                        CURLOPT_POSTFIELDS => "{\n  \"bulk\": [\n    ".substr($bulk, 0, -1)."\n  ]\n}",
                        CURLOPT_HTTPHEADER => [
                            'Content-Type: application/json',
                            'account: '.$servicio->user,
                            'apiKey: '.$servicio->api_key,
                            'token: '.$servicio->pass,
                            ],
                    ]);

                    $response = curl_exec($curl);
                    $err = curl_error($curl);
                    curl_close($curl);
                }
            }elseif($servicio->nombre == 'SmsEasySms'){
                if($servicio->user && $servicio->pass){
                    $post['to'] = $numeros;
                    $post['text'] = $mensaje;
                    $post['from'] = "SMS";
                    $login = $servicio->user;
                    $password = $servicio->pass;

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, "https://sms.istsas.com/Api/rest/message");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
                    curl_setopt($ch, CURLOPT_HTTPHEADER,
                        array(
                            "Accept: application/json",
                            "Authorization: Basic ".base64_encode($login.":".$password)));
                    $result = curl_exec ($ch);
                    $err  = curl_error($ch);
                    curl_close($ch);
                }
            }else{
                if($servicio->user && $servicio->pass){
                    $post['to'] = $numeros;
                    $post['text'] = $mensaje;
                    $post['from'] = "";
                    $login = $servicio->user;
                    $password = $servicio->pass;

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, "https://masivos.colombiared.com.co/Api/rest/message");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
                    curl_setopt($ch, CURLOPT_HTTPHEADER,
                        array(
                            "Accept: application/json",
                            "Authorization: Basic ".base64_encode($login.":".$password)));
                    $result = curl_exec ($ch);
                    $err  = curl_error($ch);
                    curl_close($ch);
                }
            }
        }

        if (file_exists("PagoOportuno.txt")){
            $file = fopen("PagoOportuno.txt", "a");
            fputs($file, "-----------------".PHP_EOL);
            fputs($file, "Fecha de Notificación: ".date('d-m-Y').''. PHP_EOL);
            fputs($file, "SMS Enviados: ".$succ.''. PHP_EOL);
            fputs($file, "SMS NO Enviados: ".$fail.''. PHP_EOL);
            fputs($file, "-----------------".PHP_EOL);
            fclose($file);
        }else{
            $file = fopen("PagoOportuno.txt", "w");
            fputs($file, "-----------------".PHP_EOL);
            fputs($file, "Fecha de Notificación: ".date('d-m-Y').''. PHP_EOL);
            fputs($file, "SMS Enviados: ".$succ.''. PHP_EOL);
            fputs($file, "SMS NO Enviados: ".$fail.''. PHP_EOL);
            fputs($file, "-----------------".PHP_EOL);
            fclose($file);
        }
    }

    public static function PagoVencimiento(){
        $empresa = Empresa::find(1);
        $i=0;
        $fecha = date('Y-m-d');
        $numeros = [];
        $bulk = '';
        $fail = 0;
        $succ = 0;

        $contactos = Contacto::join('factura as f','f.cliente','=','contactos.id')->
            join('contracts as cs','cs.client_id','=','contactos.id')->
            select('contactos.celular', 'contactos.id as idContacto')->
            where('f.estatus',1)->
            whereIn('f.tipo', [1,2])->
            where('f.vencimiento', $fecha)->
            where('contactos.status',1)->
            where('cs.status',1)->
            get();

        foreach ($contactos as $contacto) {
            $numero = str_replace('+','',$contacto->celular);
            $numero = str_replace(' ','',$numero);
            array_push($numeros, '57'.$numero);
            if($empresa->sms_factura_generada){
                $facturaDetalle = Factura::where('cliente', $contacto->idContacto)->whereIn('tipo', [1,2])->where('vencimiento', $fecha)->get();
                foreach($facturaDetalle as $fd){

                    $nombreCliente = trim($fd->cliente()->nombre.' '.$fd->cliente()->apellidos());
                    $nombreEmpresa = $empresa->nombre;
                    $codigoFactura = $fd->codigo ?? $fd->nro;
                    $valorFactura =  $fd->totalAPI($empresa->id)->total;
                    $fechaVencimiento = date('d-m-Y', strtotime($fd->vencimiento));

                    $bulksms = $empresa->sms_factura_generada;
                    $bulksms = str_replace("{cliente}", $nombreCliente, $bulksms);
                    $bulksms = str_replace("{empresa}", $nombreEmpresa, $bulksms);
                    $bulksms = str_replace("{factura}", $codigoFactura, $bulksms);
                    $bulksms = str_replace("{valor}", $valorFactura, $bulksms);
                    $bulksms = str_replace("{vencimiento}", $fechaVencimiento, $bulksms);

                    $bulk .= '{"numero": "57'.$numero.'", "sms": "'.$bulksms.'"},';
                }
            }else if($empresa->nombre == 'FIBRACONEXION S.A.S.' || $empresa->nit == '900822955' || $empresa->nombre == 'Almeidas Comunicaciones S.A.S' ||  $empresa->nit == '901044772'){
                $facturaDetalle = Factura::where('cliente', $contacto->idContacto)->whereIn('tipo', [1,2])->where('vencimiento', $fecha)->get();
                foreach($facturaDetalle as $fd){
                    $fullname = $fd->cliente()->nombre.' '.$fd->cliente()->apellidos();
                    $bulk .= '{"numero": "57'.$numero.'", "sms": "'.trim($fullname).'. '.$empresa->nombre.' le informa que su factura de servicio de internet. Tiene como fecha de vencimiento: '.date('d-m-Y', strtotime($fd->vencimiento)).' Total a pagar '.$fd->totalAPI($empresa->id)->total.'"},';
                }
            }else{
                    $bulk .= '{"numero": "57'.$numero.'", "sms": "Estimado cliente, se le informa que su factura de internet ha sido generada. '.$empresa->slogan.'"},';
            }
        }

        $servicio = Integracion::where('empresa', 1)->where('tipo', 'SMS')->where('status', 1)->first();
        if($servicio){
            $mensaje = "Estimado cliente su servicio ha sido suspendido por falta de pago, por favor realice su pago para continuar disfrutando de su servicio. ".$empresa->slogan;
            if($servicio->nombre == 'Hablame SMS'){
                if($servicio->api_key && $servicio->user && $servicio->pass){
                    $curl = curl_init();
                    curl_setopt_array($curl, [
                        CURLOPT_URL => "https://api103.hablame.co/api/sms/v3/send/marketing/bulk",
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 30,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "POST",
                        CURLOPT_POSTFIELDS => "{\n  \"bulk\": [\n    ".substr($bulk, 0, -1)."\n  ]\n}",
                        CURLOPT_HTTPHEADER => [
                            'Content-Type: application/json',
                            'account: '.$servicio->user,
                            'apiKey: '.$servicio->api_key,
                            'token: '.$servicio->pass,
                            ],
                    ]);

                    $response = curl_exec($curl);
                    $err = curl_error($curl);
                    curl_close($curl);
                }
            }elseif($servicio->nombre == 'SmsEasySms'){
                if($servicio->user && $servicio->pass){
                    $post['to'] = $numeros;
                    $post['text'] = $mensaje;
                    $post['from'] = "SMS";
                    $login = $servicio->user;
                    $password = $servicio->pass;

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, "https://sms.istsas.com/Api/rest/message");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
                    curl_setopt($ch, CURLOPT_HTTPHEADER,
                        array(
                            "Accept: application/json",
                            "Authorization: Basic ".base64_encode($login.":".$password)));
                    $result = curl_exec ($ch);
                    $err  = curl_error($ch);
                    curl_close($ch);
                }
            }else{
                if($servicio->user && $servicio->pass){
                    $post['to'] = $numeros;
                    $post['text'] = $mensaje;
                    $post['from'] = "";
                    $login = $servicio->user;
                    $password = $servicio->pass;

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, "https://masivos.colombiared.com.co/Api/rest/message");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
                    curl_setopt($ch, CURLOPT_HTTPHEADER,
                        array(
                            "Accept: application/json",
                            "Authorization: Basic ".base64_encode($login.":".$password)));
                    $result = curl_exec ($ch);
                    $err  = curl_error($ch);
                    curl_close($ch);
                }
            }
        }

        if (file_exists("PagoVencimiento.txt")){
            $file = fopen("PagoVencimiento.txt", "a");
            fputs($file, "-----------------".PHP_EOL);
            fputs($file, "Fecha de Notificación: ".date('d-m-Y').''. PHP_EOL);
            fputs($file, "SMS Enviados: ".$succ.''. PHP_EOL);
            fputs($file, "SMS NO Enviados: ".$fail.''. PHP_EOL);
            fputs($file, "-----------------".PHP_EOL);
            fclose($file);
        }else{
            $file = fopen("PagoVencimiento.txt", "w");
            fputs($file, "-----------------".PHP_EOL);
            fputs($file, "Fecha de Notificación: ".date('d-m-Y').''. PHP_EOL);
            fputs($file, "SMS Enviados: ".$succ.''. PHP_EOL);
            fputs($file, "SMS NO Enviados: ".$fail.''. PHP_EOL);
            fputs($file, "-----------------".PHP_EOL);
            fclose($file);
        }
    }


    public function eventosWompi(Request $request){
        $empresa = Empresa::find(1);
        $request = (object) $request->all();
        if($request->event == 'transaction.updated'){
            $request = (object) $request->data['transaction'];
            $servicio = Integracion::where('nombre', 'WOMPI')->where('tipo', 'PASARELA')->where('lectura', 1)->first();

            if($request->status == 'APPROVED'){
                $factura = Factura::where('codigo', explode("-", $request->reference)[1])->first();
                if($factura->estatus == 1){
                    $empresa = Empresa::find($factura->empresa);
                    $nro = Numeracion::where('empresa', $empresa->id)->first();
                    $caja = $nro->caja;

                    while (true) {
                        $numero = Ingreso::where('empresa', $empresa->id)->where('nro', $caja)->count();
                        if ($numero == 0) {
                            break;
                        }
                        $caja++;
                    }

                    $banco = Banco::where('nombre', 'WOMPI')->where('estatus', 1)->where('lectura', 1)->first();

                    # REGISTRAMOS EL INGRESO
                    $ingreso                = new Ingreso;
                    $ingreso->nro           = $caja;
                    $ingreso->empresa       = $empresa->id;
                    $ingreso->cliente       = $factura->cliente;
                    $ingreso->cuenta        = $banco->id;
                    $ingreso->metodo_pago   = 9;
                    $ingreso->tipo          = 1;
                    $ingreso->fecha         = date('Y-m-d');
                    $ingreso->observaciones = 'Pago Wompi ID: '.$request->id;
                    $ingreso->save();

                    # REGISTRAMOS EL INGRESO_FACTURA
                    // Precio que pagó el cliente (incluye cobro_extra si existe)
                    $precioPagado = $this->precisionAPI($request->amount_in_cents/100, $empresa->id);

                    // Precio real de la factura (sin cobro_extra)
                    $precioReal = $this->precisionAPI($factura->porpagarAPI($empresa->id), $empresa->id);

                    $items          = new IngresosFactura;
                    $items->ingreso = $ingreso->id;
                    $items->factura = $factura->id;
                    $items->pagado  = $factura->pagado();
                    $items->pago    = $precioReal;

                    if ($precioReal >= $this->precisionAPI($factura->porpagarAPI($empresa->id), $empresa->id)) {
                        $factura->estatus = 0;
                        $factura->save();

                        CRM::where('cliente', $factura->cliente)->whereIn('estado', [0,2,3,6])->delete();

                        $crms = CRM::where('cliente', $factura->cliente)->whereIn('estado', [0,2,3,6])->get();
                        foreach ($crms as $crm) {
                            $crm->delete();
                        }
                    }

                    $items->save();

                    # AUMENTAMOS LA NUMERACIÓN DE PAGOS
                    $nro->caja = $caja + 1;
                    $nro->save();

                    # REGISTRAMOS EL MOVIMIENTO
                    $ingreso = Ingreso::find($ingreso->id);

                    $this->up_transaccion_(1, $ingreso->id, $ingreso->cuenta, $ingreso->cliente, 1, $ingreso->pago(), $ingreso->fecha, $ingreso->descripcion,null, $empresa->id);

                    if($factura->estatus == 0){
                        # EJECUTAMOS COMANDOS EN MIKROTIK
                        $cliente = Contacto::where('id', $factura->cliente)->first();
                        $contrato = Contrato::where('client_id', $cliente->id)->first();
                        $res = DB::table('contracts')->where('client_id', $cliente->id)->update(["state" => 'enabled']);

                        $asignacion = Producto::where('contrato', $contrato->id)->where('venta', 1)->where('status', 2)->where('cuotas_pendientes', '>', 0)->get()->last();

                        if ($asignacion) {
                            $cuotas_pendientes = $asignacion->cuotas_pendientes -= 1;
                            $asignacion->cuotas_pendientes = $cuotas_pendientes;
                            if ($cuotas_pendientes == 0) {
                                $asignacion->status = 1;
                            }
                            $asignacion->save();
                        }

                        # API MK
                        if($contrato->server_configuration_id){
                            $mikrotik = Mikrotik::where('id', $contrato->server_configuration_id)->first();

                            $API = new RouterosAPI();
                            $API->port = $mikrotik->puerto_api;

                            if ($API->connect($mikrotik->ip,$mikrotik->usuario,$mikrotik->clave)) {
                                $API->write('/ip/firewall/address-list/print', TRUE);
                                $ARRAYS = $API->read();

                                #ELIMINAMOS DE MOROSOS#
                                $API->write('/ip/firewall/address-list/print', false);
                                $API->write('?address='.$contrato->ip, false);
                                $API->write("?list=morosos",false);
                                $API->write('=.proplist=.id');
                                $ARRAYS = $API->read();

                                if(count($ARRAYS)>0){

                                    $API->write('/ip/firewall/address-list/remove', false);
                                    $API->write('=.id='.$ARRAYS[0]['.id']);
                                    $READ = $API->read();

                                    #AGREGAMOS A IP_AUTORIZADAS#
                                    $API->comm("/ip/firewall/address-list/add", array(
                                        "address" => $contrato->ip,
                                        "list" => 'ips_autorizadas'
                                        )
                                    );
                                    #AGREGAMOS A IP_AUTORIZADAS#

                                    $ingreso->revalidacion_enable_internet = 1;
                                    $ingreso->save();

                                    $contrato->state = 'enabled';
                                    $contrato->save();
                                }
                                #ELIMINAMOS DE MOROSOS#
                                $API->disconnect();

                            }
                        }else{
                            $ingreso->revalidacion_enable_internet = 1;
                            $ingreso->save();
                        }

                        # ENVÍO SMS
                        $servicio = Integracion::where('empresa', $empresa->id)->where('tipo', 'SMS')->where('status', 1)->first();
                        if($servicio){
                            $numero = str_replace('+','',$cliente->celular);
                            $numero = str_replace(' ','',$numero);

                            if($empresa->sms_pago && isset($factura)){
                                $nombreCliente = $factura->cliente()->nombre.' '.$factura->cliente()->apellidos();
                                $nombreEmpresa = $empresa->nombre;
                                $codigoFactura = $factura->codigo ?? $factura->nro;
                                $valorFactura =  $factura->totalAPI($empresa->id)->total;
                                $fechaVencimiento = date('d-m-Y', strtotime($factura->vencimiento));
                                $pagoRecibido = Funcion::ParsearAPI($precioPagado, $empresa->id);

                                $bulksms = $empresa->sms_pago;
                                $bulksms = str_replace("{cliente}", $nombreCliente, $bulksms);
                                $bulksms = str_replace("{empresa}", $nombreEmpresa, $bulksms);
                                $bulksms = str_replace("{factura}", $codigoFactura, $bulksms);
                                $bulksms = str_replace("{valor}", $valorFactura, $bulksms);
                                $bulksms = str_replace("{pagado}", $pagoRecibido, $bulksms);
                                $bulksms = str_replace("{vencimiento}", $fechaVencimiento, $bulksms);

                                $mensaje =  $bulksms;
                            }else{
                                $mensaje = "Estimado Cliente, le informamos que hemos recibido el pago de su factura por valor de ".Funcion::ParsearAPI($precioPagado, $empresa->id)." gracias por preferirnos. ".$empresa->slogan;
                            }

                            if($servicio->nombre == 'Hablame SMS'){
                                if($servicio->api_key && $servicio->user && $servicio->pass){
                                    $post['numero'] = $numero;
                                    $post['sms'] = $mensaje;

                                    $curl = curl_init();
                                    curl_setopt_array($curl, array(
                                        CURLOPT_URL => 'https://api103.hablame.co/api/sms/v3/send/marketing/bulk',
                                        CURLOPT_RETURNTRANSFER => true,
                                        CURLOPT_ENCODING => '',
                                        CURLOPT_MAXREDIRS => 10,
                                        CURLOPT_TIMEOUT => 0,
                                        CURLOPT_FOLLOWLOCATION => true,
                                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                        CURLOPT_CUSTOMREQUEST => 'POST',CURLOPT_POSTFIELDS => json_encode($post),
                                        CURLOPT_HTTPHEADER => array(
                                            'account: '.$servicio->user,
                                            'apiKey: '.$servicio->api_key,
                                            'token: '.$servicio->pass,
                                            'Content-Type: application/json'
                                        ),
                                    ));
                                    $result = curl_exec ($curl);
                                    $err  = curl_error($curl);
                                    curl_close($curl);
                                }
                            }elseif($servicio->nombre == 'SmsEasySms'){
                                if($servicio->user && $servicio->pass){
                                    $post['to'] = array('57'.$numero);
                                    $post['text'] = $mensaje;
                                    $post['from'] = "SMS";
                                    $login = $servicio->user;
                                    $password = $servicio->pass;

                                    $ch = curl_init();
                                    curl_setopt($ch, CURLOPT_URL, "https://sms.istsas.com/Api/rest/message");
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                    curl_setopt($ch, CURLOPT_POST, 1);
                                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
                                    curl_setopt($ch, CURLOPT_HTTPHEADER,
                                        array(
                                            "Accept: application/json",
                                            "Authorization: Basic ".base64_encode($login.":".$password)));
                                    $result = curl_exec ($ch);
                                    $err  = curl_error($ch);
                                    curl_close($ch);
                                }
                            }else{
                                if($servicio->user && $servicio->pass){
                                    $post['to'] = array('57'.$numero);
                                    $post['text'] = $mensaje;
                                    $post['from'] = "";
                                    $login = $servicio->user;
                                    $password = $servicio->pass;

                                    $ch = curl_init();
                                    curl_setopt($ch, CURLOPT_URL, "https://masivos.colombiared.com.co/Api/rest/message");
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                    curl_setopt($ch, CURLOPT_POST, 1);
                                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
                                    curl_setopt($ch, CURLOPT_HTTPHEADER,
                                        array(
                                            "Accept: application/json",
                                            "Authorization: Basic ".base64_encode($login.":".$password)));
                                    $result = curl_exec ($ch);
                                    $err  = curl_error($ch);
                                    curl_close($ch);
                                }
                            }
                        }
                    }
                    return response('success', 200);
                }
                return response('false', 200);
            }else{
                return response('false', 200);
            }
        }
    }

    public function eventosOnePay(Request $request){
        $requestData = $request->all();

        // Verificar que el evento sea payment.approved o invoice.paid
        if(isset($requestData['event']['type']) && in_array($requestData['event']['type'], ['payment.approved', 'invoice.paid'])){
            
            $factura = null;
            $paymentId = null;
            $montoPagado = 0;

            if ($requestData['event']['type'] == 'invoice.paid') {
                $invoice = $requestData['invoice'] ?? [];
                $payment = $invoice['payment'] ?? [];
                
                if(isset($invoice['provider_id'])){
                    $factura = Factura::where('codigo', $invoice['provider_id'])->first();
                }
                
                if(!$factura && isset($invoice['metadata']['factura_id'])){
                    $factura = Factura::find($invoice['metadata']['factura_id']);
                }

                if(!$factura && isset($invoice['payment_id'])){
                    $factura = Factura::where('onepay_invoice_id', $invoice['payment_id'])->first();
                }
                
                $paymentId = $payment['id'] ?? ($invoice['payment_id'] ?? null);
                // En invoice.paid el monto viene en valor normal
                $montoPagado = $payment['amount'] ?? 0;
            } else {
                $payment = $requestData['payment'] ?? [];

                if(isset($payment['provider_id'])){
                    $factura = Factura::where('codigo', $payment['provider_id'])->first();
                }
                
                if(!$factura && isset($payment['id'])){
                    $factura = Factura::where('onepay_invoice_id', $payment['id'])->first();
                }

                $paymentId = $payment['id'] ?? null;
                // En payment.approved (o implementacion previa) se dividía por 100
                $montoPagado = isset($payment['amount']) ? ($payment['amount'] / 100) : 0;
            }

            if($factura && $factura->estatus == 1){
                $empresa = Empresa::find($factura->empresa);
                $nro = Numeracion::where('empresa', $empresa->id)->first();
                $caja = $nro->caja;

                while (true) {
                    $numero = Ingreso::where('empresa', $empresa->id)->where('nro', $caja)->count();
                    if ($numero == 0) {
                        break;
                    }
                    $caja++;
                }

                $banco = Banco::where('nombre', 'ONEPAY')->where('estatus', 1)->where('lectura', 1)->first();

                // Si no existe el banco ONEPAY, crearlo o usar uno genérico
                if(!$banco){
                    // Buscar cualquier banco activo como fallback
                    $banco = Banco::where('empresa', $empresa->id)->where('estatus', 1)->first();
                }

                # REGISTRAMOS EL INGRESO
                $ingreso                = new Ingreso;
                $ingreso->nro           = $caja;
                $ingreso->empresa       = $empresa->id;
                $ingreso->cliente       = $factura->cliente;
                $ingreso->cuenta        = $banco ? $banco->id : 1;
                $ingreso->metodo_pago   = 9;
                $ingreso->tipo          = 1;
                $ingreso->fecha         = date('Y-m-d');
                $ingreso->observaciones = 'Pago OnePay ID: '.$paymentId;
                $ingreso->save();

                # REGISTRAMOS EL INGRESO_FACTURA
                // Precio que pagó el cliente
                $precioPagado = $this->precisionAPI($montoPagado, $empresa->id);

                // Precio real de la factura (sin cobro_extra)
                $precioReal = $this->precisionAPI($factura->porpagarAPI($empresa->id), $empresa->id);

                $items          = new IngresosFactura;
                $items->ingreso = $ingreso->id;
                $items->factura = $factura->id;
                $items->pagado  = $factura->pagado();
                $items->pago    = $precioReal;

                if ($precioReal >= $this->precisionAPI($factura->porpagarAPI($empresa->id), $empresa->id)) {
                    $factura->estatus = 0;
                    $factura->save();

                    CRM::where('cliente', $factura->cliente)->whereIn('estado', [0,2,3,6])->delete();

                    $crms = CRM::where('cliente', $factura->cliente)->whereIn('estado', [0,2,3,6])->get();
                    foreach ($crms as $crm) {
                        $crm->delete();
                    }
                }

                $items->save();

                # AUMENTAMOS LA NUMERACIÓN DE PAGOS
                $nro->caja = $caja + 1;
                $nro->save();

                # REGISTRAMOS EL MOVIMIENTO
                $ingreso = Ingreso::find($ingreso->id);

                $this->up_transaccion_(1, $ingreso->id, $ingreso->cuenta, $ingreso->cliente, 1, $ingreso->pago(), $ingreso->fecha, $ingreso->descripcion,null, $empresa->id);

                # REGISTRAR LOG DE PAGO CON ONEPAY
                $movimiento = new MovimientoLOG();
                $movimiento->contrato = $factura->id;
                $movimiento->modulo = 8; // Módulo de facturas
                $movimiento->descripcion = '<i class="fas fa-check text-success"></i> <b>Pago recibido</b> mediante OnePay por valor de '.Funcion::ParsearAPI($precioPagado, $empresa->id).' - ID: '.$paymentId;
                $movimiento->created_by = null; // Sistema
                $movimiento->empresa = $empresa->id;
                $movimiento->save();

                if($factura->estatus == 0){
                    # EJECUTAMOS COMANDOS EN MIKROTIK
                    $cliente = Contacto::where('id', $factura->cliente)->first();
                    $contrato = Contrato::where('client_id', $cliente->id)->first();

                    if($contrato){
                        $res = DB::table('contracts')->where('client_id', $cliente->id)->update(["state" => 'enabled']);

                        $asignacion = Producto::where('contrato', $contrato->id)->where('venta', 1)->where('status', 2)->where('cuotas_pendientes', '>', 0)->get()->last();

                        if ($asignacion) {
                            $cuotas_pendientes = $asignacion->cuotas_pendientes -= 1;
                            $asignacion->cuotas_pendientes = $cuotas_pendientes;
                            if ($cuotas_pendientes == 0) {
                                $asignacion->status = 1;
                            }
                            $asignacion->save();
                        }

                        # API MK
                        if($contrato->server_configuration_id){
                            $mikrotik = Mikrotik::where('id', $contrato->server_configuration_id)->first();

                            $API = new RouterosAPI();
                            $API->port = $mikrotik->puerto_api;

                            if ($API->connect($mikrotik->ip,$mikrotik->usuario,$mikrotik->clave)) {
                                $API->write('/ip/firewall/address-list/print', TRUE);
                                $ARRAYS = $API->read();

                                #ELIMINAMOS DE MOROSOS#
                                $API->write('/ip/firewall/address-list/print', false);
                                $API->write('?address='.$contrato->ip, false);
                                $API->write("?list=morosos",false);
                                $API->write('=.proplist=.id');
                                $ARRAYS = $API->read();

                                if(count($ARRAYS)>0){

                                    $API->write('/ip/firewall/address-list/remove', false);
                                    $API->write('=.id='.$ARRAYS[0]['.id']);
                                    $READ = $API->read();

                                    #AGREGAMOS A IP_AUTORIZADAS#
                                    $API->comm("/ip/firewall/address-list/add", array(
                                        "address" => $contrato->ip,
                                        "list" => 'ips_autorizadas'
                                        )
                                    );
                                    #AGREGAMOS A IP_AUTORIZADAS#

                                    $ingreso->revalidacion_enable_internet = 1;
                                    $ingreso->save();

                                    $contrato->state = 'enabled';
                                    $contrato->save();
                                }
                                #ELIMINAMOS DE MOROSOS#
                                $API->disconnect();

                            }
                        }else{
                            $ingreso->revalidacion_enable_internet = 1;
                            $ingreso->save();
                        }
                    }

                    # ENVÍO SMS
                    $servicio = Integracion::where('empresa', $empresa->id)->where('tipo', 'SMS')->where('status', 1)->first();
                    if($servicio){
                        $numero = str_replace('+','',$cliente->celular);
                        $numero = str_replace(' ','',$numero);

                        if($empresa->sms_pago && isset($factura)){
                            $nombreCliente = $factura->cliente()->nombre.' '.$factura->cliente()->apellidos();
                            $nombreEmpresa = $empresa->nombre;
                            $codigoFactura = $factura->codigo ?? $factura->nro;
                            $valorFactura =  $factura->totalAPI($empresa->id)->total;
                            $fechaVencimiento = date('d-m-Y', strtotime($factura->vencimiento));
                            $pagoRecibido = Funcion::ParsearAPI($precioPagado, $empresa->id);

                            $bulksms = $empresa->sms_pago;
                            $bulksms = str_replace("{cliente}", $nombreCliente, $bulksms);
                            $bulksms = str_replace("{empresa}", $nombreEmpresa, $bulksms);
                            $bulksms = str_replace("{factura}", $codigoFactura, $bulksms);
                            $bulksms = str_replace("{valor}", $valorFactura, $bulksms);
                            $bulksms = str_replace("{pagado}", $pagoRecibido, $bulksms);
                            $bulksms = str_replace("{vencimiento}", $fechaVencimiento, $bulksms);

                            $mensaje =  $bulksms;
                        }else{
                            $mensaje = "Estimado Cliente, le informamos que hemos recibido el pago de su factura por valor de ".Funcion::ParsearAPI($precioPagado, $empresa->id)." gracias por preferirnos. ".$empresa->slogan;
                        }

                        if($servicio->nombre == 'Hablame SMS'){
                            if($servicio->api_key && $servicio->user && $servicio->pass){
                                $post['numero'] = $numero;
                                $post['sms'] = $mensaje;

                                $curl = curl_init();
                                curl_setopt_array($curl, array(
                                    CURLOPT_URL => 'https://api103.hablame.co/api/sms/v3/send/marketing/bulk',
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_ENCODING => '',
                                    CURLOPT_MAXREDIRS => 10,
                                    CURLOPT_TIMEOUT => 0,
                                    CURLOPT_FOLLOWLOCATION => true,
                                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                    CURLOPT_CUSTOMREQUEST => 'POST',CURLOPT_POSTFIELDS => json_encode($post),
                                    CURLOPT_HTTPHEADER => array(
                                        'account: '.$servicio->user,
                                        'apiKey: '.$servicio->api_key,
                                        'token: '.$servicio->pass,
                                        'Content-Type: application/json'
                                    ),
                                ));
                                $result = curl_exec ($curl);
                                $err  = curl_error($curl);
                                curl_close($curl);
                            }
                        }elseif($servicio->nombre == 'SmsEasySms'){
                            if($servicio->user && $servicio->pass){
                                $post['to'] = array('57'.$numero);
                                $post['text'] = $mensaje;
                                $post['from'] = "SMS";
                                $login = $servicio->user;
                                $password = $servicio->pass;

                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, "https://sms.istsas.com/Api/rest/message");
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                curl_setopt($ch, CURLOPT_POST, 1);
                                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
                                curl_setopt($ch, CURLOPT_HTTPHEADER,
                                    array(
                                        "Accept: application/json",
                                        "Authorization: Basic ".base64_encode($login.":".$password)));
                                $result = curl_exec ($ch);
                                $err  = curl_error($ch);
                                curl_close($ch);
                            }
                        }else{
                            if($servicio->user && $servicio->pass){
                                $post['to'] = array('57'.$numero);
                                $post['text'] = $mensaje;
                                $post['from'] = "";
                                $login = $servicio->user;
                                $password = $servicio->pass;

                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, "https://masivos.colombiared.com.co/Api/rest/message");
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                curl_setopt($ch, CURLOPT_POST, 1);
                                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
                                curl_setopt($ch, CURLOPT_HTTPHEADER,
                                    array(
                                        "Accept: application/json",
                                        "Authorization: Basic ".base64_encode($login.":".$password)));
                                $result = curl_exec ($ch);
                                $err  = curl_error($ch);
                                curl_close($ch);
                            }
                        }
                    }
                }
                return response('success', 200);
            }
            return response('false', 200);
        }
        return response('false', 200);
    }

    public function eventosPayu(Request $request){
        $empresa = Empresa::find(1);
        if($request->state_pol == 4){
            $timestamp = $request->timestamp;
            $payu = Integracion::where('nombre', 'PayU')->where('tipo', 'PASARELA')->where('lectura', 1)->first();

            $hash = md5($payu->api_key.'~'.$request->merchant_id.'~'.$request->reference_sale.'~'.$request->value.'~'.$request->currency.'~'.$request->state_pol);

            if($request->sign == $hash){
                $factura = Factura::where('codigo', substr($request->reference_sale, 4))->first();

                if($factura->estatus == 1){
                    $empresa = Empresa::find($factura->empresa);
                    $nro = Numeracion::where('empresa', $empresa->id)->first();
                    $caja = $nro->caja;

                    while (true) {
                        $numero = Ingreso::where('empresa', $empresa->id)->where('nro', $caja)->count();
                        if ($numero == 0) {
                            break;
                        }
                        $caja++;
                    }

                    $banco = Banco::where('nombre', 'PAYU')->where('estatus', 1)->where('lectura', 1)->first();

                    # REGISTRAMOS EL INGRESO
                    $ingreso                = new Ingreso;
                    $ingreso->nro           = $caja;
                    $ingreso->empresa       = $empresa->id;
                    $ingreso->cliente       = $factura->cliente;
                    $ingreso->cuenta        = $banco->id;
                    $ingreso->metodo_pago   = 9;
                    $ingreso->tipo          = 1;
                    $ingreso->fecha         = date('Y-m-d');
                    $ingreso->observaciones = 'Pago PayU ID: '.$request->transaction_id;
                    $ingreso->save();

                    # REGISTRAMOS EL INGRESO_FACTURA
                    $precio         = $this->precisionAPI($request->value, $empresa->id);

                    $items          = new IngresosFactura;
                    $items->ingreso = $ingreso->id;
                    $items->factura = $factura->id;
                    $items->pagado  = $factura->pagado();
                    $items->pago    = $precio;

                    if ($precio >= $this->precisionAPI($factura->porpagarAPI($empresa->id), $empresa->id)) {
                        $factura->estatus = 0;
                        $factura->save();

                        CRM::where('cliente', $factura->cliente)->whereIn('estado', [0,2,3,6])->delete();

                        $crms = CRM::where('cliente', $factura->cliente)->whereIn('estado', [0,2,3,6])->get();
                        foreach ($crms as $crm) {
                            $crm->delete();
                        }
                    }

                    $items->save();

                    # AUMENTAMOS LA NUMERACIÓN DE PAGOS
                    $nro->caja = $caja + 1;
                    $nro->save();

                    # REGISTRAMOS EL MOVIMIENTO
                    $ingreso = Ingreso::find($ingreso->id);

                    $this->up_transaccion_(1, $ingreso->id, $ingreso->cuenta, $ingreso->cliente, 1, $ingreso->pago(), $ingreso->fecha, $ingreso->descripcion,null, $empresa->id);

                    if($factura->estatus == 0){
                        # EJECUTAMOS COMANDOS EN MIKROTIK
                        $cliente = Contacto::where('id', $factura->cliente)->first();
                        $contrato = Contrato::where('client_id', $cliente->id)->first();
                        $res = DB::table('contracts')->where('client_id', $cliente->id)->update(["state" => 'enabled']);

                        $asignacion = Producto::where('contrato', $contrato->id)->where('venta', 1)->where('status', 2)->where('cuotas_pendientes', '>', 0)->get()->last();

                        if ($asignacion) {
                            $cuotas_pendientes = $asignacion->cuotas_pendientes -= 1;
                            $asignacion->cuotas_pendientes = $cuotas_pendientes;
                            if ($cuotas_pendientes == 0) {
                                $asignacion->status = 1;
                            }
                            $asignacion->save();
                        }

                        # API MK
                        if($contrato->server_configuration_id){
                            $mikrotik = Mikrotik::where('id', $contrato->server_configuration_id)->first();

                            $API = new RouterosAPI();
                            $API->port = $mikrotik->puerto_api;

                            if ($API->connect($mikrotik->ip,$mikrotik->usuario,$mikrotik->clave)) {
                                $API->write('/ip/firewall/address-list/print', TRUE);
                                $ARRAYS = $API->read();

                                #ELIMINAMOS DE MOROSOS#
                                $API->write('/ip/firewall/address-list/print', false);
                                $API->write('?address='.$contrato->ip, false);
                                $API->write("?list=morosos",false);
                                $API->write('=.proplist=.id');
                                $ARRAYS = $API->read();

                                if(count($ARRAYS)>0){
                                    $API->write('/ip/firewall/address-list/remove', false);
                                    $API->write('=.id='.$ARRAYS[0]['.id']);
                                    $READ = $API->read();
                                }
                                #ELIMINAMOS DE MOROSOS#

                                #AGREGAMOS A IP_AUTORIZADAS#
                                $API->comm("/ip/firewall/address-list/add", array(
                                    "address" => $contrato->ip,
                                    "list" => 'ips_autorizadas'
                                    )
                                );
                                #AGREGAMOS A IP_AUTORIZADAS#

                                $API->disconnect();

                                $contrato->state = 'enabled';
                                $contrato->save();
                            }
                        }

                        # ENVÍO SMS
                        $servicio = Integracion::where('empresa', $empresa->id)->where('tipo', 'SMS')->where('status', 1)->first();
                        if($servicio){
                            $numero = str_replace('+','',$cliente->celular);
                            $numero = str_replace(' ','',$numero);

                            if($empresa->sms_pago && isset($factura)){
                                $nombreCliente = $factura->cliente()->nombre.' '.$factura->cliente()->apellidos();
                                $nombreEmpresa = $empresa->nombre;
                                $codigoFactura = $factura->codigo ?? $factura->nro;
                                $valorFactura =  $factura->totalAPI($empresa->id)->total;
                                $fechaVencimiento = date('d-m-Y', strtotime($factura->vencimiento));
                                $pagoRecibido = Funcion::ParsearAPI($precio, $empresa->id);

                                $bulksms = $empresa->sms_pago;
                                $bulksms = str_replace("{cliente}", $nombreCliente, $bulksms);
                                $bulksms = str_replace("{empresa}", $nombreEmpresa, $bulksms);
                                $bulksms = str_replace("{factura}", $codigoFactura, $bulksms);
                                $bulksms = str_replace("{valor}", $valorFactura, $bulksms);
                                $bulksms = str_replace("{pagado}", $pagoRecibido, $bulksms);
                                $bulksms = str_replace("{vencimiento}", $fechaVencimiento, $bulksms);

                                $mensaje =  $bulksms;
                            }else{
                                 $mensaje = "Estimado Cliente, le informamos que hemos recibido el pago de su factura por valor de ".Funcion::ParsearAPI($precio, $empresa->id)." gracias por preferirnos. ".$empresa->slogan;
                            }
                            if($servicio->nombre == 'Hablame SMS'){
                                if($servicio->api_key && $servicio->user && $servicio->pass){
                                    $post['numero'] = $numero;
                                    $post['sms'] = $mensaje;

                                    $curl = curl_init();
                                    curl_setopt_array($curl, array(
                                        CURLOPT_URL => 'https://api103.hablame.co/api/sms/v3/send/marketing/bulk',
                                        CURLOPT_RETURNTRANSFER => true,
                                        CURLOPT_ENCODING => '',
                                        CURLOPT_MAXREDIRS => 10,
                                        CURLOPT_TIMEOUT => 0,
                                        CURLOPT_FOLLOWLOCATION => true,
                                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                        CURLOPT_CUSTOMREQUEST => 'POST',CURLOPT_POSTFIELDS => json_encode($post),
                                        CURLOPT_HTTPHEADER => array(
                                            'account: '.$servicio->user,
                                            'apiKey: '.$servicio->api_key,
                                            'token: '.$servicio->pass,
                                            'Content-Type: application/json'
                                        ),
                                    ));
                                    $result = curl_exec ($curl);
                                    $err  = curl_error($curl);
                                    curl_close($curl);
                                }
                            }elseif($servicio->nombre == 'SmsEasySms'){
                                if($servicio->user && $servicio->pass){
                                    $post['to'] = array('57'.$numero);
                                    $post['text'] = $mensaje;
                                    $post['from'] = "SMS";
                                    $login = $servicio->user;
                                    $password = $servicio->pass;

                                    $ch = curl_init();
                                    curl_setopt($ch, CURLOPT_URL, "https://sms.istsas.com/Api/rest/message");
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                    curl_setopt($ch, CURLOPT_POST, 1);
                                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
                                    curl_setopt($ch, CURLOPT_HTTPHEADER,
                                        array(
                                            "Accept: application/json",
                                            "Authorization: Basic ".base64_encode($login.":".$password)));
                                    $result = curl_exec ($ch);
                                    $err  = curl_error($ch);
                                    curl_close($ch);
                                }
                            }else{
                                if($servicio->user && $servicio->pass){
                                    $post['to'] = array('57'.$numero);
                                    $post['text'] = $mensaje;
                                    $post['from'] = "";
                                    $login = $servicio->user;
                                    $password = $servicio->pass;

                                    $ch = curl_init();
                                    curl_setopt($ch, CURLOPT_URL, "https://masivos.colombiared.com.co/Api/rest/message");
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                    curl_setopt($ch, CURLOPT_POST, 1);
                                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
                                    curl_setopt($ch, CURLOPT_HTTPHEADER,
                                        array(
                                            "Accept: application/json",
                                            "Authorization: Basic ".base64_encode($login.":".$password)));
                                    $result = curl_exec ($ch);
                                    $err  = curl_error($ch);
                                    curl_close($ch);
                                }
                            }
                        }
                    }
                    return abort(200);
                }
                return abort(400);
            }else{
                return abort(400);
            }
        }
        return abort(400);
    }

    public function eventosEpayco(Request $request){

        $empresa = Empresa::find(1);
        $request = (object) $request->all();
        if($request->x_respuesta == 'Aceptada'){

            $servicio = Integracion::where('nombre', 'ePayco')->where('tipo', 'PASARELA')->where('lectura', 1)->first();

            if($request->x_respuesta == 'Aceptada'){

                if(isset(explode("-", $request->x_description)[1])){
                    $factura = Factura::where('codigo', explode("-", $request->x_description)[1])->first();
                }else{
                    $factura = Factura::where('codigo', $request->x_description)->first();
                }

                if($factura->estatus == 1){
                    $empresa = Empresa::find($factura->empresa);
                    $nro = Numeracion::where('empresa', $empresa->id)->first();
                    $caja = $nro->caja;

                    while (true) {
                        $numero = Ingreso::where('empresa', $empresa->id)->where('nro', $caja)->count();
                        if ($numero == 0) {
                            break;
                        }
                        $caja++;
                    }

                    $banco = Banco::where('nombre', 'EPAYCO')->where('estatus', 1)->where('lectura', 1)->first();

                    # REGISTRAMOS EL INGRESO
                    $ingreso                = new Ingreso;
                    $ingreso->nro           = $caja;
                    $ingreso->empresa       = $empresa->id;
                    $ingreso->cliente       = $factura->cliente;
                    $ingreso->cuenta        = $banco->id;
                    $ingreso->metodo_pago   = 9;
                    $ingreso->tipo          = 1;
                    $ingreso->fecha         = date('Y-m-d');
                    $ingreso->observaciones = 'Pago Epayco ID: '.$request->x_ref_payco;
                    $ingreso->save();

                    # REGISTRAMOS EL INGRESO_FACTURA
                    $precio         = $request->x_amount;

                    $items          = new IngresosFactura;
                    $items->ingreso = $ingreso->id;
                    $items->factura = $factura->id;
                    $items->pagado  = $request->x_amount;
                    $items->pago    = $precio;

                    if ($precio >= $this->precisionAPI($factura->porpagarAPI($empresa->id), $empresa->id)) {
                        $factura->estatus = 0;
                        $factura->save();

                        CRM::where('cliente', $factura->cliente)->whereIn('estado', [0,2,3,6])->delete();

                        $crms = CRM::where('cliente', $factura->cliente)->whereIn('estado', [0,2,3,6])->get();
                        foreach ($crms as $crm) {
                            $crm->delete();
                        }
                    }

                    $items->save();

                    # AUMENTAMOS LA NUMERACIÓN DE PAGOS
                    $nro->caja = $caja + 1;
                    $nro->save();

                    # REGISTRAMOS EL MOVIMIENTO
                    $ingreso = Ingreso::find($ingreso->id);

                    $this->up_transaccion_(1, $ingreso->id, $ingreso->cuenta, $ingreso->cliente, 1, $ingreso->pago(), $ingreso->fecha, $ingreso->descripcion,null, $empresa->id);

                    if($factura->estatus == 0){
                        # EJECUTAMOS COMANDOS EN MIKROTIK
                        $cliente = Contacto::where('id', $factura->cliente)->first();
                        $contrato = Contrato::where('client_id', $cliente->id)->first();
                        $res = DB::table('contracts')->where('client_id', $cliente->id)->update(["state" => 'enabled']);

                        $asignacion = Producto::where('contrato', $contrato->id)->where('venta', 1)->where('status', 2)->where('cuotas_pendientes', '>', 0)->get()->last();

                        if ($asignacion) {
                            $cuotas_pendientes = $asignacion->cuotas_pendientes -= 1;
                            $asignacion->cuotas_pendientes = $cuotas_pendientes;
                            if ($cuotas_pendientes == 0) {
                                $asignacion->status = 1;
                            }
                            $asignacion->save();
                        }

                        # API MK
                        if($contrato->server_configuration_id){
                            $mikrotik = Mikrotik::where('id', $contrato->server_configuration_id)->first();

                            $API = new RouterosAPI();
                            $API->port = $mikrotik->puerto_api;

                            if ($API->connect($mikrotik->ip,$mikrotik->usuario,$mikrotik->clave)) {
                                $API->write('/ip/firewall/address-list/print', TRUE);
                                $ARRAYS = $API->read();

                                #ELIMINAMOS DE MOROSOS#
                                $API->write('/ip/firewall/address-list/print', false);
                                $API->write('?address='.$contrato->ip, false);
                                $API->write("?list=morosos",false);
                                $API->write('=.proplist=.id');
                                $ARRAYS = $API->read();

                                if(count($ARRAYS)>0){
                                    $API->write('/ip/firewall/address-list/remove', false);
                                    $API->write('=.id='.$ARRAYS[0]['.id']);
                                    $READ = $API->read();
                                }
                                #ELIMINAMOS DE MOROSOS#

                                #AGREGAMOS A IP_AUTORIZADAS#
                                $API->comm("/ip/firewall/address-list/add", array(
                                    "address" => $contrato->ip,
                                    "list" => 'ips_autorizadas'
                                    )
                                );
                                #AGREGAMOS A IP_AUTORIZADAS#

                                $API->disconnect();

                                $contrato->state = 'enabled';
                                $contrato->save();
                            }
                        }

                        # ENVÍO SMS
                        $servicio = Integracion::where('empresa', $empresa->id)->where('tipo', 'SMS')->where('status', 1)->first();
                        if($servicio){
                            $numero = str_replace('+','',$cliente->celular);
                            $numero = str_replace(' ','',$numero);

                            if($empresa->sms_pago && isset($factura)){
                                $nombreCliente = $factura->cliente()->nombre.' '.$factura->cliente()->apellidos();
                                $nombreEmpresa = $empresa->nombre;
                                $codigoFactura = $factura->codigo ?? $factura->nro;
                                $valorFactura =  $factura->totalAPI($empresa->id)->total;
                                $fechaVencimiento = date('d-m-Y', strtotime($factura->vencimiento));
                                $pagoRecibido = Funcion::ParsearAPI($precio, $empresa->id);

                                $bulksms = $empresa->sms_pago;
                                $bulksms = str_replace("{cliente}", $nombreCliente, $bulksms);
                                $bulksms = str_replace("{empresa}", $nombreEmpresa, $bulksms);
                                $bulksms = str_replace("{factura}", $codigoFactura, $bulksms);
                                $bulksms = str_replace("{valor}", $valorFactura, $bulksms);
                                $bulksms = str_replace("{pagado}", $pagoRecibido, $bulksms);
                                $bulksms = str_replace("{vencimiento}", $fechaVencimiento, $bulksms);

                                $mensaje =  $bulksms;
                            }else{
                                $mensaje = "Estimado Cliente, le informamos que hemos recibido el pago de su factura por valor de ".Funcion::ParsearAPI($precio, $empresa->id)." gracias por preferirnos. ".$empresa->slogan;
                            }

                            if($servicio->nombre == 'Hablame SMS'){
                                if($servicio->api_key && $servicio->user && $servicio->pass){
                                    $post['numero'] = $numero;
                                    $post['sms'] = $mensaje;

                                    $curl = curl_init();
                                    curl_setopt_array($curl, array(
                                        CURLOPT_URL => 'https://api103.hablame.co/api/sms/v3/send/marketing/bulk',
                                        CURLOPT_RETURNTRANSFER => true,
                                        CURLOPT_ENCODING => '',
                                        CURLOPT_MAXREDIRS => 10,
                                        CURLOPT_TIMEOUT => 0,
                                        CURLOPT_FOLLOWLOCATION => true,
                                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                        CURLOPT_CUSTOMREQUEST => 'POST',CURLOPT_POSTFIELDS => json_encode($post),
                                        CURLOPT_HTTPHEADER => array(
                                            'account: '.$servicio->user,
                                            'apiKey: '.$servicio->api_key,
                                            'token: '.$servicio->pass,
                                            'Content-Type: application/json'
                                        ),
                                    ));
                                    $result = curl_exec ($curl);
                                    $err  = curl_error($curl);
                                    curl_close($curl);
                                }
                            }elseif($servicio->nombre == 'SmsEasySms'){
                                if($servicio->user && $servicio->pass){
                                    $post['to'] = array('57'.$numero);
                                    $post['text'] = $mensaje;
                                    $post['from'] = "SMS";
                                    $login = $servicio->user;
                                    $password = $servicio->pass;

                                    $ch = curl_init();
                                    curl_setopt($ch, CURLOPT_URL, "https://sms.istsas.com/Api/rest/message");
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                    curl_setopt($ch, CURLOPT_POST, 1);
                                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
                                    curl_setopt($ch, CURLOPT_HTTPHEADER,
                                        array(
                                            "Accept: application/json",
                                            "Authorization: Basic ".base64_encode($login.":".$password)));
                                    $result = curl_exec ($ch);
                                    $err  = curl_error($ch);
                                    curl_close($ch);
                                }
                            }else{
                                if($servicio->user && $servicio->pass){
                                    $post['to'] = array('57'.$numero);
                                    $post['text'] = $mensaje;
                                    $post['from'] = "";
                                    $login = $servicio->user;
                                    $password = $servicio->pass;

                                    $ch = curl_init();
                                    curl_setopt($ch, CURLOPT_URL, "https://masivos.colombiared.com.co/Api/rest/message");
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                    curl_setopt($ch, CURLOPT_POST, 1);
                                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
                                    curl_setopt($ch, CURLOPT_HTTPHEADER,
                                        array(
                                            "Accept: application/json",
                                            "Authorization: Basic ".base64_encode($login.":".$password)));
                                    $result = curl_exec ($ch);
                                    $err  = curl_error($ch);
                                    curl_close($ch);
                                }
                            }
                        }
                    }
                    return response('success', 200);
                }
                return response('false', 200);
            }else{
                return response('false', 200);
            }
        }
    }

    public function eventosCombopay(Request $request){

        $empresa = Empresa::find(1);
        if($request->transaction_state == 'payment_approved'){

            $factura = Factura::where('codigo', substr($request->invoice_number, $empresa->caracter_combo_pay))->first();

            if (!$factura) {
                $factura = Factura::where('codigo', substr($request->invoice_number, 4))->first();
            }
            if($factura->estatus == 1){

                $empresa = Empresa::find($factura->empresa);
                $nro = Numeracion::where('empresa', $empresa->id)->first();
                $caja = $nro->caja;

                while (true) {
                    $numero = Ingreso::where('empresa', $empresa->id)->where('nro', $caja)->count();
                    if ($numero == 0) {
                        break;
                    }
                    $caja++;
                }

                $banco = Banco::where('nombre', 'COMBOPAY')->where('estatus', 1)->where('lectura', 1)->first();

                # REGISTRAMOS EL INGRESO
                $ingreso                = new Ingreso;
                $ingreso->nro           = $caja;
                $ingreso->empresa       = $empresa->id;
                $ingreso->cliente       = $factura->cliente;
                $ingreso->cuenta        = $banco->id;
                $ingreso->metodo_pago   = 9;
                $ingreso->tipo          = 1;
                $ingreso->fecha         = date('Y-m-d');
                $ingreso->observaciones = 'Pago ComboPay ID: '.$request->ticket_id;
                $ingreso->save();

                # REGISTRAMOS EL INGRESO_FACTURA
                $precio = ($this->precisionAPI($request->transaction_value, $empresa->id) > $factura->porpagarAPI($empresa->id)) ? $factura->porpagarAPI($empresa->id) : $this->precisionAPI($request->transaction_value, $empresa->id);
                //$precio         = $this->precisionAPI($request->transaction_value, $empresa->id);
                //$precio         = $this->precisionAPI($factura->totalAPI($empresa->id)->total, $empresa->id);

                $items          = new IngresosFactura;
                $items->ingreso = $ingreso->id;
                $items->factura = $factura->id;
                $items->pagado  = $factura->pagado();
                $items->pago    = $precio;

                if ($precio >= $this->precisionAPI($factura->porpagarAPI($empresa->id), $empresa->id)) {
                    $factura->estatus = 0;
                    $factura->save();

                    CRM::where('cliente', $factura->cliente)->whereIn('estado', [0,2,3,6])->delete();

                    $crms = CRM::where('cliente', $factura->cliente)->whereIn('estado', [0,2,3,6])->get();
                    foreach ($crms as $crm) {
                        $crm->delete();
                    }
                }

                $items->save();

                # AUMENTAMOS LA NUMERACIÓN DE PAGOS
                $nro->caja = $caja + 1;
                $nro->save();

                # REGISTRAMOS EL MOVIMIENTO
                $ingreso = Ingreso::find($ingreso->id);

                $this->up_transaccion_(1, $ingreso->id, $ingreso->cuenta, $ingreso->cliente, 1, $ingreso->pago(), $ingreso->fecha, $ingreso->descripcion, null, $empresa->id);

                if($factura->estatus == 0){
                    # EJECUTAMOS COMANDOS EN MIKROTIK
                    $cliente = Contacto::where('id', $factura->cliente)->first();
                    $contrato = Contrato::where('client_id', $cliente->id)->first();
                    $res = DB::table('contracts')->where('client_id', $cliente->id)->update(["state" => 'enabled']);

                    $asignacion = Producto::where('contrato', $contrato->id)->where('venta', 1)->where('status', 2)->where('cuotas_pendientes', '>', 0)->get()->last();

                    if ($asignacion) {
                        $cuotas_pendientes = $asignacion->cuotas_pendientes -= 1;
                        $asignacion->cuotas_pendientes = $cuotas_pendientes;
                        if ($cuotas_pendientes == 0) {
                            $asignacion->status = 1;
                        }
                        $asignacion->save();
                    }

                    # API MK
                    if($contrato){
                        if($contrato->server_configuration_id){
                            $mikrotik = Mikrotik::where('id', $contrato->server_configuration_id)->first();

                            $API = new RouterosAPI();
                            $API->port = $mikrotik->puerto_api;

                            if ($API->connect($mikrotik->ip,$mikrotik->usuario,$mikrotik->clave)) {
                                $API->write('/ip/firewall/address-list/print', TRUE);
                                $ARRAYS = $API->read();

                                #ELIMINAMOS DE MOROSOS#
                                $API->write('/ip/firewall/address-list/print', false);
                                $API->write('?address='.$contrato->ip, false);
                                $API->write("?list=morosos",false);
                                $API->write('=.proplist=.id');
                                $ARRAYS = $API->read();

                                if(count($ARRAYS)>0){
                                    $API->write('/ip/firewall/address-list/remove', false);
                                    $API->write('=.id='.$ARRAYS[0]['.id']);
                                    $READ = $API->read();
                                }
                                #ELIMINAMOS DE MOROSOS#

                                #AGREGAMOS A IP_AUTORIZADAS#
                                $API->comm("/ip/firewall/address-list/add", array(
                                    "address" => $contrato->ip,
                                    "list" => 'ips_autorizadas'
                                    )
                                );
                                #AGREGAMOS A IP_AUTORIZADAS#

                                $API->disconnect();

                                $contrato->state = 'enabled';
                                $contrato->save();
                            }
                        }
                    }

                    # ENVÍO SMS
                     $servicio = Integracion::where('empresa', $empresa->id)->where('tipo', 'SMS')->where('status', 1)->first();
                     if($servicio){
                         $numero = str_replace('+','',$cliente->celular);
                         $numero = str_replace(' ','',$numero);

                         if($empresa->sms_pago && isset($factura)){
                             $nombreCliente = $factura->cliente()->nombre.' '.$factura->cliente()->apellidos();
                             $nombreEmpresa = $empresa->nombre;
                             $codigoFactura = $factura->codigo ?? $factura->nro;
                             $valorFactura =  $factura->totalAPI($empresa->id)->total;
                             $fechaVencimiento = date('d-m-Y', strtotime($factura->vencimiento));
                             $pagoRecibido = Funcion::ParsearAPI($precio, $empresa->id);

                             $bulksms = $empresa->sms_pago;
                             $bulksms = str_replace("{cliente}", $nombreCliente, $bulksms);
                             $bulksms = str_replace("{empresa}", $nombreEmpresa, $bulksms);
                             $bulksms = str_replace("{factura}", $codigoFactura, $bulksms);
                             $bulksms = str_replace("{valor}", $valorFactura, $bulksms);
                             $bulksms = str_replace("{pagado}", $pagoRecibido, $bulksms);
                             $bulksms = str_replace("{vencimiento}", $fechaVencimiento, $bulksms);

                             $mensaje =  $bulksms;
                         }else{
                             $mensaje = "Estimado Cliente, le informamos que hemos recibido el pago de su factura por valor de ".Funcion::ParsearAPI($precio, $empresa->id)." gracias por preferirnos. ".$empresa->slogan;
                         }

                         if($servicio->nombre == 'Hablame SMS'){
                             if($servicio->api_key && $servicio->user && $servicio->pass){
                                 $post['numero'] = $numero;
                                 $post['sms'] = $mensaje;

                                $curl = curl_init();
                                curl_setopt_array($curl, array(
                                     CURLOPT_URL => 'https://api103.hablame.co/api/sms/v3/send/marketing/bulk',
                                     CURLOPT_RETURNTRANSFER => true,
                                     CURLOPT_ENCODING => '',
                                     CURLOPT_MAXREDIRS => 10,
                                     CURLOPT_TIMEOUT => 0,
                                     CURLOPT_FOLLOWLOCATION => true,
                                     CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                     CURLOPT_CUSTOMREQUEST => 'POST',CURLOPT_POSTFIELDS => json_encode($post),
                                     CURLOPT_HTTPHEADER => array(
                                         'account: '.$servicio->user,
                                         'apiKey: '.$servicio->api_key,
                                         'token: '.$servicio->pass,
                                         'Content-Type: application/json'
                                     ),
                                 ));
                                 $result = curl_exec ($curl);
                                 $err  = curl_error($curl);
                                 curl_close($curl);
                             }
                         }elseif($servicio->nombre == 'SmsEasySms'){
                             if($servicio->user && $servicio->pass){
                                 $post['to'] = array('57'.$numero);
                                 $post['text'] = $mensaje;
                                 $post['from'] = "SMS";
                                 $login = $servicio->user;
                                 $password = $servicio->pass;

                                 $ch = curl_init();
                                 curl_setopt($ch, CURLOPT_URL, "https://sms.istsas.com/Api/rest/message");
                                 curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                 curl_setopt($ch, CURLOPT_POST, 1);
                                 curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
                                 curl_setopt($ch, CURLOPT_HTTPHEADER,
                                     array(
                                         "Accept: application/json",
                                         "Authorization: Basic ".base64_encode($login.":".$password)));
                                 $result = curl_exec ($ch);
                                 $err  = curl_error($ch);
                                 curl_close($ch);
                             }
                         }else{
                             if($servicio->user && $servicio->pass){
                                 $post['to'] = array('57'.$numero);
                                 $post['text'] = $mensaje;
                                 $post['from'] = "";
                                 $login = $servicio->user;
                                 $password = $servicio->pass;

                                 $ch = curl_init();
                                 curl_setopt($ch, CURLOPT_URL, "https://masivos.colombiared.com.co/Api/rest/message");
                                 curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                 curl_setopt($ch, CURLOPT_POST, 1);
                                 curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
                                 curl_setopt($ch, CURLOPT_HTTPHEADER,
                                     array(
                                         "Accept: application/json",
                                         "Authorization: Basic ".base64_encode($login.":".$password)));
                                 $result = curl_exec ($ch);
                                 $err  = curl_error($ch);
                                 curl_close($ch);
                             }
                         }
                     }
                }
                return response('success', 200);
            }
            return response('Factura ya pagada', 200);
        }
        return response('Factura ya pagada', 200);
    }

    //metodo para recibir la respuesta de la api de toppay
    public function eventosTopPay(Request $request){
        $empresa = Empresa::find(1);
        if($request->status == 'success'){

            $reference = $request->reference;
            preg_match('/^\d+/', $reference, $matches);
            $codigoFactura = $matches[0];

            $factura = Factura::where('codigo','LIKE', '%' . $codigoFactura . '%')->first();

            if($factura->estatus == 1){
                $empresa = Empresa::find($factura->empresa);
                $nro = Numeracion::where('empresa', $empresa->id)->first();
                $caja = $nro->caja;

                while (true) {
                    $numero = Ingreso::where('empresa', $empresa->id)->where('nro', $caja)->count();
                    if ($numero == 0) {
                        break;
                    }
                    $caja++;
                }

                $banco = Banco::where('nombre', 'TOPPAY')->where('estatus', 1)->where('lectura', 1)->first();

                # REGISTRAMOS EL INGRESO
                $ingreso                = new Ingreso;
                $ingreso->nro           = $caja;
                $ingreso->empresa       = $empresa->id;
                $ingreso->cliente       = $factura->cliente;
                $ingreso->cuenta        = $banco->id;
                $ingreso->metodo_pago   = 9;
                $ingreso->tipo          = 1;
                $ingreso->fecha         = date('Y-m-d');
                $ingreso->observaciones = 'Pago topPay ID: '.$request->reference;
                $ingreso->save();

                # REGISTRAMOS EL INGRESO_FACTURA
                $precio = ($this->precisionAPI($request->amount, $empresa->id) > $factura->porpagarAPI($empresa->id)) ? $factura->porpagarAPI($empresa->id) : $this->precisionAPI($request->amount, $empresa->id);
                //$precio         = $this->precisionAPI($request->transaction_value, $empresa->id);
                //$precio         = $this->precisionAPI($factura->totalAPI($empresa->id)->total, $empresa->id);

                $items          = new IngresosFactura;
                $items->ingreso = $ingreso->id;
                $items->factura = $factura->id;
                $items->pagado  = $factura->pagado();
                $items->pago    = $precio;

                if ($precio >= $this->precisionAPI($factura->porpagarAPI($empresa->id), $empresa->id)) {
                    $factura->estatus = 0;
                    $factura->save();

                    CRM::where('cliente', $factura->cliente)->whereIn('estado', [0,2,3,6])->delete();

                    $crms = CRM::where('cliente', $factura->cliente)->whereIn('estado', [0,2,3,6])->get();
                    foreach ($crms as $crm) {
                        $crm->delete();
                    }
                }

                $items->save();

                # AUMENTAMOS LA NUMERACIÓN DE PAGOS
                $nro->caja = $caja + 1;
                $nro->save();

                # REGISTRAMOS EL MOVIMIENTO
                $ingreso = Ingreso::find($ingreso->id);

                $this->up_transaccion_(1, $ingreso->id, $ingreso->cuenta, $ingreso->cliente, 1, $ingreso->pago(), $ingreso->fecha, $ingreso->descripcion,null, $empresa->id);

                if($factura->estatus == 0){
                    # EJECUTAMOS COMANDOS EN MIKROTIK
                    $cliente = Contacto::where('id', $factura->cliente)->first();
                    $contrato = Contrato::where('client_id', $cliente->id)->first();
                    $res = DB::table('contracts')->where('client_id', $cliente->id)->update(["state" => 'enabled']);

                    $asignacion = Producto::where('contrato', $contrato->id)->where('venta', 1)->where('status', 2)->where('cuotas_pendientes', '>', 0)->get()->last();

                    if ($asignacion) {
                        $cuotas_pendientes = $asignacion->cuotas_pendientes -= 1;
                        $asignacion->cuotas_pendientes = $cuotas_pendientes;
                        if ($cuotas_pendientes == 0) {
                            $asignacion->status = 1;
                        }
                        $asignacion->save();
                    }

                    # API MK
                    if($contrato){
                        if($contrato->server_configuration_id){
                            $mikrotik = Mikrotik::where('id', $contrato->server_configuration_id)->first();

                            $API = new RouterosAPI();
                            $API->port = $mikrotik->puerto_api;

                            if ($API->connect($mikrotik->ip,$mikrotik->usuario,$mikrotik->clave)) {
                                $API->write('/ip/firewall/address-list/print', TRUE);
                                $ARRAYS = $API->read();

                                #ELIMINAMOS DE MOROSOS#
                                $API->write('/ip/firewall/address-list/print', false);
                                $API->write('?address='.$contrato->ip, false);
                                $API->write("?list=morosos",false);
                                $API->write('=.proplist=.id');
                                $ARRAYS = $API->read();

                                if(count($ARRAYS)>0){
                                    $API->write('/ip/firewall/address-list/remove', false);
                                    $API->write('=.id='.$ARRAYS[0]['.id']);
                                    $READ = $API->read();
                                }
                                #ELIMINAMOS DE MOROSOS#

                                #AGREGAMOS A IP_AUTORIZADAS#
                                $API->comm("/ip/firewall/address-list/add", array(
                                    "address" => $contrato->ip,
                                    "list" => 'ips_autorizadas'
                                    )
                                );
                                #AGREGAMOS A IP_AUTORIZADAS#

                                $API->disconnect();

                                $contrato->state = 'enabled';
                                $contrato->save();
                            }
                        }
                    }

                    # ENVÍO SMS
                   /* $servicio = Integracion::where('empresa', $empresa->id)->where('tipo', 'SMS')->where('status', 1)->first();
                    if($servicio){
                        $numero = str_replace('+','',$cliente->celular);
                        $numero = str_replace(' ','',$numero);

                        if($empresa->sms_pago && isset($factura)){
                            $nombreCliente = $factura->cliente()->nombre.' '.$factura->cliente()->apellidos();
                            $nombreEmpresa = $empresa->nombre;
                            $codigoFactura = $factura->codigo ?? $factura->nro;
                            $valorFactura =  $factura->totalAPI($empresa->id)->total;
                            $fechaVencimiento = date('d-m-Y', strtotime($factura->vencimiento));
                            $pagoRecibido = Funcion::ParsearAPI($precio, $empresa->id);

                            $bulksms = $empresa->sms_pago;
                            $bulksms = str_replace("{cliente}", $nombreCliente, $bulksms);
                            $bulksms = str_replace("{empresa}", $nombreEmpresa, $bulksms);
                            $bulksms = str_replace("{factura}", $codigoFactura, $bulksms);
                            $bulksms = str_replace("{valor}", $valorFactura, $bulksms);
                            $bulksms = str_replace("{pagado}", $pagoRecibido, $bulksms);
                            $bulksms = str_replace("{vencimiento}", $fechaVencimiento, $bulksms);

                            $mensaje =  $bulksms;
                        }else{
                            $mensaje = "Estimado Cliente, le informamos que hemos recibido el pago de su factura por valor de ".Funcion::ParsearAPI($precio, $empresa->id)." gracias por preferirnos. ".$empresa->slogan;
                        }

                        if($servicio->nombre == 'Hablame SMS'){
                            if($servicio->api_key && $servicio->user && $servicio->pass){
                                $post['numero'] = $numero;
                                $post['sms'] = $mensaje;

                                $curl = curl_init();
                                curl_setopt_array($curl, array(
                                    CURLOPT_URL => 'https://api103.hablame.co/api/sms/v3/send/marketing/bulk',
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_ENCODING => '',
                                    CURLOPT_MAXREDIRS => 10,
                                    CURLOPT_TIMEOUT => 0,
                                    CURLOPT_FOLLOWLOCATION => true,
                                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                    CURLOPT_CUSTOMREQUEST => 'POST',CURLOPT_POSTFIELDS => json_encode($post),
                                    CURLOPT_HTTPHEADER => array(
                                        'account: '.$servicio->user,
                                        'apiKey: '.$servicio->api_key,
                                        'token: '.$servicio->pass,
                                        'Content-Type: application/json'
                                    ),
                                ));
                                $result = curl_exec ($curl);
                                $err  = curl_error($curl);
                                curl_close($curl);
                            }
                        }elseif($servicio->nombre == 'SmsEasySms'){
                            if($servicio->user && $servicio->pass){
                                $post['to'] = array('57'.$numero);
                                $post['text'] = $mensaje;
                                $post['from'] = "SMS";
                                $login = $servicio->user;
                                $password = $servicio->pass;

                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, "https://sms.istsas.com/Api/rest/message");
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                curl_setopt($ch, CURLOPT_POST, 1);
                                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
                                curl_setopt($ch, CURLOPT_HTTPHEADER,
                                    array(
                                        "Accept: application/json",
                                        "Authorization: Basic ".base64_encode($login.":".$password)));
                                $result = curl_exec ($ch);
                                $err  = curl_error($ch);
                                curl_close($ch);
                            }
                        }else{
                            if($servicio->user && $servicio->pass){
                                $post['to'] = array('57'.$numero);
                                $post['text'] = $mensaje;
                                $post['from'] = "";
                                $login = $servicio->user;
                                $password = $servicio->pass;

                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, "https://masivos.colombiared.com.co/Api/rest/message");
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                curl_setopt($ch, CURLOPT_POST, 1);
                                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
                                curl_setopt($ch, CURLOPT_HTTPHEADER,
                                    array(
                                        "Accept: application/json",
                                        "Authorization: Basic ".base64_encode($login.":".$password)));
                                $result = curl_exec ($ch);
                                $err  = curl_error($ch);
                                curl_close($ch);
                            }
                        }
                    }*/
                }
                return response('success', 200);
            }
            return response('Factura ya pagada', 200);
        }else{
            return response('Error al pagar la Factura', 500);
        }

    }

    public static function SMSFacturas($fecha){
        $numeros = [];
        $bulk = '';
        $empresa = Empresa::find(1);
        $facturas = Factura::where('fecha', $fecha)->where('estatus', 1)->get();

        foreach ($facturas as $factura) {
            if($factura->cliente()->celular){
                $numero = str_replace('+','',$factura->cliente()->celular);
                $numero = str_replace(' ','',$numero);
                array_push($numeros, '57'.$numero);

                if($empresa->sms_factura_generada){

                    $nombreCliente = trim($factura->cliente()->nombre.' '.$factura->cliente()->apellidos());
                    $nombreEmpresa = $empresa->nombre;
                    $codigoFactura = $factura->codigo ?? $factura->nro;
                    $valorFactura =  $factura->totalAPI($empresa->id)->total;
                    $fechaVencimiento = date('d-m-Y', strtotime($factura->vencimiento));

                    $bulksms = $empresa->sms_factura_generada;
                    $bulksms = str_replace("{cliente}", $nombreCliente, $bulksms);
                    $bulksms = str_replace("{empresa}", $nombreEmpresa, $bulksms);
                    $bulksms = str_replace("{factura}", $codigoFactura, $bulksms);
                    $bulksms = str_replace("{valor}", $valorFactura, $bulksms);
                    $bulksms = str_replace("{vencimiento}", $fechaVencimiento, $bulksms);

                    $bulk .= '{"numero": "57'.$numero.'", "sms": "'.$bulksms.'"},';

                }else if($empresa->nombre == 'FIBRACONEXION S.A.S.' || $empresa->nit == '900822955' || $empresa->nombre == 'Almeidas Comunicaciones S.A.S' ||  $empresa->nit == '901044772'){
                    $fullname = $factura->cliente()->nombre.' '.$factura->cliente()->apellidos();
                    $bulk .= '{"numero": "57'.$numero.'", "sms": "'.trim($fullname).'. '.$empresa->nombre.' le informa que su factura de servicio de internet. Tiene como fecha de vencimiento: '.date('d-m-Y', strtotime($factura->vencimiento)).' Total a pagar '.$factura->totalAPI($empresa->id)->total.'"},';
                }else{
                    $bulk .= '{"numero": "57'.$numero.'", "sms": "Hola, '.$empresa->nombre.' le informa que su factura de internet ha sido generada. '.$empresa->slogan.'"},';
                }
            }
        }

        $servicio = Integracion::where('empresa', 1)->where('tipo', 'SMS')->where('status', 1)->first();
        if($servicio){
            $mensaje = Auth::user()->empresa()->nombre." Estimado cliente, se le informa que su factura de internet ha sido generada. ".$empresa->slogan;
            if($servicio->nombre == 'Hablame SMS'){
                if($servicio->api_key && $servicio->user && $servicio->pass){
                    $curl = curl_init();
                    curl_setopt_array($curl, [
                        CURLOPT_URL => "https://api103.hablame.co/api/sms/v3/send/marketing/bulk",
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 30,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "POST",
                        CURLOPT_POSTFIELDS => "{\n  \"bulk\": [\n    ".substr($bulk, 0, -1)."\n  ]\n}",
                        CURLOPT_HTTPHEADER => [
                            'Content-Type: application/json',
                            'account: '.$servicio->user,
                            'apiKey: '.$servicio->api_key,
                            'token: '.$servicio->pass,
                            ],
                    ]);

                    $response = curl_exec($curl);
                    $err = curl_error($curl);
                    curl_close($curl);

                    isset($response) ? dd($response) : dd($err);
                }
            }elseif($servicio->nombre == 'SmsEasySms'){
                if($servicio->user && $servicio->pass){
                    $post['to'] = $numeros;
                    $post['text'] = $mensaje;
                    $post['from'] = "SMS";
                    $login = $servicio->user;
                    $password = $servicio->pass;

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, "https://sms.istsas.com/Api/rest/message");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
                    curl_setopt($ch, CURLOPT_HTTPHEADER,
                        array(
                            "Accept: application/json",
                            "Authorization: Basic ".base64_encode($login.":".$password)));
                    $result = curl_exec ($ch);
                    $err  = curl_error($ch);
                    curl_close($ch);
                }
            }else{
                if($servicio->user && $servicio->pass){
                    $post['to'] = $numeros;
                    $post['text'] = $mensaje;
                    $post['from'] = "";
                    $login = $servicio->user;
                    $password = $servicio->pass;

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, "https://masivos.colombiared.com.co/Api/rest/message");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
                    curl_setopt($ch, CURLOPT_HTTPHEADER,
                        array(
                            "Accept: application/json",
                            "Authorization: Basic ".base64_encode($login.":".$password)));
                    $result = curl_exec ($ch);
                    $err  = curl_error($ch);
                    curl_close($ch);
                }
            }
        }
    }

    public static function DeshabilitarContratosMK($mk){
        $i=0;
        $mikrotik = Mikrotik::find($mk);
        $empresa = Empresa::find(1);

        if($mikrotik){
            $contratos = Contrato::where('server_configuration_id', $mikrotik->id)->where('state', 'disabled')->where('status', 1)->where('disabled', 0)->take(25)->get();

            //dd($contratos);

            $API = new RouterosAPI();
            $API->port = $mikrotik->puerto_api;

            if ($API->connect($mikrotik->ip,$mikrotik->usuario,$mikrotik->clave)) {
                foreach ($contratos as $contrato) {
                    if($contrato->state == 'disabled'){
                        if($contrato->ip){
                            $API->comm("/ip/firewall/address-list/add", array(
                                "address" => $contrato->ip,
                                "comment" => $contrato->servicio,
                                "list" => 'morosos'
                                )
                            );

                            #ELIMINAMOS DE IP_AUTORIZADAS#
                            $API->write('/ip/firewall/address-list/print', false);
                            $API->write('?address='.$contrato->ip, false);
                            $API->write("?list=ips_autorizadas",false);
                            $API->write('=.proplist=.id');
                            $ARRAYS = $API->read();
                            if(count($ARRAYS)>0){
                                $API->write('/ip/firewall/address-list/remove', false);
                                $API->write('=.id='.$ARRAYS[0]['.id']);
                                $READ = $API->read();
                            }
                            #ELIMINAMOS DE IP_AUTORIZADAS#
                            $i++;
                            $contrato->disabled = 1;
                            $contrato->save();

                            $descripcion = '<i class="fas fa-check text-success"></i> <b>Cambio de Status</b> de habilitado a deshabilitado por cronjob de contratos mikrotik<br>';
                            $movimiento = new MovimientoLOG();
                            $movimiento->contrato    = $contrato->id;
                            $movimiento->modulo      = 5;
                            $movimiento->descripcion = $descripcion;
                            $movimiento->created_by  = 1;
                            $movimiento->empresa     = $contrato->empresa;
                            $movimiento->save();
                        }
                    }
                }
            }
            $API->disconnect();

            dd(Contrato::where('server_configuration_id', $mikrotik->id)->where('state', 'disabled')->where('status', 1)->where('disabled', 0)->count());
        }
    }

    public function creacionContratosDinamico(){

        $contactos = Contacto::get();

        $i = 1;

        foreach($contactos as $contacto){

            $contrato = new Contrato();
            $contrato->empresa = 1;
            $contrato->oficina = null;
            $contrato->nro = $i;
            $contrato->servicio = $contacto->nombre ."-" . $i;
            $contrato->pago_siigo_contrato = 0;
            $contrato->servicio_tv = 28;
            $contrato->client_id = $contacto->id;
            $contrato->rd_item_vencimiento = 0;
            $contrato->server_configuration_id = null;
            $contrato->state = "enabled";
            $contrato->ip = null;
            $contrato->latitude = 3.619790333228524;
            $contrato->longitude = -73.79701780434284;
            $contrato->address_street = $contacto->direccion;
            $contrato->fecha_corte = null;
            $contrato->usuario = null;
            $contrato->conexion = null;
            $contrato->direccion_local_address = null;
            $contrato->local_address_new = null;
            $contrato->profile = null;
            $contrato->grupo_corte = 5;
            $contrato->regla_ip = 0;
            $contrato->ip_autorizada = 0;
            $contrato->facturacion = 1;
            $contrato->factura_individual = 1;
            $contrato->mk = 1;
            $contrato->contrato_permanencia = 0;
            $contrato->contrato_permanencia_meses = 12;
            $contrato->fact_primer_mes = 1;
            $contrato->costo_reconexion = 1;
            $contrato->tipo_contrato = 0;
            $contrato->observaciones = "Contrato predeterminado";
            $contrato->disabled = 0;
            $contrato->opciones_dian = 1;
            $contrato->tipo_nosuspension = 0;

            $contrato->save();
            $i++;
        }

        return "creados";
    }

    public function createDuplicateFacturas(){


        $facturas = Factura::leftJoin('contactos as c','c.id','factura.cliente')
       ->where('fecha','>=','2025-09-01')->where('fecha','<=','2025-09-31')
       // ->where('c.id',5911)
       ->select('factura.*')
       ->whereNotIn('c.tipo_contacto',[1,2])
       ->groupBy('c.id')
       ->get();

       $fecha = '2025-10-12';
       $vencimiento = '2025-10-26';
       $pago_oportuno = '2025-10-15';
       $numero = 1724;
       $nro = NumeracionFactura::Find(9);
       $grupo_corte = GrupoCorte::Find(1);

       foreach($facturas as $f){

           $ultimaF = Factura::where('cliente',$f->cliente)->orderBy('id','desc')->first();
           // $ultimaF->fecha = '2025-10-10';
           if($ultimaF->fecha < '2025-10-01'){

               $itemAntiguo = ItemsFactura::where('factura',$f->id)->first();
               $contrato = DB::table('facturas_contratos')->where('factura_id',$f->id)->first();

               $inicio = $nro->inicio;

               // Validacion para que solo asigne numero consecutivo si no existe.
                while (Factura::where('codigo',$nro->prefijo.$inicio)->first()) {
                   $nro->save();
                   $inicio=$nro->inicio;
                   $nro->inicio += 1;
               }


               if($contrato){
                   $contrato = Contrato::where('nro',$contrato->contrato_nro)->first();
               }

               if($contrato && $itemAntiguo){


                   $factura = new Factura;
                   $factura->nro           = $numero;
                   $factura->codigo        = $nro->prefijo.$inicio;
                   $factura->numeracion    = $nro->id;
                   $factura->plazo         = isset($plazo->id) ? $plazo->id : '';
                   $factura->term_cond     = $contrato->terminos_cond;
                   $factura->facnotas      = $contrato->notas_fact;
                   $factura->empresa       = 1;
                   $factura->cliente       = $f->cliente;
                   $factura->fecha         = $fecha;
                   $factura->tipo          = $f->tipo;
                   $factura->vencimiento   = $vencimiento;
                   $factura->suspension    = $vencimiento;
                   $factura->pago_oportuno = $pago_oportuno;
                   $factura->observaciones = 'Facturación Automática - Corte '.$grupo_corte->fecha_corte;
                   $factura->bodega        = 1;
                   $factura->vendedor      = 1;
                   $factura->prorrateo_aplicado = 0;
                   $factura->facturacion_automatica = 1;

                   if($contrato){
                       $factura->contrato_id = $contrato->id;
                   }

                   $factura->save();

                   $item_reg = new ItemsFactura();
                   $item_reg->factura     = $factura->id;
                   $item_reg->producto    = $itemAntiguo->producto;
                   $item_reg->ref         = $itemAntiguo->ref;
                   $item_reg->precio      = $itemAntiguo->precio;
                   $item_reg->descripcion = $itemAntiguo->producto;
                   $item_reg->id_impuesto = $itemAntiguo->id_impuesto;
                   $item_reg->impuesto    = $itemAntiguo->impuesto;
                   $item_reg->cant        = $itemAntiguo->cant;
                   // $item_reg->desc        = $cm->descuento;
                   $item_reg->save();

                   //guardamos en la tabla detalle para saber que esa factura tiene n contratos
                   DB::table('facturas_contratos')->insert([
                       'factura_id' => $factura->id,
                       'contrato_nro' => $contrato->nro,
                       'created_by' => 0,
                       'client_id' => $factura->cliente,
                       'is_cron' => 1,
                       'created_at' => Carbon::now()
                   ]);

                   $nro++;
               }
               else{

                   if($itemAntiguo){
                                       $factura = new Factura;
                   $factura->nro           = $numero;
                   $factura->codigo        = $nro->prefijo.$inicio;
                   $factura->numeracion    = $nro->id;
                   $factura->plazo         = isset($plazo->id) ? $plazo->id : '';
                   $factura->term_cond     = "";
                   $factura->facnotas      = "";
                   $factura->empresa       = 1;
                   $factura->cliente       = $f->cliente;
                   $factura->fecha         = $fecha;
                   $factura->tipo          = $f->tipo;
                   $factura->vencimiento   = $vencimiento;
                   $factura->suspension    = $vencimiento;
                   $factura->pago_oportuno = $pago_oportuno;
                   $factura->observaciones = 'Facturación Automática - Corte '.$grupo_corte->fecha_corte;
                   $factura->bodega        = 1;
                   $factura->vendedor      = 1;
                   $factura->prorrateo_aplicado = 0;
                   $factura->facturacion_automatica = 1;

                   $factura->save();

                   $item_reg = new ItemsFactura();
                   $item_reg->factura     = $factura->id;
                   $item_reg->producto    = $itemAntiguo->producto;
                   $item_reg->ref         = $itemAntiguo->ref;
                   $item_reg->precio      = $itemAntiguo->precio;
                   $item_reg->descripcion = $itemAntiguo->producto;
                   $item_reg->id_impuesto = $itemAntiguo->id_impuesto;
                   $item_reg->impuesto    = $itemAntiguo->impuesto;
                   $item_reg->cant        = $itemAntiguo->cant;
                   // $item_reg->desc        = $cm->descuento;
                   $item_reg->save();

                   $nro++;
                   }
               }
           }
       }
   }


   public function habilitacionMasivaTV(){
        $contratos = Contrato::where('contracts.state_olt_catv', 0)
            ->where('contracts.updated_at', 'like', '%2025-10-21%')
            ->where('contracts.olt_sn_mac', '!=', 'NULL')
            ->select('contracts.*')
            ->get();

        // $logs = MovimientoLOG::where('modulo',5)->where('descripcion','LIKE','%de habilitado a deshabilitado%')->where('created_at','>','2025-08-16')->pluck('contrato');
        // $contratos = Contrato::whereIn('id',$logs)->where('state','disabled')->get();

        //Habilitando contratos masivamente segun unas especificaciones
        $empresa = Empresa::find(1);
        foreach($contratos as $contrato){
            if($contrato->state_olt_catv == 0){

                //Este es el de habilitacion de CATV
                /* * * API CATV * * */
                if($contrato->olt_sn_mac && $empresa->adminOLT != null){

                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $empresa->adminOLT.'/api/onu/enable_catv/'.$contrato->olt_sn_mac,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_HTTPHEADER => array(
                            'X-token: '.$empresa->smartOLT
                        ),
                        ));

                    $response = curl_exec($curl);
                    $response = json_decode($response);

                    if(isset($response->status) && $response->status == true){

                        $contrato->state_olt_catv = 1;
                        $contrato->save();
                    }
                }

            }
        }

        return "ok habilitacion de contratos";
    }

    public function CrearItemsFactura(){

        //las facturas que les vamos a crear los items son de facturas que estan emitidas en la dian.
        return $facturas = DB::table('factura')
        ->where('estatus',0)
        ->whereNotIn('id', function($query) {
            $query->select('factura')
                  ->distinct()
                  ->from('items_factura')
                  ->whereNotNull('factura');
        })
        ->get();


        foreach($facturas as $factura){

            $ingreso = DB::table('ingresos_factura')->where('factura',$factura->id)->first();
            $cm = Contrato::where('client_id',$factura->cliente)->first();

            if($cm){

                $descuentoPesos = 0;
                $descuentoHasta = isset($cm->fecha_hasta_desc) ? $cm->fecha_hasta_desc : null;
                $fechaActual = Carbon::now()->format('Y-m-d');

                ## Se carga el item a la factura (Plan de Internet) ##
                if($cm->plan_id){
                    $plan = PlanesVelocidad::find($cm->plan_id);
                    $item = Inventario::find($plan->item);
                    $item_reg = new ItemsFactura;
                    $item_reg->factura     = $factura->id;
                    $item_reg->producto    = $item->id;
                    $item_reg->ref         = $item->ref;
                    $item_reg->precio      = $item->precio;
                    $item_reg->descripcion = $plan->name;
                    $item_reg->id_impuesto = $item->id_impuesto;
                    $item_reg->impuesto    = $item->impuesto;
                    if($cm->iva_factura == 1){
                        $item_reg->id_impuesto = 1;
                        $item_reg->impuesto = 19;
                    }
                    $item_reg->cant        = 1;

                    if($descuentoHasta != null && $fechaActual <= $descuentoHasta){
                        $item_reg->desc        = $cm->descuento;

                        if($cm->descuento_pesos != null && $descuentoPesos == 0){
                            $item_reg->precio      = $item_reg->precio - $cm->descuento_pesos;
                            $descuentoPesos = 1;
                        }
                    }else if($descuentoHasta == null || $descuentoHasta == ""){
                        $item_reg->desc        = $cm->descuento;

                        if($cm->descuento_pesos != null && $descuentoPesos == 0){
                            $item_reg->precio      = $item_reg->precio - $cm->descuento_pesos;
                            $descuentoPesos = 1;
                        }
                    }

                    $item_reg->save();
                }

                ## Se carga el item a la factura (Plan de Televisión) ##
                if($cm->servicio_tv){
                    $item = Inventario::find($cm->servicio_tv);
                    $item_reg = new ItemsFactura;
                    $item_reg->factura     = $factura->id;
                    $item_reg->producto    = $item->id;
                    $item_reg->ref         = $item->ref;
                    $item_reg->precio      = $item->precio;
                    $item_reg->descripcion = $item->producto;
                    $item_reg->id_impuesto = $item->id_impuesto;
                    $item_reg->impuesto    = $item->impuesto;
                    $item_reg->cant        = 1;

                    if($descuentoHasta != null && $fechaActual <= $descuentoHasta){
                        $item_reg->desc        = $cm->descuento;
                        if($cm->descuento_pesos != null && $descuentoPesos == 0){
                            $item_reg->precio      = $item_reg->precio - $cm->descuento_pesos;
                            $descuentoPesos = 1;
                        }
                    }elseif($descuentoHasta == null || $descuentoHasta == ""){
                        $item_reg->desc        = $cm->descuento;
                        if($cm->descuento_pesos != null && $descuentoPesos == 0){
                            $item_reg->precio      = $item_reg->precio - $cm->descuento_pesos;
                            $descuentoPesos = 1;
                        }
                    }

                    $item_reg->save();
                }

                ## Se carga el item de otro tipo de servicio ##
                if($cm->servicio_otro){
                    $item = Inventario::find($cm->servicio_otro);
                    $item_reg = new ItemsFactura;
                    $item_reg->factura     = $factura->id;
                    $item_reg->producto    = $item->id;
                    $item_reg->ref         = $item->ref;
                    $item_reg->precio      = $item->precio;
                    $item_reg->descripcion = $item->producto;
                    $item_reg->id_impuesto = $item->id_impuesto;
                    $item_reg->impuesto    = $item->impuesto;
                    $item_reg->cant        = 1;

                    if($descuentoHasta != null && $fechaActual <= $descuentoHasta){
                        $item_reg->desc        = $cm->descuento;
                        if($cm->descuento_pesos != null && $descuentoPesos == 0){
                            $item_reg->precio      = $item_reg->precio - $cm->descuento_pesos;
                            $descuentoPesos = 1;
                        }
                    }elseif($descuentoHasta == null || $descuentoHasta == ""){
                        $item_reg->desc        = $cm->descuento;
                        if($cm->descuento_pesos != null && $descuentoPesos == 0){
                            $item_reg->precio      = $item_reg->precio - $cm->descuento_pesos;
                            $descuentoPesos = 1;
                        }
                    }


                    if($cm->rd_item_vencimiento == 1){

                        if($cm->dt_item_hasta > now()){
                            $item_reg->save();
                        }
                    }else{
                        $item_reg->save();
                    }
                }

                    //guardamos en la tabla detalle para saber que esa factura tiene n contratos
                    DB::table('facturas_contratos')->insert([
                        'factura_id' => $factura->id,
                        'contrato_nro' => $cm->nro,
                        'created_by' => 0,
                        'client_id' => $factura->cliente,
                        'is_cron' => 1,
                        'created_at' => Carbon::now()
                    ]);

            }else if($ingreso){
                    $item = Inventario::Find(170);
                if($item){
                    $item_reg = new ItemsFactura;
                    $item_reg->factura     = $factura->id;
                    $item_reg->producto    = $item->id;
                    $item_reg->ref         = $item->ref;
                    $item_reg->precio      = $ingreso->pagado;
                    $item_reg->descripcion = $item->producto;
                    $item_reg->id_impuesto = 0;
                    $item_reg->impuesto    = 0;
                    $item_reg->save();
                }
            }

        }

        return "ookok";

   }

   public static function agregarIVA(){

    //Agrega el iva solamente al item que es tv y no cambia el total de la factura, solo discrmina el iva.
    $facturas = Factura::where('fecha','>=','2026-01-01')->where('tipo',2)->get();

       foreach($facturas as $factura){

            $items = $factura->itemsFactura()
            ->where('descripcion', 'LIKE', '%TV%')
            ->get();

            foreach ($items as $i) {

                if($i->id_impuesto != 1){
                    $precio = (float) $i->precio; // precio con IVA
                    $cant   = (float) $i->cant;

                    $totalConIva = $precio * $cant;

                    $base = $totalConIva / 1.19;
                    $iva  = $totalConIva - $base;

                    $i->precio = round($base, 2); // ahora precio SIN IVA
                    $i->impuesto = round($iva, 2);
                    $i->id_impuesto = 1; // 19%

                    // return $i;
                    $i->save(); // solo si quieres persistir
                }


            }
       }

       return "ok cambios";


   }

    public function deleteFactura(){

        /* SCRIPT PARA DETECTAR CONTRATOS DESHABILITADOS CON DEUDA MENOR A 5 PESOS */
        $contratos = Contrato::where('state', 'disabled')->get();
        $result = [];
        $i = 0;

        foreach($contratos as $contrato){
            // Buscar última factura abierta validando tanto la relación directa como la tabla pivote
            $ultimaFactura = Factura::select('factura.*')
            ->leftJoin('facturas_contratos as fc', 'fc.factura_id', 'factura.id')
            ->where(function($q) use ($contrato){
                $q->where('factura.contrato_id', $contrato->id);
                if($contrato->nro){
                    $q->orWhere('fc.contrato_nro', $contrato->nro);
                }
            })
            ->where('factura.estatus', 1) 
            ->orderBy('factura.id', 'desc')
            ->first();

            if($ultimaFactura){
                $deuda = $ultimaFactura->porpagar(); // Calcular deuda
                
                // Verificamos si la deuda es mayor a 0 y menor a 5 pesos
                if($deuda > 0 && $deuda < 5){
                    $clienteNombre = $contrato->cliente() ? $contrato->cliente()->nombre : 'N/A';
                    $result[] = [
                        'contrato' => $contrato->nro,
                        'cliente' => $clienteNombre,
                        'factura' => $ultimaFactura->codigo,
                        'deuda'   => $deuda
                    ];
                    $i++;
                }
            }
        }

        return response()->json([
            'total' => $i,
            'contratos' => $result
        ]);
        /* FIN SCRIPT */

        // return $contratos = Contrato::join('facturas_contratos as fc','fc.contrato_nro','contracts.nro')
        //     ->join('factura as f','f.id','fc.factura_id')
        //     ->where('f.fecha', '2025-02-01')
        //     ->where('f.vencimiento', '>', date('Y-m-d'))
        //     ->where('contracts.state', 'disabled')
        //     ->select('contracts.*')
        //     ->get();

        $logs = MovimientoLOG::where('modulo',5)->where('descripcion','LIKE','%de habilitado a deshabilitado%')->where('created_at','>','2025-08-16')->pluck('contrato');
        $contratos = Contrato::whereIn('id',$logs)->where('state','disabled')->get();

        //Habilitando contratos masivamente segun unas especificaciones
        foreach($contratos as $contrato){
            if($contrato->state != 'enabled'){

            if(isset($contrato->server_configuration_id)){

                $mikrotik = Mikrotik::where('id', $contrato->server_configuration_id)->first();
                $API = new RouterosAPI();
                $API->port = $mikrotik->puerto_api;

                if ($API->connect($mikrotik->ip,$mikrotik->usuario,$mikrotik->clave)) {
                    $API->write('/ip/firewall/address-list/print', TRUE);
                    $ARRAYS = $API->read();


                #ELIMINAMOS DE MOROSOS#
                $API->write('/ip/firewall/address-list/print', false);
                $API->write('?address='.$contrato->ip, false);
                $API->write("?list=morosos",false);
                $API->write('=.proplist=.id');
                $ARRAYS = $API->read();

                if(count($ARRAYS)>0){
                    $API->write('/ip/firewall/address-list/remove', false);
                    $API->write('=.id='.$ARRAYS[0]['.id']);
                    $READ = $API->read();
                }
                #ELIMINAMOS DE MOROSOS#

                #HABILITACION DEL PPOE#
                if ($contrato->conexion == 1 && $contrato->usuario != null) {
                    // Buscar el ID interno del secret
                    $API->write('/ppp/secret/print', false);
                    $API->write('?name=' . $contrato->usuario, true);
                    $ARRAYS = $API->read();

                    if (count($ARRAYS) > 0) {
                        $id = $ARRAYS[0]['.id'];
                        // Habilitar el secret
                        $API->write('/ppp/secret/enable', false);
                        $API->write('=numbers=' . $id, true);
                        $response = $API->read();
                        // Log::info("[MIKROTIK] Usuario {$contrato->usuario} habilitado correctamente");
                    }
                }
                #HABILITACION DEL PPOE#

                #AGREGAMOS A IP_AUTORIZADAS#
                $API->comm("/ip/firewall/address-list/add", array(
                    "address" => $contrato->ip,
                    "list" => 'ips_autorizadas'
                    )
                );
                #AGREGAMOS A IP_AUTORIZADAS#

                $contrato->state = 'enabled';

                $contrato->update();
                $API->disconnect();
                }
            }
        }
        }

        return "ok habilitacion de contratos";
        //Script para habilitar contratos por mk tambien segun unas especificaciones


            //Envio de facturas solo por correo por fecha unica.
            //  $fechaInvoice = Carbon::now()->format('Y-m').'-'.substr(str_repeat(0, 2)."15", - 2);
            //  $this->sendInvoices($fechaInvoice);
            //  return "ok";


            // SCRIPT PARA VER CONTRATOS DESHABILUTADOS CON SU ULTIMA FACTURA CERRADA //
            // $contratos = DB::table('contracts as cont')
            // ->where('state', 'disabled')
            // ->join('facturas_contratos', 'cont.nro', '=', 'facturas_contratos.contrato_nro')
            // ->leftJoin('factura as fac', function ($join) {
            //     $join->on('fac.id', '=', DB::raw('(SELECT factura_id FROM facturas_contratos WHERE facturas_contratos.contrato_nro = cont.nro ORDER BY id DESC LIMIT 1)'));
            // })
            // ->where(function ($query) {
            //     $query->whereNull('fac.estatus')->orWhere('fac.estatus', 0);
            // })
            // ->select('cont.*')
            // ->distinct()
            // ->get();

            // $contratos = Contrato::where('state','disabled')->get();
            // $i = 0;
            // foreach($contratos as $contrato){

            //     $facturaContratos = DB::table('facturas_contratos')
            //         ->where('contrato_nro',$contrato->nro)->orderBy('id','DESC')->first();

            //     if($facturaContratos){
            //         $ultFactura = Factura::Find($facturaContratos->factura_id);
            //         if($ultFactura->estatus == 0){
            //             $i = $i+1;
            //             echo $contrato->nro . "<br>";
            //             // return $contrato;
            //             // return $ultFactura;
            //         }
            //     }
            // }
            // return "Deshabilitaods mal hay: " . $i;
            // SCRIPT PARA VER CONTRATOS DESHABILUTADOS CON SU ULTIMA FACTURA CERRADA //

            //--------- Obtener facturas relacionadas con constratos de la manera nueva o antigua por varias validaciones ------- ///
            return $facturas = Factura::leftJoin('facturas_contratos as fc','fc.factura_id','factura.id')
            ->leftJoin('contracts as cs', function ($join) {
                $join->on('cs.nro', '=', 'fc.contrato_nro')
                        ->orOn('cs.id', '=', 'factura.contrato_id');
            })
            ->where('factura.estatus',2)
            ->where('factura.observaciones','LIKE','%Facturación Automática -%')->where('factura.fecha',"2024-12-01")
            //   ->where('cs.grupo_corte',2)
            ->select('factura.id')
            ->get();

            $eliminadas = 0;
            foreach($facturas as $f){

                if($f->pagado() == 0){
                    DB::table('facturas_contratos')->where('factura_id',$f->id)->delete();
                    $itemsFactura = ItemsFactura::where('factura',$f->id)->delete();
                    DB::table('crm')->where('factura',$f->id)->delete();

                    //Si queremos eliminar ingresos tambien si no comentar linea:
                    // $if = DB::table('ingresos_factura')->where('factura',$f->id)->first();
                    // if($if){
                    //     DB::table('ingresos')->where('id',$if->ingreso)->delete();
                    //     DB::table('ingresos_factura')->where('factura',$f->id)->delete();
                    // }

                    $eliminadas++;
                    $f->delete();
                }
            }


            return "Se eliminaron un total de:" . $eliminadas . " facturas correctamente";

            //comprobar en bd
            //SELECT factura.* FROM `factura` WHERE factura.observaciones LIKE "%Facturación Automática - Corte%" AND factura.fecha = "2022-08-25"

            // SOPORTE AGREGAR ITEMS A FACTURAS SIN ITEMS MASIVAMENTE  POR UN GRUPO DE CORTE//
            // $facturas = Factura::join('contracts as c','c.id','=','factura.contrato_id')
            // ->select('factura.*','c.grupo_corte','c.plan_id','c.servicio_tv','c.descuento')
            // ->where('factura.fecha','2022-12-20')
            // ->get();

            // $cont = 0;
            // foreach($facturas as $factura){


                //#SOPORTE FECHA DE VENCIMIENTO MAL INGRESADA CAMBIO MASIVO //
                // if(Carbon::parse($factura->vencimiento)->format('Y') == "2022"){
                // $cont=$cont+1;
                //  $dia = Carbon::parse($factura->vencimiento)->format('d');
                //  $mes = Carbon::parse($factura->vencimiento)->format('m');
                //  $year = "2023";
                //  $fechaCompleta = $year . "-" . $mes . "-" . $dia;
                //  $factura->vencimiento = $fechaCompleta;
                //  $factura->suspension = $fechaCompleta;
                //  $factura->save();
                // }
                //#SOPORTE FECHA DE VENCIMIENTO MAL INGRESADA CAMBIO MASIVO //

                // if($factura->total()->total == 0){
                //     $cont=$cont+1;
                //     if(!DB::table('items_factura')->where('factura',$factura->id)->first()){
                //         $factura->estatus = 1;
                //         $factura->save();
                //         if($factura->plan_id){
                //                     $plan = PlanesVelocidad::find($factura->plan_id);
                //                     $item = Inventario::find($plan->item);

                //                     $item_reg = new ItemsFactura;
                //                     $item_reg->factura     = $factura->id;
                //                     $item_reg->producto    = $item->id;
                //                     $item_reg->ref         = $item->ref;
                //                     $item_reg->precio      = $item->precio;
                //                     $item_reg->descripcion = $plan->name;
                //                     $item_reg->id_impuesto = $item->id_impuesto;
                //                     $item_reg->impuesto    = $item->impuesto;
                //                     $item_reg->cant        = 1;
                //                     $item_reg->desc        = $factura->descuento;
                //                     $item_reg->save();
                //                 }

                //         //         ## Se carga el item a la factura (Plan de Televisión) ##

                //                 if($factura->servicio_tv){
                //                     $item = Inventario::find($factura->servicio_tv);
                //                     $item_reg = new ItemsFactura;
                //                     $item_reg->factura     = $factura->id;
                //                     $item_reg->producto    = $item->id;
                //                     $item_reg->ref         = $item->ref;
                //                     $item_reg->precio      = $item->precio;
                //                     $item_reg->descripcion = $item->producto;
                //                     $item_reg->id_impuesto = $item->id_impuesto;
                //                     $item_reg->impuesto    = $item->impuesto;
                //                     $item_reg->cant        = 1;
                //                     $item_reg->desc        = $factura->descuento;
                //                     $item_reg->save();
                //                 }
                //     }
                // }
            // }
            // return "ok productos actualizados" . $cont;
            //END SOPORTE AGREGAR ITEMS A FACTURAS SIN ITEMS MASIVAMENTE  POR UN GRUPO DE CORTE//

           /// ELIMINAR FACTURAS REPETIDAS EN UN MISMO MES PARA UN MISMO CONTRATO QUE NO ESTEN PAGAS ///
           return;
           $contratos = Contrato::where('status',1)->get();
           $eli = 0;
           foreach($contratos as $contrato){

               $mes = 12;
               $year = 2024;
               $dia = 16;

               $query_facturas = Factura::leftJoin('facturas_contratos as fc','fc.factura_id','factura.id')
                ->leftJoin('contracts as cs', function ($join) {
                       $join->on('cs.nro', '=', 'fc.contrato_nro')
                            ->orOn('cs.id', '=', 'factura.contrato_id');
                   })
               ->where('fc.contrato_nro',$contrato->nro)
               ->whereYear('factura.fecha', $year)
               ->whereMonth('factura.fecha', $mes)
               ->whereDay('factura.fecha', $dia)
               ->orWhere('factura.contrato_id',$contrato->id)
               ->whereYear('factura.fecha', $year)
               ->whereMonth('factura.fecha', $mes)
               ->whereDay('factura.fecha', $dia)
               ->select('factura.*')
               ->groupBy('factura.codigo');


               $facturas = $query_facturas->get();

                   if($facturas->count() > 1){

                       foreach($facturas as $f){

                           if($f->pagado() == 0){

                           $itemsFactura = ItemsFactura::where('factura',$f->id)->delete();
                           DB::table('facturas_contratos')->where('factura_id',$f->id)->delete();
                               DB::table('crm')->where('factura',$f->id)->delete();
                                   $eli++;
                                   $f->delete();
                           }else{
                               $facturas = $query_facturas->get();

                                   if($facturas->count() > 1){
                                       DB::table('facturas_contratos')->where('factura_id',$f->id)->delete();
                                        $itemsFactura = ItemsFactura::where('factura',$f->id)->delete();
                                       DB::table('crm')->where('factura',$f->id)->delete();
                                               $eli++;
                                               $f->delete();
                                   }
                           }
                       }
                   }

                   // return "ok";
           }

           return "se eliminaron " . $eli;

           /// FIN ELIMINAR FACTURAS REPETIDAS EN UN MISMO MES PARA UN MISMO CONTRATO QUE NO ESTEN PAGAS ///

    }

    public function getFacturaTemp($id, $token)
    {
        // 1️⃣ Validar token de seguridad
        if ($token !== config('app.key')) {
            abort(403, 'Token inválido');
        }

        // 2️⃣ Buscar factura
        $factura = Factura::findOrFail($id);

        // 3️⃣ Generar nombre y rutas relativas
        $fileName = 'Factura_' . $factura->codigo . '.pdf';
        $relativePath = 'temp/' . $fileName; // se guarda en storage/app/public/temp/
        $storagePath = storage_path('app/public/' . $relativePath);

        // 4️⃣ Si ya existe, devolver directamente
        if (file_exists($storagePath)) {
            return response()->file($storagePath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            ]);
        }

        // 5️⃣ Generar el PDF en binario
        $facturaPDF = $this->getPdfFactura($id);

        // 6️⃣ Crear carpeta si no existe
        if (!Storage::disk('public')->exists('temp')) {
            Storage::disk('public')->makeDirectory('temp');
        }

        // 7️⃣ Guardar el archivo usando el Filesystem de Laravel
        Storage::disk('public')->put($relativePath, $facturaPDF);

        // 8️⃣ Retornar el archivo directamente
        return response()->file($storagePath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ]);
    }

    public function envioFacturaWpp()
    {
        try {
            $empresa = Empresa::find(1);
            if (!$empresa) {
                Log::error("Empresa no encontrada (id=1).");
                return;
            }

            $fecha = $empresa->cron_fecha_whatsapp ?? date('Y-m-d');

            // Reinicio de variable cron_fecha_whatsapp
            $horaActual = Carbon::now()->format('H:i');
            if ($horaActual >= '00:00' && $horaActual <= '03:00') {
                $empresa->cron_fecha_whatsapp = Carbon::now()->format('Y-m-d');
                $empresa->save();
            }

            // Refrescar empresa
            $empresa = Empresa::find(1);

            $grupos_corte = GrupoCorte::where('status', 1)->get();
            if ($grupos_corte->count() === 0) {
                return;
            }

            $grupos_corte_array = $grupos_corte->pluck('id')->toArray();

            // ===========================
            // ✅ Buscar instancia META DIRECT
            // ===========================
            $instance = Instance::where('company_id', $empresa->id)
                ->where('activo', 1)
                ->where('meta', 0)
                ->first();

            if (!$instance || empty($instance->phone_number_id)) {
                Log::error("Instancia Meta Direct activa no encontrada o sin phone_number_id (meta=0, activo=1).");
                return;
            }

            // Validar type = 1 (Meta Direct)
            if ($instance->type != 1) {
                Log::error("La instancia configurada no es compatible con Meta Direct (Type != 1).");
                return;
            }

            // ✅ Límite para Meta (generalmente más alto, pero seguro)
            $limit = 45;

            // ===========================
            // ✅ Query de facturas
            // ===========================
            $facturas = Factura::join('contracts as c', 'c.id', '=', 'factura.contrato_id')
                ->join('contactos as con', 'con.id', 'c.client_id')
                ->where(function ($query) {
                    $query->whereNotNull('con.celular')
                        ->orWhereNotNull('con.telefono1');
                })
                ->where('factura.fecha', $fecha)
                ->where('factura.whatsapp', 0)
                ->whereIn('c.grupo_corte', $grupos_corte_array)
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('ingresos_factura as i')
                        ->whereColumn('i.factura', 'factura.id');
                })
                ->select('factura.*')
                ->orderBy('factura.updated_at', 'asc')
                ->limit($limit)
                ->get();

            Log::info("Facturas encontradas para procesar: " . $facturas->count());

            $metaService = new \App\Services\MetaWhatsAppService();

            foreach ($facturas as $factura) {

                // ✅ Blindaje por si pagó
                $yaPago = DB::table('ingresos_factura')
                    ->where('factura', $factura->id)
                    ->exists();

                if ($yaPago) {
                    continue;
                }

                // Actualizar timestamp
                $factura->updated_at = now();
                $factura->save();

                $contacto = $factura->cliente();

                // Validar celular
                $celular = null;
                if (!empty($contacto->celular) && strlen($contacto->celular) > 9) {
                    $celular = $contacto->celular;
                } elseif (!empty($contacto->telefono1) && strlen($contacto->telefono1) > 9) {
                    $celular = $contacto->telefono1;
                }

                if (!$celular) {
                    Log::warning("Factura {$factura->codigo}: Sin celular válido.");
                    continue;
                }

                $prefijo = '57';
                if (!empty($contacto->fk_idpais)) {
                    $prefijoData = DB::table('prefijos_telefonicos')
                        ->where('iso2', strtoupper($contacto->fk_idpais))
                        ->first();
                    if ($prefijoData && !empty($prefijoData->phone_code)) {
                        $prefijo = $prefijoData->phone_code;
                    }
                }

                // ===================================
                // 🧩 GENERAR PDF TEMPORAL
                // ===================================
                $this->getFacturaTemp($factura->id, config('app.key'));
                $fileName = "Factura_{$factura->codigo}.pdf";
                $storagePath = storage_path("app/public/temp/{$fileName}");

                $attempts = 0;
                while (!file_exists($storagePath) && $attempts < 5) {
                    usleep(300000);
                    $attempts++;
                }

                if (!file_exists($storagePath)) {
                    Log::error("Factura {$factura->codigo}: No se pudo generar PDF.");
                    continue;
                }

                $urlFactura = url("storage/temp/{$fileName}");

                // ===================================
                // 📦 PREPARAR DATOS PLANTILLA
                // ===================================
                $estadoCuenta = $factura->estadoCuenta();
                $total = $factura->total()->total;
                $saldo = $estadoCuenta->saldoMesAnterior > 0
                    ? $estadoCuenta->saldoMesAnterior + $total
                    : $total;

                // Buscar plantilla preferida
                $plantilla = Plantilla::where('preferida_cron_factura', 1)
                    ->where('tipo', 3)
                    ->where('status', 1)
                    ->where('empresa', $empresa->id)
                    ->first();

                if (!$plantilla) {
                    Log::warning("Factura {$factura->codigo}: No hay plantilla preferida para CRON.");
                    continue;
                }

                // Procesar variables dinámicas
                $bodyTextParams = [];
                if ($plantilla->body_dinamic) {
                    $bodyDinamicArray = json_decode($plantilla->body_dinamic, true);
                    if (is_array($bodyDinamicArray) && isset($bodyDinamicArray[0]) && is_array($bodyDinamicArray[0])) {
                        $bodyDinamicArray = $bodyDinamicArray[0];
                    }

                    if (is_array($bodyDinamicArray)) {
                        foreach ($bodyDinamicArray as $paramTemplate) {
                            $paramValue = is_string($paramTemplate) ? $paramTemplate : '';
                            $paramValue = \App\Helpers\CamposDinamicosHelper::procesarCamposDinamicos($paramValue, $contacto, $factura, $empresa);
                            $bodyTextParams[] = $paramValue;
                        }
                    }
                } else {
                    $bodyTextArray = json_decode($plantilla->body_text, true);
                    if (is_array($bodyTextArray) && isset($bodyTextArray[0]) && is_array($bodyTextArray[0])) {
                        $bodyTextParams = $bodyTextArray[0];
                    } else {
                        $bodyTextParams = [
                            $contacto->nombre . " " . $contacto->apellido1,
                            $empresa->nombre,
                            number_format($saldo, 0, ',', '.')
                        ];
                    }
                }

                // Construir Components
                $components = [];

                if ($plantilla->body_header === 'DOCUMENT') {
                    $components[] = [
                        "type" => "header",
                        "parameters" => [
                            [
                                "type" => "document",
                                "document" => [
                                    "link" => $urlFactura,
                                    "filename" => "Factura_{$factura->codigo}.pdf"
                                ]
                            ]
                        ]
                    ];
                }

                $parameters = [];
                foreach ($bodyTextParams as $paramValue) {
                    $parameters[] = ["type" => "text", "text" => strval($paramValue)];
                }
                $components[] = [
                    "type" => "body",
                    "parameters" => $parameters
                ];

                // ===================================
                // 🚀 ENVIAR VIA META
                // ===================================
                $response = (object) $metaService->sendTemplate(
                    $instance->phone_number_id,
                    $prefijo . ltrim($celular, '0'),
                    $plantilla->title,
                    $plantilla->language ?? 'es',
                    $components
                );

                // Validar Respuesta
                $responseData = json_decode(json_encode($response), true);
                $status = 'error';
                
                // El MetaWhatsAppService devuelve la respuesta de la API de Meta dentro de una llave 'data'
                $metaData = $responseData['data'] ?? $responseData;

                if (isset($metaData['messaging_product']) && $metaData['messaging_product'] === 'whatsapp') {
                    if (isset($metaData['messages']) && count($metaData['messages']) > 0) {
                        // Asumimos 'success' si Meta devuelve message_id
                         $status = 'success';
                    }
                }

                // Construir el mensaje completo procesado para registro
                $mensajeProcesado = $plantilla->contenido ?? '';
                foreach ($bodyTextParams as $index => $paramValue) {
                    $mensajeProcesado = str_replace('{{' . ($index + 1) . '}}', $paramValue, $mensajeProcesado);
                }

                // Log
                WhatsappMetaLog::create([
                    'status' => $status,
                    'response' => json_encode($response),
                    'factura_id' => $factura->id,
                    'contacto_id' => $contacto->id,
                    'empresa' => $empresa->id,
                    'mensaje_enviado' => $mensajeProcesado ?: ("Cron Meta: " . $plantilla->title),
                    'plantilla_id' => $plantilla->id,
                    'enviado_por' => 0 
                ]);

                if ($status === 'success') {
                    $factura->whatsapp = 1;
                    $factura->save();
                    Log::info("Factura {$factura->codigo} enviada correctamente a {$celular}");

                    // Sync con Chat System (Centralizado)
                    $phone = $prefijo . ltrim($celular, '0');
                    $wamid = $responseData['data']['messages'][0]['id'] ?? ($responseData['messages'][0]['id'] ?? null);
                    
                    if ($wamid) {
                        $this->registerCentralizedBatch(
                            $instance->phone_number_id,
                            $phone,
                            $wamid,
                            $mensajeProcesado,
                            $contacto->nombre . ' ' . $contacto->apellido1
                        );
                    }
                } else {
                    Log::error("Error enviando factura {$factura->codigo} a {$celular}: " . json_encode($responseData));
                }
            }

        } catch (\Exception $e) {
            Log::error("Error general en envioFacturaWpp: " . $e->getMessage());
        }
    }


    public function aplicateProrrateo(){

        $facturas = Factura::where('observaciones','LIKE','%Facturación Automática - Corte%')
        ->where('fecha',"2022-09-01")
        ->where('estatus',1)->get();

        if(Auth::user()->empresaObj->prorrateo == 1){

            foreach($facturas as $factura){
                $dias = $factura->diasCobradosProrrateo();
                //si es diferente de 30 es por que se cobraron menos dias y hay prorrateo
                if($dias < 30){
                    if(isset($factura->prorrateo_aplicado)){
                        $factura->prorrateo_aplicado = 1;
                        $factura->save();
                    }

                    foreach($factura->itemsFactura as $item){

                        //dividimos el precio del item en 30 para saber cuanto vamos a cobrar en total restando los dias
                        $precioItemProrrateo = $this->precision($item->precio * $dias / 30);
                        $item->precio = $precioItemProrrateo;
                        $item->save();

                    }
                }
            }

        }
    }

    public static function disabledAndCRM($ip){
        $i=0;$j=0;$anuladas=0;$ingreso=0;

        $contactos = Contacto::join('factura as f','f.cliente','=','contactos.id')->
            join('contracts as cs','cs.client_id','=','contactos.id')->
            select('contactos.id', 'contactos.nombre', 'contactos.nit', 'f.id as factura', 'f.estatus', 'f.suspension', 'cs.state', 'cs.id as contrato_id', 'f.contrato_id')->
            where('f.estatus',1)->
            whereIn('f.tipo', [1,2])->
            where('contactos.status',1)->
            where('cs.ip',$ip)->
            get();

        if ($contactos) {
            foreach($contactos as $item){
                $contrato = Contrato::find($item->contrato_id);
                $contrato->state = 'disabled';
                $contrato->save();

                $descripcion = '<i class="fas fa-check text-success"></i> <b>Cambio de Status</b> de habilitado a deshabilitado por cronjob de disabled CRM<br>';
                $movimiento = new MovimientoLOG();
                $movimiento->contrato    = $contrato->id;
                $movimiento->modulo      = 5;
                $movimiento->descripcion = $descripcion;
                $movimiento->created_by  = 1;
                $movimiento->empresa     = $contrato->empresa;
                $movimiento->save();

                if($j==0){
                    $crm = CRM::where('cliente', $item->id)->whereIn('estado', [0, 3])->delete();
                    $crm = new CRM();
                    $crm->cliente = $item->id;
                    $crm->factura = $item->factura;
                    $crm->servidor = isset($contrato->server_configuration_id) ? $contrato->server_configuration_id : '';
                    $crm->grupo_corte = isset($contrato->grupo_corte) ? $contrato->grupo_corte : '';
                    $crm->save();
                    $ingreso++;
                    $j++;
                }else{
                    $factura = Factura::find($item->factura);
                    $factura->estatus = 2;
                    $factura->save();
                    $anuladas++;
                }
            }
        }
        return 'Anuladas: '.$anuladas.' - Ingresados a CRM: '.$ingreso;
    }

    public static function sendInvoices($date){
        $facturas = Factura::where('facturacion_automatica', 1)->where('fecha', $date)->where('correo_sendinblue', 0)->get();
        //dd($facturas);
        foreach ($facturas as $factura) {
            $empresa = Empresa::find($factura->empresa);
            $emails  = $factura->cliente()->email;
            $tipo    = 'Factura de venta original';
            view()->share(['title' => 'Imprimir Factura']);
            if ($factura) {
                $items = ItemsFactura::where('factura',$factura->id)->get();
                $itemscount=ItemsFactura::where('factura',$factura->id)->count();
                $retenciones = FacturaRetencion::where('factura', $factura->id)->get();
                $resolucion = NumeracionFactura::where('empresa',$empresa->id)->latest()->first();
                //---------------------------------------------//
                if($factura->emitida == 1){
                    $impTotal = 0;
                    foreach ($factura->totalAPI($empresa->id)->imp as $totalImp){
                        if(isset($totalImp->total)){
                            $impTotal = $totalImp->total;
                        }
                    }

                    $CUFEvr = $factura->info_cufeAPI($factura->id, $impTotal, $empresa->id);
                    $infoEmpresa = Empresa::find($empresa->id);
                    $data['Empresa'] = $infoEmpresa->toArray();
                    $infoCliente = Contacto::find($factura->cliente);
                    $data['Cliente'] = $infoCliente->toArray();
                    /*..............................
                    Construcción del código qr a la factura
                    ................................*/
                    $impuesto = 0;
                    foreach ($factura->totalAPI($empresa->id)->imp as $key => $imp) {
                        if(isset($imp->total)){
                            $impuesto = $imp->total;
                        }
                    }

                    $codqr = "NumFac:" . $factura->codigo . "\n" .
                    "NitFac:"  . $data['Empresa']['nit']   . "\n" .
                    "DocAdq:" .  $data['Cliente']['nit'] . "\n" .
                    "FecFac:" . Carbon::parse($factura->created_at)->format('Y-m-d') .  "\n" .
                    "HoraFactura" . Carbon::parse($factura->created_at)->format('H:i:s').'-05:00' . "\n" .
                    "ValorFactura:" .  number_format($factura->totalAPI($empresa->id)->subtotal, 2, '.', '') . "\n" .
                    "ValorIVA:" .  number_format($impuesto, 2, '.', '') . "\n" .
                    "ValorOtrosImpuestos:" .  0.00 . "\n" .
                    "ValorTotalFactura:" .  number_format($factura->totalAPI($empresa->id)->subtotal + $factura->impuestos_totalesFe(), 2, '.', '') . "\n" .
                    "CUFE:" . $CUFEvr;
                    /*..............................
                    Construcción del código qr a la factura
                    ................................*/
                    //$pdf = PDF::loadView('pdf.electronicaAPI', compact('items', 'factura', 'itemscount', 'tipo', 'retenciones','resolucion','codqr','CUFEvr', 'empresa'))->save(public_path() . "/convertidor/" . $factura->codigo . ".pdf")->stream();
                }else{
                    //$pdf = PDF::loadView('pdf.electronicaAPI', compact('items', 'factura', 'itemscount', 'tipo', 'retenciones','resolucion', 'empresa'))->save(public_path() . "/convertidor/" . $factura->codigo . ".pdf")->stream();
                }
                //-----------------------------------------------//

                $total = Funcion::ParsearAPI($factura->totalAPI($empresa->id)->total, $empresa->id);
                $key = Hash::make(date("H:i:s"));
                $toReplace = array('/', '$','.');
                $key = str_replace($toReplace, "", $key);
                $factura->nonkey = $key;
                $factura->save();
                $cliente = $factura->cliente()->nombre;
                $tituloCorreo = $empresa->nombre.": Factura N° $factura->codigo";
                $xmlPath = 'xml/empresa1/FV/FV-'.$factura->codigo.'.xml';
            }

            $html = view('emails.emailSendInBlue', [
                'factura' => $factura,
                'total'   => $total,
                'cliente' => $cliente,
                'empresa' => $empresa,
            ]);

            $fields = [
                'to' => [
                    [
                        'email' => $emails,
                        'name' => $cliente.' '.$factura->cliente()->apellidos()
                    ]
                ],
                'sender' => [
                    'name' => $empresa->nombre,
                    'email' => $empresa->email
                ],
                'subject' => $tituloCorreo,
                'htmlContent' => '<html>'.$html.'</html>',

            ];

            $fields = json_encode($fields);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.sendinblue.com/v3/smtp/email');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'accept: application/json',
                'api-key: '.$empresa->api_key_mail.'', 'content-type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);

            $response = json_decode($response, true);

            if(isset($response['messageId'])){
                $factura->correo_sendinblue = 1;
            }

            $factura->response_sendinblue = $response;
            $factura->save();
            //unlink(public_path() . "/convertidor/" . $factura->codigo . ".pdf");
        }

        return $facturas;
    }

    public function validateEmisionApi(){

        $bearerToken = env('EMISION_TOKEN');
        $urlEmision = env('URL_EMISION_DIAN');
        $mesInicio = Carbon::now()->startOfMonth()->toDateString();
        $finMes = Carbon::now()->endOfMonth()->toDateString();

        if($bearerToken != "" && $urlEmision != "")
        {
            $facturas = Factura::where('fecha','>=',$mesInicio)->where('fecha','<=',$finMes)
            ->where('emitida',1)
            ->where('tipo',2)
            ->where('dian_service',0);

            $pos = Factura::where('fecha','>=',$mesInicio)->where('fecha','<=',$finMes)
            ->where('emitida',1)
            ->where('tipo',6)
            ->where('dian_service',0);

            $documentoSoporte = FacturaProveedores::where('fecha','>=',$mesInicio)->where('fecha','<=',$finMes)
            ->where('emitida',1)
            ->where('dian_service',0);

            $notasCredito = NotaCredito::where('fecha','>=',$mesInicio)->where('fecha','<=',$finMes)
            ->where('emitida',1)
            ->where('dian_service',0);

            $notasDebito = NotaDebito::where('fecha','>=',$mesInicio)->where('fecha','<=',$finMes)
            ->where('emitida',1)
            ->where('dian_service',0);

            $nominas = Nomina::where('fecha_emision','>=',$mesInicio)->where('fecha_emision','<=',$finMes)
            ->where('emitida',1)
            ->where('dian_service',0);

            $data = [
                'facturas' => $facturas->count(),
                'pos' => $pos->count(),
                'documentosoporte' => $documentoSoporte->count(),
                'notascredito' => $notasCredito->count(),
                'notasdebito' => $notasDebito->count(),
                'nomina' => $nominas->count(),
            ];

            try {
                $emisionService = new EmisionesService();
                $response = $emisionService->sendEmisionsEmpresa($data);

                $response = json_decode($response);

                if(isset($response->status) && $response->status == 200){
                    $facturas->update(['dian_service'=> 1]);
                    $pos->update(['dian_service'=> 1]);
                    $documentoSoporte->update(['dian_service'=> 1]);
                    $notasCredito->update(['dian_service'=> 1]);
                    $notasDebito->update(['dian_service'=> 1]);
                    $nominas->update(['dian_service'=> 1]);
                }
                // Log::info('Finalizado con exito el informe de emisiones del dia: ' . Carbon::now()->format('Y-m-d'));

            } catch (ClientException $e) {
                if($e->getResponse()->getStatusCode() === 404) {
                    Log::error('Hay un error en la importacion de la informacion: ' . Carbon::now()->format('Y-m-d'));
                    // return $e;
                }
            }
        }else{
            Log::error('No hay credenciales para registrar las emisiones: ' . Carbon::now()->format('Y-m-d'));
        }

        //REVISION RECONEXION GENERAL//.
        $empresa = Empresa::Find(1);
        if($empresa->reconexion_generica == 1 && $empresa->dias_reconexion_generica != null){
            $diasMas = $empresa->dias_reconexion_generica;

            $contactos = Contacto::join('factura as f','f.cliente','=','contactos.id')->
            leftJoin('facturas_contratos as fcs', 'fcs.factura_id', '=', 'f.id')
            ->leftJoin('contracts as cs', function ($join) {
                $join->on('cs.nro', '=', 'fcs.contrato_nro');
            })->
            select('contactos.id', 'contactos.nombre', 'contactos.nit', 'f.id as factura', 'f.estatus', 'f.suspension', 'cs.state', 'f.contrato_id')->
            where('f.estatus',1)->
            whereIn('f.tipo', [1,2])->
            where('contactos.status',1)->
            where('cs.fecha_suspension', null)->
            // where('f.id',191)->
            whereDate(DB::raw("DATE_ADD(f.vencimiento, INTERVAL $diasMas DAY)"), '<=', now())->
            orderBy('f.id', 'desc')->
            get();

            foreach ($contactos as $contacto) {

                $factura = Factura::find($contacto->factura);

                //ESto es lo que hay que refactorizar.
                $facturaContratos = DB::table('facturas_contratos')
                ->where('factura_id',$factura->id)->pluck('contrato_nro');

                if(!DB::table('facturas_contratos')
                ->where('factura_id',$factura->id)->first()){
                    $facturaContratos = Contrato::where('id',$factura->contrato_id)->pluck('nro');
                }

                $contratosId = Contrato::whereIn('nro',$facturaContratos)
                ->pluck('id');

                $ultimaFacturaRegistrada = Factura::
                where('cliente',$factura->cliente)
                ->where('estatus','<>',2)
                ->whereIn('contrato_id',$contratosId)
                ->orderBy('created_at', 'desc')
                ->value('id');

                //manera antigua de buscar el contrato.
                if(!$ultimaFacturaRegistrada){
                      $ultimaFacturaRegistrada = Factura::
                        where('cliente',$factura->cliente)
                        ->where('contrato_id',$factura->contrato_id)
                        ->orderBy('created_at', 'desc')
                        ->value('id');
                }

                if($factura->id == $ultimaFacturaRegistrada){
                    $itemReconexion = Inventario::where('type','RECONEXION')->first();
                    $itemExiste = ItemsFactura::where('factura',$factura->id)->where('ref','RECONEXION')->first();
                    if($itemReconexion && !$itemExiste){
                        $item = new ItemsFactura();
                        $item->factura     = $factura->id;
                        $item->producto    = $itemReconexion->id;
                        $item->ref         = $itemReconexion->ref;
                        $item->precio      = $itemReconexion->precio;
                        $item->descripcion = $itemReconexion->descripcion;
                        $item->id_impuesto = $itemReconexion->id_impuesto;
                        $item->impuesto    = $itemReconexion->impuesto;
                        $item->cant        = 1;
                        $item->desc        = $itemReconexion->descuento;
                        $item->save();
                    }
                }
            }
        }
        //Fin REVISION RECONEXION GENERAL//.


        //Inicio validacion de codigos iguales emitidos
        // $this->validateCodeEmision();
        //Fin validacion de codigos iguales emitidos
    }

    //Este metodo me permite validar que facturas se crearon con el mismo codigo y quedaron emitidas, la que tiene el
    //codigo 409 es la que no quedo emitida y debe cambiar de codigo.
    public function validarFacturasDobles(){

        $fecha_inicio = "2024-04-01";
        $fecha_fin = "2024-04-31";

        // Consulta para obtener facturas con el mismo código y dian_response = 409
         return $noObtener = Factura::
        where('fecha', '>=',$fecha_inicio)
        ->where('fecha', '<=',$fecha_fin)
        ->where('dian_response', 409)
        ->groupBy('codigo') // Agrupamos por el atributo código
        ->havingRaw('COUNT(codigo) > 1') // Condición para obtener facturas con el mismo código
        ->pluck('codigo');


        // Obtener los códigos duplicados sin filtrar por dian_response
        $duplicatedCodes = Factura::whereBetween('fecha', [$fecha_inicio, $fecha_fin])
            ->groupBy('codigo')
            ->havingRaw('COUNT(codigo) > 1')
            ->pluck('codigo');

        // Obtener las facturas que tienen el dian_response = 409 y cuyo código esté en los duplicados
        $facturas = Factura::whereIn('codigo', $duplicatedCodes)
            ->whereNotIn('codigo',$noObtener)
            ->where('dian_response', 409)
            ->whereBetween('fecha', [$fecha_inicio, $fecha_fin])
            ->get();

        // tipo 2 numeracion dian
        $nro=NumeracionFactura::where('empresa',1)->where('preferida',1)->where('estado',1)->where('tipo',2)->first();

        foreach($facturas as $factura){
            //Actualiza el nro de inicio para la numeracion seleccionada
            $inicio = $nro->inicio;

            // Validacion para que solo asigne numero consecutivo si no existe.
            while (Factura::where('codigo',$nro->prefijo.$inicio)->first()) {
                $nro->save();
                $inicio=$nro->inicio;
                $nro->inicio += 1;
            }

            $factura->codigo=$nro->prefijo.$inicio;
            $factura->emitida = 0;

            $nro->save();
            $factura->save();
        }

        return "correccion finalizada";
    }

    //Este metodo me permite validar y eliminar facturas duplicadas con los mismos criterios.
    public static function validateFacturasDuplicadas($fecha){

        $eliminadas = 0;
        $mensaje = [];

        // VALIDACIÓN 1: Facturas duplicadas con mismo cliente, código y fecha
        $facturasDuplicadas = DB::table('factura')
            ->select('cliente', 'codigo', 'fecha', DB::raw('COUNT(*) as total'))
            ->where('fecha', $fecha)
            ->where('facturacion_automatica', 1)
            ->groupBy('cliente', 'codigo', 'fecha')
            ->having('total', '>', 1)
            ->get();

        foreach ($facturasDuplicadas as $grupo) {
            // Obtener todas las facturas del grupo duplicado
            $facturas = Factura::where('fecha', $grupo->fecha)
                ->where('cliente', $grupo->cliente)
                ->where('codigo', $grupo->codigo)
                ->where('facturacion_automatica', 1)
                ->orderBy('id', 'asc') // Ordenar por ID para conservar la primera
                ->get();

            if ($facturas->count() > 1) {
                // Conservar la primera factura (la más antigua por ID)
                $facturaConservar = $facturas->first();

                // Eliminar las duplicadas (todas excepto la primera)
                $facturasEliminar = $facturas->skip(1);

                foreach ($facturasEliminar as $facturaEliminar) {
                    self::eliminarFacturaCompleta($facturaEliminar);
                    $eliminadas++;
                    $mensaje[] = "Factura duplicada eliminada: ID {$facturaEliminar->id} (Código: {$facturaEliminar->codigo}, Cliente: {$facturaEliminar->cliente}). Se conservó la factura ID {$facturaConservar->id}";
                }
            }
        }

        // VALIDACIÓN 2: Facturas con mismo cliente, fecha y created_at (mismo año, mes, día, hora y minuto) pero diferente código
        $facturasDuplicadasPorTiempo = DB::table('factura')
            ->select('cliente', 'fecha', DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d %H:%i") as created_at_formatted'), DB::raw('COUNT(*) as total'))
            ->where('fecha', $fecha)
            ->where('facturacion_automatica', 1)
            ->groupBy('cliente', 'fecha', DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d %H:%i")'))
            ->having('total', '>', 1)
            ->get();

        foreach ($facturasDuplicadasPorTiempo as $grupo) {
            // Obtener todas las facturas del grupo duplicado por tiempo
            $facturas = Factura::where('fecha', $grupo->fecha)
                ->where('cliente', $grupo->cliente)
                ->where('facturacion_automatica', 1)
                ->whereRaw('DATE_FORMAT(created_at, "%Y-%m-%d %H:%i") = ?', [$grupo->created_at_formatted])
                ->orderBy('id', 'asc') // Ordenar por ID para conservar la primera
                ->get();

            if ($facturas->count() > 1) {
                // Conservar la primera factura (la más antigua por ID)
                $facturaConservar = $facturas->first();

                // Eliminar las duplicadas (todas excepto la primera)
                $facturasEliminar = $facturas->skip(1);

                foreach ($facturasEliminar as $facturaEliminar) {
                    self::eliminarFacturaCompleta($facturaEliminar);
                    $eliminadas++;
                    $mensaje[] = "Factura duplicada eliminada por tiempo de creación: ID {$facturaEliminar->id} (Código: {$facturaEliminar->codigo}, Cliente: {$facturaEliminar->cliente}, Creada: {$grupo->created_at_formatted}). Se conservó la factura ID {$facturaConservar->id}";
                }
            }
        }

        $resultado = [
            'eliminadas' => $eliminadas,
            'mensaje' => $mensaje,
            'resumen' => "Se eliminaron {$eliminadas} factura(s) duplicada(s) de la fecha {$fecha}"
        ];

        return $resultado;
    }

    /**
     * Elimina completamente una factura y todos sus registros relacionados
     * @param Factura $factura
     * @return void
     */
    private static function eliminarFacturaCompleta($factura)
    {
        // Eliminar registros de CRM que referencian esta factura
        DB::table('crm')
            ->where('factura', $factura->id)
            ->delete();

        // Eliminar items_factura
        ItemsFactura::where('factura', $factura->id)->delete();

        // Eliminar de ingresos_factura
        DB::table('ingresos_factura')
            ->where('factura', $factura->id)
            ->delete();

        // Eliminar de facturas_contratos
        DB::table('facturas_contratos')
            ->where('factura_id', $factura->id)
            ->delete();

        // Eliminar la factura misma
        $factura->delete();
    }

    public function refreshCorteIntertTV(){

        $fecha = Carbon::now()->format('Y-m-d');

        //ingresos asociados a facturas del dia de hoy.
        $ingresos = Ingreso::where('fecha', $fecha)
        ->where('tipo', 1)
        ->where(function ($q) {
            $q->where('revalidacion_enable_internet', 0)
              ->orWhere('revalidacion_enable_tv', 0);
        })
        ->orderBy('updated_at', 'asc')
        ->get();

        //obtenemos los contratos o el contrato que la factura tiene
        foreach($ingresos as $ingreso){

            $ingreso->updated_at = now();
            $ingreso->save();

            $facturas = IngresosFactura::where('ingreso',$ingreso->id)->get()->pluck('factura');
            if($facturas->count() > 0){

                $contratos = Factura::leftJoin('facturas_contratos as fc','fc.factura_id','factura.id')
                ->whereIn('fc.factura_id',$facturas)
                ->where('factura.estatus',0)
                ->pluck('fc.contrato_nro')
                ->unique()
                ->values();

                if($contratos->count() > 0){
                    foreach($contratos as $contrato){

                        $contrato = Contrato::where('nro',$contrato)->first();

                        //Este es el de habilitacion de CATV
                        /* * * API CATV * * */
                        $empresa = Empresa::find(1);
                        if($contrato->olt_sn_mac && $empresa->adminOLT != null){

                            $curl = curl_init();
                            curl_setopt_array($curl, array(
                                CURLOPT_URL => $empresa->adminOLT.'/api/onu/enable_catv/'.$contrato->olt_sn_mac,
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_ENCODING => '',
                                CURLOPT_MAXREDIRS => 10,
                                CURLOPT_TIMEOUT => 0,
                                CURLOPT_FOLLOWLOCATION => true,
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                CURLOPT_CUSTOMREQUEST => 'POST',
                                CURLOPT_HTTPHEADER => array(
                                    'X-token: '.$empresa->smartOLT
                                ),
                                ));

                            $response = curl_exec($curl);
                            $response = json_decode($response);

                            if(isset($response->status) && $response->status == true){

                                $ingreso->revalidacion_enable_tv = 1;
                                $ingreso->save();

                                $contrato->state_olt_catv = 1;
                                $contrato->save();
                            }
                        }else{
                            $ingreso->revalidacion_enable_tv = 1;
                            $ingreso->save();
                        }
                        /* * * API CATV * * */

                        //Este es el de internet

                        /* * * API MIKROTIK * * */
                        if($contrato->server_configuration_id){
                            $mikrotik = Mikrotik::where('id', $contrato->server_configuration_id)->first();
                            $API = new RouterosAPI();
                            $API->port = $mikrotik->puerto_api;

                            if ($API->connect($mikrotik->ip,$mikrotik->usuario,$mikrotik->clave)) {
                                $API->write('/ip/firewall/address-list/print', TRUE);
                                $ARRAYS = $API->read();

                                $API->write('/ip/firewall/address-list/print', false);
                                $API->write('?address=' . $contrato->ip, false);
                                $API->write('?list=morosos', true);
                                $result = $API->read();

                                if (!empty($result)) {

                                    #ELIMINAMOS DE MOROSOS#
                                    $API->write('/ip/firewall/address-list/print', false);
                                    $API->write('?address='.$contrato->ip, false);
                                    $API->write("?list=morosos",false);
                                    $API->write('=.proplist=.id');
                                    $ARRAYS = $API->read();
                                    #ELIMINAMOS DE MOROSOS#

                                    if(count($ARRAYS)>0){
                                        $API->write('/ip/firewall/address-list/remove', false);
                                        $API->write('=.id='.$ARRAYS[0]['.id']);
                                        $READ = $API->read();

                                        #AGREGAMOS A IP_AUTORIZADAS#
                                        $API->comm("/ip/firewall/address-list/add", array(
                                            "address" => $contrato->ip,
                                            "list" => 'ips_autorizadas'
                                            )
                                        );
                                        #AGREGAMOS A IP_AUTORIZADAS#

                                        $ingreso->revalidacion_enable_internet = 1;
                                        $ingreso->save();

                                        $contrato->state = 'enabled';
                                        $contrato->save();
                                    }

                                } else {

                                    #AGREGAMOS A IP_AUTORIZADAS#
                                    $API->comm("/ip/firewall/address-list/add", array(
                                        "address" => $contrato->ip,
                                        "list" => 'ips_autorizadas'
                                        )
                                    );
                                    #AGREGAMOS A IP_AUTORIZADAS#

                                    $ingreso->revalidacion_enable_internet = 1;
                                    $ingreso->save();
                                }

                                $API->disconnect();
                            }else{
                                echo "no se conecto a la mikrotik";
                            }
                        }else{
                            $ingreso->revalidacion_enable_internet = 1;
                            $ingreso->save();
                        }
                        /* * * API MIKROTIK * * */
                    }
                }

            }
        }
    }

    public function validateCodeEmision(){

        $facturas = Factura::select('codigo')
            ->groupBy('codigo')
            ->havingRaw('COUNT(*) > 1')
            ->where('tipo',2)
            ->get();

        $empresa = Empresa::Find(1);
        $resolucionNumeracion = NumeracionFactura::where('empresa', Auth::user()->empresa)
        ->where('num_equivalente', 0)
        ->where('nomina', 0)
        ->where('estado',1)
        ->where('tipo',2)
        ->where('preferida', 1)->first();

        foreach($facturas as $f){

            $facturasDobles = Factura::where('codigo',$f->codigo)->get();

            foreach($facturasDobles as $fd){
             $validacion = $this->validateStatusDian($empresa->nit,$fd->codigo,"01",$resolucionNumeracion->prefijo);
             $validacion = json_decode($validacion, true);

            $xmlString = base64_decode($validacion['document']);

            // Convertir string a XML principal
            $xml = new \SimpleXMLElement($xmlString);

            // Registrar espacios de nombres
            $namespaces = $xml->getNamespaces(true);
            $xml->registerXPathNamespace('cac', $namespaces['cac']);
            $xml->registerXPathNamespace('cbc', $namespaces['cbc']);

            // Buscar el contenido de <cbc:Description> que contiene el XML embebido
            $descriptionNodes = $xml->xpath('//cac:Attachment/cac:ExternalReference/cbc:Description');

            if (!empty($descriptionNodes)) {
                $embeddedXmlString = (string)$descriptionNodes[0];

                // Convertir el XML embebido (factura) a otro objeto SimpleXMLElement
                $embeddedXml = new \SimpleXMLElement($embeddedXmlString);

                // Registrar namespace del QR
                $embeddedXml->registerXPathNamespace('sts', 'dian:gov:co:facturaelectronica:Structures-2-1');

                // Buscar la etiqueta <sts:QRCode>
                $qrNode = $embeddedXml->xpath('//sts:QRCode');
                $qrCodeRaw = (string) $qrNode[0];

                // Limpia saltos de línea, espacios, tabs, etc.
                $qrCodeText = preg_replace('/\s+/', '', $qrCodeRaw);

                // Extrae NitAdquiriente con regex
                if (preg_match('/NitAdquiriente=(\d+)/', $qrCodeText, $matches)) {
                    $nitAdquiriente = $matches[1];

                    if($nitAdquiriente != $fd->cliente()->nit){
                        $fd->codigo = $resolucionNumeracion->prefijo . $resolucionNumeracion->inicio;
                        $fd->emitida = 0;
                        $fd->save();

                        $resolucionNumeracion->inicio++;
                        $resolucionNumeracion->save();

                        $resolucionNumeracion->fresh();
                        $fd->fresh();
                    }
                }

                if (!empty($qrNode)) {
                    // echo nl2br((string)$qrNode[0]); // Imprime QR con saltos de línea
                }
            }
            }
        }
            return "Cambio completado";
    }

    public function validacionFacturasContratos(){
        $facturasContratos = DB::table('facturas_contratos')->get();

        foreach($facturasContratos as $fc){

            $contrato = Contrato::where('nro',$fc->contrato_nro)->first();
            if($contrato){
                DB::table('facturas_contratos')
                ->where('id',$fc->id)
                ->update([
                   'client_id' => $contrato->client_id
                ]);
            }else{
                $factura = Factura::Find($fc->factura_id);

                if($factura && isset($factura->cliente)){
                    DB::table('facturas_contratos')
                    ->where('id',$fc->id)
                    ->update([
                       'client_id' => $factura->cliente
                    ]);
                }

            }

        }

        //Revision de que facturas_contratos si pertenezcan al contrato que es
        $facturasContratos = DB::table('facturas_contratos')->get();

        foreach($facturasContratos as $fc){

            $factura = Factura::Find($fc->factura_id);
            if($factura){

                if($factura->cliente != $fc->client_id){
                    DB::table('facturas_contratos')
                    ->where('id',$fc->id)
                    ->update([
                       'client_id' => $factura->cliente
                    ]);
                }

                $contratos = Contrato::where('client_id',$factura->cliente)->get();

                $siPertenece = 0;
                foreach($contratos as $c){
                    if($c->nro == $fc->contrato_nro && $siPertenece == 0){
                        $siPertenece = 1;
                    }
                }

                if($siPertenece == 0){
                    $fc->contrato_nro = $c->nro;
                    DB::table('facturas_contratos')
                    ->where('id',$fc->id)
                    ->update([
                       'contrato_nro' => $c->nro
                    ]);
                }
            }

        }

        return "ok validaciones";
    }

        /**
     * Elimina registros específicos de factura (solo items_factura, facturas_contratos y facturas)
     * @param string $contrato_nro Número del contrato
     * @return array Resultado de la eliminación
     */
    private static function eliminarRegistrosContratoSeguro($contrato_nro) {
        $eliminados = [
            'facturas' => 0,
            'items_factura' => 0,
            'facturas_contratos' => 0
        ];

        try {
            // Obtener las facturas existentes para este contrato_nro
            $facturas_existentes = DB::table('facturas_contratos')
                ->where('contrato_nro', $contrato_nro)
                ->pluck('factura_id');

            if ($facturas_existentes->count() > 0) {
                $facturas_ids = $facturas_existentes->toArray();

                // Log::info("Iniciando eliminación para contrato {$contrato_nro}. Facturas: " . implode(',', $facturas_ids));

                // Eliminar en orden específico para evitar violaciones de integridad referencial

                // 1. Eliminar items_factura
                $eliminados['items_factura'] = DB::table('items_factura')
                    ->whereIn('factura', $facturas_ids)
                    ->delete();

                // 2. Eliminar facturas_contratos (relación)
                $eliminados['facturas_contratos'] = DB::table('facturas_contratos')
                    ->where('contrato_nro', $contrato_nro)
                    ->delete();

                // 3. Finalmente eliminar facturas
                $eliminados['facturas'] = DB::table('factura')
                    ->whereIn('id', $facturas_ids)
                    ->delete();

                Log::info("Eliminación completada para contrato {$contrato_nro}");

            } else {
                // Solo eliminar registros huérfanos en facturas_contratos
                $eliminados['facturas_contratos'] = DB::table('facturas_contratos')
                    ->where('contrato_nro', $contrato_nro)
                    ->delete();
            }

        } catch (Exception $e) {
            Log::error("Error en eliminación del contrato {$contrato_nro}: " . $e->getMessage());
            throw $e;
        }

        return $eliminados;
    }

    /**
     * Genera facturas para números de contratos específicos con precios personalizados
     * Elimina registros existentes antes de crear nuevos (versión mejorada)
     * @param array $contratos_precios Array con formato:
     *   [['contrato_nro' => '123', 'precio' => 50000], ...] o
     *   [['cedula' => '12345678', 'precio' => 50000], ...]
     * @return array Resultado con las facturas generadas
     */
    public static function generarFacturasPersonalizadas($contratos_precios) {
        $empresa = Empresa::find(1);
        $facturas_generadas = [];
        $errores = [];
        $registros_eliminados = [];

        foreach ($contratos_precios as $contrato_precio) {
            $precio = $contrato_precio['precio'];
            $contrato = null;
            $contrato_nro = null;
            $cedula = null;

            try {
                // Determinar si viene cedula o contrato_nro
                if (isset($contrato_precio['cedula'])) {
                    $cedula = $contrato_precio['cedula'];

                    // Buscar el contrato por identificación del cliente
                    $contrato = Contrato::join('contactos as c', 'c.id', '=', 'contracts.client_id')
                        ->where('c.nit', $cedula)
                        ->where('contracts.status', 1)
                        ->select('contracts.*')
                        ->first();

                    if (!$contrato) {
                        $errores[] = "No se encontró contrato activo para identificación {$cedula}";
                        continue;
                    }

                    $contrato_nro = $contrato->nro;

                } elseif (isset($contrato_precio['contrato_nro'])) {
                    $contrato_nro = $contrato_precio['contrato_nro'];

                    // Buscar el contrato por número
                    $contrato = Contrato::where('nro', $contrato_nro)->first();

                    if (!$contrato) {
                        $errores[] = "Contrato {$contrato_nro} no encontrado";
                        continue;
                    }

                } else {
                    $errores[] = "Debe proporcionar 'cedula' o 'contrato_nro'";
                    continue;
                }

                // Verificar que el contrato esté activo
                // if ($contrato->status != 1) {
                //     $errores[] = "Contrato {$contrato_nro} no está activo";
                //     continue;
                // }

                // PASO 1: Eliminar todos los registros existentes para este contrato_nro (versión segura)
                $eliminados = self::eliminarRegistrosContratoSeguro($contrato_nro);
                $registros_eliminados[] = [
                    'contrato_nro' => $contrato_nro,
                    'cedula' => $cedula,
                    'eliminados' => $eliminados
                ];

                // Log de eliminación
                Log::info("Eliminados registros del contrato {$contrato_nro} (identificación: {$cedula}): " . json_encode($eliminados));

                // PASO 2: Crear nueva factura

                // Obtener el siguiente número de factura
                $num = Factura::where('empresa', 1)->orderby('id', 'desc')->first();
                $numero = $num ? $num->nro + 1 : 1;

                // Obtener numeración
                $nro = NumeracionFactura::tipoNumeracion($contrato);

                if (!$nro) {
                    $errores[] = "No se pudo obtener numeración para contrato {$contrato_nro}";
                    continue;
                }

                // Validar que el código sea único
                $inicio = $nro->inicio;
                while (Factura::where('codigo', $nro->prefijo . $inicio)->first()) {
                    $inicio++;
                }

                // Crear la factura
                $factura = new Factura;
                $factura->nro           = $numero;
                $factura->codigo        = $nro->prefijo . $inicio;
                $factura->numeracion    = $nro->id;
                $factura->empresa       = 1;
                $factura->cliente       = $contrato->client_id;
                $factura->fecha         = Carbon::now()->format('Y-m-d');
                $factura->tipo          = 1; // Normal
                $factura->vencimiento   = "2025-07-30"; // 30 días
                $factura->suspension    = "2025-07-30";
                $factura->pago_oportuno = "2025-07-30"; // 15 días
                $factura->observaciones = 'Factura generada manualmente con precio personalizado';
                $factura->bodega        = 1;
                $factura->vendedor      = 1;
                $factura->prorrateo_aplicado = 0;
                $factura->facturacion_automatica = 1;

                if($contrato){
                    $factura->contrato_id = $contrato->id;
                }

                //validacion extra antes de guardar que no haya ningun mismo codigo.
                if(Factura::where('codigo',$factura->codigo)->count() == 0){
                    $factura->save();

                    // Crear el item de factura con el producto 348 y precio personalizado
                    $item_reg = new ItemsFactura;
                    $item_reg->factura = $factura->id;
                    $item_reg->producto = 254; // Producto fijo como solicitado
                    $item_reg->ref = 'PERSONALIZADO';
                    $item_reg->precio = $precio;
                    $item_reg->descripcion = 'Servicio personalizado - Contrato ' . $contrato_nro;
                    $item_reg->id_impuesto = 0;
                    $item_reg->impuesto = 0;
                    $item_reg->cant = 1;
                    $item_reg->desc = 0;
                    $item_reg->save();

                    // Crear la relación en facturas_contratos
                    DB::table('facturas_contratos')->insert([
                        'factura_id' => $factura->id,
                        'contrato_nro' => $contrato_nro,
                        'created_by' => 1, // Usuario manual
                        'client_id' => $contrato->client_id,
                        'is_cron' => 0, // No es automático
                        'created_at' => Carbon::now()
                    ]);

                    // Actualizar numeración
                    $nro->inicio = $inicio + 1;
                    $nro->save();

                    $facturas_generadas[] = [
                        'factura_id' => $factura->id,
                        'factura_codigo' => $factura->codigo,
                        'contrato_nro' => $contrato_nro,
                        'cedula' => $cedula,
                        'precio' => $precio,
                        'cliente_id' => $contrato->client_id
                    ];

                }else{
                    $errores[] = "Factura con código {$factura->codigo} ya existe";
                }
            } catch (Exception $e) {
                $errores[] = "Error generando factura para " .
                    ($cedula ? "identificación {$cedula}" : "contrato {$contrato_nro}") .
                    ": " . $e->getMessage();
            }
        }

        return [
            'facturas_generadas' => $facturas_generadas,
            'errores' => $errores,
            'registros_eliminados' => $registros_eliminados,
            'total_generadas' => count($facturas_generadas),
            'total_errores' => count($errores),
            'total_contratos_procesados' => count($contratos_precios)
        ];
    }

    /**
     * Ejemplo de uso del método generarFacturasPersonalizadas (actualizado)
     * Se puede llamar desde una ruta o comando
     */
    public function ejemploGenerarFacturas() {
        // Ejemplo de datos de entrada con ambos formatos
        $contratos_precios = [
            [
                'contrato_nro' => 'C123',
                'cedula' => '12345678',
                'precio' => 100
            ],
            [
                'contrato_nro' => 'C124',
                'cedula' => '87654321',
                'precio' => 200
            ]
        ];

        $resultado = self::generarFacturasPersonalizadas($contratos_precios);

        return response()->json($resultado);
    }

    /**
     * Valida y corrige una fecha si el día excede los días del mes
     * Si el día es inválido para el mes, lo ajusta al último día del mes
     *
     * @param int|string $year Año
     * @param int|string $month Mes (1-12)
     * @param int|string $day Día
     * @return string Fecha corregida en formato Y-m-d
     */
    private static function validarFechaUltimoDiaMes($year, $month, $day)
    {
        try {
            // Convertir a enteros
            $year = (int)$year;
            $month = (int)$month;
            $day = (int)$day;

            // Intentar crear la fecha con el primer día del mes para obtener el último día válido
            $fecha = Carbon::create($year, $month, 1);
            $ultimoDiaMes = $fecha->endOfMonth()->day;

            // Si el día excede el último día del mes, usar el último día
            if ($day > $ultimoDiaMes) {
                $day = $ultimoDiaMes;
            }

            // Crear y retornar la fecha corregida
            $fechaCorregida = Carbon::create($year, $month, $day);
            return $fechaCorregida->format('Y-m-d');

        } catch (\Exception $e) {
            // En caso de error, usar el último día del mes
            try {
                $fecha = Carbon::create($year, $month, 1);
                return $fecha->endOfMonth()->format('Y-m-d');
            } catch (\Exception $e2) {
                // Si aún hay error, retornar fecha actual como fallback
                return Carbon::now()->format('Y-m-d');
            }
        }
    }
}
