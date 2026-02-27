<?php

namespace App\Http\Controllers;

use App\Builders\JsonBuilders\InvoiceJsonBuilder;
use App\CamposExtra;
use App\Model\Ingresos\IngresosRetenciones;
use http\Url;
use Illuminate\Http\Request;
use App\Empresa; use App\Contacto; use App\TipoIdentificacion;
use App\Impuesto; use App\NumeracionFactura;
use App\TerminosPago; use App\Funcion; use App\Vendedor;
use App\Model\Ingresos\Factura;
use App\Model\Ingresos\ItemsFactura;
use App\Model\Ingresos\FacturaRetencion;
use App\Model\Inventario\Inventario;
use App\Model\Inventario\Bodega;
use App\Model\Inventario\ListaPrecios;
use App\Model\Inventario\ProductosBodega;
use App\Model\Ingresos\Remision;
use App\Model\Ingresos\ItemsRemision;
use App\Model\Ingresos\IngresosFactura;
use App\Model\Ingresos\NotaCreditoFactura;
use Illuminate\Support\Facades\Hash;
use Session;
use Response;
use Carbon\Carbon;
use Validator; use Illuminate\Validation\Rule; use QrCode; use File;
use App\PromesaPago;
include_once(app_path() . '/../public/PHPExcel/Classes/PHPExcel.php');
use PHPExcel;
use PHPExcel_IOFactory;
use PHPExcel_Style_Alignment;
use PHPExcel_Style_Fill;
use PHPExcel_Style_Border;
use DOMDocument;
use App\TipoEmpresa; use App\Categoria;
use App\Retencion;
use Mail; use bcrypt;
use Barryvdh\DomPDF\Facade as PDF;
use App\Contrato;
use App\GrupoCorte;
use App\Mikrotik;
include_once(app_path() .'/../public/routeros_api.class.php');
use RouterosAPI;
use App\Descuento;
use App\Campos;
use Config;
use App\ServidorCorreo;
use App\FormaPago;
use ZipArchive;
use App\Integracion;
use App\PucMovimiento; use App\Puc;
use App\Plantilla;
use App\Services\ElectronicBillingService;
use App\CRM;
use App\Instance;
use App\MovimientoLOG;
use App\NumeracionPos;
use App\Services\BTWService;
use App\Services\OnePayService;
use Facade\FlareClient\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\WhatsappMetaLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Helpers\CamposDinamicosHelper;
use App\Traits\CentralizedWhatsApp;

class FacturasController extends Controller{
    use CentralizedWhatsApp;

    protected $url;

    /**
     * Constantes para tipos de cambios registrables en facturas
     * Estas constantes se utilizan para identificar el tipo de cambio
     * al registrar logs en el sistema de auditoría
     */
    const CAMBIO_FECHA_VENCIMIENTO = 'fecha_vencimiento';
    const CAMBIO_PRECIO = 'precio';
    const CAMBIO_CLIENTE = 'cliente';
    const CAMBIO_FECHA = 'fecha';
    const PAGO_ONEPAY = 'pago_onepay';
    // Agregar más constantes según se necesiten en el futuro

    public function __construct(ElectronicBillingService $electronicBillingService){
        $this->middleware('auth');
        view()->share(['seccion' => 'facturas', 'title' => 'Factura de Venta', 'icon' =>'fas fa-plus', 'subseccion' => 'venta']);
        $this->electronicBillingService = $electronicBillingService;
    }

    /**
     * Registra un log de cambio en una factura en el sistema de auditoría
     *
     * Este método centraliza el registro de logs para cambios en facturas,
     * permitiendo un código más limpio y mantenible. Está diseñado para ser
     * extensible, facilitando la adición de nuevos tipos de cambios en el futuro.
     *
     * @param Factura $factura La factura que fue modificada
     * @param string $tipoCambio Tipo de cambio (usar constantes CAMBIO_* definidas en la clase)
     * @param mixed $valorAnterior Valor anterior del campo antes de la modificación
     * @param mixed $valorNuevo Valor nuevo del campo después de la modificación
     * @param array $opciones Opciones adicionales para personalizar el log:
     *                       - 'icono': Clase del icono FontAwesome (ej: 'fas fa-calendar-alt')
     *                       - 'color_icono': Clase de color para el icono (ej: 'text-info')
     *                       - 'titulo': Título del cambio (ej: 'Se actualizó la fecha de vencimiento')
     *                       - 'formato_anterior': Función callback para formatear el valor anterior
     *                       - 'formato_nuevo': Función callback para formatear el valor nuevo
     *                       - 'color_anterior': Clase de color para el valor anterior (ej: 'text-danger')
     *                       - 'color_nuevo': Clase de color para el valor nuevo (ej: 'text-success')
     * @return void
     *
     * @example
     * // Registrar cambio de fecha de vencimiento
     * $this->registrarLogCambioFactura(
     *     $factura,
     *     self::CAMBIO_FECHA_VENCIMIENTO,
     *     $vencimientoAnterior,
     *     $factura->vencimiento
     * );
     *
     * @example
     * // Registrar cambio personalizado
     * $this->registrarLogCambioFactura(
     *     $factura,
     *     'descuento',
     *     $descuentoAnterior,
     *     $factura->descuento,
     *     [
     *         'icono' => 'fas fa-percent',
     *         'titulo' => 'Se actualizó el descuento'
     *     ]
     * );
     */
    protected function registrarLogCambioFactura(
        Factura $factura,
        string $tipoCambio,
        $valorAnterior,
        $valorNuevo,
        array $opciones = []
    ) {
        // Si los valores son iguales, no registrar log
        if ($valorAnterior == $valorNuevo) {
            return;
        }

        // Configuración de mensajes por tipo de cambio
        // Esta estructura permite agregar fácilmente nuevos tipos de cambios
        $configuraciones = [
            self::CAMBIO_FECHA_VENCIMIENTO => [
                'icono' => 'fas fa-calendar-alt',
                'color_icono' => 'text-info',
                'titulo' => 'Se actualizó la fecha de vencimiento',
                'formato_anterior' => function($valor) {
                    return $valor ? date('d-m-Y', strtotime($valor)) : 'N/A';
                },
                'formato_nuevo' => function($valor) {
                    return $valor ? date('d-m-Y', strtotime($valor)) : 'N/A';
                },
                'color_anterior' => 'text-danger',
                'color_nuevo' => 'text-success',
            ],
            self::CAMBIO_PRECIO => [
                'icono' => 'fas fa-dollar-sign',
                'color_icono' => 'text-warning',
                'titulo' => 'Se actualizó el precio',
                'formato_anterior' => function($valor) {
                    return number_format($valor, 2, ',', '.');
                },
                'formato_nuevo' => function($valor) {
                    return number_format($valor, 2, ',', '.');
                },
                'color_anterior' => 'text-danger',
                'color_nuevo' => 'text-success',
            ],
            self::CAMBIO_CLIENTE => [
                'icono' => 'fas fa-user',
                'color_icono' => 'text-primary',
                'titulo' => 'Se actualizó el cliente',
                'formato_anterior' => function($valor) {
                    $cliente = Contacto::find($valor);
                    return $cliente ? $cliente->nombre : 'N/A';
                },
                'formato_nuevo' => function($valor) {
                    $cliente = Contacto::find($valor);
                    return $cliente ? $cliente->nombre : 'N/A';
                },
                'color_anterior' => 'text-muted',
                'color_nuevo' => 'text-primary',
            ],
            self::CAMBIO_FECHA => [
                'icono' => 'fas fa-calendar',
                'color_icono' => 'text-secondary',
                'titulo' => 'Se actualizó la fecha',
                'formato_anterior' => function($valor) {
                    return $valor ? date('d-m-Y', strtotime($valor)) : 'N/A';
                },
                'formato_nuevo' => function($valor) {
                    return $valor ? date('d-m-Y', strtotime($valor)) : 'N/A';
                },
                'color_anterior' => 'text-muted',
                'color_nuevo' => 'text-info',
            ],
        ];

        // Obtener configuración del tipo de cambio o usar valores por defecto
        // Si el tipo de cambio no está en las configuraciones, se usan las opciones
        // personalizadas o valores por defecto
        $config = $configuraciones[$tipoCambio] ?? [
            'icono' => $opciones['icono'] ?? 'fas fa-edit',
            'color_icono' => $opciones['color_icono'] ?? 'text-secondary',
            'titulo' => $opciones['titulo'] ?? 'Se actualizó un campo',
            'formato_anterior' => $opciones['formato_anterior'] ?? function($valor) {
                return $valor ?? 'N/A';
            },
            'formato_nuevo' => $opciones['formato_nuevo'] ?? function($valor) {
                return $valor ?? 'N/A';
            },
            'color_anterior' => $opciones['color_anterior'] ?? 'text-muted',
            'color_nuevo' => $opciones['color_nuevo'] ?? 'text-info',
        ];

        // Formatear valores usando las funciones de formato definidas
        $valorAnteriorFormateado = is_callable($config['formato_anterior'])
            ? $config['formato_anterior']($valorAnterior)
            : ($valorAnterior ?? 'N/A');

        $valorNuevoFormateado = is_callable($config['formato_nuevo'])
            ? $config['formato_nuevo']($valorNuevo)
            : ($valorNuevo ?? 'N/A');

        // Construir descripción del log con formato HTML
        // El formato incluye icono, título, código de factura y valores anterior/nuevo
        $descripcion = sprintf(
            '<i class="%s %s"></i> <b>%s</b> de la factura %s de <span class="%s">%s</span> a <span class="%s">%s</span>',
            $config['icono'],
            $config['color_icono'],
            $config['titulo'],
            $factura->codigo,
            $config['color_anterior'],
            $valorAnteriorFormateado,
            $config['color_nuevo'],
            $valorNuevoFormateado
        );

        // Registrar el log en la tabla log_movimientos
        $movimiento = new MovimientoLOG();
        $movimiento->contrato    = $factura->id; // ID de la factura
        $movimiento->modulo      = 8; // Módulo de facturas (según estándar del sistema)
        $movimiento->descripcion = $descripcion; // Descripción formateada con HTML
        $movimiento->created_by  = Auth::user()->id; // Usuario que realizó el cambio
        $movimiento->empresa     = $factura->empresa; // Empresa asociada
        $movimiento->save();
    }

    public function indexold(Request $request){
        $this->getAllPermissions(Auth::user()->id);
        $busqueda=false;
        $campos=array('factura.nro', 'factura.id', 'nombrecliente', 'factura.fecha', 'factura.vencimiento', 'total', 'pagado', 'porpagar', 'factura.estatus','contrato.fecha_corte', 'factura.correo');
        if (!$request->orderby) {
          $request->orderby=1; $request->order=1;
        }
        $orderby=$campos[$request->orderby];
        $order=$request->order==1?'DESC':'ASC';
        $facturas=Factura::join('contactos as c', 'factura.cliente', '=', 'c.id')
        ->join('items_factura as if', 'factura.id', '=', 'if.factura')
        ->leftJoin('contracts as cs', 'c.id', '=', 'cs.client_id')
        ->leftJoin('vendedores as v', 'factura.vendedor', '=', 'v.id')
        ->select('factura.id', 'factura.correo', 'factura.codigo', 'factura.nro', DB::raw('c.nombre as nombrecliente'), DB::raw('c.email as emailcliente'), 'factura.cliente', 'factura.fecha', 'factura.vencimiento', 'factura.estatus', 'factura.vendedor','factura.emitida', DB::raw('v.nombre as nombrevendedor'),
          DB::raw('SUM(
          (if.cant*if.precio)-(if.precio*(if(if.desc,if.desc,0)/100)*if.cant)+(if.precio-(if.precio*(if(if.desc,if.desc,0)/100)))*(if.impuesto/100)*if.cant) as total'),
          DB::raw('((Select SUM(pago) from ingresos_factura where factura=factura.id) + (Select if(SUM(valor), SUM(valor), 0) from ingresos_retenciones where factura=factura.id)) as pagado'),
          DB::raw('(SUM(
              (if.cant*if.precio)-(if.precio*(if(if.desc,if.desc,0)/100)*if.cant)+
              (if.precio-(if.precio*(if(if.desc,if.desc,0)/100)))*(if.impuesto/100)*if.cant) -
              ((Select SUM(pago) from ingresos_factura where factura=factura.id) +
              (Select if(SUM(valor), SUM(valor), 0) from ingresos_retenciones where factura=factura.id)) -
              (Select if(SUM(pago), SUM(pago), 0) from notas_factura where factura=factura.id) )    as porpagar'))
        ->where('factura.empresa',Auth::user()->empresa)->
        where('tipo','!=',5)->
        //where('factura.fecha','>=', '2021-03-01')->
        where('lectura',1);

        $appends=array('orderby'=>$request->orderby, 'order'=>$request->order);

        /*
         * Codigo buscador total, en desarrollo
         */
        if($request->has('search')){
            $busqueda              = true;
            $search                = mb_strtolower($request->input('search'));
            $filter                = '';
            switch ($request->input('search')){
                case (preg_match('/-fven/', $search) ? true : false):
                    $filter        = "ven";
                    break;
                case (preg_match('/-fvto/', $search) ? true : false):
                    $filter        = "vto";
                    break;
                case (preg_match('/-fiva/', $search) ? true : false):
                    $filter        = "iva";
                    break;
                case (preg_match('/-fpgo/', $search) ? true : false):
                    $filter        = "pgo";
                    break;
                case (preg_match('/-fppr/', $search) ? true : false):
                    $filter        = "ppr";
                    break;
            }

            if($filter)
                $search            = str_replace(' -f'.$filter, '', $search);
            if(is_numeric($request->input('search'))){
                if($filter != ''){

                    // En construcción

                }else{
                    $facturas          = $facturas->havingRaw('SUM((if.cant*if.precio)-(if.precio*(if(if.desc,if.desc,0)/100)*if.cant)+
                    (if.precio-(if.precio*(if(if.desc,if.desc,0)/100)))*(if.impuesto/100)*if.cant) > ?',
                        [$search]);
                }

            }else{

                if (preg_match('/[A-Za-z]/', $search) && preg_match('/[0-9]/', $search)){
                    $facturas      = $facturas->where('factura.codigo', 'like', '%' .$search.'%');
                }else{
                    if (strcmp($search, 'abierta') == 0 || strcmp($search, 'cerrada') == 0 || strcmp($search, 'anulada') == 0){
                        $facturas  = $facturas->whereIn('factura.estatus', $search);
                    }elseif (date('d-m-Y', strtotime($search)) == $search){

                        if(preg_match('/-vto/i', $search)){
                            $facturas  = $facturas->where('factura.vencimiento', date('Y-m-d', strtotime($search)));
                        }else{
                            $facturas  = $facturas->where('factura.fecha', date('Y-m-d', strtotime($search)));
                        }

                    }
                    else{
                        $facturas  = $facturas->where('c.nombre', 'like', '%' .$search.'%');
                    }
                }

            }
        }
        /*
         *
         */

        if ($request->name_1) {
          $busqueda=true; $appends['name_1']=$request->name_1; $facturas=$facturas->where('factura.codigo', 'like', '%' .$request->name_1.'%');
        }
        if ($request->name_2) {
          $busqueda=true; $appends['name_2']=$request->name_2; $facturas=$facturas->where('c.nombre', 'like', '%' .$request->name_2.'%');
        }
        if ($request->name_3) {
          $busqueda=true; $appends['name_3']=$request->name_3; $facturas=$facturas->where('factura.fecha', date('Y-m-d', strtotime($request->name_3)));
        }
        if ($request->name_4) {
          $busqueda=true; $appends['name_4']=$request->name_4; $facturas=$facturas->where('factura.vencimiento', date('Y-m-d', strtotime($request->name_4)));
        }
        if ($request->name_8) {
          $busqueda=true; $appends['name_8']=$request->name_8; $facturas=$facturas->whereIn('factura.estatus', $request->name_8);
        }

        if ($request->name_6) {
          $busqueda=true; $appends['name_6']=$request->name_6; $appends['name_6_simb']=$request->name_6_simb; $facturas=$facturas->whereRaw(DB::raw('((Select SUM(pago) from ingresos_factura where factura=factura.id) + (Select if(SUM(valor), SUM(valor), 0) from ingresos_retenciones where factura=factura.id)) '.$request->name_6_simb.' ?'), [$request->name_6]);
        }


        if ($request->name_9) {
          $busqueda=true; $appends['name_9']=$request->name_9; $facturas=$facturas->where('v.nombre', 'like', '%' .$request->name_9.'%');
        }


          if ($request->name_7) {
              $tmpFacturas = $facturas->groupBy('if.factura');
              $tmpFacturas = $tmpFacturas->get();
              foreach ($tmpFacturas as $tmpFactura){
                  if ($request->name_7_simb == '>'){
                      if($tmpFactura->porpagar() > $request->name_7){
                          $tmpArry[] = $tmpFactura->id;
                      }
                  }elseif($request->name_7_simb == '<'){
                      if($tmpFactura->porpagar() < $request->name_7){
                          $tmpArry[] = $tmpFactura->id;
                      }
                  }else{
                      if($tmpFactura->porpagar() == $request->name_7){
                          $tmpArry[] = $tmpFactura->id;
                      }
                  }
              }
              $facturas = $facturas->whereIn('factura.id', $tmpArry);

              $appends['name_7']=$request->name_7;
              $appends['name_7_simb']=$request->name_7_simb;

              $busqueda=true;
          }

        if ($request->name_10) {
          $busqueda = true; $appends['name_10'] = $request->name_10; $facturas = $facturas->where('cs.fecha_corte', $request->name_10);
        }

        if ($request->name_11) {
          $busqueda=true; $appends['name_11']=$request->name_11; $facturas=$facturas->where('c.nit', 'like', '%' .$request->name_11.'%');
        }

        if ($request->name_12) {
          $busqueda=true; $appends['name_12']=$request->name_12; $facturas=$facturas->where('c.direccion', 'like', '%' .$request->name_12.'%');
        }

        if ($request->name_13) {
          $busqueda = true; $appends['name_13'] = $request->name_13; $facturas = $facturas->where('cs1.server_configuration_id', $request->name_13);
        }

        if ($request->name_14) {
          $busqueda = true; $appends['name_14'] = $request->name_14; $facturas = $facturas->where('cs.ip', $request->name_14);
        }

        if ($request->name_15) {
          $busqueda = true; $appends['name_15'] = $request->name_15; $facturas = $facturas->where('cs.mac_address', $request->name_15);
        }

        $facturas=$facturas->groupBy('if.factura');


        if ($request->name_5) {
          $busqueda=true;
          $appends['name_5']=$request->name_5;
          $appends['name_5_simb']=$request->name_5_simb;
          $facturas=$facturas->havingRaw('(SUM(
          (if.cant*if.precio)-(if.precio*(if(if.desc,if.desc,0)/100)*if.cant)+
          (if.precio-(if.precio*(if(if.desc,if.desc,0)/100)))*(if.impuesto/100)*if.cant) )'
              .$request->name_5_simb.' ?', [$request->name_5]);
        }

        if ($busqueda==false) {
          $facturas=$facturas->where('factura.estatus', 1);
        }

        if(Auth::user()->id == 29){
            $facturas=$facturas->where('factura.estatus', 1);
        }

        $facturas=$facturas->OrderBy($orderby, $order)->paginate(15)->appends($appends);

        $clientes = Contacto::join('factura AS F','F.cliente','=','contactos.id')->select('contactos.id', 'contactos.nombre', 'contactos.nit')->groupBy('F.cliente')->orderBy('contactos.nombre','ASC')->get();

        view()->share(['title' => 'Facturas de Venta', 'subseccion' => 'venta']);
        return view('facturas.index')->with(compact('facturas', 'request', 'busqueda','clientes'));
    }

    public function index(Request $request){

        $user = auth()->user();
        $this->getAllPermissions($user->id);
        $empresaActual = auth()->user()->empresa;
        $empresa = Empresa::Find($user->empresa);

        $clientes = Contacto::join('factura as f', 'contactos.id', '=', 'f.cliente')->where('contactos.status', 1)->groupBy('f.cliente')->select('contactos.*')->orderBy('contactos.nombre','asc')->get();

        view()->share(['title' => 'Facturas de Venta', 'subseccion' => 'venta', 'precice' => true]);
        $tipo = false;

        $userServer = $user->servidores->pluck('id')->toArray();
        $servidores = Mikrotik::where('empresa', $empresaActual)->whereIn('id',$userServer)->get();

        $tabla = Campos::join('campos_usuarios', 'campos_usuarios.id_campo', '=', 'campos.id')
        ->where('campos_usuarios.id_modulo', 4)
        ->where('campos_usuarios.id_usuario', Auth::user()->id)
        ->where('campos_usuarios.estado', 1)
        ->orderBy('campos_usuarios.orden', 'ASC')->get();

        $municipios = DB::table('municipios')->orderBy('nombre', 'asc')->get();
        $barrios = DB::table('barrios')->orderBy('nombre', 'asc')->get();
        $grupos_corte = GrupoCorte::where('empresa', $empresaActual)->where('status',1)->get();

        return view('facturas.indexnew', compact('clientes','tipo','tabla','municipios','servidores','barrios','grupos_corte','empresa'));
    }

    public function indexNew(Request $request, $tipo){
        $this->getAllPermissions(Auth::user()->id);
        $empresaActual = auth()->user()->empresa;

        $clientes = Contacto::join('factura as f', 'contactos.id', '=', 'f.cliente')->where('contactos.status', 1)->groupBy('f.cliente')->select('contactos.*')->orderBy('contactos.nombre','asc')->get();

        view()->share(['title' => 'Facturas de Venta', 'subseccion' => 'venta', 'precice' => true]);
        $tipo = false;
        $servidores = Mikrotik::where('empresa', $empresaActual)->get();
        $tabla = Campos::join('campos_usuarios', 'campos_usuarios.id_campo', '=', 'campos.id')->where('campos_usuarios.id_modulo', 4)->where('campos_usuarios.id_usuario', Auth::user()->id)->where('campos_usuarios.estado', 1)->orderBy('campos_usuarios.orden', 'ASC')->get();
        $municipios = DB::table('municipios')->orderBy('nombre', 'asc')->get();
        $barrios = DB::table('barrios')->orderBy('nombre', 'asc')->get();
        $grupos_corte = GrupoCorte::where('empresa', $empresaActual)->where('status',1)->get();
        $empresa = Empresa::Find(auth()->user()->id);

        return view('facturas.indexnew', compact('clientes','tipo','tabla','municipios', 'servidores','barrios','grupos_corte','empresa'));
    }

    /*
    * Tabla principal de facturación electrónica.
    */
    public function index_electronica(){

        $user = auth()->user();
        $this->getAllPermissions($user->id);
        $empresaActual = $user->empresa;

        $clientes = Contacto::join('factura as f', 'contactos.id', '=', 'f.cliente')->where('contactos.status', 1)->groupBy('f.cliente')->select('contactos.*')->orderBy('contactos.nombre','asc')->get();
        $municipios = DB::table('municipios')->orderBy('nombre', 'asc')->get();
        $barrios = DB::table('barrios')->orderBy('nombre', 'asc')->get();
        $tabla = Campos::join('campos_usuarios', 'campos_usuarios.id_campo', '=', 'campos.id')
        ->where('campos_usuarios.id_modulo', 4)->where('campos_usuarios.id_usuario', $user->id)
        ->where('campos_usuarios.estado', 1)
        ->orderBy('campos_usuarios.orden', 'ASC')->get();

        $userServer = $user->servidores->pluck('id')->toArray();
        $servidores = Mikrotik::where('empresa', $empresaActual)->whereIn('id',$userServer)->get();
        $grupos_corte = GrupoCorte::where('empresa', $empresaActual)->where('status',1)->get();
        $empresa = Empresa::Find($user->empresa);

        $numeracionActual = NumeracionFactura::
        where('nomina',0)->where('num_equivalente',0)
        ->where('preferida',1)
        ->where('estado',1)
        ->where('tipo',2)
        ->first();

        if(!$numeracionActual){
            return redirect()->route('configuracion.numeraciones_dian')->with('error', 'No hay una numeración de factura electrónica activa. Por favor configure una para continuar.');
        }

        $prefijo = $numeracionActual->prefijo;   // FE
        $inicio  = (int) $numeracionActual->inicioverdadero; // 567
        $final   = (int) $numeracionActual->final;  // 10000

        // 2. Consultar todas las facturas con ese prefijo
        $facturas = Factura::where('codigo', 'like', $prefijo . '%')
        ->where('tipo',2)
        ->pluck('codigo')
        ->toArray();

        // 3. Extraer solo el número
        $usados = array_map(function($codigo) use ($prefijo) {
            return (int) str_replace($prefijo, '', $codigo);
        }, $facturas);

        sort($usados);

        // 4. Buscar el último consecutivo usado
        $ultimoUsado = !empty($usados) ? max($usados) : $inicio - 1;

        // 5. Generar todos los posibles consecutivos hasta el último usado
        $todos = range($inicio, $ultimoUsado);

        // 6. Calcular los faltantes
        $faltantes = array_diff($todos, $usados);

        $reporteFaltantes =  [
            'prefijo' => $prefijo,
            'inicio' => $inicio,
            'final' => $final,
            'ultimo_usado' => $ultimoUsado,
            'faltantes' => array_values($faltantes),
        ];



        try {

        $dianFecthSync = DB::table('factura')
                                ->selectRaw("GROUP_CONCAT(CONCAT(codigo, ' - (', fecha, ')') SEPARATOR ', ') as alertas")
                                ->where('dian_response', 'like', '%409%')
                                ->whereIn('estatus', [0, 2])
                                ->where('statusdian', 1)
                                ->where('dian_service', 0)
                                ->where('emitida', 0)
                                ->whereRaw('MONTH(fecha) = MONTH(CURDATE())')
                                ->whereRaw('YEAR(fecha) = YEAR(CURDATE())')
                                ->orderByDesc('id')
                                ->limit(10)
                                ->value('alertas');

        }catch (\Throwable $e) {

                $dianFecthSync = '';

                \Log::error("Error consultando facturas con 409: " . $e->getMessage());

        }



        view()->share(['title' => 'Facturas de Venta Electrónica', 'subseccion' => 'venta-electronica']);
        return view('facturas-electronica.index', compact('clientes', 'municipios', 'tabla','servidores','barrios','grupos_corte','empresa','reporteFaltantes', 'dianFecthSync'));
    }

    /*
    * Método que obtiene una colección de facturas por medio de oracle Datatable.
    */
    public function facturas_electronica(Request $request){

        $user = auth()->user();
        $modoLectura = $user->modo_lectura();
        $identificadorEmpresa = $user->empresa;
        $moneda = $user->empresa()->moneda;

        $empresa = Empresa::Find($identificadorEmpresa);

        $orderByDefault = null;
        $orderDefault = null;

        if ($request->order && is_array($request->order)) {
            foreach ($request->order as $or) {
                if (isset($or['column']) && $or['column'] == '0') {
                    $orderByDefault = 'id';
                    $orderDefault = $or['dir'];
                }
            }
        }

        //consulta nueva
        $facturas = Factura::query()
        ->join('contactos as c', 'factura.cliente', '=', 'c.id')
        ->join('empresas as em', 'em.id', '=', 'factura.empresa')
        ->join('items_factura as if', 'factura.id', '=', 'if.factura')
        ->leftJoin('vendedores as v', 'factura.vendedor', '=', 'v.id')
        ->leftJoin('barrios as barrio','barrio.id','c.barrio_id')
        ->leftJoin(
            DB::raw('
                (SELECT factura_id, contrato_nro
                 FROM (
                     SELECT fc.factura_id, fc.contrato_nro, ROW_NUMBER() OVER (PARTITION BY fc.factura_id ORDER BY fc.id ASC) AS rn
                     FROM facturas_contratos fc
                 ) ranked
                 WHERE ranked.rn = 1
                ) as fc
            '),
            'factura.id', '=', 'fc.factura_id'
        )
        ->leftJoin('contracts as cs1', 'cs1.nro', '=', 'fc.contrato_nro')
        ->leftJoin('contracts as cs2', 'cs2.id', '=', 'factura.contrato_id')
        ->leftJoin('mikrotik as mk', 'mk.id', '=', 'cs1.server_configuration_id')
        ->select(
            'barrio.nombre as barrio',
            'mk.nombre as servidor',
            'cs1.server_configuration_id',
            'cs1.opciones_dian',
            'cs1.address_street as direccion',
            'cs1.nro as contrato',
            'cs1.servicio_tv',
            'c.email as emailcliente',
            'c.celular as celularcliente',
            'c.nombre as nombrecliente',
            'c.nit as nitcliente',
            'c.apellido1 as ape1cliente',
            'c.apellido2 as ape2cliente',
            'factura.tipo',
            'factura.cliente',
            'factura.emitida',
            'factura.promesa_pago',
            'factura.id',
            'factura.correo',
            'factura.mensaje',
            'factura.estatus',
            'factura.codigo',
            'factura.fecha',
            'factura.vencimiento',
            'factura.siigo_id',
            'factura.siigo_name',
            'em.api_key_siigo as api_key_siigo',
            DB::raw('v.nombre as nombrevendedor'),
              DB::raw('
            SUM((if.cant * if.precio) - (if.precio * (if(if.desc, if.desc, 0) / 100) * if.cant) + (if.precio - (if.precio * (if(if.desc, if.desc, 0) / 100))) * (if.impuesto / 100) * if.cant) as total
        '),
                DB::raw('
            ((SELECT SUM(pago) FROM ingresos_factura WHERE factura = factura.id) +
            (SELECT IF(SUM(valor), SUM(valor), 0) FROM ingresos_retenciones WHERE factura = factura.id)) as pagado
        '),
                DB::raw('
            (SUM((if.cant * if.precio) - (if.precio * (if(if.desc, if.desc, 0) / 100) * if.cant) + (if.precio - (if.precio * (if(if.desc, if.desc, 0) / 100)) * (if.impuesto / 100) * if.cant)) -
            ((SELECT SUM(pago) FROM ingresos_factura WHERE factura = factura.id) +
            (SELECT IF(SUM(valor), SUM(valor), 0) FROM ingresos_retenciones WHERE factura = factura.id)) -
            (SELECT IF(SUM(pago), SUM(pago), 0) FROM notas_factura WHERE factura = factura.id)) as porpagar
        ')
        )
        ->selectRaw("CAST(REGEXP_REPLACE(factura.codigo, '[^0-9]', '') AS UNSIGNED) as codigo_numerico")
        ->where('tipo','!=',3)
        ->groupBy('factura.id')
        ->orderByRaw("CAST(REGEXP_REPLACE(factura.codigo, '[^0-9]', '') AS UNSIGNED) DESC");

        if ($user->servidores->count() > 0) {
            $servers = $user->servidores->pluck('id')->toArray();

            $facturas->where(function ($query) use ($servers) {
                $query
                    // Caso 1: Contrato asociado con servidor del usuario
                    ->where(function ($q) use ($servers) {
                        $q->whereIn('cs1.server_configuration_id', $servers)
                          ->orWhereIn('cs2.server_configuration_id', $servers);
                    })
                    // Caso 2: Contrato NO en servidores del usuario, pero con servicio TV
                    ->orWhere(function ($q) use ($servers) {
                        $q->where(function ($notInServers) use ($servers) {
                            $notInServers->where(function ($sub) use ($servers) {
                                $sub->whereNotIn('cs1.server_configuration_id', $servers)
                                    ->orWhereNull('cs1.server_configuration_id');
                            })->where(function ($sub2) use ($servers) {
                                $sub2->whereNotIn('cs2.server_configuration_id', $servers)
                                     ->orWhereNull('cs2.server_configuration_id');
                            });
                        })->where(function ($hasTv) {
                            $hasTv->whereNotNull('cs1.servicio_tv')
                                  ->orWhereNotNull('cs2.servicio_tv');
                        });
                    })
                    // Caso 3: Factura sin contrato asociado
                    ->orWhere(function ($q) {
                        $q->whereNull('cs1.id')->whereNull('cs2.id');
                    });
            });
        }

        if ($request->filtro == true) {

            if($request->codigo){
                $facturas->where(function ($query) use ($request) {
                    $query->orWhere('factura.codigo', 'like', "%{$request->codigo}%");
                });
            }
            if($request->cliente){
                $facturas->where(function ($query) use ($request) {
                    $query->orWhere('factura.cliente', $request->cliente);
                });
            }
            if($request->corte){
                $facturas->where(function ($query) use ($request) {
                    $query->orWhere('cs1.fecha_corte', $request->corte);
                });
            }
            if($request->fact_siigo){
                if(in_array('1', $request->fact_siigo) && in_array('0', $request->fact_siigo)){
                    // Si selecciona ambos, no filtrar (mostrar todas)
                } elseif(in_array('1', $request->fact_siigo)){
                    // Solo "Sí": facturas con siigo_id (ya se enviaron a Siigo)
                    $facturas->whereNotNull('factura.siigo_id');
                } elseif(in_array('0', $request->fact_siigo)){
                    // Solo "No": facturas sin siigo_id (no se han enviado a Siigo)
                    $facturas->whereNull('factura.siigo_id');
                }
            }
            if($request->creacion){
                $facturas->where(function ($query) use ($request) {
                    $query->orWhere('factura.fecha', $request->creacion);
                });
            }
            if($request->vencimiento){
                $facturas->where(function ($query) use ($request) {
                    $query->orWhere('factura.vencimiento', $request->vencimiento);
                });
            }
            if($request->prorrateo){
                if($request->prorrateo == '1'){
                    $facturas->where('factura.prorrateo_aplicado', 1);
                }else{
                    $facturas->where(function ($query) {
                        $query->where('factura.prorrateo_aplicado', 0)->orWhereNull('factura.prorrateo_aplicado');
                    });
                }
            }
            if($request->estado){
                $facturas->where(function ($query) use ($request) {
                    $query->orWhere('factura.estatus', $request->estado);
                });
            }
            if($request->correo){
                $correo = ($request->correo == 'A') ? 0 : $request->correo;
                $facturas->where(function ($query) use ($request, $correo) {
                    $query->orWhere('factura.correo', $correo);
                });
            }
            if($request->municipio){
                $facturas->where(function ($query) use ($request) {
                    $query->orWhere('c.fk_idmunicipio', $request->municipio);
                });
            }
            if($request->servidor){
                $facturas->where(function ($query) use ($request) {
                    $query->orWhere('cs1.server_configuration_id', $request->servidor);
            });
            }
            if($request->grupos_corte){
                $facturas->where(function ($query) use ($request) {
                    $query->orWhereIn('cs1.grupo_corte', $request->grupos_corte);
                });
            }
            if($request->emision != null){
                $facturas->where(function ($query) use ($request) {
                    if($request->emision == 1){
                        $query->orWhere('factura.emitida', $request->emision);
                    }else if($request->emision == 0){
                        $query->orWhere('factura.emitida', 0)->where('factura.dian_response',null);
                    }
                    else{
                        $query->orWhere('factura.emitida', 0)->whereIn('factura.dian_response',[409,504,401]);
                    }
                });
            }
            // Filtro por rango de fechas (desde - hasta)
            if ($request->desde) {
                $facturas->where('factura.fecha', '>=', $request->desde);
            }
            if ($request->hasta) {
                $facturas->where('factura.fecha', '<=', $request->hasta);
            }
            // Filtro de otras opciones
            if ($request->otras_opciones == 'ultimas_contratos') {
                // Obtener las facturas más recientes de cada contrato desde facturas_contratos
                $ultimasFacturasIds = DB::table('facturas_contratos as fc1')
                    ->select(DB::raw('MAX(fc1.factura_id) as factura_id'))
                    ->whereIn('fc1.factura_id', function($query) use ($identificadorEmpresa) {
                        $query->select('id')
                            ->from('factura')
                            ->where('empresa', $identificadorEmpresa)
                            ->where('tipo', 2)
                            ->where('lectura', 1);
                    })
                    ->groupBy('fc1.contrato_nro')
                    ->pluck('factura_id')
                    ->toArray();

                if (!empty($ultimasFacturasIds)) {
                    $facturas->whereIn('factura.id', $ultimasFacturasIds);
                } else {
                    // Si no hay facturas, no mostrar ninguna
                    $facturas->where('factura.id', '=', 0);
                }
            }
            // Filtro de clientes con más de 1 factura
            if ($request->otras_opciones == 'clientes_multiples_facturas') {
                // Obtener clientes con más de 1 factura en el rango de fechas
                $clientesMultiplesIds = DB::table('factura')
                    ->select('cliente')
                    ->where('empresa', $identificadorEmpresa)
                    ->where('tipo', 2)
                    ->where('lectura', 1);

                // Aplicar filtro de fechas si existe
                if ($request->desde) {
                    $clientesMultiplesIds->where('fecha', '>=', $request->desde);
                }
                if ($request->hasta) {
                    $clientesMultiplesIds->where('fecha', '<=', $request->hasta);
                }

                $clientesMultiplesIds = $clientesMultiplesIds
                    ->groupBy('cliente')
                    ->havingRaw('COUNT(*) >= 2')
                    ->pluck('cliente')
                    ->toArray();

                if (!empty($clientesMultiplesIds)) {
                    $facturas->whereIn('factura.cliente', $clientesMultiplesIds);
                } else {
                    // Si no hay clientes con múltiples facturas, no mostrar ninguna
                    $facturas->where('factura.id', '=', 0);
                }
            }
        } else {
            // Si no hay filtros aplicados, aplicar rango por defecto (2025-2026)
            $facturas->where(function ($query) {
                $query->whereBetween('factura.fecha', ['2025-01-01', '2026-12-31']);
            });
        }

        $facturas->where('factura.empresa', $identificadorEmpresa);
        $facturas->where('factura.tipo', 2)->where('factura.lectura',1);

        if(Auth::user()->empresa()->oficina){
            if(auth()->user()->oficina){
                $facturas->where('cs1.oficina', auth()->user()->oficina);
            }
        }

        if ($orderByDefault) {
            $facturas->orderby($orderByDefault, $orderDefault);
        }

        return datatables()->eloquent($facturas)
        ->editColumn('codigo', function (Factura $factura) {
            if($factura->porpagar() == 0 && $factura->estatus == 1){
                $factura->estatus = 0;
                $factura->save();
            }
            return $factura->id ? "<a href=" . route('facturas.show', $factura->id) . ">$factura->codigo</a>" : "";
        })
        ->editColumn('cliente', function (Factura $factura) {
            return  $factura->cliente ? "<a href=" . route('contactos.show', $factura->cliente) . ">{$factura->nombrecliente} {$factura->ape1cliente} {$factura->ape2cliente}</a>" : "";
        })
        ->editColumn('direccion', function (Factura $factura) {
            return  ($factura->direccion)?$factura->direccion:$factura->direccioncliente;
        })
        ->editColumn('fecha', function (Factura $factura) {
            return date('d-m-Y', strtotime($factura->fecha));
        })
        ->editColumn('vencimiento', function (Factura $factura) {
            return (date('Y-m-d') > $factura->vencimiento && $factura->estatus == 1) ? '<span class="text-danger">' . date('d-m-Y', strtotime($factura->vencimiento)) . '</span>' : date('d-m-Y', strtotime($factura->vencimiento));
        })
        ->addColumn('total', function (Factura $factura) use ($moneda) {
            return "{$moneda} {$factura->parsear($factura->total()->total)}";
        })
        ->addColumn('impuesto', function (Factura $factura) use ($moneda) {
            return "{$moneda} {$factura->parsear($factura->impuestos_totales())}";
        })
        ->addColumn('pagado', function (Factura $factura) use ($moneda) {
            return "{$moneda} {$factura->parsear($factura->pagado)}";
        })
        ->addColumn('pendiente', function (Factura $factura) use ($moneda) {
            return "{$moneda} {$factura->parsear($factura->porpagar())}";
        })
        ->addColumn('contrato', function (Factura $factura)  {
            if($factura->contratos() != false){
                return $factura->contratos()->first()->contrato_nro;
            }else return "n/a";
        })
        ->addColumn('estado', function (Factura $factura) {
            $msj = '';
            if (Auth::user()->empresaObj->estado_dian == 1) {
                if($factura->emitida == 1){
                    $msj = '- Emitida';
                }
                else if($factura->emitida == 0 && $factura->dian_response != 409 && $factura->dian_response != 504){
                    $msj = '- No Emitida';
                }
                else if($factura->emitida == 0 && $factura->dian_response == 409 || $factura->emitida == 0 && $factura->dian_response == 504){
                    $msj = '- Error';
                }
            }

            return   '<span class="text-' . $factura->estatus(true) . '">' . $factura->estatus() . ' ' . $msj . '</span>';
        })
        ->editColumn('nitcliente', function (Factura $factura) {
            return  $factura->cliente ? "<a href=" . route('contactos.show', $factura->cliente) . ">{$factura->cliente()->tip_iden('mini')} {$factura->nitcliente}</a>" : "";
        })
        ->addColumn('acciones', function ($factura) use ($empresa) {
            return view('facturas.acciones-facturas', compact('factura', 'empresa'));
        })
        ->rawColumns(['codigo', 'cliente', 'nitcliente', 'estado', 'acciones', 'vencimiento'])
        ->toJson();
    }

    /**
     * Método optimizado para listar facturas estándar
     * Optimizaciones aplicadas:
     * 1. Reemplazo de subconsultas correlacionadas por LEFT JOINs (ingresos_factura, ingresos_retenciones, notas_factura)
     * 2. Simplificación de WHEREs innecesarios con funciones anidadas
     * 3. Optimización del ORDER BY usando campo calculado en lugar de función en cada fila
     * 4. Uso de valores calculados en la consulta principal en lugar de métodos que hacen consultas adicionales
     * 5. Mejora en el uso de IFNULL en lugar de IF anidados
     */
    public function facturas(Request $request){

        $user = auth()->user();
        $modoLectura = $user->modo_lectura();
        $identificadorEmpresa = $user->empresa;
        $moneda = $user->empresa()->moneda;

        $orderByDefault = null;
        $orderDefault = null;

        $empresa = Empresa::select(
            'id',
            'moneda',
            'estado_dian',
            'tirilla',
            'form_fe',
            'estado_dian',
            'technicalkey',
            'proveedor',
            'oficina',
            'codigo'
        )
        ->where('id', auth()->user()->empresa)
        ->first();

        if ($request->order && is_array($request->order)) {
            foreach ($request->order as $or) {
                if (isset($or['column']) && $or['column'] == '0') {
                    $orderByDefault = 'id';
                    $orderDefault = $or['dir'];
                }
            }
        }

        // Consulta optimizada - Estrategia: Consulta base más simple con agregaciones optimizadas
        $facturas = Factura::query()
        ->join('contactos as c', 'factura.cliente', '=', 'c.id')
        ->join('empresas as em', 'em.id', '=', 'factura.empresa')
        ->join('items_factura as if', 'factura.id', '=', 'if.factura')
        ->leftJoin('vendedores as v', 'factura.vendedor', '=', 'v.id')
        ->leftJoin('barrios as barrio','barrio.id','c.barrio_id')
        // Optimización: Subconsulta de contratos simplificada
        ->leftJoin(
            DB::raw('(
                SELECT fc1.factura_id, fc1.contrato_nro
                FROM facturas_contratos fc1
                INNER JOIN (
                    SELECT factura_id, MIN(id) as min_id
                    FROM facturas_contratos
                    GROUP BY factura_id
                ) fc2 ON fc1.factura_id = fc2.factura_id AND fc1.id = fc2.min_id
            ) as fc'),
            'factura.id', '=', 'fc.factura_id'
        )
        ->leftJoin('contracts as cs1', 'cs1.nro', '=', 'fc.contrato_nro')
        ->leftJoin('contracts as cs2', 'cs2.id', '=', 'factura.contrato_id')
        ->leftJoin('mikrotik as mk', 'mk.id', '=', 'cs1.server_configuration_id')
        // Optimización: Agregaciones más eficientes usando COALESCE directamente
        ->leftJoin(DB::raw('(SELECT factura, COALESCE(SUM(pago), 0) as total_pago FROM ingresos_factura GROUP BY factura) as ing_fact'), 'ing_fact.factura', '=', 'factura.id')
        ->leftJoin(DB::raw('(SELECT factura, COALESCE(SUM(valor), 0) as total_retencion FROM ingresos_retenciones GROUP BY factura) as ing_ret'), 'ing_ret.factura', '=', 'factura.id')
        ->leftJoin(DB::raw('(SELECT factura, COALESCE(SUM(pago), 0) as total_nota FROM notas_factura GROUP BY factura) as notas_fact'), 'notas_fact.factura', '=', 'factura.id')
        ->select(
            'barrio.nombre as barrio',
            'mk.nombre as servidor',
            'cs1.server_configuration_id',
            'cs1.opciones_dian',
            'cs1.servicio_tv',
            'cs1.address_street as direccion',
            'cs1.nro as contrato',
            'c.email as emailcliente',
            'c.nit as nitcliente',
            'c.celular as celularcliente',
            'c.nombre as nombrecliente',
            'c.apellido1 as ape1cliente',
            'c.apellido2 as ape2cliente',
            DB::raw('c.direccion as direccioncliente'),
            'factura.tipo',
            'factura.cliente',
            'factura.emitida',
            'factura.promesa_pago',
            'factura.id',
            'factura.correo',
            'factura.mensaje',
            'factura.estatus',
            'factura.codigo',
            'factura.fecha',
            'factura.siigo_id',
            'factura.siigo_name',
            'factura.vencimiento',
            'em.api_key_siigo as api_key_siigo',
            DB::raw('v.nombre as nombrevendedor'),
            // Cálculo del total optimizado
            DB::raw('
                SUM((if.cant * if.precio) - (if.precio * (IFNULL(if.desc, 0) / 100) * if.cant) +
                (if.precio - (if.precio * (IFNULL(if.desc, 0) / 100))) * (if.impuesto / 100) * if.cant) as total
            '),
            // Optimización: Usar los JOINs en lugar de subconsultas correlacionadas
            DB::raw('
                COALESCE(ing_fact.total_pago, 0) + COALESCE(ing_ret.total_retencion, 0) as pagado
            '),
            DB::raw('
                (SUM((if.cant * if.precio) - (if.precio * (IFNULL(if.desc, 0) / 100) * if.cant) +
                (if.precio - (if.precio * (IFNULL(if.desc, 0) / 100))) * (if.impuesto / 100) * if.cant)) -
                (COALESCE(ing_fact.total_pago, 0) + COALESCE(ing_ret.total_retencion, 0)) -
                COALESCE(notas_fact.total_nota, 0) as porpagar
            ')
        )
        // Optimización: Calcular codigo_numerico una vez y usarlo para ordenar
        ->selectRaw("CAST(REGEXP_REPLACE(factura.codigo, '[^0-9]', '') AS UNSIGNED) as codigo_numerico")
        ->where('factura.tipo','!=',3)
        ->groupBy('factura.id')
        ->orderBy('codigo_numerico', 'DESC')
        ->orderBy('factura.id', 'DESC');

        if ($user->servidores->count() > 0) {
            $servers = $user->servidores->pluck('id')->toArray();

            $facturas->where(function ($query) use ($servers) {
                $query
                    // Caso 1: Contrato asociado con servidor del usuario
                    ->where(function ($q) use ($servers) {
                        $q->whereIn('cs1.server_configuration_id', $servers)
                          ->orWhereIn('cs2.server_configuration_id', $servers);
                    })
                    // Caso 2: Contrato NO en servidores del usuario, pero con servicio TV
                    ->orWhere(function ($q) use ($servers) {
                        $q->where(function ($notInServers) use ($servers) {
                            $notInServers->where(function ($sub) use ($servers) {
                                $sub->whereNotIn('cs1.server_configuration_id', $servers)
                                    ->orWhereNull('cs1.server_configuration_id');
                            })->where(function ($sub2) use ($servers) {
                                $sub2->whereNotIn('cs2.server_configuration_id', $servers)
                                     ->orWhereNull('cs2.server_configuration_id');
                            });
                        })->where(function ($hasTv) {
                            $hasTv->whereNotNull('cs1.servicio_tv')
                                  ->orWhereNotNull('cs2.servicio_tv');
                        });
                    })
                    // Caso 3: Factura sin contrato asociado
                    ->orWhere(function ($q) {
                        $q->whereNull('cs1.id')->whereNull('cs2.id');
                    });
            });
        }

        if ($request->filtro == true) {
            // Optimización: Eliminar funciones anidadas innecesarias cuando solo hay una condición
            if($request->codigo){
                $facturas->where('factura.codigo', 'like', "%{$request->codigo}%");
            }
            if($request->cliente){
                $facturas->where('factura.cliente', $request->cliente);
            }
            if($request->fact_siigo){
                if(in_array('1', $request->fact_siigo) && in_array('0', $request->fact_siigo)){
                    // Si selecciona ambos, no filtrar (mostrar todas)
                } elseif(in_array('1', $request->fact_siigo)){
                    // Solo "Sí": facturas con siigo_id (ya se enviaron a Siigo)
                    $facturas->whereNotNull('factura.siigo_id');
                } elseif(in_array('0', $request->fact_siigo)){
                    // Solo "No": facturas sin siigo_id (no se han enviado a Siigo)
                    $facturas->whereNull('factura.siigo_id');
                }
            }
            if($request->corte){
                $facturas->where('cs1.fecha_corte', $request->corte);
            }
            if($request->creacion){
                $facturas->where('factura.fecha', $request->creacion);
            }
            if($request->prorrateo){
                if($request->prorrateo == '1'){
                    $facturas->where('factura.prorrateo_aplicado', 1);
                }else{
                    $facturas->where(function ($query) {
                        $query->where('factura.prorrateo_aplicado', 0)->orWhereNull('factura.prorrateo_aplicado');
                    });
                }
            }
            if($request->vencimiento){
                $facturas->where('factura.vencimiento', $request->vencimiento);
            }
            if($request->estado){
                $status = ($request->estado == 'A') ? 0 : $request->estado;
                $facturas->where('factura.estatus', $status);
            }else{
                $facturas->where('factura.estatus', 1);
            }
            if($request->correo){
                $correo = ($request->correo == 'A') ? 0 : $request->correo;
                $facturas->where('factura.correo', $correo);
            }
            if($request->servidor){
                $facturas->where('cs1.server_configuration_id', $request->servidor);
            }
            if($request->state_contrato){
                $facturas->where('cs1.state', $request->state_contrato);
            }
            if($request->grupos_corte){
                $facturas->whereIn('cs1.grupo_corte', $request->grupos_corte);
            }
            if($request->municipio){
                $facturas->where('c.fk_idmunicipio', $request->municipio);
            }
            if ($request->barrio) {
                $facturas->where('c.barrio_id', $request->barrio);
            }
            // Filtro por tipo de facturación del contrato
            if ($request->tipo_facturacion && is_array($request->tipo_facturacion) && count($request->tipo_facturacion) > 0) {
                $facturas->where(function ($query) use ($request) {
                    $query->whereIn('cs1.facturacion', $request->tipo_facturacion)
                          ->orWhereIn('cs2.facturacion', $request->tipo_facturacion);
                });
            }
            // Filtro por rango de fechas (desde - hasta)
            if ($request->desde) {
                $facturas->where('factura.fecha', '>=', $request->desde);
            }
            if ($request->hasta) {
                $facturas->where('factura.fecha', '<=', $request->hasta);
            }
            // Filtro de otras opciones
            if ($request->otras_opciones == 'ultimas_contratos') {
                // Obtener las facturas más recientes de cada contrato desde facturas_contratos
                $ultimasFacturasIds = DB::table('facturas_contratos as fc1')
                    ->select(DB::raw('MAX(fc1.factura_id) as factura_id'))
                    ->whereIn('fc1.factura_id', function($query) use ($identificadorEmpresa) {
                        $query->select('id')
                            ->from('factura')
                            ->where('empresa', $identificadorEmpresa)
                            ->where('tipo', '!=', 2)
                            ->where('tipo', '!=', 5)
                            ->where('tipo', '!=', 6)
                            ->where('lectura', 1);
                    })
                    ->groupBy('fc1.contrato_nro')
                    ->pluck('factura_id')
                    ->toArray();

                if (!empty($ultimasFacturasIds)) {
                    $facturas->whereIn('factura.id', $ultimasFacturasIds);
                } else {
                    // Si no hay facturas, no mostrar ninguna
                    $facturas->where('factura.id', '=', 0);
                }
            }
            // Filtro de clientes con más de 1 factura
            if ($request->otras_opciones == 'clientes_multiples_facturas') {
                // Obtener clientes con más de 1 factura en el rango de fechas
                $clientesMultiplesIds = DB::table('factura')
                    ->select('cliente')
                    ->where('empresa', $identificadorEmpresa)
                    ->where('tipo', '!=', 2)
                    ->where('tipo', '!=', 5)
                    ->where('tipo', '!=', 6)
                    ->where('lectura', 1);

                // Aplicar filtro de fechas si existe
                if ($request->desde) {
                    $clientesMultiplesIds->where('fecha', '>=', $request->desde);
                }
                if ($request->hasta) {
                    $clientesMultiplesIds->where('fecha', '<=', $request->hasta);
                }

                $clientesMultiplesIds = $clientesMultiplesIds
                    ->groupBy('cliente')
                    ->havingRaw('COUNT(*) >= 2')
                    ->pluck('cliente')
                    ->toArray();

                if (!empty($clientesMultiplesIds)) {
                    $facturas->whereIn('factura.cliente', $clientesMultiplesIds);
                } else {
                    // Si no hay clientes con múltiples facturas, no mostrar ninguna
                    $facturas->where('factura.id', '=', 0);
                }
            }
        } else {
            // Si no hay filtros aplicados, aplicar rango por defecto (2025-2026)
            $facturas->where(function ($query) {
                $query->whereBetween('factura.fecha', ['2025-01-01', '2026-12-31']);
            });
        }


        if(auth()->user()->rol == 8){
            // Verificar si el usuario tipo 8 tiene el permiso 862
            $tienePermiso862 = DB::table('permisos_usuarios')
                ->where('id_usuario', auth()->user()->id)
                ->where('id_permiso', 862)
                ->exists();

            // Si no tiene el permiso y no hay filtros aplicados, no mostrar registros
            if (!$tienePermiso862 && !$request->filtros_aplicados) {
                $facturas->where('factura.id', '=', 0); // Condición que no retorna registros
            }
        }

        $facturas->where('factura.empresa', $identificadorEmpresa);
        $facturas->where('factura.tipo', '!=', 2)->where('factura.tipo', '!=', 5)->where('factura.tipo', '!=', 6)
                 ->where('factura.lectura',1);

        if($empresa->oficina){
            if(auth()->user()->oficina){
                $facturas->where('cs1.oficina', auth()->user()->oficina);
            }
        }

        if ($orderByDefault) {
            $facturas->orderby($orderByDefault, $orderDefault);
        }

        // Optimización crítica: Construir una consulta de conteo que replique todas las condiciones
        // pero de manera más eficiente, contando solo los IDs únicos después del GROUP BY
        $countQuery = Factura::query()
            ->join('items_factura as if', 'factura.id', '=', 'if.factura')
            ->leftJoin('contactos as c', 'factura.cliente', '=', 'c.id')
            ->leftJoin(
                DB::raw('(
                    SELECT fc1.factura_id, fc1.contrato_nro
                    FROM facturas_contratos fc1
                    INNER JOIN (
                        SELECT factura_id, MIN(id) as min_id
                        FROM facturas_contratos
                        GROUP BY factura_id
                    ) fc2 ON fc1.factura_id = fc2.factura_id AND fc1.id = fc2.min_id
                ) as fc'),
                'factura.id', '=', 'fc.factura_id'
            )
            ->leftJoin('contracts as cs1', 'cs1.nro', '=', 'fc.contrato_nro')
            ->leftJoin('contracts as cs2', 'cs2.id', '=', 'factura.contrato_id')
            ->where('factura.empresa', $identificadorEmpresa)
            ->where('factura.tipo', '!=', 2)
            ->where('factura.tipo', '!=', 3)
            ->where('factura.tipo', '!=', 5)
            ->where('factura.tipo', '!=', 6)
            ->where('factura.lectura', 1)
            ->groupBy('factura.id');

        // Aplicar los mismos filtros de servidores
        if ($user->servidores->count() > 0) {
            $servers = $user->servidores->pluck('id')->toArray();
            $countQuery->where(function ($query) use ($servers) {
                $query
                    ->where(function ($q) use ($servers) {
                        $q->whereIn('cs1.server_configuration_id', $servers)
                          ->orWhereIn('cs2.server_configuration_id', $servers);
                    })
                    ->orWhere(function ($q) use ($servers) {
                        $q->where(function ($notInServers) use ($servers) {
                            $notInServers->where(function ($sub) use ($servers) {
                                $sub->whereNotIn('cs1.server_configuration_id', $servers)
                                    ->orWhereNull('cs1.server_configuration_id');
                            })->where(function ($sub2) use ($servers) {
                                $sub2->whereNotIn('cs2.server_configuration_id', $servers)
                                     ->orWhereNull('cs2.server_configuration_id');
                            });
                        })->where(function ($hasTv) {
                            $hasTv->whereNotNull('cs1.servicio_tv')
                                  ->orWhereNotNull('cs2.servicio_tv');
                        });
                    })
                    ->orWhere(function ($q) {
                        $q->whereNull('cs1.id')->whereNull('cs2.id');
                    });
            });
        }

        // Aplicar los mismos filtros de la consulta principal
        if ($request->filtro == true) {
            if($request->codigo){
                $countQuery->where('factura.codigo', 'like', "%{$request->codigo}%");
            }
            if($request->cliente){
                $countQuery->where('factura.cliente', $request->cliente);
            }
            if($request->fact_siigo){
                if(in_array('1', $request->fact_siigo) && in_array('0', $request->fact_siigo)){
                    // Si selecciona ambos, no filtrar (mostrar todas)
                } elseif(in_array('1', $request->fact_siigo)){
                    // Solo "Sí": facturas con siigo_id (ya se enviaron a Siigo)
                    $countQuery->whereNotNull('factura.siigo_id');
                } elseif(in_array('0', $request->fact_siigo)){
                    // Solo "No": facturas sin siigo_id (no se han enviado a Siigo)
                    $countQuery->whereNull('factura.siigo_id');
                }
            }
            if($request->corte){
                $countQuery->where('cs1.fecha_corte', $request->corte);
            }
            if($request->creacion){
                $countQuery->where('factura.fecha', $request->creacion);
            }
            if($request->prorrateo){
                if($request->prorrateo == '1'){
                    $countQuery->where('factura.prorrateo_aplicado', 1);
                }else{
                    $countQuery->where(function ($query) {
                        $query->where('factura.prorrateo_aplicado', 0)->orWhereNull('factura.prorrateo_aplicado');
                    });
                }
            }
            if($request->vencimiento){
                $countQuery->where('factura.vencimiento', $request->vencimiento);
            }
            if($request->estado){
                $status = ($request->estado == 'A') ? 0 : $request->estado;
                $countQuery->where('factura.estatus', $status);
            }else{
                $countQuery->where('factura.estatus', 1);
            }
            if($request->correo){
                $correo = ($request->correo == 'A') ? 0 : $request->correo;
                $countQuery->where('factura.correo', $correo);
            }
            if($request->servidor){
                $countQuery->where('cs1.server_configuration_id', $request->servidor);
            }
            if($request->state_contrato){
                $countQuery->where('cs1.state', $request->state_contrato);
            }
            if($request->grupos_corte){
                $countQuery->whereIn('cs1.grupo_corte', $request->grupos_corte);
            }
            if($request->municipio){
                $countQuery->where('c.fk_idmunicipio', $request->municipio);
            }
            if ($request->barrio) {
                $countQuery->where('c.barrio_id', $request->barrio);
            }
            // Filtro por tipo de facturación del contrato
            if ($request->tipo_facturacion && is_array($request->tipo_facturacion) && count($request->tipo_facturacion) > 0) {
                $countQuery->where(function ($query) use ($request) {
                    $query->whereIn('cs1.facturacion', $request->tipo_facturacion)
                          ->orWhereIn('cs2.facturacion', $request->tipo_facturacion);
                });
            }
            if ($request->desde) {
                $countQuery->where('factura.fecha', '>=', $request->desde);
            }
            if ($request->hasta) {
                $countQuery->where('factura.fecha', '<=', $request->hasta);
            }
            // Filtro de otras opciones
            if ($request->otras_opciones == 'ultimas_contratos') {
                // Obtener las facturas más recientes de cada contrato desde facturas_contratos
                $ultimasFacturasIds = DB::table('facturas_contratos as fc1')
                    ->select(DB::raw('MAX(fc1.factura_id) as factura_id'))
                    ->whereIn('fc1.factura_id', function($query) use ($identificadorEmpresa) {
                        $query->select('id')
                            ->from('factura')
                            ->where('empresa', $identificadorEmpresa)
                            ->where('tipo', '!=', 2)
                            ->where('tipo', '!=', 5)
                            ->where('tipo', '!=', 6)
                            ->where('lectura', 1);
                    })
                    ->groupBy('fc1.contrato_nro')
                    ->pluck('factura_id')
                    ->toArray();

                if (!empty($ultimasFacturasIds)) {
                    $countQuery->whereIn('factura.id', $ultimasFacturasIds);
                } else {
                    // Si no hay facturas, no mostrar ninguna
                    $countQuery->where('factura.id', '=', 0);
                }
            }
            // Filtro de clientes con más de 1 factura
            if ($request->otras_opciones == 'clientes_multiples_facturas') {
                // Obtener clientes con más de 1 factura en el rango de fechas
                $clientesMultiplesIds = DB::table('factura')
                    ->select('cliente')
                    ->where('empresa', $identificadorEmpresa)
                    ->where('tipo', '!=', 2)
                    ->where('tipo', '!=', 5)
                    ->where('tipo', '!=', 6)
                    ->where('lectura', 1);

                // Aplicar filtro de fechas si existe
                if ($request->desde) {
                    $clientesMultiplesIds->where('fecha', '>=', $request->desde);
                }
                if ($request->hasta) {
                    $clientesMultiplesIds->where('fecha', '<=', $request->hasta);
                }

                $clientesMultiplesIds = $clientesMultiplesIds
                    ->groupBy('cliente')
                    ->havingRaw('COUNT(*) >= 2')
                    ->pluck('cliente')
                    ->toArray();

                if (!empty($clientesMultiplesIds)) {
                    $countQuery->whereIn('factura.cliente', $clientesMultiplesIds);
                } else {
                    // Si no hay clientes con múltiples facturas, no mostrar ninguna
                    $countQuery->where('factura.id', '=', 0);
                }
            }
        } else {
            // Si no hay filtros aplicados, aplicar rango por defecto (2025-2026)
            $countQuery->where(function ($query) {
                $query->whereBetween('factura.fecha', ['2025-01-01', '2026-12-31']);
            });
        }

        // Aplicar filtro de rol 8
        if(auth()->user()->rol == 8){
            $tienePermiso862 = DB::table('permisos_usuarios')
                ->where('id_usuario', auth()->user()->id)
                ->where('id_permiso', 862)
                ->exists();
            if (!$tienePermiso862 && !$request->filtros_aplicados) {
                $countQuery->where('factura.id', '=', 0);
            }
        }

        // Aplicar filtro de oficina
        if($empresa->oficina){
            if(auth()->user()->oficina){
                $countQuery->where('cs1.oficina', auth()->user()->oficina);
            }
        }

        // Contar los IDs únicos después del GROUP BY
        $totalRecords = DB::table(DB::raw("({$countQuery->select('factura.id')->toSql()}) as facturas_count"))
            ->mergeBindings($countQuery->getQuery())
            ->count();

        return datatables()->eloquent($facturas)
        ->setTotalRecords($totalRecords)
        ->editColumn('codigo', function ($factura) {
            // Optimización: Usar el valor calculado porpagar en lugar de llamar al método
            $porpagar = $factura->porpagar ?? 0;
            if($porpagar == 0 && $factura->estatus == 1){
                // Actualizar en batch para mejor rendimiento
                DB::table('factura')->where('id', $factura->id)->update(['estatus' => 0]);
                $factura->estatus = 0;
            }
            return $factura->id ? "<a href=" . route('facturas.show', $factura->id) . ">$factura->codigo</a>" : "";
        })
        ->editColumn('cliente', function ($factura) {
            return  $factura->cliente ? "<a href=" . route('contactos.show', $factura->cliente) . ">{$factura->nombrecliente} {$factura->ape1cliente} {$factura->ape2cliente}</a>" : "";
        })
        ->editColumn('direccion', function ($factura) {
            return  ($factura->direccion)?$factura->direccion:$factura->direccioncliente;
        })
        ->editColumn('fecha', function ($factura) {
            return $factura->fecha ? date('d-m-Y', strtotime($factura->fecha)) : '';
        })
        ->editColumn('vencimiento', function ($factura) {
            if(!$factura->vencimiento) return '';
            $vencimientoFormateado = date('d-m-Y', strtotime($factura->vencimiento));
            return (date('Y-m-d') > $factura->vencimiento && $factura->estatus == 1)
                ? '<span class="text-danger">' . $vencimientoFormateado . '</span>'
                : $vencimientoFormateado;
        })
        // Optimización: Usar valores calculados directamente de la consulta cuando estén disponibles
        ->addColumn('total', function ($factura) use ($moneda) {
            // Intentar usar el valor calculado, si no está disponible usar el método
            if(isset($factura->total)){
                return "{$moneda} {$factura->parsear($factura->total)}";
            }
            // Fallback al método original si el valor calculado no está disponible
            return "{$moneda} {$factura->parsear($factura->total()->total)}";
        })
        ->addColumn('impuesto', function ($factura) use ($moneda) {
            // impuestos_totales no está en la consulta, mantener método
            return "{$moneda} {$factura->parsear($factura->impuestos_totales())}";
        })
        ->addColumn('pagado', function ($factura) use ($moneda) {
            // Intentar usar el valor calculado, si no está disponible usar el método
            if(isset($factura->pagado)){
                return "{$moneda} {$factura->parsear($factura->pagado)}";
            }
            // Fallback al método original
            return "{$moneda} {$factura->parsear($factura->pagado)}";
        })
        ->addColumn('pendiente', function ($factura) use ($moneda) {
            // Intentar usar el valor calculado, si no está disponible usar el método
            if(isset($factura->porpagar)){
                return "{$moneda} {$factura->parsear($factura->porpagar)}";
            }
            // Fallback al método original
            return "{$moneda} {$factura->parsear($factura->porpagar())}";
        })
        ->addColumn('contrato', function ($factura)  {
            // Optimización: Usar el valor ya calculado en la consulta
            return $factura->contrato ?? "n/a";
        })
        ->addColumn('estado', function ($factura) {
            return   '<span class="text-' . $factura->estatus(true) . '">' . $factura->estatus() . '</span>';
        })
        ->editColumn('nitcliente', function ($factura) {
            if(!$factura->cliente) return '';
            // Optimización: Usar el método cliente() del modelo que ya está optimizado
            $cliente = $factura->cliente();
            $tipIden = $cliente ? $cliente->tip_iden('mini') : '';
            return "<a href=" . route('contactos.show', $factura->cliente) . ">{$tipIden} {$factura->nitcliente}</a>";
        })
        ->addColumn('acciones', function ($factura) use ($empresa) {
            return view('facturas.acciones-facturas', compact('factura', 'empresa'));
        })
        ->rawColumns(['codigo', 'cliente', 'nitcliente', 'estado', 'acciones', 'vencimiento'])
        ->toJson();
    }

    public function create($producto=false, $cliente=false){
        $this->getAllPermissions(Auth::user()->id);
        //echo $cliente;die;
        $empresa =Auth::user()->empresaObj;
        $nro=NumeracionFactura::where('empresa',$empresa->id)->where('preferida',1)->where('estado',1)->where('tipo',1)->first();

        $tipo_documento = Factura::where('empresa',$empresa->id)->latest('tipo')->first();

        //obtiene las formas de pago relacionadas con este modulo (Facturas)
        $relaciones = FormaPago::where('relacion',1)->orWhere('relacion',3)->get();

        if (!$nro) {
            $mensaje='Debes crear una numeración para facturas de venta preferida';
            return redirect('empresa/configuracion/numeraciones')->with('error', $mensaje);
        }
        if ($nro->inicio==$nro->final) {
            $nro->estado=0;
            $nro->save();
            $mensaje='Debes crear una numeración para facturas de venta preferida';
            return redirect('empresa/configuracion/numeraciones')->with('error', $mensaje);
        }
        if ($nro->hasta) {
            if ($nro->hasta<date('Y-m-d')) {
                $nro->estado=0;
                $nro->save();
                $mensaje='Debes crear una numeración para facturas de venta preferida';
                return redirect('empresa/configuracion/numeraciones')->with('error', $mensaje);
            }
        }
        //se obtiene la fecha de hoy
        $fecha = date('d-m-Y');

        $bodega = Bodega::where('empresa',$empresa->id)->where('status', 1)->first();
        if (!$bodega) {
            $inventario = collect();
        } else {
            $inventario = Inventario::select('inventario.id','inventario.tipo_producto','inventario.producto','inventario.ref',
                DB::raw('COALESCE(MAX(productos_bodegas.nro), 0) as nro'))
                ->leftJoin('productos_bodegas', function($join) use ($bodega) {
                    $join->on('productos_bodegas.producto', '=', 'inventario.id')
                         ->where('productos_bodegas.bodega', '=', $bodega->id);
                })
                ->where('inventario.empresa', $empresa->id)
                ->where('inventario.status', 1)
                ->where(function($query) use ($bodega) {
                    $query->where('inventario.tipo_producto', '!=', 1)
                          ->orWhereIn('inventario.id', function($subquery) use ($bodega) {
                              $subquery->select('producto')
                                      ->from('productos_bodegas')
                                      ->where('bodega', $bodega->id);
                          });
                })
                ->groupBy('inventario.id', 'inventario.tipo_producto', 'inventario.producto', 'inventario.ref')
                ->orderBy('inventario.producto','ASC')
                ->get();
        }
        $extras = CamposExtra::where('empresa',$empresa->id)->where('status', 1)->get();
        $bodegas = Bodega::where('empresa',$empresa->id)->where('status', 1)->get();
        //$clientes = Contacto::where('empresa',$empresa->id)->whereIn('tipo_contacto',[0,2])->where('status',1)->orderBy('nombre','asc')->get();
        $clientes = (Auth::user()->oficina && Auth::user()->empresa()->oficina) ? Contacto::whereIn('tipo_contacto', [0,2])->where('status', 1)->where('empresa', $empresa->id)->where('oficina', Auth::user()->oficina)->orderBy('nombre', 'ASC')->get() : Contacto::whereIn('tipo_contacto', [0,2])->where('status', 1)->where('empresa', $empresa->id)->orderBy('nombre', 'ASC')->get();
        $numeraciones=NumeracionFactura::where('empresa',$empresa->id)->get();
        $vendedores = Vendedor::where('empresa',$empresa->id)->where('estado',1)->get();
        $listas = ListaPrecios::where('empresa',$empresa->id)->where('status', 1)->get();
        $terminos=TerminosPago::where('empresa',$empresa->id)->get();
        $impuestos = Impuesto::where('empresa',$empresa->id)->orWhere('empresa', null)->Where('estado', 1)->get();

        //Datos necesarios para hacer funcionar la ventana modal
        $dataPro = (new InventarioController)->create();
        $medidas2 = $dataPro->medidas;
        $unidades2 = $dataPro->unidades;
        $extras2 = $dataPro->extras;
        $listas2 = $dataPro->listas;
        $bodegas2 = $dataPro->bodegas;
        $categorias = Puc::where('empresa',auth()->user()->empresa)
         ->whereRaw('length(codigo) >= 6')
         ->get();
        $identificaciones=TipoIdentificacion::all();
        //$vendedores = Vendedor::where('empresa',$empresa->id)->where('estado', 1)->get();
        //$listas = ListaPrecios::where('empresa',$empresa->id)->where('status', 1)->get();
        $tipos_empresa=TipoEmpresa::where('empresa',$empresa->id)->get();
        $prefijos=DB::table('prefijos_telefonicos')->get();
        // /Datos necesarios para hacer funcionar la ventana modal
        $retenciones = Retencion::where('empresa',$empresa->id)->where('modulo',1)->get();
        view()->share(['icon' =>'', 'title' => 'Nueva Facturas de Venta', 'subseccion' => 'venta']);

        $title = "Nueva Factura de Venta";
        $seccion = "facturas";
        $subseccion = "venta";

        return view('facturas.create')->with(compact('clientes', 'tipo_documento',
            'inventario', 'numeraciones', 'nro','vendedores', 'terminos', 'impuestos',
            'cliente', 'bodegas', 'listas', 'producto', 'fecha', 'retenciones',
            'categorias', 'identificaciones', 'tipos_empresa', 'prefijos', 'medidas2',
            'unidades2', 'extras2', 'listas2','bodegas2','title','seccion','subseccion',
            'extras','relaciones','empresa'));
    }

    public function create_electronica($producto=false, $cliente=false){
        $this->getAllPermissions(Auth::user()->id);
        //echo $cliente;die;
        $nro=NumeracionFactura::where('empresa',Auth::user()->empresa)->where('preferida',1)->where('estado',1)->where('tipo',2)->first();
        $tipo_documento = Factura::where('empresa',Auth::user()->empresa)->latest('tipo')->first();
        $empresa =Auth::user()->empresaObj;

        //obtiene las formas de pago relacionadas con este modulo (Facturas)
        $relaciones = FormaPago::where('relacion',1)->orWhere('relacion',3)->get();

        if (!$nro) {
            $mensaje='Debes crear una numeración para facturas de venta preferida';
            return redirect('empresa/configuracion/numeraciones/dian')->with('error', $mensaje);
        }
        if ($nro->inicio==$nro->final) {
            $nro->estado=0;
            $nro->save();
            $mensaje='Debes crear una numeración para facturas de venta preferida';
            return redirect('empresa/configuracion/numeraciones/dian')->with('error', $mensaje);
        }
        if ($nro->hasta) {
            if ($nro->hasta<date('Y-m-d')) {
                $nro->estado=0;
                $nro->save();
                $mensaje='Debes crear una numeración para facturas de venta preferida';
                return redirect('empresa/configuracion/numeraciones/dian')->with('error', $mensaje);
            }
        }
        //se obtiene la fecha de hoy
        $fecha = date('d-m-Y');

        $bodega = Bodega::where('empresa',$empresa->id)->where('status', 1)->first();
        if (!$bodega) {
            $inventario = collect();
        } else {
            $inventario = Inventario::select('inventario.id','inventario.tipo_producto','inventario.producto','inventario.ref',
                DB::raw('COALESCE(MAX(productos_bodegas.nro), 0) as nro'))
                ->leftJoin('productos_bodegas', function($join) use ($bodega) {
                    $join->on('productos_bodegas.producto', '=', 'inventario.id')
                         ->where('productos_bodegas.bodega', '=', $bodega->id);
                })
                ->where('inventario.empresa', $empresa->id)
                ->where('inventario.status', 1)
                ->where(function($query) use ($bodega) {
                    $query->where('inventario.tipo_producto', '!=', 1)
                          ->orWhereIn('inventario.id', function($subquery) use ($bodega) {
                              $subquery->select('producto')
                                      ->from('productos_bodegas')
                                      ->where('bodega', $bodega->id);
                          });
                })
                ->groupBy('inventario.id', 'inventario.tipo_producto', 'inventario.producto', 'inventario.ref')
                ->orderBy('inventario.producto','ASC')
                ->get();
        }

        $extras = CamposExtra::where('empresa',$empresa->id)->where('status', 1)->get();
        $bodegas = Bodega::where('empresa',$empresa->id)->where('status', 1)->get();
        //$clientes = Contacto::where('empresa',$empresa->id)->whereIn('tipo_contacto',[0,2])->where('status',1)->orderBy('nombre','asc')->get();
        $clientes = (Auth::user()->oficina && Auth::user()->empresa()->oficina) ? Contacto::whereIn('tipo_contacto', [0,2])->where('status', 1)->where('empresa', $empresa->id)->where('oficina', Auth::user()->oficina)->orderBy('nombre', 'ASC')->get() : Contacto::whereIn('tipo_contacto', [0,2])->where('status', 1)->where('empresa', $empresa->id)->orderBy('nombre', 'ASC')->get();
        $numeraciones=NumeracionFactura::where('empresa',$empresa->id)->get();
        $vendedores = Vendedor::where('empresa',$empresa->id)->where('estado',1)->get();
        $listas = ListaPrecios::where('empresa',$empresa->id)->where('status', 1)->get();
        $terminos=TerminosPago::where('empresa',$empresa->id)->get();
        $impuestos = Impuesto::where('empresa',$empresa->id)->orWhere('empresa', null)->Where('estado', 1)->get();

        //Datos necesarios para hacer funcionar la ventana modal
        $dataPro = (new InventarioController)->create();
        $medidas2 = $dataPro->medidas;
        $unidades2 = $dataPro->unidades;
        $extras2 = $dataPro->extras;
        $listas2 = $dataPro->listas;
        $bodegas2 = $dataPro->bodegas;
        $categorias=Categoria::where('empresa',$empresa->id)->orWhere('empresa', 1)->whereNull('asociado')->get();
        $identificaciones=TipoIdentificacion::all();
        //$vendedores = Vendedor::where('empresa',$empresa->id)->where('estado', 1)->get();
        //$listas = ListaPrecios::where('empresa',$empresa->id)->where('status', 1)->get();
        $tipos_empresa=TipoEmpresa::where('empresa',$empresa->id)->get();
        $prefijos=DB::table('prefijos_telefonicos')->get();
        // /Datos necesarios para hacer funcionar la ventana modal

        $retenciones = Retencion::where('empresa',$empresa->id)->where('modulo',1)->get();
        view()->share(['icon' =>'', 'title' => 'Nueva Factura Electrónica', 'subseccion' => 'venta-electronica']);

        $title = "Nueva Factura Electrónica";
        $seccion = "facturas";
        $subseccion = "venta-electronica";

        return view('facturas-electronica.create')->with(compact('empresa','clientes', 'tipo_documento', 'inventario', 'numeraciones', 'nro','vendedores', 'terminos', 'impuestos','cliente', 'bodegas', 'listas', 'producto', 'fecha', 'retenciones','categorias', 'identificaciones', 'tipos_empresa', 'prefijos', 'medidas2','unidades2', 'extras2', 'listas2','bodegas2','title','seccion','subseccion','extras','relaciones'));
    }

    public function create_cliente($cliente){
        return $this->create(false, $cliente);
    }

    public function create_item($item){
        $inventario =Inventario::where('id',$item)->where('empresa',Auth::user()->empresa)->first();
        if ($inventario) {
            return $this->create($inventario, false);
        }
        abort(404);
    }

    public function remisionAfactura($nroR,$producto=false, $cliente=false){
        $this->getAllPermissions(Auth::user()->id);
        $remision = Remision::where('remisiones.empresa',Auth::user()->empresa)->where('remisiones.nro',$nroR)->first();
        $itemsRemision = ItemsRemision::where('items_remision.remision', $remision->id)->get();
        $nro=NumeracionFactura::where('empresa',Auth::user()->empresa)->where('preferida',1)->where('estado',1)->first();
        if (!$nro) {
            $mensaje='Debes crear una numeración para facturas de venta preferida';
            return redirect('empresa/configuracion/numeraciones')->with('error', $mensaje);
        }
        if ($nro->inicio==$nro->final) {
            $nro->estado=0;
            $nro->save();
            $mensaje='Debes crear una numeración para facturas de venta preferida';
            return redirect('empresa/configuracion/numeraciones')->with('error', $mensaje);
        }
        if ($nro->hasta) {
            if ($nro->hasta<date('Y-m-d')) {
                $nro->estado=0;
                $nro->save();
                $mensaje='Debes crear una numeración para facturas de venta preferida';
                return redirect('empresa/configuracion/numeraciones')->with('error', $mensaje);
            }
        }

        //se obtiene la fecha de hoy
        $fecha = date('d-m-Y');

        $bodega = Bodega::where('empresa',Auth::user()->empresa)->where('status', 1)->first();
        $inventario = Inventario::select('inventario.*', DB::raw('(Select nro from productos_bodegas where bodega='.$bodega->id.' and producto=inventario.id) as nro'))->where('empresa',Auth::user()->empresa)->where('status', 1)->havingRaw('if(inventario.tipo_producto=1, id in (Select producto from productos_bodegas where bodega='.$bodega->id.'), true)')->get();
        $bodegas = Bodega::where('empresa',Auth::user()->empresa)->where('status', 1)->get();
        $clientes = Contacto::where('empresa',Auth::user()->empresa)->whereIn('tipo_contacto',[0,2])->get();
        $numeraciones=NumeracionFactura::where('empresa',Auth::user()->empresa)->get();
        $vendedores = Vendedor::where('empresa',Auth::user()->empresa)->where('estado',1)->get();
        $listas = ListaPrecios::where('empresa',Auth::user()->empresa)->where('status', 1)->get();
        $terminos=TerminosPago::where('empresa',Auth::user()->empresa)->get();
        $impuestos = Impuesto::where('empresa',Auth::user()->empresa)->orWhere('empresa', null)->Where('estado', 1)->get();

        //Datos necesarios para hacer funcionar la ventana modal
        $dataPro = (new InventarioController)->create();
        $medidas2 = $dataPro->medidas;
        $unidades2 = $dataPro->unidades;
        $extras2 = $dataPro->extras;
        $listas2 = $dataPro->listas;
        $bodegas2 = $dataPro->bodegas;
        $categorias=Categoria::where('empresa',Auth::user()->empresa)->orWhere('empresa', 1)->whereNull('asociado')->get();
        $identificaciones=TipoIdentificacion::all();
        $tipos_empresa=TipoEmpresa::where('empresa',Auth::user()->empresa)->get();
        $prefijos=DB::table('prefijos_telefonicos')->get();
        $extras = CamposExtra::where('empresa',Auth::user()->empresa)->where('status', 1)->get();
        // /Datos necesarios para hacer funcionar la ventana modal

        $retenciones = Retencion::where('empresa',Auth::user()->empresa)->where('modulo',1)->get();
        view()->share(['icon' =>'', 'title' => 'Nueva Facturas de Venta', 'subseccion' => 'venta']);
        return view('facturas.facturaRemision')->with(compact('clientes', 'inventario', 'numeraciones', 'nro','vendedores', 'terminos', 'impuestos', 'cliente', 'bodegas', 'listas', 'producto', 'fecha', 'retenciones','categorias', 'identificaciones', 'tipos_empresa', 'prefijos', 'medidas2', 'unidades2', 'extras2', 'listas2','bodegas2','remision','itemsRemision', 'extras'));
    }

  /**
  * Registrar una nueva factura
  * Si hay items inventariable resta los valores al inventario
  * @param Request $request
  * @return redirect
  */
    public function store(Request $request){

        $request->validate([
            'vendedor' => 'required',
        ]);

        // return $request->all();

        $user = Auth::user();
        $nro = false;
        $contrato = false;
        $num = Factura::where('empresa',1)->orderby('nro','asc')->get()->last();

        //Nota: En conclusion si no es electrónica, se debe seleccionar un contrato. De lo contrario si se puede crear sin contrato.
        if(!isset($request->electronica)){
            $nro=NumeracionFactura::where('empresa',$user->empresa)->where('preferida',1)->where('estado',1)->where('tipo',1)->first();

            if($request->contratos_json != ''){
                $contrato = Contrato::where('id', $request->contratos_json)->first();
            }else{
                // Verificar si el cliente tiene factura_est_elec = 1 para permitir crear sin contrato
                $cliente = Contacto::where('id', $request->cliente)->where('empresa', $user->empresa)->first();
                if(!$cliente || $cliente->factura_est_elec != 1){
                    $mensaje='Debes seleccionar un contrato para el tipo de facturacion estandar.';
                    return redirect('empresa/configuracion/numeraciones')->with('error', $mensaje);
                }
            }

        }else{

            $nro=NumeracionFactura::where('empresa',$user->empresa)->where('preferida',1)->where('estado',1)->where('tipo',2)->first();
            if(!$nro){
                $mensaje='Debes crear una numeración para facturas de venta preferida';
                return redirect('empresa/configuracion/numeraciones')->with('error', $mensaje);
            }

        }

        //Actualiza el nro de inicio para la numeracion seleccionada
        $inicio = $nro->inicio;
        $codigoEditado = $request->codigo_editado;

        // Si hay un código editado, validarlo y usarlo
        if ($codigoEditado && !empty($codigoEditado)) {
            // Validar que el código no exista
            $existe = Factura::where('numeracion', $nro->id)
                ->where('codigo', $codigoEditado)
                ->exists();

            if ($existe) {
                $mensaje = 'El código editado ya existe en otra factura.';
                return redirect()->back()->with('error', $mensaje)->withInput();
            }

            $codigoFinal = $codigoEditado;
        } else {
            // Validacion para que solo asigne numero consecutivo si no existe.
            while (Factura::where('codigo',$nro->prefijo.$inicio)->first()) {
                $nro->save();
                $inicio=$nro->inicio;
                $nro->inicio += 1;
            }
            $codigoFinal = $nro->prefijo.$inicio;
        }

        if($request->nro_remision){
            DB::table('remisiones')->where('nro', $request->nro_remision)->update(['estatus' => 3]);
        }

        //Generacion de llave unica para acceso por correo
        $key = Hash::make(date("H:i:s"));
        $toReplace = array('/', '$','.');
        $key = str_replace($toReplace, "", $key);

        if($num){
            $numero = $num->nro + 1;
        }else{
            $numero = 1;
        }

        $tipo = 1; //1= normal, 2=Electrónica.

        // Retorna si un cliente puede crear factura electrónica o no.
        $electronica = Factura::booleanFacturaElectronica($request->cliente);

        if($contrato){
            if($contrato->facturacion == 3 && !$electronica){
                return redirect('empresa/facturas/facturas_electronica')->with('success', "La Factura Electrónica no pudo ser creada por que no ha pasado el tiempo suficiente desde la ultima factura");
            }elseif($contrato->facturacion == 3 && $electronica){
                $tipo = 2;
                $request->documento = $tipo;
            }
        }

        if(isset($request->electronica)){$tipo = 2;}

        //Si el tipo de documento es cuenta de cobro sigue su proceso normal.
        if($request->documento != 3){
            $request->documento = $tipo;
        }

        $factura = new Factura;
        $factura->nonkey = $key;
        $factura->nro = $numero;
        $factura->codigo = $codigoFinal;
        $factura->numeracion=$nro->id;
        $factura->plazo=$request->plazo;
        $factura->term_cond=$request->term_cond;
        $factura->facnotas=$request->notas;
        $factura->empresa=$user->empresa;
        $factura->cliente=$request->cliente;
        $factura->tipo=$tipo;
        $factura->fecha=Carbon::parse($request->fecha)->format('Y-m-d');
        $factura->vencimiento= Carbon::parse($request->vencimiento)->format('Y-m-d');
        $factura->suspension= Carbon::parse($request->vencimiento)->format('Y-m-d');
        $factura->pago_oportuno = date('Y-m-d', strtotime("+".($request->plazo-1)." days", strtotime($request->fecha)));
        $factura->observaciones=mb_strtolower($request->observaciones);
        $factura->vendedor=$request->vendedor;
        $factura->lista_precios=$request->lista_precios;
        $factura->bodega=$request->bodega;
        $factura->nro_remision = $request->nro_remision;
        $factura->tipo_operacion = $request->tipo_operacion;
        $factura->ordencompra    = $request->ordencompra;
        $factura->periodo_facturacion = $request->periodo_facturacion;
        $factura->created_by = $user->id;
        $factura->ordenservicio = $request->ordenservicio;
        $factura->factura_mes_manual = isset($request->factura_mes_manual) ? $request->factura_mes_manual : 0;
        $factura->periodo_cobrado_text = isset($request->periodo_cobrado_text) ? $request->periodo_cobrado_text : '';

        if($contrato){
            $factura->contrato_id = $contrato->id;
        }

        $factura->save();

        // Relacionar contrato con la factura una vez exista el ID de la factura
        if($contrato){
            DB::table('facturas_contratos')->insert([
                'factura_id' => $factura->id,
                'contrato_nro' => $contrato->nro,
                'created_by' => $user->id,
                'client_id' => $factura->cliente,
                'is_cron' => 0,
                'created_at' => Carbon::now()
            ]);
        }
        $nro->save();

        //Asociamos los contratos asociados a la factura.
        if(isset($request->contratos_asociados)){

            $contratosArray = explode(',',$request->contratos_asociados);
            for($i = 0 ; $i < count($contratosArray); $i++){

                //Validamos que no ingrese dos veces el mismo contrato.
                // Si hay contrato principal, solo guardamos si es diferente al principal
                // Si NO hay contrato principal, guardamos todos los contratos asociados
                $debeGuardar = false;
                if($contrato){
                    // Hay contrato principal: solo guardar si es diferente
                    if($contratosArray[$i] != $contrato->nro){
                        $debeGuardar = true;
                    }
                } else {
                    // No hay contrato principal: guardar todos los contratos asociados
                    $debeGuardar = true;
                }

                if($debeGuardar){
                    DB::table('facturas_contratos')->insert([
                        'factura_id' => $factura->id,
                        'contrato_nro' => $contratosArray[$i],
                        'created_by' => $user->id,
                        'client_id' => $factura->cliente,
                        'is_cron' => 0,
                        'created_at' => Carbon::now()
                    ]);
                }
            }
        }


        $bodega = Bodega::where('empresa',Auth::user()->empresa)->where('status', 1)->where('id', $request->bodega)->first();
        if (!$bodega) { //Si el valor seleccionado para bodega no existe, tomara la primera activa registrada
            $bodega = Bodega::where('empresa',Auth::user()->empresa)->where('status', 1)->first();
        }
        //Ciclo para registrar los itemas de la factura
        for ($i=0; $i < count($request->ref) ; $i++) {
            $impuesto = Impuesto::where('id', $request->impuesto[$i])->first();
            if($impuesto){
                $impuesto->porcentaje = $impuesto->porcentaje;
            }else{
                $impuesto->porcentaje = '';
            }
            $producto = Inventario::where('id', $request->item[$i])->first();
            //Si el producto es inventariable y existe esa bodega, restará el valor registrado
            if ($producto->tipo_producto==1) {
                $ajuste=ProductosBodega::where('empresa', Auth::user()->empresa)->where('bodega', $bodega->id)->where('producto', $producto->id)->first();
                if ($ajuste) {
                    $ajuste->nro-=$request->cant[$i];
                    $ajuste->save();
                }
            }
            $items = new ItemsFactura;
            $items->factura=$factura->id;
            $items->producto=$request->item[$i];
            $items->ref=$request->ref[$i];
            $items->precio=$request->precio[$i];
            $items->descripcion=$request->descripcion[$i];
            $items->id_impuesto=$request->impuesto[$i];
            $items->impuesto=$impuesto->porcentaje;
            $items->cant=$request->cant[$i];
            //$items->desc=$request->desc[$i];
            $desc=$request->desc[$i];
            $items->save();
        }

        if($desc > 0){
            $descuento             = new Descuento;
            $descuento->factura    = $items->factura;
            $descuento->descuento  = $desc;
            $descuento->created_by = Auth::user()->id;
            if($request->comentario_2){
                $descuento->comentario_2 = $request->comentario_2;
            }
            $descuento->save();
        }

        //Registrar retennciones
        if ($request->retencion) {
            foreach ($request->retencion as $key => $value) {
                if ($request->precio_reten[$key]) {
                    $retencion = Retencion::where('id', $request->retencion[$key])->first();
                    $reten = new FacturaRetencion;
                    $reten->factura=$factura->id;
                    $reten->valor=$this->precision($request->precio_reten[$key]);
                    $reten->retencion=$retencion->porcentaje;
                    $reten->id_retencion=$retencion->id;
                    $reten->save();
                }
            }
        }

        //Actualiza el nro de inicio para la numeracion seleccionada
        $cant=Factura::where('empresa',Auth::user()->empresa)->where('codigo','=',($nro->prefijo.$inicio))->count();
        if($cant==0){
            $nro->inicio-=1;
            $nro->save();
        }

        PucMovimiento::facturaVenta($factura,1, $request);

        // Integración con OnePay si está habilitado
        if(OnePayService::isEnabled($user->empresa)){
            try {
                $onePayService = new OnePayService($user->empresa);
                $onePayService->createInvoice($factura, $user->empresa);
            } catch (\Exception $e) {
                // Log del error pero no interrumpir el flujo
                Log::error('Error al crear factura en OnePay: ' . $e->getMessage(), [
                    'factura_id' => $factura->id,
                    'empresa_id' => $user->empresa
                ]);
            }
        }

        //Creo la variable para el mensaje final, y la variable print (imprimir)
        $mensaje='Se ha creado satisfactoriamente la factura';
        $print=false;

        if($tipo == 2){
            $mensaje = 'Se ha creado correctamente la factura electrónica';
        }

        //Si se selecciono imprimir, para enviarla y que se abra la ventana emergente con el pdf
        if ($request->print) {
            $print=$factura->nro;
        }

        //Llamada a la funcion enviar en caso de que se haya seleccionado la opcion "Enviar por correo"
        if ($request->send) {
            $this->enviar($factura->nro, null, false);
        }

        //Se redirecciona a la vista Nuevo ingreso, si se selecciono la opcion "Agregar Pago"
        if ($request->pago) {
            return redirect('empresa/ingresos/create/'.$request->cliente.'/'.$factura->id)->with('print', $print)->with('success', $mensaje);
        }
        //Se redirecciona a la vista Nuevo Factura, si se selecciono la opcion "Crear una nueva"
        else if ($request->new) {
            return redirect('empresa/facturas/create')->with('success', $mensaje)->with('print', $print);
        }else if($tipo == 2){
            return redirect('empresa/facturas/facturas_electronica')->with('success', $mensaje)->with('print', $print)->with('codigo', $factura->id);
        }
        return redirect('empresa/factura-index')->with('success', $mensaje)->with('print', $print)->with('codigo', $factura->id);
    }

  /**
  * Formulario para modificar los datos de una factura
  * @param int $id
  * @return view
  */
    public function edit($id){
        $this->getAllPermissions(Auth::user()->id);
        $this->url = back()->getTargetUrl();
        $factura = Factura::where('empresa',Auth::user()->empresa)->where('id', $id)->first();
        $retencionesFacturas = FacturaRetencion::where('factura', $factura->id)->get();
        $retenciones = Retencion::where('empresa',Auth::user()->empresa)->where('modulo',1)->get();

        //obtiene las formas de pago relacionadas con este modulo (Facturas)
        $relaciones = FormaPago::where('relacion',1)->orWhere('relacion',3)->get();
        $formasPago = PucMovimiento::where('documento_id',$factura->id)->where('tipo_comprobante',3)->where('enlace_a',4)->get();

        if ($factura) {
            if ($factura->estatus==1) {
                //Obtengo el objeto bodega
                $bodega = Bodega::where('empresa',Auth::user()->empresa)->where('id', $factura->bodega)->first();
                if (!$bodega) {
                    $bodega = Bodega::where('empresa',Auth::user()->empresa)->where('status', 1)->first();
                }
                if (!$bodega) {
                    $inventario = collect();
                } else {
                    $inventario = Inventario::select('inventario.id','inventario.tipo_producto','inventario.producto','inventario.ref',
                        DB::raw('COALESCE(MAX(productos_bodegas.nro), 0) as nro'))
                        ->leftJoin('productos_bodegas', function($join) use ($bodega) {
                            $join->on('productos_bodegas.producto', '=', 'inventario.id')
                                 ->where('productos_bodegas.bodega', '=', $bodega->id);
                        })
                        ->where('inventario.empresa', Auth::user()->empresa)
                        ->where('inventario.status', 1)
                        ->where(function($query) use ($bodega) {
                            $query->where('inventario.tipo_producto', '!=', 1)
                                  ->orWhereIn('inventario.id', function($subquery) use ($bodega) {
                                      $subquery->select('producto')
                                              ->from('productos_bodegas')
                                              ->where('bodega', $bodega->id);
                                  });
                        })
                        ->groupBy('inventario.id', 'inventario.tipo_producto', 'inventario.producto', 'inventario.ref')
                        ->orderBy('inventario.producto','ASC')
                        ->get();
                }

                $listas = ListaPrecios::where('empresa',Auth::user()->empresa)->where('status', 1)->get();
                $bodegas = Bodega::where('empresa',Auth::user()->empresa)->where('status', 1)->get();
                $items = ItemsFactura::where('factura',$factura->id)->get();
                $clientes = (Auth::user()->oficina && Auth::user()->empresa()->oficina) ? Contacto::whereIn('tipo_contacto', [0,2])->where('status', 1)->where('empresa', Auth::user()->empresa)->where('oficina', Auth::user()->oficina)->orderBy('nombre', 'ASC')->get() : Contacto::whereIn('tipo_contacto', [0,2])->where('status', 1)->where('empresa', Auth::user()->empresa)->orderBy('nombre', 'ASC')->get();
                $vendedores = Vendedor::where('empresa',Auth::user()->empresa)->where('estado',1)->get();
                $terminos=TerminosPago::where('empresa',Auth::user()->empresa)->get();
                $impuestos = Impuesto::where('empresa',Auth::user()->empresa)->orWhere('empresa', null)->Where('estado', 1)->get();
                $tipo_documento = Factura::where('empresa',Auth::user()->empresa)->latest('tipo')->first();

                $categorias=Categoria::where('empresa',Auth::user()->empresa)->where('estatus', 1)->whereNull('asociado')->get();
                $medidas=DB::table('medidas')->get();
                $unidades=DB::table('unidades_medida')->get();
                $extras = CamposExtra::where('empresa',Auth::user()->empresa)->where('status', 1)->get();

                $identificaciones=TipoIdentificacion::all();
                $tipos_empresa=TipoEmpresa::where('empresa',Auth::user()->empresa)->get();
                $prefijos=DB::table('prefijos_telefonicos')->get();

                if($factura->tipo==1) {
                    view()->share(['icon' =>'', 'title' => 'Modificar Factura de Venta '.$factura->codigo, 'subseccion' => 'venta']);
                }elseif($factura->tipo==2){
                    view()->share(['icon' =>'', 'title' => 'Modificar Factura Electrónica '.$factura->codigo, 'subseccion' => 'venta-electronica']);
                }else{
                    view()->share(['title' => 'Cuenta de Cobro '.$factura->codigo]);
                }

                $contratos = Contrato::where('client_id',$factura->cliente)
                // ->where('state','enabled')
                ->get();
                $contratosFacturas = DB::table('facturas_contratos')->where('factura_id',$factura->id)->first();
                $contratosFacturasArray = DB::table('facturas_contratos')->where('factura_id',$factura->id)->pluck('contrato_nro')->toArray();

                // Obtener el prefijo de la numeración para el modal de edición
                $numeracionPrefijo = null;
                if ($factura->numeracion) {
                    $numeracionObj = NumeracionFactura::find($factura->numeracion);
                    if ($numeracionObj) {
                        $numeracionPrefijo = $numeracionObj->prefijo;
                    }
                }

                return view('facturas.edit')->with(compact('clientes', 'inventario', 'vendedores', 'terminos', 'impuestos', 'factura', 'items', 'listas', 'bodegas', 'retencionesFacturas', 'retenciones', 'tipo_documento', 'categorias', 'medidas', 'unidades', 'prefijos', 'tipos_empresa', 'identificaciones', 'extras','relaciones','formasPago',
                'contratos','contratosFacturas', 'contratosFacturasArray', 'numeracionPrefijo'
            ));
            }
            return redirect('empresa/factura-index')->with('success', 'La factura de venta '.$factura->codigo.' ya esta cerrada');
        }
        return redirect('empresa/factura-index')->with('success', 'No existe un registro con ese id');
    }

  /**
  * Modificar los datos de la factura
  * @param Request $request
  * @return redirect
  */
    public function update(Request $request, $id){

        $factura =Factura::find($id);

        // Validación: Para facturas con facturación automática, normalmente se requiere un contrato
        // Pero permitimos eliminarlo si el usuario lo desea explícitamente
        // Comentamos esta validación para permitir la eliminación del contrato
        // if($factura->facturacion_automatica == 1 && (empty($request->contratos_json) || $request->contratos_json == '')){
        //     return back()->with('error', 'Debe escoger un contrato asociado a la factura.');
        // }

        $user = Auth::user();
        if ($factura) {
            if ($factura->estatus==1) {
                //se devolveran todos los items al inventario
                // Asi evitar que no exista la posibilidad de error en el momento de restar los items abajo
                $bodega = Bodega::where('empresa',$user->empresa)->where('id', $factura->bodega)->first();
                $items = ItemsFactura::join('inventario as inv', 'inv.id', '=', 'items_factura.producto')->select('items_factura.*')->where('items_factura.factura',$factura->id)->where('inv.tipo_producto', 1)->get();
                foreach ($items as $item) {
                    $ajuste=ProductosBodega::where('empresa', $user->empresa)->where('bodega', $bodega->id)->where('producto', $item->producto)->first();
                    if ($ajuste) {
                        $ajuste->nro+=$item->cant;
                        $ajuste->save();
                    }
                }

                // Guardar valores anteriores para comparación y registro de logs
                // Esto permite detectar cambios y registrar logs solo cuando hay modificaciones
                $vencimientoAnterior = $factura->vencimiento;

                // Calcular total anterior para comparar con OnePay
                $totalAnterior = $factura->totalAPI($user->empresa)->total;

                //Modificacion de los datos de la factura
                $factura->notas =$request->notas;
                $factura->cliente=$request->cliente;
                $factura->fecha=Carbon::parse($request->fecha)->format('Y-m-d');
                $factura->vencimiento=Carbon::parse($request->vencimiento)->format('Y-m-d');
                $factura->suspension=Carbon::parse($request->vencimiento)->format('Y-m-d');
                $factura->observaciones=mb_strtolower($request->observaciones).' | Factura Editada por: '.$user->nombres.' el '.date('d-m-Y g:i:s A');
                $factura->vendedor=$request->vendedor;
                $factura->lista_precios=$request->lista_precios;
                $factura->bodega=$request->bodega;
                $factura->plazo=$request->plazo;
                $factura->term_cond=$request->term_cond;
                $factura->facnotas=$request->notas;
                $factura->tipo_operacion = $request->tipo_operacion;
                $factura->ordencompra    = $request->ordencompra;
                $factura->periodo_facturacion = $request->periodo_facturacion;
                $factura->factura_mes_manual = isset($request->factura_mes_manual) ? $request->factura_mes_manual : 0;
                $factura->periodo_cobrado_text = isset($request->periodo_cobrado_text) ? $request->periodo_cobrado_text : '';

                if($request->plazo != "n"){
                    $factura->pago_oportuno = date('Y-m-d', strtotime("+".($request->plazo-1)." days", strtotime($request->fecha)));
                }else{
                    $factura->pago_oportuno =$factura->vencimiento;
                }

                // Registrar log de cambio de fecha de vencimiento si hubo modificación
                // El método registrarLogCambioFactura solo registra si los valores son diferentes
                $this->registrarLogCambioFactura(
                    $factura,
                    self::CAMBIO_FECHA_VENCIMIENTO,
                    $vencimientoAnterior,
                    $factura->vencimiento
                );

                // Nota: Para registrar logs de otros campos en el futuro, seguir este patrón:
                // 1. Guardar el valor anterior antes de modificar: $campoAnterior = $factura->campo;
                // 2. Modificar el campo: $factura->campo = $nuevoValor;
                // 3. Registrar el log: $this->registrarLogCambioFactura($factura, self::CAMBIO_CAMPO, $campoAnterior, $factura->campo);

                $factura->save();

                // Manejo de contratos principales (contratos_json)
                // Primero, eliminamos todas las relaciones de contratos principales que no sean de cron
                // Esto incluye tanto el contrato principal como cualquier relación manual previa
                // También eliminamos cualquier registro que no pertenezca al cliente de la factura
                // (esto previene registros huérfanos de clientes incorrectos)
                DB::table('facturas_contratos')
                    ->where('factura_id', $factura->id)
                    ->where(function($query) use ($factura) {
                        $query->where('is_cron', 0)
                              ->orWhere('client_id', '!=', $factura->cliente);
                    })
                    ->delete();

                // Array para almacenar todos los contratos que deben estar asociados
                $contratosFinales = [];

                // Si se seleccionó un contrato principal o múltiples, agregarlos a la lista
                if(isset($request->contratos_json) && !empty($request->contratos_json)){
                    if(is_array($request->contratos_json)){
                        $contratosObj = Contrato::whereIn('id', $request->contratos_json)->get();
                        foreach($contratosObj as $co) {
                            $contratosFinales[] = $co->nro;
                        }
                    } else {
                        $contrato = Contrato::find($request->contratos_json);
                        if($contrato){
                            $contratosFinales[] = $contrato->nro;
                        }
                    }
                }

                // Manejo de contratos asociados adicionales (contratos_asociados)
                // Si el campo viene en el request (incluso si está vacío), procesarlo
                if(isset($request->contratos_asociados)){
                    $contratosArray = explode(',', $request->contratos_asociados);
                    // Eliminamos valores vacíos del array
                    $contratosArray = array_filter($contratosArray, function($value) {
                        return trim($value) !== '';
                    });

                    foreach($contratosArray as $contratoNro){
                        $contratoNro = trim($contratoNro);
                        if(empty($contratoNro)) continue;

                        // Agregar a la lista final si no está ya incluido
                        if(!in_array($contratoNro, $contratosFinales)){
                            $contratosFinales[] = $contratoNro;
                        }
                    }
                }

                // Insertar todos los contratos finales
                foreach($contratosFinales as $contratoNro){
                    DB::table('facturas_contratos')->insert([
                        'factura_id' => $factura->id,
                        'contrato_nro' => $contratoNro,
                        'client_id' => $factura->cliente,
                        'is_cron' => 0,
                        'created_by' => $user->id,
                        'created_at' => Carbon::now()
                    ]);
                }

                $inner=array();
                $bodega = Bodega::where('empresa',$user->empresa)->where('status', 1)->where('id', $request->bodega)->first();
                if (!$bodega) { //Si el valor seleccionado para bodega no existe, tomara la primera activa registrada
                    $bodega = Bodega::where('empresa',$user->empresa)->where('status', 1)->first();
                }
                //Ciclo para registrar y/o modificar los itemas de la factura
                $desc = 0;
                for ($i=0; $i < count($request->ref) ; $i++) {
                    $cat='id_item'.($i+1);
                    if($request->$cat){
                        $items = ItemsFactura::where('id', $request->$cat)->first();
                    }else{
                        $items = new ItemsFactura;
                    }
                    $impuesto = Impuesto::where('id', $request->impuesto[$i])->first();
                    $producto = Inventario::where('id', $request->item[$i])->first();
                    //Si el producto es inventariable y existe esa bodega, restará el valor registrado
                    if ($producto->tipo_producto==1) {
                        if($bodega){
                            $ajuste=ProductosBodega::where('empresa', $user->empresa)->where('bodega', $bodega->id)->where('producto', $item->producto)->first();
                           if ($ajuste) {
                           $ajuste->nro+=$item->cant;
                           $ajuste->save();
                           }
                       }
                    }
                    $items->factura=$factura->id;
                    $items->producto=$request->item[$i];
                    $items->ref=$request->ref[$i];
                    $items->precio=$request->precio[$i];
                    $items->descripcion=$request->descripcion[$i];
                    $items->id_impuesto=$request->impuesto[$i];
                    $items->impuesto=$impuesto->porcentaje;
                    $items->cant=$request->cant[$i];

                    //El descuneto no se debe aplicar sin ser aprobado.
                    if(isset($request->desc[$i])){
                        $desc+=$request->desc[$i];
                    }
                    $items->save();
                    $inner[]=$items->id;
                }
                DB::table('factura_retenciones')->where('factura', $factura->id)->delete();
                //Registrar retennciones
                if ($request->retencion) {
                    foreach ($request->retencion as $key => $value) {
                        if ($request->precio_reten[$key]) {
                            $retencion = Retencion::where('id', $request->retencion[$key])->first();
                            $reten = new FacturaRetencion;
                            $reten->factura=$factura->id;
                            $reten->valor=$this->precision($request->precio_reten[$key]);
                            $reten->retencion=$retencion->porcentaje;
                            $reten->id_retencion=$retencion->id;
                            $reten->save();
                        }
                    }
                }

                if (count($inner)>0) {
                    DB::table('items_factura')->where('factura', $factura->id)->whereNotIn('id', $inner)->delete();
                }

                if($desc > 0){

                    $oldDescuento = Descuento::where('factura', $items->factura)->where('estado', 2)->first();

                    Descuento::where('factura', $items->factura)->where('estado', 2)->delete();

                    $descuento = new Descuento;
                    $descuento->factura    = $items->factura;
                    $descuento->descuento  = $desc;
                    $descuento->created_by = $user->id;
                    if($request->comentario_2){
                        $descuento->comentario_2 = $request->comentario_2;
                    }
                    if($oldDescuento && $oldDescuento->comentario){
                        $descuento->comentario = $oldDescuento->comentario;
                    }
                    if(!$descuento->comentario_2){
                        if($oldDescuento && $oldDescuento->comentario_2){
                            $descuento->comentario_2 = $oldDescuento->comentario_2;
                        }
                    }
                    $descuento->save();
                }

                PucMovimiento::facturaVenta($factura,2,$request);

                // Integración con OnePay: crear o actualizar según corresponda
                if(OnePayService::isEnabled($user->empresa)){
                    try {
                        // Recalcular total después de guardar los items
                        $factura = Factura::find($factura->id); // Refrescar modelo
                        $totalNuevo = $factura->totalAPI($user->empresa)->total;

                        $onePayService = new OnePayService($user->empresa);

                        // Si no tiene onepay_invoice_id, es la primera vez que se crea en OnePay
                        if(!$factura->onepay_invoice_id){
                            // Crear factura en OnePay por primera vez
                            $onePayService->createInvoice($factura, $user->empresa);
                        } else {
                            // Si ya existe, solo actualizar si cambió el total
                            if(abs($totalAnterior - $totalNuevo) > 0.01){
                                $onePayService->updateInvoice($factura, $user->empresa);
                            }
                        }
                    } catch (\Exception $e) {
                        // Log del error pero no interrumpir el flujo
                        Log::error('Error al procesar factura en OnePay: ' . $e->getMessage(), [
                            'factura_id' => $factura->id,
                            'empresa_id' => $user->empresa
                        ]);
                    }
                }

                $mensaje='Se ha modificado satisfactoriamente la factura';
                return redirect($request->page)->with('success', $mensaje)->with('codigo', $factura->id);
            }
            return redirect('empresa/facturas/facturas_electronica')->with('success', 'La factura de venta '.$factura->codigo.' ya esta cerrada');
        }
        return redirect('empresa/facturas/facturas_electronica')->with('success', 'No existe un registro con ese id');
    }

  /**
  * Ver los datos de una factura
  * @param int $id
  * @return view
  */
    public function show($id){
        $this->getAllPermissions(Auth::user()->id);
        $factura = Factura::where('empresa',Auth::user()->empresa)->where('id', $id)->first();

        $contrato = Contrato::where('client_id',$factura->cliente)->first();
        $retenciones = FacturaRetencion::where('factura', $factura->id)->get();

        $limitDate   = (Carbon::parse($factura->created_at))->addDay();
        $actualDate  = Carbon::now();
        $wait        = (( $limitDate->greaterThanOrEqualTo($actualDate) && $factura->modificado == 0)? false: true);
        $mody        = $factura->modificado == 1 ? true : false;

        if($mody){
            $realStatus = $mody;
        }elseif ($wait){
            $realStatus = $wait;
        }else{
            $realStatus = false;
        }
        if ($factura) {
            if($factura->tipo == 1){
                view()->share(['title' => 'Facturas de Venta '.$factura->codigo]);
            }elseif($factura->tipo == 2){
                view()->share(['title' => 'Factura Electrónica '.$factura->codigo, 'subseccion' => 'venta-electronica']);
            }else{
                view()->share(['title' => 'Cuenta de Cobro '.$factura->codigo]);
            }
            $items = ItemsFactura::where('factura',$factura->id)->get();

            return view('facturas.show')->with(compact('factura', 'items', 'retenciones', 'realStatus','contrato'));
        }
        return redirect('empresa/facturas/facturas_electronica')->with('success', 'No existe un registro con ese id');
    }

    public function showMovimiento($id){
        $this->getAllPermissions(Auth::user()->id);
        $factura = Factura::find($id);
        /*
        obtenemos los movimiento sque ha tenido este documento
        sabemos que se trata de un tipo de movimiento 03
        */
        $movimientos = PucMovimiento::where('documento_id',$id)->where('tipo_comprobante',3)->get();
        if(count($movimientos) == 0){
            return back()->with('error', 'La factura: ' . $factura->codigo . " no tiene un asiento contable.");
        }
        if ($factura) {
            view()->share(['title' => 'Detalle Movimiento ' .$factura->codigo]);
            $items = ItemsFactura::where('factura',$factura->id)->get();
            return view('facturas.show-movimiento')->with(compact('factura','movimientos'));
        }
    }

    public function copia($id){
        return $this->pdf($id, 'copia');
    }

    public function pdf($id, $tipo='original'){
        $tipo1=$tipo;
        /**
         * * toma en cuenta que para ver los mismos
         * * datos debemos hacer la misma consulta
         * **/
        $empresa = Auth::user()->empresaObj;
        $factura = Factura::where('empresa',$empresa->id)->where('id', $id)->first();
        $resolucion = NumeracionFactura::where('empresa',$empresa->id)->latest()->first();

        if($factura->tipo == 1){
            view()->share(['title' => 'Descargar Factura']);
            if ($tipo<>'original') {
                $tipo='Copia Factura de Venta';
            }else{
                $tipo='Factura de Venta Original';
            }
        }elseif($factura->tipo == 3){
            view()->share(['title' => 'Descargar Cuenta de Cobro']);
            if ($tipo<>'original') {
                $tipo='Cuenta de Cobro Copia';
            }else{
                $tipo='Cuenta de Cobro Original';
            }
        }

        if ($factura) {
            $items = ItemsFactura::where('factura',$factura->id)->get();
            $itemscount=ItemsFactura::where('factura',$factura->id)->count();
            $retenciones = FacturaRetencion::where('factura', $factura->id)->get();

            // Inicializar array $data
            $data = [];

            if($factura->emitida == 1){
                $impTotal = 0;
                foreach ($factura->total()->imp as $totalImp){
                    if(isset($totalImp->total)){
                        $impTotal = $totalImp->total;
                    }
                }

                $CUFEvr = $factura->info_cufe($factura->id, $impTotal);
                $infoEmpresa = Empresa::find(Auth::user()->empresa);
                $data['Empresa'] = $infoEmpresa->toArray();
                $infoCliente = Contacto::find($factura->cliente);
                $data['Cliente'] = $infoCliente->toArray();

                /*..............................
                Construcción del código qr a la factura
                ................................*/

                $impuesto = 0;
                foreach ($factura->total()->imp as $key => $imp) {
                    if(isset($imp->total)){
                        $impuesto = $imp->total;
                    }
                }

                $codqr = "NumFac:" . $factura->codigo . "\n" .
                "NitFac:"  . $data['Empresa']['nit']   . "\n" .
                "DocAdq:" .  $data['Cliente']['nit'] . "\n" .
                "FecFac:" . Carbon::parse($factura->created_at)->format('Y-m-d') .  "\n" .
                "HoraFactura:" . Carbon::parse($factura->created_at)->format('H:i:s').'-05:00' . "\n" .
                "ValorFactura:" .  number_format($factura->total()->subtotal, 2, '.', '') . "\n" .
                "ValorIVA:" .  number_format($impuesto, 2, '.', '') . "\n" .
                "ValorOtrosImpuestos:" .  0.00 . "\n" .
                "ValorTotalFactura:" .  number_format($factura->total()->subtotal + $factura->impuestos_totales(), 2, '.', '') . "\n" .
                "CUFE:" . $CUFEvr;

            }else{
                // Si no es factura emitida, también necesitamos los datos básicos
                $infoEmpresa = Empresa::find(Auth::user()->empresa);
                $data['Empresa'] = $infoEmpresa->toArray();
                $infoCliente = Contacto::find($factura->cliente);
                $data['Cliente'] = $infoCliente->toArray();
                $codqr = null;
                $CUFEvr = null;
            }

            // NUEVO: Obtener información del contrato
            $contrato = null;

            // Opción 1: Si la factura tiene un campo contrato_id directo
            if (isset($factura->contrato_id) && $factura->contrato_id) {
                $contrato = Contrato::find($factura->contrato_id);
            }
            // Opción 2: Si necesitas buscar el contrato a través de los items
            elseif (!$contrato) {
                foreach ($items as $item) {
                    if (isset($item->contrato_id) && $item->contrato_id) {
                        $contrato = Contrato::find($item->contrato_id);
                        break; // Tomar el primer contrato encontrado
                    }
                }
            }
            // Opción 3: Buscar contrato activo del cliente (si no hay relación directa)
            if (!$contrato) {
                $contrato = Contrato::where('client_id', $factura->cliente)
                                ->where('state', 'enabled') // o el campo que uses para estado
                                ->first();
            }

            // Agregar datos del contrato al array $data
            if ($contrato) {
                $data['Contrato'] = [
                    'direccion_instalacion' => $contrato->direccion_instalacion,
                    'numero_contrato' => $contrato->numero ?? $contrato->id,
                    // Puedes agregar más campos del contrato si los necesitas
                ];
            }

            if($empresa->formato_impresion == 1){
                if(!isset($CUFEvr)){
                    $CUFEvr = null;
                }
                $pdf = PDF::loadView('pdf.electronica', compact('items', 'factura', 'itemscount', 'tipo', 'retenciones','resolucion','codqr','CUFEvr','data'));
            }else{
                $pdf = PDF::loadView('pdf.factura', compact('items', 'factura', 'itemscount', 'tipo', 'retenciones','resolucion','data'));
            }
            return $pdf->download('factura-'.$factura->codigo.($tipo<>'original'?'-copia':'').'.pdf');
        }
    }

    public function Imprimircopia($id){
        return $this->Imprimir($id, 'copia');
    }

    public static function Imprimir($id, $tipo = 'original', $especialFe = false, $save = false, $prevLoad = false){
        $tipo1=$tipo;

        /**
         * * toma en cuenta que para ver los mismos
         * * datos debemos hacer la misma consulta
         * **/

        $empresa = Auth::user()->empresaObj;

        $factura = ($especialFe) ? Factura::where('nonkey', $id)->first()
        : Factura::where('empresa',$empresa->id)->where('id', $id)->first();

        if(!$factura)
        {
            $factura = Factura::where('empresa', Auth::user()->empresa)->where('id', $id)->first();
        }

        if (!$factura) {
            return back()->with('error', 'No se ha encontrado la factura');
        }

        if($factura->tipo == 1){
            view()->share(['title' => 'Imprimir Factura']);
            if ($tipo<>'original') {
                $tipo='Copia Factura de Venta';
            }else{
                $tipo='Factura de Venta Original';
            }
        }elseif($factura->tipo == 3){
            view()->share(['title' => 'Imprimir Cuenta de Cobro']);
            if ($tipo<>'original') {
                $tipo='Cuenta de Cobro Copia';
            }else{
                $tipo='Cuenta de Cobro Original';
            }
        }

        $resolucion = ($especialFe) ? NumeracionFactura::where('empresa', $factura->empresa)->latest()->first()
        : NumeracionFactura::where('empresa',$empresa->id)->latest()->first();

        if ($factura) {
            $items = ItemsFactura::where('factura',$factura->id)->get();
            $itemscount=ItemsFactura::where('factura',$factura->id)->count();
            $retenciones = FacturaRetencion::where('factura', $factura->id)->get();

            // Obtener datos básicos para el QR siempre
            $infoEmpresa = $empresa;
            $data['Empresa'] = $infoEmpresa->toArray();
            $infoCliente = Contacto::find($factura->cliente);
            $data['Cliente'] = $infoCliente->toArray();

            // Calcular impuestos
            $impuesto = 0;
            foreach ($factura->total()->imp as $key => $imp) {
                if(isset($imp->total)){
                    $impuesto = $imp->total;
                }
            }

            $codqr = null;
            $CUFEvr = null;

            if($factura->emitida == 1){
                // Factura emitida - QR completo con CUFE
                $impTotal = 0;
                foreach ($factura->total()->imp as $totalImp){
                    if(isset($totalImp->total)){
                        $impTotal = $totalImp->total;
                    }
                }

                $CUFEvr = $factura->info_cufe($factura->id, $impTotal);

                $codqr = "NumFac:" . $factura->codigo . "\n" .
                "NitFac:"  . $data['Empresa']['nit']   . "\n" .
                "DocAdq:" .  $data['Cliente']['nit'] . "\n" .
                "FecFac:" . Carbon::parse($factura->created_at)->format('Y-m-d') .  "\n" .
                "HoraFactura:" . Carbon::parse($factura->created_at)->format('H:i:s').'-05:00' . "\n" .
                "ValorFactura:" .  number_format($factura->total()->subtotal, 2, '.', '') . "\n" .
                "ValorIVA:" .  number_format($impuesto, 2, '.', '') . "\n" .
                "ValorOtrosImpuestos:" .  0.00 . "\n" .
                "ValorTotalFactura:" .  number_format($factura->total()->subtotal + $factura->impuestos_totales(), 2, '.', '') . "\n" .
                "CUFE:" . $CUFEvr;
            } else {
                // Factura NO emitida - QR básico sin CUFE
                $codqr = "NumFac:" . $factura->codigo . "\n" .
                "NitFac:"  . $data['Empresa']['nit']   . "\n" .
                "DocAdq:" .  $data['Cliente']['nit'] . "\n" .
                "FecFac:" . Carbon::parse($factura->created_at)->format('Y-m-d') .  "\n" .
                "HoraFactura:" . Carbon::parse($factura->created_at)->format('H:i:s').'-05:00' . "\n" .
                "ValorFactura:" .  number_format($factura->total()->subtotal, 2, '.', '') . "\n" .
                "ValorIVA:" .  number_format($impuesto, 2, '.', '') . "\n" .
                "ValorOtrosImpuestos:" .  0.00 . "\n" .
                "ValorTotalFactura:" .  number_format($factura->total()->subtotal + $factura->impuestos_totales(), 2, '.', '');
            }

            // Generar PDF siempre con las variables del QR
            if($empresa->formato_impresion == 1){
                $pdf = PDF::loadView('pdf.electronica', compact('items', 'factura', 'itemscount', 'tipo', 'retenciones','resolucion','codqr','CUFEvr','data'));
            }else{
                $pdf = PDF::loadView('pdf.factura', compact('items', 'factura', 'itemscount', 'tipo', 'retenciones','resolucion','codqr','CUFEvr','data'));
            }

            if($save){
                return $pdf;
            }

            if($prevLoad){
                return $pdf;
            }

            return response($pdf->stream())->withHeaders(['Content-Type' =>'application/pdf']);
        }
    }

    public function imprimirFe($id){
        return $this->Imprimir($id, 'original', true);
    }

    public function imprimirTirilla($id, $tipo='original'){
        $tipo1=$tipo;

        /**
         * toma en cuenta que para ver los mismos
         * datos debemos hacer la misma consulta
         **/
        $factura = Factura::where('empresa',Auth::user()->empresa)->where('id', $id)->first();
        if($factura->tipo == 1){
            view()->share(['title' => 'Imprimir Factura']);
            if ($tipo<>'original') {
                $tipo='Copia Factura de Venta';
            }else{
                $tipo='Factura de Venta Original';
            }
        }elseif($factura->tipo == 3){
            view()->share(['title' => 'Imprimir Cuenta de Cobro']);
            if ($tipo<>'original') {
                $tipo='Cuenta de Cobro Copia';
            }else{
                $tipo='Cuenta de Cobro Original';
            }
        }

        $resolucion = NumeracionFactura::where('empresa',Auth::user()->empresa)->latest()->first();

        if ($factura) {
            $items = ItemsFactura::where('factura',$factura->id)->get();
            $itemscount=ItemsFactura::where('factura',$factura->id)->count();
            $retenciones = FacturaRetencion::where('factura', $factura->id)->get();
            $ingreso = IngresosFactura::where('factura',$factura->id)->first();

            // NUEVO: Inicializar array $data y obtener información básica
            $data = [];

            // Obtener información del cliente y empresa
            $infoEmpresa = Empresa::find(Auth::user()->empresa);
            $data['Empresa'] = $infoEmpresa->toArray();
            $infoCliente = Contacto::find($factura->cliente);
            $data['Cliente'] = $infoCliente->toArray();

            // NUEVO: Obtener información del contrato
            $contrato = null;

            // Opción 1: Si la factura tiene un campo contrato_id directo
            if (isset($factura->contrato_id) && $factura->contrato_id) {
                $contrato = Contrato::find($factura->contrato_id);
            }

            // Opción 2: Buscar contrato a través de la tabla de relaciones facturas_contratos
            if (!$contrato) {
                $contratoRelacion = DB::table('facturas_contratos')
                    ->where('factura_id', $factura->id)
                    ->first();

                if ($contratoRelacion) {
                    $contrato = Contrato::where('nro', $contratoRelacion->contrato_nro)->first();
                }
            }

            // Opción 3: Buscar contrato del cliente
            if (!$contrato) {
                $contrato = Contrato::where('client_id', $factura->cliente)
                                  ->first(); // Cambiado para buscar cualquier contrato, no solo activos
            }

            // Agregar datos del contrato al array $data
            if ($contrato) {
                $data['Contrato'] = [
                    'direccion_instalacion' => $contrato->address_street ?? $contrato->direccion_instalacion ?? null,
                    'numero_contrato' => $contrato->nro ?? $contrato->numero ?? $contrato->id,
                    // Puedes agregar más campos del contrato si los necesitas
                ];
            }

            $paper_size = array(0,0,270,580);
            $pdf = PDF::loadView('pdf.plantillas.factura_tirilla', compact('items', 'factura', 'itemscount', 'tipo', 'retenciones','resolucion','ingreso','data'));
            $pdf->setPaper($paper_size, 'portrait');
            return response($pdf->stream())->withHeaders(['Content-Type' =>'application/pdf']);
        }
    }

    public function enviarcopia($id){
        return $this->enviar($id, null, true, 'copia');
    }

    public function enviar($id, $emails=null, $redireccionar=true, $tipo='original'){
        if ($tipo==!'original') {
            $tipo='Copia factura de venta';
        }else{
            $tipo='Factura de venta original';
        }

        $empresa = Auth::user()->empresaObj;

        view()->share(['title' => 'Imprimir Factura']);

        $factura = Factura::where('empresa',$empresa->id)->where('id', $id)->first();
        $cliente = Contacto::where('id', $factura->cliente)->first();

        if ($factura) {
            if (!$emails) {
                $emails=$factura->cliente()->email;
                if ($factura->cliente()->asociados('number')>0) {
                    $email=$emails;
                    $emails=array();
                    if ($email) {$emails[]=$email;}
                    foreach ($factura->cliente()->asociados() as $asociado) {
                        if ($asociado->notificacion==1 && $asociado->email) {
                            $emails[]=$asociado->email;
                        }
                    }
                }
            }

            if (!$emails) {
                return redirect('empresa/facturas/'.$factura->id)->with('error', 'El Cliente ni sus contactos asociados tienen correo registrado');
            }

            if($factura->fecha >= '2025-10-01' && $factura->emitida == 1){

                $btw = new BTWService();

                // Envio de correo con el zip.
                $mensajeCorreo = $this->sendPdfEmailBTW($btw,$factura,$cliente,$empresa,1);
                // Fin envio de correo con el zip.

                return back()->with('success', $mensajeCorreo);
                // Fin envio de correo con el zip.
            }

            $items = ItemsFactura::where('factura',$factura->id)->get();
            $itemscount=ItemsFactura::where('factura',$factura->id)->count();
            $retenciones = FacturaRetencion::where('factura', $factura->id)->get();
            //return view('pdf.factura')->with(compact('items', 'factura', 'itemscount'));
            $resolucion = NumeracionFactura::where('id',$factura->numeracion)->first();
            $ingreso = IngresosFactura::where('factura',$factura->id)->first();
            //---------------------------------------------//
            if($factura->emitida == 1){
                $impTotal = 0;
                foreach ($factura->total()->imp as $totalImp){
                    if(isset($totalImp->total)){
                        $impTotal = $totalImp->total;
                    }
                }

                $CUFEvr = $factura->info_cufe($factura->id, $impTotal);
                $infoEmpresa = $empresa;
                $dataFactura['Empresa'] = $infoEmpresa->toArray();
                $infoCliente = Contacto::find($factura->cliente);
                $dataFactura['Cliente'] = $infoCliente->toArray();
                /*..............................
                Construcción del código qr a la factura
                ................................*/
                $impuesto = 0;
                foreach ($factura->total()->imp as $key => $imp) {
                    if(isset($imp->total)){
                        $impuesto = $imp->total;
                    }
                }

                $codqr = "NumFac:" . $factura->codigo . "\n" .
                "NitFac:"  . $dataFactura['Empresa']['nit']   . "\n" .
                "DocAdq:" .  $dataFactura['Cliente']['nit'] . "\n" .
                "FecFac:" . Carbon::parse($factura->created_at)->format('Y-m-d') .  "\n" .
                "HoraFactura" . Carbon::parse($factura->created_at)->format('H:i:s').'-05:00' . "\n" .
                "ValorFactura:" .  number_format($factura->total()->subtotal, 2, '.', '') . "\n" .
                "ValorIVA:" .  number_format($impuesto, 2, '.', '') . "\n" .
                "ValorOtrosImpuestos:" .  0.00 . "\n" .
                "ValorTotalFactura:" .  number_format($factura->total()->subtotal + $factura->impuestos_totales(), 2, '.', '') . "\n" .
                "CUFE:" . $CUFEvr;
                /*..............................
                Construcción del código qr a la factura
                ................................*/
                if($empresa->formato_impresion == 1){
                     $pdf = PDF::loadView('pdf.electronica', compact('items', 'factura', 'itemscount', 'tipo', 'retenciones','resolucion','codqr','CUFEvr','ingreso'))->stream();
                }else{
                     $pdf = PDF::loadView('pdf.factura', compact('items', 'factura', 'itemscount', 'tipo', 'retenciones','resolucion','codqr','CUFEvr','ingreso'))->stream();
                }
            }else{
                if($empresa->formato_impresion == 1){
                     $pdf = PDF::loadView('pdf.electronica', compact('items', 'factura', 'itemscount', 'tipo', 'retenciones','resolucion','ingreso'))->stream();
                }else{
                     $pdf = PDF::loadView('pdf.factura', compact('items', 'factura', 'itemscount', 'tipo', 'retenciones','resolucion','ingreso'))->stream();
                }
            }

            //-----------------------------------------------//

            $data = array(
                'email'=> 'info@istingenieria.online',
            );
            $total = Funcion::Parsear($factura->total()->total);
            $empresa = Empresa::find($factura->empresa);
            $key = Hash::make(date("H:i:s"));
            $toReplace = array('/', '$','.');
            $key = str_replace($toReplace, "", $key);
            $factura->nonkey = $key;
            $factura->save();
            $cliente = $factura->cliente()->nombre.' '.$factura->cliente()->apellidos();
            $tituloCorreo = $empresa->nombre.": Factura N° $factura->codigo";
            $xmlPath = 'xml/empresa'.auth()->user()->empresa.'/FV/FV-'.$factura->codigo.'.xml';
            //return $xmlPath;

            $host = ServidorCorreo::where('estado', 1)->where('empresa', $empresa->id)->first();
            if($host){
                $existing = config('mail');
                $new =array_merge(
                    $existing, [
                        'host' => $host->servidor,
                        'port' => $host->puerto,
                        'encryption' => $host->seguridad,
                        'username' => $host->usuario,
                        'password' => $host->password,
                        'from' => [
                            'address' => $host->address,
                            'name' => $host->name
                        ],
                    ]
                );
                config(['mail'=>$new]);
            }


            if($factura->emitida == 1){
                $statusJson = $this->validateStatusDian(auth()->user()->empresaObj->nit, $factura->codigo, "01", $resolucion->prefijo);
                $statusJson = json_decode($statusJson, true);

                if ($statusJson["statusCode"] == 200) {
                    $this->generateXmlPdfEmail($statusJson['document'], $factura, $emails, $dataFactura, $CUFEvr, $items, $resolucion, $tituloCorreo);
                }
            }else{
                   self::sendMail('emails.email', compact('factura', 'total', 'cliente'), compact('pdf', 'emails', 'tituloCorreo', 'xmlPath'), function($message) use ($pdf, $emails,$tituloCorreo,$xmlPath){
                    $message->attachData($pdf, 'factura.pdf', ['mime' => 'application/pdf']);
                    if(file_exists($xmlPath)){
                        $message->attach($xmlPath, ['as' => 'factura.xml', 'mime' => 'text/plain']);
                    }
                    $message->to($emails)->subject($tituloCorreo);
                });
            }

            //$factura->correo = 1;
            $factura->observaciones = ' | Factura Enviada por: '.Auth::user()->nombres.' el '.date('d-m-Y g:i:s A');
            $factura->save();
            if ($redireccionar) {
            return redirect('empresa/facturas/'.$factura->id)->with('success', 'Se ha enviado satisfactoriamente la factura por correo electrónico');
            //return back()->with('success', 'Se ha enviado satisfactoriamente la factura por correo electrónico');
            }
        }
    }

    public function cliente_factura_json($cliente, $cerradas=false){
        $facturas=Factura::where('empresa',Auth::user()->empresa);
        $facturas=$facturas->where('cliente', $cliente)->OrderBy('id', 'desc')->select('codigo', 'id')->get();
        return json_encode($facturas);
    }

    public function cliente_factura_json_all($cliente){
        $items=$this->cliente_factura_json($cliente);
        return array('cliente'=>Contacto::find($cliente), 'items'=>$items);
    }

    public function items_factura_json($id){
        $items = ItemsFactura::where('factura',$id)->get();
        foreach ($items as $key => $value) {
            $items[$key]->producto=$value->producto();
        }
        return json_encode($items);
    }

    public function factura_json($id){
        $factura = Factura::where('empresa',Auth::user()->empresa)->where('id', $id)->first();
        $array=array();
        if ($factura) {
            $array["fecha"]=date('d/m/Y', strtotime($factura->fecha));
            $array["vencimiento"]=date('d/m/Y', strtotime($factura->vencimiento));
            $array["observaciones"]=$factura->observaciones;
            $array["total"]=$factura->total()->total;
            $array["pagado"]=$factura->pagado();
            $array["porpagar"]=$factura->porpagar();
        }
        return json_encode($array);
    }

    public function anular($id){
        $factura = Factura::where('empresa',Auth::user()->empresa)->where('id', $id)->first();
        if ($factura) {
            $onePayService = new OnePayService();

            if ($factura->estatus==1) {
                $factura->estatus=2;
                $factura->observaciones = $factura->observaciones.' | Factura Anulada por: '.Auth::user()->nombres.' el '.date('d-m-Y g:i:s A');
                $factura->save();

                // Eliminar factura en OnePay si existe
                if ($factura->onepay_invoice_id) {
                    $onePayService->deleteInvoice($factura);
                }

                CRM::where('cliente', $factura->cliente)->whereIn('estado', [0,2,3,6])->delete();

                return back()->with('success', 'Se ha anulado la factura');
            }else if($factura->estatus==2){
                $factura->estatus=1;
                $factura->observaciones = $factura->observaciones.' | Factura Abierta por: '.Auth::user()->nombres.' el '.date('d-m-Y g:i:s A');
                $factura->save();

                // Crear factura en OnePay si está habilitado
                if (OnePayService::isEnabled()) {
                    try {
                        $onePayService->createInvoice($factura, Auth::user()->empresa);
                    } catch (\Exception $e) {
                         Log::error('Error al recrear factura en OnePay al abrir: ' . $e->getMessage());
                    }
                }

                return back()->with('success', 'Se cambiado a abierta la factura');
            }
            return redirect('empresa/facturas/facturas_electronica')->with('success', 'La factura no esta abierta');
        }
        return redirect('empresa/facturas/facturas_electronica')->with('success', 'No existe un registro con ese id');
    }

    public function cerrar($id){
        $factura = Factura::where('empresa',Auth::user()->empresa)->where('tipo',1)->where('id', $id)->first();
        if ($factura) {
            if ($factura->estatus==1) {
                $factura->estatus=0;
                $factura->observaciones = $factura->observaciones.' | Factura Cerrada por: '.Auth::user()->nombres.' el '.date('d-m-Y g:i:s A');
                $factura->save();
                return back()->with('success', 'Se ha cerrado la factura');
            }
            return redirect('empresa/facturas/facturas_electronica')->with('success', 'La factura no esta abierta');
        }
        return redirect('empresa/facturas/facturas_electronica')->with('success', 'No existe un registro con ese id');
    }

    public function datatable_producto(Request $request, $producto=null){
        // storing  request (ie, get/post) global array to a variable
        $requestData =  $request;
        $columns = array(
        // datatable column index  => database column name
            0 => 'factura.codigo',
            1 => 'nombrecliente',
            2 => 'factura.fecha',
            3 => 'factura.vencimiento',
            4 => 'total',
            5 => 'pagado',
            6 => 'porpagar',
            7=>'factura.estatus',
            8=>'acciones'
        );
        $facturas=Factura::join('contactos as c', 'factura.cliente', '=', 'c.id')->select('factura.*', DB::raw('c.nombre as nombrecliente'), DB::raw('c.apellido1 as ape1cliente'), DB::raw('c.apellido2 as ape2cliente'))->where('factura.empresa',Auth::user()->empresa)->where('factura.tipo',1);

        $facturas=$facturas->whereRaw('factura.id in (Select distinct(factura) from items_factura where producto='.$producto.' and tipo_inventario=1)');



        if (isset($requestData->search['value'])) {
          // if there is a search parameter, $requestData['search']['value'] contains search parameter
           $facturas=$facturas->where(function ($query) use ($requestData) {
              $query->where('factura.codigo', 'like', '%'.$requestData->search['value'].'%')
              ->orwhere('c.nombre', 'like', '%'.$requestData->search['value'].'%');
            });
        }
        $totalFiltered=$totalData=$facturas->count();
        // $facturas->orderby($columns[$requestData['order'][0]['column']], $requestData['order'][0]['dir'])->skip($requestData['start'])->take($requestData['length']);

        $facturas=$facturas->get();

        $data = array();
        foreach ($facturas as $factura) {
            $nestedData = array();
            $nestedData[] = '<a href="'.route('facturas.show',$factura->id).'">'.$factura->codigo.'</a>';
            $nestedData[] = '<a href="'.route('contactos.show',$factura->cliente).'" target="_blank">'.$factura->nombrecliente.' '.$factura->ape1cliente.' '.$factura->ape2cliente.'</a>';
            $nestedData[] = date('d-m-Y', strtotime($factura->fecha));
            $nestedData[] = date('d-m-Y', strtotime($factura->vencimiento));
            $nestedData[] = Auth::user()->empresa()->moneda.Funcion::Parsear($factura->total()->total);
            $nestedData[] = Auth::user()->empresa()->moneda.Funcion::Parsear($factura->pagado());
            $nestedData[] = Auth::user()->empresa()->moneda.Funcion::Parsear($factura->porpagar());
            $nestedData[] = '<spam class="text-'.$factura->estatus(true).'">'.$factura->estatus().'</spam>';
            $boton = '<a href="'.route('facturas.show',$factura->nro).'" class="btn btn-outline-info btn-icons" title="Ver"><i class="far fa-eye"></i></a>
              <a href="'.route('facturas.imprimir',['id' => $factura->nro, 'name'=> 'Factura No. '.$factura->codigo.'.pdf']).'" target="_blank" class="btn btn-outline-primary btn-icons"title="Imprimir"><i class="fas fa-print"></i></a> ';

              if($factura->estatus==1){
              $boton .= '<a  href="'.route('ingresos.create_id', ['cliente'=>$factura->cliente, 'factura'=>$factura->nro]).'" class="btn btn-outline-primary btn-icons" title="Agregar pago"><i class="fas fa-money-bill"></i></a>
              <a href="'.route('facturas.edit',$factura->nro).'"  class="btn btn-outline-primary btn-icons" title="Editar"><i class="fas fa-edit"></i></a>';

            }

            $boton.=' <form action="'.route('factura.anular',$factura->nro).'" method="POST" class="delete_form" style="display: none;" id="anular-factura'.$factura->id.'">'.csrf_field().'</form>';
            if($factura->estatus==1){
              $boton .= '<button class="btn btn-outline-danger  btn-icons" type="button" title="Anular" onclick="confirmar('."'anular-factura".$factura->id."', '¿Está seguro de que desea anular la factura de venta?', ' ');".'"><i class="fas fa-minus"></i></button> ';
            }
            else if($factura->estatus==2){
              $boton.='<button class="btn btn-outline-success  btn-icons" type="submit" title="Abrir" onclick="confirmar('."'anular-factura".$factura->id."', '¿Está seguro de que desea abrir la factura de venta?', ' ');".'"><i class="fas fa-unlock-alt"></i></button>';
            }

            $nestedData[]=$boton;
            $data[] = $nestedData;
        }
        $json_data = array(
            "draw" => intval($requestData->draw),   // for every request/draw by clientside , they send a number as a parameter, when they recieve a response/data they first check the draw number, so we are sending same number in draw.
            "recordsTotal" => intval($totalData),  // total number of records
            "recordsFiltered" => intval($totalFiltered), // total number of records after searching, if there is no searching then totalFiltered = totalData
            "data" => $data   // total data array
        );
        return json_encode($json_data);
    }

    public function datatable_producto_R($producto=null){
        // storing  request (ie, get/post) global array to a variable
        $columns = array(
            // datatable column index  => database column name
            0 => 'factura.codigo',
            1 => 'nombrecliente',
            2 => 'factura.fecha',
            3 => 'factura.vencimiento',
            4 => 'total',
            5 => 'pagado',
            6 => 'porpagar',
            7=>'factura.estatus',
            8=>'acciones'
        );
        $facturas=Factura::join('contactos as c', 'factura.cliente', '=', 'c.id')->select('factura.*', DB::raw('c.nombre as nombrecliente'), DB::raw('c.apellido1 as ape1cliente'), DB::raw('c.apellido2 as ape2cliente'))->where('factura.empresa',Auth::user()->empresa)->where('factura.tipo',1);

        $facturas=$facturas->whereRaw('factura.id in (Select distinct(factura) from items_factura where producto='.$producto.' and tipo_inventario=1)');

        $totalFiltered=$totalData=$facturas->count();
        $facturas=$facturas->get();

        $data = array();
        foreach ($facturas as $factura) {
            $nestedData = array();
            $nestedData[] = '<a href="'.route('facturas.show',$factura->nro).'">'.$factura->codigo.'</a>';
            $nestedData[] = '<a href="'.route('contactos.show',$factura->cliente).'" target="_blank">'.$factura->nombrecliente.' '.$factura->ape1cliente.' '.$factura->ape2cliente.'</a>';
            $nestedData[] = date('d-m-Y', strtotime($factura->fecha));
            $nestedData[] = date('d-m-Y', strtotime($factura->vencimiento));
            $nestedData[] = Auth::user()->empresa()->moneda.Funcion::Parsear($factura->total()->total);
            $nestedData[] = Auth::user()->empresa()->moneda.Funcion::Parsear($factura->pagado());
            $nestedData[] = Auth::user()->empresa()->moneda.Funcion::Parsear($factura->porpagar());
            $nestedData[] = '<spam class="text-'.$factura->estatus(true).'">'.$factura->estatus().'</spam>';
            $boton = '<a href="'.route('facturas.show',$factura->nro).'" class="btn btn-outline-info btn-icons" title="Ver"><i class="far fa-eye"></i></a>
          <a href="'.route('facturas.imprimir',['id' => $factura->nro, 'name'=> 'Factura No. '.$factura->codigo.'.pdf']).'" target="_blank" class="btn btn-outline-primary btn-icons"title="Imprimir"><i class="fas fa-print"></i></a> ';

            if($factura->estatus==1){
                $boton .= '<a  href="'.route('ingresos.create_id', ['cliente'=>$factura->cliente, 'factura'=>$factura->nro]).'" class="btn btn-outline-primary btn-icons" title="Agregar pago"><i class="fas fa-money-bill"></i></a>
          <a href="'.route('facturas.edit',$factura->nro).'"  class="btn btn-outline-primary btn-icons" title="Editar"><i class="fas fa-edit"></i></a>';

            }

            $boton.=' <form action="'.route('factura.anular',$factura->nro).'" method="POST" class="delete_form" style="display: none;" id="anular-factura'.$factura->id.'">'.csrf_field().'</form>';
            if($factura->estatus==1){
                $boton .= '<button class="btn btn-outline-danger  btn-icons" type="button" title="Anular" onclick="confirmar('."'anular-factura".$factura->id."', '¿Está seguro de que desea anular la factura de venta?', ' ');".'"><i class="fas fa-minus"></i></button> ';
            }
            else if($factura->estatus==2){
                $boton.='<button class="btn btn-outline-success  btn-icons" type="submit" title="Abrir" onclick="confirmar('."'anular-factura".$factura->id."', '¿Está seguro de que desea abrir la factura de venta?', ' ');".'"><i class="fas fa-unlock-alt"></i></button>';
            }

            $nestedData[]=$boton;
            $data[] = $nestedData;
        }
        return json_encode($data);
    }

    public function datatable_cliente(Request $request, $contacto){
        // storing  request (ie, get/post) global array to a variable
        $requestData =  $request;
        $empresa = Auth::user()->empresa();
        $columns = array(
        // datatable column index  => database column name
            0 => 'factura.codigo',
            1 => 'nombrecliente',
            2 => 'contratos',
            3 => 'factura.fecha',
            4 => 'factura.vencimiento',
            5 => 'fecha_pago',
            6 => 'total',
            7 => 'pagado',
            8 => 'porpagar',
            9 => 'factura.estatus',
            10 => 'acciones'
        );
        $facturas = Factura::
            join('contactos as c', 'factura.cliente', '=', 'c.id')
            ->join('items_factura as if', 'factura.id', '=', 'if.factura')
            ->leftJoin('vendedores as v', 'factura.vendedor', '=', 'v.id')
            ->select('factura.*', DB::raw('c.nombre as nombrecliente'), DB::raw('c.apellido1 as ape1cliente'), DB::raw('c.apellido2 as ape2cliente'),
                DB::raw('SUM((if.cant*if.precio)-(if.precio*(if(if.desc,if.desc,0)/100)*if.cant)+(if.precio-(if.precio*(if(if.desc,if.desc,0)/100)))*(if.impuesto/100)*if.cant) as total'),
                DB::raw('((Select SUM(pago) from ingresos_factura where factura=factura.id) + (Select if(SUM(valor), SUM(valor), 0) from ingresos_retenciones where factura=factura.id)) as pagado'),
                DB::raw('(SUM((if.cant*if.precio)-(if.precio*(if(if.desc,if.desc,0)/100)*if.cant)+(if.precio-(if.precio*(if(if.desc,if.desc,0)/100)))*(if.impuesto/100)*if.cant)-((Select SUM(pago) from ingresos_factura where factura=factura.id) + (Select if(SUM(valor), SUM(valor), 0) from ingresos_retenciones where factura=factura.id)) - (Select if(SUM(pago), SUM(pago), 0) from notas_factura where factura=factura.id) ) as porpagar'),
                )
            ->where('factura.empresa',$empresa->id)
            ->whereIn('factura.tipo',[1,2])
            ->where('factura.cliente',$contacto)
            ->groupBy('if.factura')
            ->orderBy('factura.id','DESC');

        if (isset($requestData->search['value'])) {
          // if there is a search parameter, $requestData['search']['value'] contains search parameter
           $facturas=$facturas->where(function ($query) use ($requestData) {
              $query->where('factura.codigo', 'like', '%'.$requestData->search['value'].'%')
              ->orwhere('c.nombre', 'like', '%'.$requestData->search['value'].'%');
            });
        }
        $totalFiltered=$totalData=$facturas->count();
        //$facturas->orderby($columns[$requestData['order'][0]['column']], $requestData['order'][0]['dir'])->skip($requestData['start'])->take($requestData['length']);

        $facturas=$facturas->get();

        $data = array();
        foreach ($facturas as $factura) {

            if($factura->pagado() >= $factura->total()->total && $factura->estatus == 1){
                $factura->estatus = 0;
                $factura->save();
            }

            // ** Obtencion de los contratos
            $contratos = DB::table('facturas_contratos as fc')->where('fc.factura_id',$factura->id)->get();
            $textContratos = "";
            $textDireccion = "";

            if(count($contratos) > 0){
                foreach($contratos as $c){
                    $textContratos.=  "|" .$c->contrato_nro . "|";
                    $con = Contrato::where("nro",$c->contrato_nro)->first();

                    if($con){
                        $textDireccion .="|";
                        $textDireccion .=$con->address_street?:$con->cliente()->direccion;
                        $textDireccion .="|";
                    }
                }
            }else{
                $con = Contrato::find($factura->contrato_id);

                if ($con){
                    $textContratos.=  "|" .$con->nro . "|";

                    $textDireccion .="|";
                    $textDireccion .=$con->address_street?:$con->cliente()->direccion;
                    $textDireccion .="|";
                }
            }
            $pagado = $factura->pagado();
            $nestedData = array();
            $nestedData[] = '<a href="'.route('facturas.show',$factura->id).'">'.$factura->codigo.'</a>';
            $nestedData[] = '<a href="'.route('contactos.show',$factura->cliente).'" target="_blank">'.$factura->nombrecliente.' '.$factura->ape1cliente.' '.$factura->ape2cliente.'</a>';
            $nestedData[] = date('d-m-Y', strtotime($factura->fecha));
            if(date('Y-m-d') > $factura->vencimiento && $factura->estatus==1){
                $nestedData[] = '<spam class="text-danger">'.date('d-m-Y', strtotime($factura->vencimiento)).'</spam>';
            }else{
               $nestedData[] = date('d-m-Y', strtotime($factura->vencimiento));
            }
            if($pagado > 0 && $factura->estatus==0){
                $fecha = isset($factura->ingreso()->fecha) ? date('d-m-Y', strtotime($factura->ingreso()->fecha)) : '';
                $nestedData[] = '<spam class="text-success">'.$fecha.'</spam>';
            }else{
                $nestedData[] = '<spam class="text-danger">Fac no cerrada</spam>';
            }
            $nestedData[] = $empresa->moneda.Funcion::Parsear($factura->total()->total);
            $nestedData[] = $empresa->moneda.Funcion::Parsear($pagado);
            $nestedData[] = $empresa->moneda.Funcion::Parsear($factura->porpagar());
            $nestedData[] = '<spam class="text-'.$factura->estatus(true).'">'.$factura->estatus().'</spam>';
            $nestedData[] = $textContratos;
            $nestedData[] = $textDireccion;
            $boton = '<a href="'.route('facturas.show',$factura->id).'" class="btn btn-outline-info btn-icons" title="Ver"><i class="far fa-eye"></i></a>
            <a href="'.route('facturas.imprimir',['id' => $factura->id, 'name'=> 'Factura No. '.$factura->codigo.'.pdf']).'" target="_blank" class="btn btn-outline-primary btn-icons"title="Imprimir"><i class="fas fa-print"></i></a> ';

            if($factura->estatus==1){
                $boton .= '<a  href="'.route('ingresos.create_id', ['cliente'=>$factura->cliente, 'factura'=>$factura->id]).'" class="btn btn-outline-primary btn-icons" title="Agregar pago"><i class="fas fa-money-bill"></i></a>'
                ;
              }

              if($factura->emitida != 1){
                $boton .= '<a href="'.route('facturas.edit',$factura->id).'"  class="btn btn-outline-primary btn-icons" title="Editar"><i class="fas fa-edit"></i></a>'
                ;
              }


            if($factura->estatus==1 && ($factura->promesa_pago==null || $factura->promesa_pago < Carbon::now()->format('Y-m-d'))){
                $boton .= '<a href="javascript:modificarPromesa('.$factura->id.')" class="btn btn-outline-danger btn-icons promesa ml-1" idfactura="'.$factura->id.'" title="Promesa de Pago"><i class="fas fa-calendar"></i></a>';
            }

            if(isset($_SESSION['permisos']['43'])){
            $boton.=' <form action="'.route('factura.anular',$factura->id).'" method="POST" class="delete_form" style="display: none;" id="anular-factura'.$factura->id.'">'.csrf_field().'</form>';
            if($factura->estatus==1){
                $boton .= '<button class="btn btn-outline-danger  btn-icons" type="button" title="Anular" onclick="confirmar('."'anular-factura".$factura->id."', '¿Está seguro de que desea anular la factura de venta?', ' ');".'"><i class="fas fa-minus"></i></button> ';
            }else if($factura->estatus==2){
                $boton.='<button class="btn btn-outline-success  btn-icons" type="submit" title="Abrir" onclick="confirmar('."'anular-factura".$factura->id."', '¿Está seguro de que desea abrir la factura de venta?', ' ');".'"><i class="fas fa-unlock-alt"></i></button>';
            }
            }


            $nestedData[]=$boton;
            $data[] = $nestedData;
        }
        $json_data = array(
            "draw" => intval($requestData->draw),   // for every request/draw by clientside , they send a number as a parameter, when they recieve a response/data they first check the draw number, so we are sending same number in draw.
            "recordsTotal" => intval($totalData),  // total number of records
            "recordsFiltered" => intval($totalFiltered), // total number of records after searching, if there is no searching then totalFiltered = totalData
            "data" => $data   // total data array
        );
        return json_encode($json_data);
    }

    public function aceptarFe($id){
        $factura = Factura::find($id);
        $factura->statusdian = 1;
        $factura->save();
        $mensaje = "Se ha aceptado la factura electrónica";
        return redirect('empresa/facturas/facturas_electronica')->with('success', $mensaje);
    }

    public function facturaRetenciones(Request $request){
        $factura = Factura::findOrFail($request->id);
        $retenciones = FacturaRetencion::where('factura', $factura->id)->get();
        foreach ($retenciones as $retencion){
            $retencionId[] = $retencion->id;
        }
        return json_encode($retencionId);
    }

    public function xmlFacturaVentaMasivoIni(){
        $empresa = Auth::user()->empresa;
        $facturas = Factura::where('empresa', $empresa)->where('emitida', 0)->where('tipo',2)->where('modificado', 0)->limit(5)->get();

        foreach ($facturas as $factura) {
            $factura->modificado = 1;
            $factura->save();
        }

        foreach ($facturas as $factura) {
            $this->xmlFacturaVentaMasivo($factura->id, $empresa);
        }

        return back()->with('message_success', "Importacion masiva temrinada");
    }


    public function jsonDianFacturaVenta($id, $emails = false) {
        try {

            if(request()->code){
                $factura = Factura::where('empresa', auth()->user()->empresa)->where('codigo', $id)->first();
            }else{

                $factura = Factura::Find($id);
            }


            //Validacion de dia 00 en vencimiento
            if (substr($factura->vencimiento, -2) == '00' || $factura->vencimiento < Carbon::now()->format("Y-m-d")) {
                $anoMes = substr($factura->vencimiento, 0, 7);
                $fecha = Carbon::createFromFormat('Y-m', $anoMes)->endOfMonth();
                $factura->vencimiento = $fecha->toDateString();
                $factura->save();
            }

            //Validacion de dia 00 en suspension
            if (substr($factura->suspension, -2) == '00' || $factura->suspension < Carbon::now()->format("Y-m-d")) {
                $anoMes = substr($factura->suspension, 0, 7);
                $fecha = Carbon::createFromFormat('Y-m', $anoMes)->endOfMonth();
                $factura->suspension = $fecha->toDateString();
                $factura->save();
            }

            $empresa = Empresa::Find($factura->empresa);
            $cliente = $factura->clienteObj;

            // Validación de NIT sin guiones (requerido por DIAN)
            if($cliente && $cliente->nit && strpos($cliente->nit, '-') !== false){
                if(request()->ajax()){
                    return response()->json(['status'=>'error', 'message' => 'El documento del cliente no puede contener guiones. Por favor corrija el documento del cliente antes de emitir la factura electrónica.'], 400);
                }else{
                    return redirect('/empresa/facturas/facturas_electronica')->with('message_denied', 'El documento del cliente no puede contener guiones. Por favor corrija el documento del cliente antes de emitir la factura electrónica.');
                }
            }

            $operacionCodigo = "10";
            $modoBTW = env('BTW_TEST_MODE') == 1 ? 'test' : 'prod';
            $resolucion = false;



            //Factura Exportacion.
            if($factura->tipo == 4){

                $operacionCodigo = "04";

                $trmActual = DB::table('ex_valor_moneda_diario')->where('empresa_id',$empresa->id)
                ->where('fecha',Carbon::now()->format('Y-m-d'))->first();

                if(!$trmActual){
                    if(request()->ajax()){
                        return response()->json(['status'=>'error', 'message' => 'No hay TRM registrada para la fecha actual'], 404);
                    }else{
                        return redirect('/empresa/facturas/facturas_electronica')->with('message_denied', 'No hay TRM registrada para la fecha actual');
                    }
                }else{
                    $factura->trmActual =  $trmActual;
                    $factura->datosExportacion = $factura->getInfoFactuExpo();
                }
            }

            //Factura Servicios AIU.
            if($factura->tipo_operacion == 2){
                $operacionCodigo = "09";
            }

            if (!$factura && !$empresa) {
                if(request()->ajax()){
                    return response()->json(['status'=>'error', 'message' => 'Factura o empresa no encontrada'], 404);
                }else{
                    return redirect('/empresa/facturas/facturas_electronica')->with('message_denied', 'Factura o empresa no encontrada');
                }
            }

            // Numeracion Factura o POS
            if($factura->tipo == 6){

                $resolucion = NumeracionPos::where('empresa', Auth::user()->empresa)
                ->where('preferida', 1)->first();

            }else{

                $resolucion = NumeracionFactura::where('empresa', Auth::user()->empresa)
                ->where('num_equivalente', 0)
                ->where('nomina', 0)
                ->where('preferida', 1)
                ->where('tipo',2)
                ->first();

            }

            if(!$resolucion){
                if(request()->ajax()){
                    return response()->json(['status'=>'error', 'message' => 'No hay resolucion de facturacion activa, por favor verifique'], 404);
                }else{
                    return redirect('/empresa/facturas/facturas_electronica')->with('message_denied', 'No hay resolucion de facturacion activa, por favor verifique');
                }
            }

            if($empresa->btw_login == null){
                if(request()->ajax()){
                    return response()->json(['status'=>'error', 'message' => 'La empresa no tiene configurado el login para el servicio de BTW'], 404);
                }else{
                    return redirect('/empresa/facturas/facturas_electronica')->with('message_denied', 'La empresa no tiene configurado el login para el servicio de BTW');
                }
            }

            $factura->fecha = Carbon::now()->format('Y-m-d');
            $factura->save();

            // Construccion del json por partes.
            $jsonInvoiceHead = InvoiceJsonBuilder::buildFromHeadInvoice($factura,$resolucion,$modoBTW, $operacionCodigo);
            $jsonInvoiceDetails = InvoiceJsonBuilder::buildFromDetails($factura,$resolucion,$modoBTW);
            $jsonInvoiceCompany = InvoiceJsonBuilder::buildFromCompany($empresa, $modoBTW);
            $jsonInvoiceCustomer = InvoiceJsonBuilder::buildFromCustomer($cliente,$empresa, $modoBTW, $factura);
            $jsonInvoiceTaxes = InvoiceJsonBuilder::buildFromTaxes(false,$factura,$empresa,$modoBTW);

            $fullJson = InvoiceJsonBuilder::buildFullInvoice([
                'head'              => $jsonInvoiceHead,
                'details'           => $jsonInvoiceDetails,
                'company'           => $jsonInvoiceCompany,
                'customer'          => $jsonInvoiceCustomer,
                'taxes'             => $jsonInvoiceTaxes,
                'mode'              => $modoBTW,
                'btw_login'         => $empresa->btw_login,
                'software'          => 2,
            ]);

            // Envio de json completo a microservicio de gestoru.
            $btw = new BTWService;
            $response = (object)$btw->sendInvoiceBTW($fullJson);

            //Validacion de que no existe la resolucion.
            if(isset($response->statusCode) && $response->statusCode == 422){
                return redirect('/empresa/facturas/facturas_electronica')->with('message_denied_btw', $response->th['message']);
            }

            if(isset($response->status) && $response->status == 'success'){

                // Reconectar la base de datos antes de guardar, ya que la llamada a BTW puede tardar mucho
                // y la conexión MySQL puede expirar (error: Server has gone away)
                DB::reconnect();

                $factura->emitida = 1;
                $factura->uuid = $response->cufe;
                unset($factura->trmActual, $factura->datosExportacion);

                // Intentar guardar con reintento en caso de error de conexión
                try {
                    $factura->save();
                } catch (\Exception $e) {
                    // Si aún falla, reconectar nuevamente y reintentar
                    if (strpos($e->getMessage(), 'Server has gone away') !== false ||
                        strpos($e->getMessage(), '2006') !== false) {
                        DB::reconnect();
                        $factura = Factura::find($factura->id);
                        $factura->emitida = 1;
                        $factura->uuid = $response->cufe;
                        $factura->save();
                    } else {
                        throw $e;
                    }
                }
                $mensaje = "Factura emitida correctamente con el cufe: " . $factura->uuid;
                $mensajeCorreo = '';

                // Envio de correo con el zip.
                if($modoBTW =='prod'){
                    $mensajeCorreo = $this->sendPdfEmailBTW($btw,$factura,$cliente,$empresa,1);
                }
                // Fin envio de correo con el zip.

                if(request()->code){
                    return response()->json(['status' => 1, 'codigo' => $factura->codigo, 'mensaje' => 'ya fue emitida']);
                }

                if(request()->ajax()){
                    return response()->json([
                        'status' => 'success',
                        'message' => $mensaje . " " . $mensajeCorreo,
                        'data' => $response
                    ]);

                }else{
                    return redirect('/empresa/facturas/facturas_electronica')->with('message_success', $mensaje . " " . $mensajeCorreo);
                }
            }


            if(isset($response->success) && $response->success == false){

                if(isset($response->result)){

                    if($response->result->descResponseDian != ""){
                        $message = $this->formatedResponseErrorBTW($response->result->descResponseDian);
                    }else{
                        $message = $this->formatedResponseErrorBTW($response->result->tracer);
                    }

                    if(request()->code){
                        return response()->json(['status' => 0, 'codigo' => $factura->codigo, 'mensaje' => 'Error en campos mandatorios.']);
                    }

                    if(request()->ajax()){
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Error en campos mandatorios.',
                        'error' => $message
                    ]);
                    }else{
                        return redirect('/empresa/facturas/facturas_electronica')->with('message_denied_btw', $message);
                    }
                }

                if(request()->code){
                    return response()->json(['status' => 0, 'codigo' => $factura->codigo, 'mensaje' => 'Error al enviar la factura.']);
                }

                if(request()->ajax()){
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Error al enviar la factura',
                        'error' => $response->message
                    ], 500);
                }else{
                    return redirect('/empresa/facturas/facturas_electronica')->with('message_denied_btw', $response->message);
                }

            }else{


                if(isset($response->statusCode) && $response->statusCode == 500){

                    //EVALUANDO SI YA HABIA SIDO EMITIDA//
                    $resArr = json_decode(json_encode($response), true);
                    $mensaje = $resArr['th']['btw_response'] ?? '';
                    $cufeDian = null;

                    // Patrón para extraer CUFE DIAN
                    if (preg_match('/CUFE DIAN:\s*([a-f0-9]{96})/i', $mensaje, $match)) {
                        $cufeDian = $match[1];
                    }

                    // Si detectamos el CUFE DIAN en el mensaje, entonces marcamos como emitida
                    if ($cufeDian) {
                        $factura->emitida = 1;
                        $factura->uuid = $cufeDian;
                        $factura->save();

                        return redirect('/empresa/facturas/facturas_electronica')->with('message_success', 'Factura emitida correctamente con el cufe: ' . $cufeDian);
                    }
                    //FIN EVALUACION

                    if(isset($response->th['btw_response'])){
                        $message = $this->formatedResponseErrorBTW($response->th['btw_response']);
                    }else{
                        $message = $this->formatedResponseErrorBTW($response->th);
                    }

                    if(request()->code){
                        return response()->json(['status' => 0, 'codigo' => $factura->codigo, 'mensaje' => 'Error al procesar la solicitud.']);
                    }

                    if(request()->ajax()){
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Error al procesar la solicitud',
                            'error' => $message
                        ], 500);
                    }else{
                        return redirect('/empresa/facturas/facturas_electronica')->with('message_denied_btw', $message);
                    }
                }

                return redirect()->back()->with('message_denied_btw', 'Error al procesar la solicitud, por favor intente nuevamente.');
            }

        } catch (\Throwable $th) {

            if(request()->code){
                return response()->json(['status' => 0, 'codigo' => 'n/a', 'mensaje' => 'Error al procesar la solicitud: ' . $th->getMessage()]);
            }

            if(request()->ajax()){
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error al procesar la solicitud',
                    'error' => 'Error al procesar la solicitud: ' . $th->getMessage()],
                     500
                );
            }
            else{
                return redirect('/empresa/facturas/facturas_electronica')->with('message_denied_btw', $th->getMessage());
            }
        }
    }
    public function xmlFacturaVenta($id){
        $FacturaVenta = Factura::find($id);
        $FacturaVenta->fecha = Carbon::now()->format('Y-m-d');

        if (!$FacturaVenta) {
            return redirect('/empresa/facturas/facturas_electronica')->with('error', "No se ha encontrado la factura de venta, comuniquese con soporte.");
        }

        //Validacion de dia 00 en vencimiento
        if (substr($FacturaVenta->vencimiento, -2) == '00' || $FacturaVenta->vencimiento < Carbon::now()->format("Y-m-d")) {
            $anoMes = substr($FacturaVenta->vencimiento, 0, 7);
            $fecha = Carbon::createFromFormat('Y-m', $anoMes)->endOfMonth();
            $FacturaVenta->vencimiento = $fecha->toDateString();
            $FacturaVenta->save();
        }

        //Validacion de dia 00 en suspension
        if (substr($FacturaVenta->suspension, -2) == '00' || $FacturaVenta->suspension < Carbon::now()->format("Y-m-d")) {
            $anoMes = substr($FacturaVenta->suspension, 0, 7);
            $fecha = Carbon::createFromFormat('Y-m', $anoMes)->endOfMonth();
            $FacturaVenta->suspension = $fecha->toDateString();
            $FacturaVenta->save();
        }

        $ResolucionNumeracion = NumeracionFactura::where('empresa', Auth::user()->empresa)->
        where('num_equivalente', 0)->
        where('nomina',0)->where('tipo',2)->
        where('preferida', 1)->first();

        $ValidateInvoices = Factura::where('empresa', auth()->user()->empresa)->where('codigo', $FacturaVenta->codigo)
        ->where('id', '!=', $FacturaVenta->id)
        ->count();

        $empresaId = auth()->user()->empresa;

        if($ValidateInvoices > 0){
            while(Factura::where('empresa', $empresaId)->
            where('codigo', $FacturaVenta->codigo)->
            where('id', '!=', $FacturaVenta->id)->count() > 0){

                $FacturaVenta->codigo = $ResolucionNumeracion->prefijo . $ResolucionNumeracion->inicio;
                $FacturaVenta->save();

                $ResolucionNumeracion->inicio++;
                $ResolucionNumeracion->save();

                $ResolucionNumeracion->fresh();
                $FacturaVenta->fresh();
            }
        }

        $FacturaVenta->emitida = $FacturaVenta->emitida;
        $FacturaVenta->save();

        if (Factura::where('empresa', auth()->user()->empresa)->count() > 0) {
            //Tomamos el tiempo en el que se crea el registro
            Session::put('posttimer', Factura::where('empresa', auth()->user()->empresa)->orderBy('updated_at', 'desc')->first()->updated_at);
            $sw = 1;

            if(isset($ultimoingreso)){
                //Recorremos la sesion para obtener la fecha
                foreach (Session::get('posttimer') as $key) {
                    if ($sw == 1) {
                        $ultimoingreso = $key;
                        $sw = 0;
                    }
                }

                //Tomamos la diferencia entre la hora exacta acutal y hacemos una diferencia con la ultima creación
                $diasDiferencia = Carbon::now()->diffInseconds($ultimoingreso);

                //Si el tiempo es de menos de 10 segundos mandamos al listado general
                if ($diasDiferencia <= 10) {
                    $mensaje = "La factura electrónica ya ha sido enviada.";
                    return redirect('empresa/facturas/facturas_electronica')->with('success', $mensaje);
                }
            }

        }

        $infoEmpresa = Auth::user()->empresaObj;
        $data['Empresa'] = $infoEmpresa->toArray();

        $retenciones = FacturaRetencion::where('factura', $FacturaVenta->id)->get();

        $impTotal = 0;

        foreach ($FacturaVenta->total()->imp as $totalImp) {
            if (isset($totalImp->total)) {
                $impTotal += $totalImp->total;
            }
        }
        $items = ItemsFactura::where('factura', $id)->get();

        $decimal = explode(".", $impTotal);
        if (
            isset($decimal[1]) && $decimal[1] >= 50 || isset($decimal[1]) && $decimal[1] == 5 || isset($decimal[1]) && $decimal[1] == 4
            || isset($decimal[1]) && $decimal[1] == 3 || isset($decimal[1]) && $decimal[1] == 2 || isset($decimal[1]) && $decimal[1] == 1
        ) {
            $impTotal = round($impTotal);
        } else {
            $impTotal = round($impTotal);
        }

        $CUFEvr = $FacturaVenta->info_cufe($FacturaVenta->id, $impTotal);

        $infoCliente = Contacto::find($FacturaVenta->cliente);
        $data['Cliente'] = $infoCliente->toArray();

        $responsabilidades_empresa = DB::table('empresa_responsabilidad as er')
            ->join('responsabilidades_facturacion as rf', 'rf.id', '=', 'er.id_responsabilidad')
            ->select('rf.*')
            ->where('er.id_empresa', '=', Auth::user()->empresa)->where('er.id_responsabilidad', 5)
            ->orWhere('er.id_responsabilidad', 7)->where('er.id_empresa', '=', Auth::user()->empresa)
            ->orWhere('er.id_responsabilidad', 12)->where('er.id_empresa', '=', Auth::user()->empresa)
            ->orWhere('er.id_responsabilidad', 20)->where('er.id_empresa', '=', Auth::user()->empresa)
            ->orWhere('er.id_responsabilidad', 29)->where('er.id_empresa', '=', Auth::user()->empresa)->get();

        //-- Construccion del pdf a enviar con el código qr + el envío del archivo xml --//
        if ($FacturaVenta) {
            $emails = $FacturaVenta->cliente()->email;
            if ($FacturaVenta->cliente()->asociados('number') > 0) {
                $email = $emails;
                $emails = array();
                if ($email) {
                    $emails[] = $email;
                }
                foreach ($FacturaVenta->cliente()->asociados() as $asociado) {
                    if ($asociado->notificacion == 1 && $asociado->email) {
                        $emails[] = $asociado->email;
                    }
                }
            }

            $tituloCorreo =  $data['Empresa']['nit'] . ";" . $data['Empresa']['nombre'] . ";" . $FacturaVenta->codigo . ";01;" . $data['Empresa']['nombre'];

            $isImpuesto = 1;
            //   if(auth()->user()->empresa == 1)
            //   {
            //       return $xml = response()->view('templates.xml.01',compact('CUFEvr','ResolucionNumeracion','FacturaVenta', 'data','items','retenciones','responsabilidades_empresa','emails','impTotal','isImpuesto'))->header('Cache-Control', 'public')
            //   ->header('Content-Description', 'File Transfer')
            //   ->header('Content-Disposition', 'attachment; filename=FV-'.$FacturaVenta->codigo.'.xml')
            //   ->header('Content-Transfer-Encoding', 'binary')
            //   ->header('Content-Type', 'text/xml');
            //   }

            //-- Generación del XML a enviar a la DIAN -- //
            $xml = view('templates.xml.01', compact('CUFEvr', 'ResolucionNumeracion', 'FacturaVenta', 'data', 'items', 'retenciones', 'responsabilidades_empresa', 'emails', 'impTotal', 'isImpuesto'));

            //-- Envío de datos a la DIAN --//
            $res = $this->EnviarDatosDian($xml);

            //-- Decodificación de respuesta de la DIAN --//
            $res = json_decode($res, true);


            if (isset($res['errorType'])) {
                if ($res['errorType'] == "KeyError") {
                    return back()->with('message_denied', "La dian está presentando problemas para emitir documentos electrónicos, inténtelo más tarde.");
                }
            }

            if (!isset($res['statusCode']) && isset($res['message'])) {
                return redirect('/empresa/facturas/facturas_electronica')->with('message_denied', $res['message']);
            }

            $statusCode = $res['statusCode'] ?? null; //200

            if (!isset($statusCode)) {
                return back()->with('message_denied', isset($res['message']) ? $res['message'] : 'Error en la emisión del docuemento, intente nuevamente en un momento');
            }

            //-- Guardamos la respuesta de la dian solo cuando son errores--//
            if ($statusCode != 200) {
                $FacturaVenta->dian_response = $res['statusCode'] ?? null;
                $FacturaVenta->save();
            }

            //-- Validación 1 del status code (Cuando hay un error) --//
            if ($statusCode != 200) {
                $message = $res['errorMessage'];
                $errorReason = $res['errorReason'];

                //Validamos si depronto la factura fue emitida pero no quedamos con ningun registro de ella.
                $saveNoJson = $statusJson = $this->validateStatusDian(auth()->user()->empresaObj->nit, $FacturaVenta->codigo, "01", $ResolucionNumeracion->prefijo);

                $statusJson = json_decode($statusJson, true);

                if ($statusJson["statusCode"] == 200) {

                    //linea comentada por ahorro de espacio en bd, ay que esta información de las facturas procesadas se puede obtener mediante consulta api.
                    // $FacturaVenta->dian_response = $saveNoJson;
                    $message = "Factura emitida correctamente por validación";
                    $FacturaVenta->emitida = 1;
                    $FacturaVenta->fecha_expedicion = Carbon::now();

                    //Llave unica para acceso por correo
                    $key = Hash::make(date("H:i:s"));
                    $toReplace = array('/', '$', '.');
                    $key = str_replace($toReplace, "", $key);
                    $FacturaVenta->nonkey = $key;

                    $FacturaVenta->save();

                    $this->generateXmlPdfEmail($statusJson['document'], $FacturaVenta, $emails, $data, $CUFEvr, $items, $ResolucionNumeracion, $tituloCorreo);
                } else {
                    return back()->with('message_denied', $message)->with('errorReason', $errorReason);
                }
            }

            $document = $res['document'];

            //-- estátus de que la factura ha sido aprobada --//
            if ($statusCode == 200) {

                //Llave unica para acceso por correo
                $key = Hash::make(date("H:i:s"));
                $toReplace = array('/', '$', '.');
                $key = str_replace($toReplace, "", $key);
                $FacturaVenta->nonkey = $key;
                $FacturaVenta->save();
                //

                $message = "Factura emitida correctamente";
                $FacturaVenta->emitida = 1;
                $FacturaVenta->fecha_expedicion = Carbon::now();
                $FacturaVenta->save();

                $this->generateXmlPdfEmail($document, $FacturaVenta, $emails, $data, $CUFEvr, $items, $ResolucionNumeracion, $tituloCorreo);
            }
            return back()->with('message_success', $message);
        }
    }


   /**
     * Metodo de consulta
     * Consultamos si una factura ya fue emititda y no quedamos con registro de ella, de ser así la guardamos, en bd, generamos el xml y enviamos el correo al cliente.
     */
    public function generateXmlPdfEmail($document, $FacturaVenta, $emails, $data, $CUFEvr, $items, $ResolucionNumeracion, $tituloCorreo){

        $empresa = auth()->user()->empresaObj;

        $document = base64_decode($document);

        //-- Generación del archivo .xml mas el lugar donde se va a guardar --//
        $path = public_path() . '/xml/empresa' . auth()->user()->empresa;

        if (!File::exists($path)) {
            File::makeDirectory($path);
            $path = $path . "/FV";
            File::makeDirectory($path);
        } else {
            $path = public_path() . '/xml/empresa' . auth()->user()->empresa . "/FV";
        }

        $namexml = 'FV-' . $FacturaVenta->codigo . ".xml";
        $ruta_xmlresponse = $path . "/" . $namexml;
        $file = fopen($ruta_xmlresponse, "w");
        fwrite($file, $document . PHP_EOL);
        fclose($file);

        if (is_array($emails)) {
            $max = count($emails);
        } else {
            $max = 1;
        }

        if (!$emails || $max == 0) {

            return redirect('empresa/facturas/facturas_electronica' . $FacturaVenta->nro)->with('error', 'El Cliente ni sus contactos asociados tienen correo registrado');
        }

        /*..............................
        Construcción del código qr a la factura
        ................................*/
        $impuesto = 0;
        foreach ($FacturaVenta->total()->imp as $key => $imp) {
            if (isset($imp->total)) {
                $impuesto = $imp->total;
            }
        }

        $decimal = explode(".", $impuesto);
        if (isset($decimal[1]) && $decimal[1] > 50) {
            $impuesto = round($impuesto);
        }

        $codqr = "NumFac:" . $FacturaVenta->codigo . "\n" .
            "NitFac:"  . $data['Empresa']['nit']   . "\n" .
            "DocAdq:" .  $data['Cliente']['nit'] . "\n" .
            "FecFac:" . Carbon::parse($FacturaVenta->created_at)->format('Y-m-d') .  "\n" .
            "HoraFactura" . Carbon::parse($FacturaVenta->created_at)->format('H:i:s') . '-05:00' . "\n" .
            "ValorFactura:" .  number_format($FacturaVenta->total()->subtotal, 2, '.', '') . "\n" .
            "ValorIVA:" .  number_format($impuesto, 2, '.', '') . "\n" .
            "ValorOtrosImpuestos:" .  0.00 . "\n" .
            "ValorTotalFactura:" .  number_format($FacturaVenta->total()->subtotal + $FacturaVenta->impuestos_totales(), 2, '.', '') . "\n" .
            "CUFE:" . $CUFEvr;

        /*..............................
        Construcción del código qr a la factura
        ................................*/

        $itemscount = $items->count();
        $retenciones = FacturaRetencion::where('factura', $FacturaVenta->id)->get();
        $resolucion  = $ResolucionNumeracion;
        $tipo = "original";
        $factura = $FacturaVenta;
        $vendedor = Vendedor::where('id', $FacturaVenta->vendedor)->first();

        if ($factura->tipo_operacion == 3) {
            $pdf = PDF::loadView('pdf.facturatercero', compact('items', 'factura', 'itemscount', 'tipo', 'retenciones', 'resolucion', 'codqr', 'CUFEvr', 'vendedor', 'empresa'))
                ->save(public_path() . "/convertidor" . "/FV-" . $factura->codigo . ".pdf")->stream();
        } else {

            if($empresa->formato_impresion == 1){
                $pdf = PDF::loadView('pdf.electronica', compact('items', 'factura', 'itemscount', 'tipo', 'retenciones', 'resolucion', 'codqr', 'CUFEvr', 'vendedor', 'empresa'))
                ->save(public_path() . "/convertidor" . "/FV-" . $factura->codigo . ".pdf")->stream();
            }else{
                $pdf = PDF::loadView('pdf.factura', compact('items', 'factura', 'itemscount', 'tipo', 'retenciones', 'resolucion', 'codqr', 'CUFEvr', 'vendedor', 'empresa'))
                ->save(public_path() . "/convertidor" . "/FV-" . $factura->codigo . ".pdf")->stream();
            }
        }

        //Construccion del archivo zip.
        $zip = new ZipArchive();

        //Después creamos un archivo zip temporal que llamamos miarchivo.zip y que eliminaremos después de descargarlo.
        //Para indicarle que tiene que crearlo ya que no existe utilizamos el valor ZipArchive::CREATE.
        $nombreArchivoZip = "FV-" . $factura->codigo . ".zip";

        $zip->open("convertidor/" . $nombreArchivoZip, ZipArchive::CREATE);

        if (!$zip->open($nombreArchivoZip, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            return ("Error abriendo ZIP en $nombreArchivoZip");
        }

        $ruta_pdf = public_path() . "/convertidor" . "/FV-" . $factura->codigo . ".pdf";

        $zip->addFile($ruta_xmlresponse, "FV-" . $factura->codigo . ".xml");
        $zip->addFile($ruta_pdf, "FV-" . $factura->codigo . ".pdf");
        $resultado = $zip->close();

        /*..............................
        Construcción del envío de correo electrónico
        ................................*/

        $data = array(
            'email' => 'info@networksoft.online',
        );
        $total = Funcion::Parsear($factura->total()->total);
        $cliente = $FacturaVenta->cliente()->nombre;

        $host = ServidorCorreo::where('estado', 1)->where('empresa', Auth::user()->empresa)->first();
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

        $rutaZip = public_path($nombreArchivoZip);

        self::sendMail('emails.email', compact('factura', 'total', 'cliente', 'empresa'), compact('pdf', 'emails', 'ruta_xmlresponse', 'FacturaVenta', 'nombreArchivoZip', 'tituloCorreo', 'empresa'), function ($message) use ($pdf, $emails, $rutaZip, $FacturaVenta, $nombreArchivoZip, $tituloCorreo, $empresa) {
            $message->attachData($pdf, 'factura.pdf', ['mime' => 'application/pdf']);
            if(file_exists($rutaZip)){
                    $message->attach($rutaZip, ['as' => 'factura.xml', 'mime' => 'text/plain']);
            }
            $message->from('info@networksoft.online', Auth::user()->empresaObj->nombre);
            $message->to($emails)->subject($tituloCorreo);
        });

        // Si quieres puedes eliminarlo después:
        if (isset($nombreArchivoZip)) {
            unlink($nombreArchivoZip);
            unlink($ruta_pdf);
        }
    }

    public function xmlFacturaVentabyCorreo($id)
    {
        $empresa = auth()->user()->empresaObj;

        $ResolucionNumeracion = NumeracionFactura::where('empresa', Auth::user()->empresa)->where('num_equivalente', 0)->where('tipo',2)->where('preferida', 1)->first();

        $infoEmpresa = $empresa;
        $data['Empresa'] = $infoEmpresa->toArray();

        $FacturaVenta = Factura::find($id);
        $vendedor = Vendedor::where('id', $FacturaVenta->vendedor)->first();

        //Generacion de llave unica para acceso por correo
        $key = Hash::make(date("H:i:s"));
        $toReplace = array('/', '$', '.');
        $key = str_replace($toReplace, "", $key);
        $FacturaVenta->nonkey = $key;
        $FacturaVenta->save();
        //
        $retenciones = FacturaRetencion::where('factura', $FacturaVenta->id)->get();

        $impTotal = 0;

        foreach ($FacturaVenta->total()->imp as $totalImp) {
            if (isset($totalImp->total)) {
                $impTotal += $totalImp->total;
            }
        }
        $items = ItemsFactura::where('factura', $id)->get();

        $CUFEvr = $FacturaVenta->info_cufe($FacturaVenta->id, $impTotal);

        $infoCliente = Contacto::find($FacturaVenta->cliente);
        $data['Cliente'] = $infoCliente->toArray();

        $responsabilidades_empresa = DB::table('empresa_responsabilidad as er')
            ->join('responsabilidades_facturacion as rf', 'rf.id', '=', 'er.id_responsabilidad')
            ->select('rf.*')
            ->where('er.id_empresa', Auth::user()->empresa)
            ->get();

        $emails = $FacturaVenta->cliente()->email;
        if ($FacturaVenta->cliente()->asociados('number') > 0) {
            $email = $emails;
            $emails = array();
            if ($email) {
                $emails[] = $email;
            }
            foreach ($FacturaVenta->cliente()->asociados() as $asociado) {
                if ($asociado->notificacion == 1 && $asociado->email) {
                    $emails[] = $asociado->email;
                }
            }
        }


        //-- Generación del XML a enviar a la DIAN -- //
        $xml = view('templates.xml.01', compact('CUFEvr', 'ResolucionNumeracion', 'FacturaVenta', 'data', 'items', 'retenciones', 'responsabilidades_empresa', 'emails', 'vendedor'));

        /*return $xml = response()->view('templates.xml.01',compact('CUFEvr','ResolucionNumeracion','FacturaVenta', 'data','items','retenciones','responsabilidades_empresa'))->header('Cache-Control', 'public')
        ->header('Content-Description', 'File Transfer')
        ->header('Content-Disposition', 'attachment; filename=FV-'.$FacturaVenta->codigo.'.xml')
        ->header('Content-Transfer-Encoding', 'binary')
        ->header('Content-Type', 'text/xml');*/

        //$message = $res['statusMessage'];
        $message = "Factura por correo con xml enviada correctamente";

        //-- Generación del archivo .xml mas el lugar donde se va a guardar --//
        $path = public_path() . '/xml/empresa' . auth()->user()->empresa;

        if (!File::exists($path)) {
            File::makeDirectory($path);
            $path = $path . "/FV";
            File::makeDirectory($path);
        } else {
            $path = public_path() . '/xml/empresa' . auth()->user()->empresa . "/FV";
        }

        $namexml = 'FV-' . $FacturaVenta->codigo . ".xml";
        $ruta_xmlresponse = $path . "/" . $namexml;
        $file = fopen($ruta_xmlresponse, "w");
        fwrite($file, $xml . PHP_EOL);
        fclose($file);

        //-- Construccion del pdf a enviar con el código qr + el envío del archivo xml --//
        if ($FacturaVenta) {
            if (!$emails || count($emails) == 0) {
                return redirect('empresa/facturas/' . $FacturaVenta->nro)->with('error', 'El Cliente ni sus contactos asociados tienen correo registrado');
            }


            /*..............................
        Construcción del código qr a la factura
        ................................*/
            $impuesto = 0;
            foreach ($FacturaVenta->total()->imp as $key => $imp) {
                if (isset($imp->total)) {
                    $impuesto = $imp->total;
                }
            }

            $codqr = "NumFac:" . $FacturaVenta->codigo . "\n" .
                "NitFac:"  . $data['Empresa']['nit']   . "\n" .
                "DocAdq:" .  $data['Cliente']['nit'] . "\n" .
                "FecFac:" . Carbon::parse($FacturaVenta->created_at)->format('Y-m-d') .  "\n" .
                "HoraFactura" . Carbon::parse($FacturaVenta->created_at)->format('H:i:s') . '-05:00' . "\n" .
                "ValorFactura:" .  number_format($FacturaVenta->total()->subtotal, 2, '.', '') . "\n" .
                "ValorIVA:" .  number_format($impuesto, 2, '.', '') . "\n" .
                "ValorOtrosImpuestos:" .  0.00 . "\n" .
                "ValorTotalFactura:" .  number_format($FacturaVenta->total()->subtotal + $FacturaVenta->impuestos_totales(), 2, '.', '') . "\n" .
                "CUFE:" . $CUFEvr;

            /*..............................
        Construcción del código qr a la factura
        ................................*/

            $itemscount = $items->count();
            $retenciones = FacturaRetencion::where('factura', $FacturaVenta->id)->get();
            $resolucion  = $ResolucionNumeracion;
            $tipo = "original";
            $factura = $FacturaVenta;

            if ($factura->tipo_operacion == 3) {
                $detalle_recaudo = $factura->detalleRecaudo();
                $pdf = PDF::loadView('pdf.facturatercero', compact('items', 'factura', 'itemscount', 'tipo', 'retenciones', 'resolucion', 'codqr', 'CUFEvr', 'detalle_recaudo', 'vendedor', 'empresa'))->stream();
            } else {
                if($empresa->formato_impresion == 1){
                    $pdf = PDF::loadView('pdf.electronica', compact('items', 'factura', 'itemscount', 'tipo', 'retenciones', 'resolucion', 'codqr', 'CUFEvr', 'vendedor', 'empresa'))->stream();
                }else{
                    $pdf = PDF::loadView('pdf.factura', compact('items', 'factura', 'itemscount', 'tipo', 'retenciones', 'resolucion', 'codqr', 'CUFEvr', 'vendedor', 'empresa'))->stream();
                }
            }
            /*..............................
        Construcción del envío de correo electrónico
        ................................*/
            $data = array(
                'email' => 'info@networksoft.online',
            );

            $total = Funcion::Parsear($factura->total()->total);
            $cliente = $FacturaVenta->cliente()->nombre;

            $host = ServidorCorreo::where('estado', 1)->where('empresa', Auth::user()->empresa)->first();
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

            self::sendMail('emails.email', compact('factura', 'total', 'cliente', 'empresa'), compact('pdf', 'emails', 'ruta_xmlresponse', 'FacturaVenta'), function ($message) use ($pdf, $emails, $ruta_xmlresponse, $FacturaVenta) {
                $message->attachData($pdf, 'FV-' . $FacturaVenta->codigo . '.pdf', ['mime' => 'application/pdf']);
                $message->attach($ruta_xmlresponse);
                $message->from('info@networksoft.online', Auth::user()->empresaObj->nombre);
                $message->to($emails)->subject(Auth::user()->empresaObj->nombre . " Factura Electrónica " . $FacturaVenta->codigo);
            });
        }
        return back()->with('message_success', $message);
    }

    public function validate_dian(Request $request)
    {
        $factura = Factura::find($request->id);

        // Validar que la factura exista
        if (!$factura) {
            return response()->json([
                "error" => "Factura no encontrada"
            ], 404);
        }

        $numeracion = NumeracionFactura::where('empresa', Auth::user()->empresa)->where('tipo',2)->where('id', $factura->numeracion)->first();

        // Validar que la numeración exista
        if (!$numeracion) {
            return response()->json([
                "error" => "Numeración no encontrada"
            ], 404);
        }

        $empresa  = Empresa::select('id', 'fk_idpais', 'fk_iddepartamento', 'fk_idmunicipio', 'dv', 'fk_idpais', 'fk_iddepartamento', 'fk_idmunicipio', 'dv')
        ->with('responsabilidades')
        ->where('id', auth()->user()->empresa)
        ->first();

        // Validar que la empresa exista
        if (!$empresa) {
            return response()->json([
                "error" => "Empresa no encontrada"
            ], 404);
        }

        $cliente  = $factura->cliente();

        // Validar que el cliente exista
        if (!$cliente) {
            return response()->json([
                "error" => "Cliente no encontrado"
            ], 404);
        }

        //Inicializamos la variable para ver si tiene las nuevas responsabilidades que no da la dian 042
        $resp = 0;
        $responsabilidades = $empresa->responsabilidades ? $empresa->responsabilidades->count() : 0;

        if ($empresa->responsabilidades) {
            foreach ($empresa->responsabilidades as $responsabilidad) {

                $listaResponsabilidadesDian = [5, 7, 12, 20, 29];

                if (in_array($responsabilidad->pivot->id_responsabilidad, $listaResponsabilidadesDian)) {
                    $resp = 1;
                }
            }
        }

        if ($cliente->tip_iden != 6) {
            $cliente->tipo_persona      = 1; //-- Persona Natural
            $cliente->responsableiva    = 2; //-- No responsable de iva
            $cliente->save();
        }

        //-- Validación de si la ultima factura creada fue emitida o si es la primer factura a emitir que la deje --//
        if (!empty($numeracion->prefijo)) {
            // Extrae solo las letras del inicio del código (el prefijo)
            $codigo = preg_replace('/[0-9]+/', '', $factura->codigo);
            $numero = intval(preg_replace('/[^0-9]+/', '', $factura->codigo), 10);
        } else {
            $codigo = $factura->codigo;
            // Extrae el número del código si existe, de lo contrario usa 0
            $numeroExtraido = preg_replace('/[^0-9]+/', '', $factura->codigo);
            $numero = !empty($numeroExtraido) ? intval($numeroExtraido, 10) : 0;
        }

        // Validar que $numero sea numérico antes de hacer operaciones aritméticas
        if (!is_numeric($numero)) {
            $numero = 0;
        } else {
            $numero = intval($numero);
        }

        //Si tenemos una pasada factura a la que estamos intentando emitir entra a este if
        if ($numero > 0 && Factura::where('empresa', Auth::user()->empresa)
                ->where('numeracion', $factura->numeracion)
                ->where('codigo', $codigo . ($numero - 1))
                ->first()) {

                $ultfact = Factura::where('empresa', Auth::user()->empresa)
                ->where('numeracion', $factura->numeracion)
                ->where('codigo', $codigo . ($numero - 1))
                ->first();

            if ($ultfact->emitida == null || $ultfact->emitida == 2 || $ultfact->emitida == 0) { //-- si es null o es 2(no emitida) o 0 no emitida
                $emitida = false;
            } else {
                $emitida = true;
            }
        } elseif (is_numeric($numero) && isset($numeracion->inicioverdadero) && $numero == $numeracion->inicioverdadero) { //-- si no entra es por que hay la posibilidad de que sea la primer factura emitida de esa numeración
            $emitida = true;
        } else { //cambió el prefijo de una numeracion existente ademas hay mas facturas con esa numeración sin emitir
            /*
            Actualizacion: Como no es igual al inicioverdadero es muy probable que no
            se este emitiendo desde el numero de inicio verdadero si no que arranco un poco mas
            adelante.
            */
            $emitida = true;
        }

        return response()->json([
            "numeracion" => $numeracion, "responsabilidades" => $responsabilidades, "empresa" => $empresa,
            "cliente" => $cliente, "total" => $factura->total()->total,
            "emitida" => $emitida, "responsabilidad" => $resp
        ]);
    }

    public function xmlFacturaVentaFe($id)
    {

        $empresa = auth()->user()->empresaObj;

        $FacturaVenta = Factura::where('nonkey', $id)->first();
        $ResolucionNumeracion = NumeracionFactura::where('empresa', $FacturaVenta->empresa)->where('tipo',2)->where('preferida', 1)->first();

        $infoEmpresa = Empresa::find($FacturaVenta->empresa);
        $data['Empresa'] = $infoEmpresa->toArray();

        $retenciones = FacturaRetencion::where('factura', $FacturaVenta->id)->get();

        $SumImp = 0;

        foreach ($FacturaVenta->total()->imp as $totalImp) {
            if (isset($totalImp->total)) {
                $impTotal += $totalImp->total;
            }
        }
        $items = ItemsFactura::where('factura', $id)->get();

        $CUFEvr = $FacturaVenta->info_cufe($FacturaVenta->id, $impTotal);

        $infoCliente = Contacto::find($FacturaVenta->cliente);
        $data['Cliente'] = $infoCliente->toArray();

        $responsabilidades_empresa = DB::table('empresa_responsabilidad as er')
            ->join('responsabilidades_facturacion as rf', 'rf.id', '=', 'er.id_responsabilidad')
            ->select('rf.*')
            ->where('er.id_empresa', Auth::user()->empresa)
            ->get();

        return $xml = response()->view('templates.xml.01', compact('CUFEvr', 'ResolucionNumeracion', 'FacturaVenta', 'data', 'items', 'retenciones', 'responsabilidades_empresa'))->header('Cache-Control', 'public')
            ->header('Content-Description', 'File Transfer')
            ->header('Content-Disposition', 'attachment; filename=FV-' . $FacturaVenta->codigo . '.xml')
            ->header('Content-Transfer-Encoding', 'binary')
            ->header('Content-Type', 'text/xml');

        //-- Construccion del pdf a enviar con el código qr + el envío del archivo xml --//
        if ($FacturaVenta) {

            /*..............................
            Construcción del código qr a la factura
            ................................*/
            foreach ($FacturaVenta->total()->imp as $key => $imp) {
                if (isset($imp->total)) {
                    $impuesto = $imp->total;
                }
            }

            $codqr = "NumFac:" . $FacturaVenta->codigo . "\n" .
                "NitFac:"  . $data['Empresa']['nit']   . "\n" .
                "DocAdq:" .  $data['Cliente']['nit'] . "\n" .
                "FecFac:" . Carbon::parse($FacturaVenta->created_at)->format('Y-m-d') .  "\n" .
                "HoraFactura" . Carbon::parse($FacturaVenta->created_at)->format('H:i:s') . '-05:00' . "\n" .
                "ValorFactura:" .  number_format($FacturaVenta->total()->subtotal, 2, '.', '') . "\n" .
                "ValorIVA:" .  number_format($impuesto, 2, '.', '') . "\n" .
                "ValorOtrosImpuestos:" .  0.00 . "\n" .
                "ValorTotalFactura:" .  number_format($FacturaVenta->total()->subtotal + $FacturaVenta->impuestos_totales(), 2, '.', '') . "\n" .
                "CUFE:" . $CUFEvr;

            /*..............................
                  Construcción del código qr a la factura
                  ................................*/

            $itemscount = $items->count();
            $retenciones = FacturaRetencion::where('factura', $FacturaVenta->id)->get();
            $resolucion  = $ResolucionNumeracion;
            $tipo = "original";
            $factura = $FacturaVenta;
            $vendedor = Vendedor::where('id', $FacturaVenta->vendedor)->first();

            if ($factura->tipo_operacion == 3) {
                $detalle_recaudo = $factura->detalleRecaudo();
                $pdf = PDF::loadView('pdf.facturatercero', compact('items', 'factura', 'itemscount', 'tipo', 'retenciones', 'resolucion', 'codqr', 'CUFEvr', 'detalle_recaudo', 'vendedor', 'empresa'))->stream();
            } else {
                if($empresa->formato_impresion == 1){
                    $pdf = PDF::loadView('pdf.electronica', compact('items', 'factura', 'itemscount', 'tipo', 'retenciones', 'resolucion', 'codqr', 'CUFEvr', 'vendedor', 'empresa'))->stream();
                }else{
                    $pdf = PDF::loadView('pdf.factura', compact('items', 'factura', 'itemscount', 'tipo', 'retenciones', 'resolucion', 'codqr', 'CUFEvr', 'vendedor', 'empresa'))->stream();
                }
            }

            return response($pdf->withHeaders(['Content-Type' => 'application/pdf',]));
        }
    }

    public function validate_technicalkey_dian()
    {
        $empresa = auth()->user()->empresaObj;

        //Si está habilitado frente a la Dian y no tiene aún la clave técnica.
        if ($empresa) {
            if ($empresa->estado_dian == 1 && $empresa->technicalkey == null) {
                $softwareCode = "49fab599-4556-4828-a30b-852a910c5bb1";
                $accountCodeVendor = "890930534";
                $accountCode = $empresa->nit;
                $json = $this->getTechnicalKey($softwareCode, $accountCodeVendor, $accountCode);
                $json = json_decode($json, true);

                if (isset($json['statusCode'])) {
                    if ($json['statusCode'] != 404) {
                        $empresa->technicalkey =  $json['numberingRangelist'][0]['technicalKey'];
                        $empresa->save();


                        //Envio del correo electrónico.
                        $rango_numeracion = $json['numberingRangelist'];
                        $tituloCorreo = "Facturador Electrónico Activado";
                        $emails = auth()->user()->empresaObj->email;

                        $host = ServidorCorreo::where('estado', 1)->where('empresa', Auth::user()->empresa)->first();
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

                        self::sendMail('emails.dian.felicidades', compact('empresa', 'rango_numeracion'), compact('emails', 'tituloCorreo'), function ($message) use ($emails, $tituloCorreo) {
                            $message->from('info@networksoft.online', 'Facturación Electrónica - Gestor de Partes');
                            $message->to($emails)->subject($tituloCorreo);
                        });

                        return response()->json(1);
                    }
                } else {
                    return response()->json(0);
                }
            } else {
                return response()->json(0);
            }
        }
    }

    public function validateTimeEmicion(){
        if(auth()->user()->empresa()->estado_dian == 1){
            $numeracion = NumeracionFactura::where('empresa',auth()->user()->empresa)->where('preferida',1)->where('tipo',2)->first();
            $pendientes = Factura::where('empresa',auth()->user()->empresa)->where('numeracion',$numeracion->id)
            ->where('emitida',0)->where('created_at','<=',Carbon::now()->subDay(1))->get();
        return response()->json($pendientes);
        }else return null;
    }

    public function mensaje($id){
        $empresa = auth()->user()->empresaObj;

        $factura = Factura::find($id);
        $hora = date('G');

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

        $contrato = null;

        $contrato = $factura->contratos()->first();
        if($contrato && $empresa->nit == "901346829"){
            $mensaje = $empresa->nombre.' informa, su factura del mes de ' .$mes.  ' fue generada por un total de $' .$factura->parsear($factura->total()->total) .  ' en el contrato nro ' . $contrato->contrato_nro . ' . Cuenta para pago en Coopenessa convenio Telepon ' . $contrato->contrato_nro;
        }else{
            $mensaje = $empresa->nombre." Le informa que su factura ha sido generada bajo el Nro. ".$factura->codigo.", por un monto de $".$factura->parsear($factura->total()->total);
        }

        $numero = str_replace('+','',$factura->cliente()->celular);
        $numero = str_replace(' ','',$numero);

        $servicio = Integracion::where('empresa', Auth::user()->empresa)->where('tipo', 'SMS')->where('status', 1)->first();
        if($servicio){
            if($servicio->nombre == '360nrs'){

                if($servicio->user && $servicio->pass && $servicio->numero){
                    $post['to'] = array('573022501174');
                    $post['text'] = $mensaje;
                    $post['from'] = "SMS";

                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://dashboard.360nrs.com/api/rest/sms',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => json_encode($post),
                    CURLOPT_HTTPHEADER => array(
                        'Content-Type: application/json',
                        'Authorization: Basic aW50ZWdyYTM2MDpUUHlhNzQ/Iw=='
                    ),
                    ));

                    $response = curl_exec($curl);

                    curl_close($curl);


                    if (isset($response['error'])) {
                        return back()->with('danger', 'Respuesta API 360nrs: '.$err);
                    }else{
                        $response = json_decode($result, true);

                        if(isset($response['error'])){
                            // if($response['error']['code'] == 102){
                            //     $msj = "No hay destinatarios válidos (Cumpla con el formato de nro +5700000000000)";
                            // }else if($response['error']['code'] == 103){
                            //     $msj = "Nombre de usuario o contraseña desconocidos";
                            // }else if($response['error']['code'] == 104){
                            //     $msj = "Falta el mensaje de texto";
                            // }else if($response['error']['code'] == 105){
                            //     $msj = "Mensaje de texto demasiado largo";
                            // }else if($response['error']['code'] == 106){
                            //     $msj = "Falta el remitente";
                            // }else if($response['error']['code'] == 107){
                            //     $msj = "Remitente demasiado largo";
                            // }else if($response['error']['code'] == 108){
                            //     $msj = "No hay fecha y hora válida para enviar";
                            // }else if($response['error']['code'] == 109){
                            //     $msj = "URL de notificación incorrecta";
                            // }else if($response['error']['code'] == 110){
                            //     $msj = "Se superó el número máximo de piezas permitido o número incorrecto de piezas";
                            // }else if($response['error']['code'] == 111){
                            //     $msj = "Crédito/Saldo insuficiente";
                            // }else if($response['error']['code'] == 112){
                            //     $msj = "Dirección IP no permitida";
                            // }else if($response['error']['code'] == 113){
                            //     $msj = "Codificación no válida";
                            // }else{
                            //     $msj = $response['error']['description'];
                            // }
                            return back()->with('danger', 'Respuesta API 360nrs: '.$msj);
                        }else{
                            return back()->with('success', 'Respuesta API 360nrs: Mensaje enviado correctamente');
                        }
                    }
                }
            }
            if($servicio->nombre == 'Hablame SMS'){
                if($servicio->api_key && $servicio->user && $servicio->pass){
                    $post['toNumber'] = $numero;
                    $post['sms'] = $mensaje;

                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => 'https://api103.hablame.co/api/sms/v3/send/marketing',
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

                    $response = json_decode($result, true);
                    if(isset($response['error'])){
                        if($response['error']['code'] == 1000303){
                            $msj = 'Cuenta no encontrada';
                        }else{
                            $msj = $response['error']['details'];
                        }
                        $factura->response = $msj;
                        $factura->save();
                        return back()->with('danger', 'Envío Fallido: '.$msj);
                    }else{
                        if($response['status'] == '1x000'){
                            $msj = 'SMS recíbido por hablame exitosamente';
                        }else if($response['status'] == '1x152'){
                            $msj = 'SMS entregado al operador';
                        }else if($response['status'] == '1x153'){
                            $msj = 'SMS entregado al celular';
                        }
                        $factura->response = $msj;
                        $factura->save();
                        return back()->with('success', 'Envío Éxitoso: '.$msj);
                    }
                }else{
                    $mensaje = 'EL MENSAJE NO SE PUDO ENVIAR PORQUE FALTA INFORMACIÓN EN LA CONFIGURACIÓN DEL SERVICIO';
                    return back()->with('danger', $mensaje);
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

                    if ($err) {

                    }else{
                        $response = json_decode($result, true);
                        if(isset($response['error'])){
                            if($response['error']['code'] == 102){
                                $msj = "No hay destinatarios válidos (Cumpla con el formato de nro +5700000000000)";
                            }else if($response['error']['code'] == 103){
                                $msj = "Nombre de usuario o contraseña desconocidos";
                            }else if($response['error']['code'] == 104){
                                $msj = "Falta el mensaje de texto";
                            }else if($response['error']['code'] == 105){
                                $msj = "Mensaje de texto demasiado largo";
                            }else if($response['error']['code'] == 106){
                                $msj = "Falta el remitente";
                            }else if($response['error']['code'] == 107){
                                $msj = "Remitente demasiado largo";
                            }else if($response['error']['code'] == 108){
                                $msj = "No hay fecha y hora válida para enviar";
                            }else if($response['error']['code'] == 109){
                                $msj = "URL de notificación incorrecta";
                            }else if($response['error']['code'] == 110){
                                $msj = "Se superó el número máximo de piezas permitido o número incorrecto de piezas";
                            }else if($response['error']['code'] == 111){
                                $msj = "Crédito/Saldo insuficiente";
                            }else if($response['error']['code'] == 112){
                                $msj = "Dirección IP no permitida";
                            }else if($response['error']['code'] == 113){
                                $msj = "Codificación no válida";
                            }else{
                                $msj = $response['error']['description'];
                            }
                            $factura->response = $msj;
                            $factura->save();
                            return back()->with('danger', 'Envío Fallido: '.$msj);
                        }else{
                            $factura->response = 'Mensaje enviado correctamente.';
                            $factura->save();
                            return back()->with('success', 'Mensaje enviado correctamente.');
                        }
                    }
                }else{
                    $mensaje = 'EL MENSAJE NO SE PUDO ENVIAR PORQUE FALTA INFORMACIÓN EN LA CONFIGURACIÓN DEL SERVICIO';
                    return back()->with('danger', $mensaje);
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

                    if ($err) {

                    }else{
                        $response = json_decode($result, true);
                        if(isset($response['error'])){
                            if($response['error']['code'] == 102){
                                $msj = "No hay destinatarios válidos (Cumpla con el formato de nro +5700000000000)";
                            }else if($response['error']['code'] == 103){
                                $msj = "Nombre de usuario o contraseña desconocidos";
                            }else if($response['error']['code'] == 104){
                                $msj = "Falta el mensaje de texto";
                            }else if($response['error']['code'] == 105){
                                $msj = "Mensaje de texto demasiado largo";
                            }else if($response['error']['code'] == 106){
                                $msj = "Falta el remitente";
                            }else if($response['error']['code'] == 107){
                                $msj = "Remitente demasiado largo";
                            }else if($response['error']['code'] == 108){
                                $msj = "No hay fecha y hora válida para enviar";
                            }else if($response['error']['code'] == 109){
                                $msj = "URL de notificación incorrecta";
                            }else if($response['error']['code'] == 110){
                                $msj = "Se superó el número máximo de piezas permitido o número incorrecto de piezas";
                            }else if($response['error']['code'] == 111){
                                $msj = "Crédito/Saldo insuficiente";
                            }else if($response['error']['code'] == 112){
                                $msj = "Dirección IP no permitida";
                            }else if($response['error']['code'] == 113){
                                $msj = "Codificación no válida";
                            }else{
                                $msj = $response['error']['description'];
                            }
                            $factura->response = $msj;
                            $factura->save();
                            return back()->with('danger', 'Envío Fallido: '.$msj);
                        }else{
                            $factura->response = 'Mensaje enviado correctamente.';
                            $factura->save();
                            return back()->with('success', 'Mensaje enviado correctamente.');
                        }
                    }
                }else{
                    $mensaje = 'EL MENSAJE NO SE PUDO ENVIAR PORQUE FALTA INFORMACIÓN EN LA CONFIGURACIÓN DEL SERVICIO';
                    return back()->with('danger', $mensaje);
                }
            }
        }else{
            return back()->with('danger', 'DISCULPE, NO POSEE NINGUN SERVICIO DE SMS HABILITADO. POR FAVOR HABILÍTELO PARA DISFRUTAR DEL SERVICIO');
        }
    }

    public function promesa_pago($id){
        $factura = Factura::where('id', $id)->first();

        // Buscar la promesa de pago existente para esta factura
        $promesaExistente = PromesaPago::where('factura', $id)->first();

        $response = [
            'id' => $factura->id,
            'promesa_pago' => $factura->promesa_pago ? date('d-m-Y', strtotime($factura->promesa_pago)) : null,
            'promesa_existente' => $promesaExistente ? [
                'id' => $promesaExistente->id,
                'vencimiento' => $promesaExistente->vencimiento ? date('d-m-Y', strtotime($promesaExistente->vencimiento)) : null,
                'hora_pago' => $promesaExistente->hora_pago
            ] : null
        ];

        return json_encode($response);
    }


    public function store_promesa(Request $request) {

        $request->validate([
            'id' => 'required',
            'promesa_pago' => 'required',
            'hora_pago' => 'required',
        ]);

        $factura = Factura::where('id', $request->id)->first();
        $empresa = Empresa::find($factura->empresa);

        // Buscar si ya existe una promesa de pago para esta factura
        $promesa_pago = PromesaPago::where('factura', $factura->id)->first();

        if ($promesa_pago) {
            // Actualizar la promesa existente
            $promesa_pago->vencimiento = date('Y-m-d', strtotime($request->promesa_pago));
            $promesa_pago->hora_pago = $request->hora_pago;
            $promesa_pago->updated_by = Auth::user()->id;
            $promesa_pago->save();

            $mensajeObservaciones = ' | Factura Editada por: '.Auth::user()->nombres.' el '.date('d-m-Y g:i:s A'). ' para editar Promesa de Pago Nro. '.$promesa_pago->nro;

            // Registro de log para edición de promesa de pago
            $movimiento = new MovimientoLOG();
            $movimiento->contrato    = $factura->id;
            $movimiento->modulo      = 8;
            $movimiento->descripcion = '<i class="fas fa-check text-success"></i> <b>Se editó la promesa de pago</b> Nro. ' . $promesa_pago->nro . ' para la factura ' . $factura->codigo . ' con fecha de vencimiento ' . date('d-m-Y', strtotime($request->promesa_pago)) . ' y hora ' . $request->hora_pago;
            $movimiento->created_by  = Auth::user()->id;
            $movimiento->empresa     = $factura->empresa;
            $movimiento->save();
        } else {
            // Crear una nueva promesa de pago
            $numero = 0;
            $numero = PromesaPago::all()->count();
            $numero++;

            $promesa_pago = New PromesaPago;
            $promesa_pago->nro = $numero;
            $promesa_pago->factura = $factura->id;
            $promesa_pago->cliente = $factura->cliente;
            $promesa_pago->fecha = date('Y-m-d');
            $promesa_pago->vencimiento = date('Y-m-d', strtotime($request->promesa_pago));
            $promesa_pago->hora_pago = $request->hora_pago;
            $promesa_pago->created_by = Auth::user()->id;
            $promesa_pago->save();

            $mensajeObservaciones = 'Añadiendo Promesa de Pago | Factura Editada por: '.Auth::user()->nombres.' el '.date('d-m-Y g:i:s A'). ' para añadir Promesa de Pago Nro. '.$promesa_pago->nro;

            // Registro de log para creación de promesa de pago
            $movimiento = new MovimientoLOG();
            $movimiento->contrato    = $factura->id;
            $movimiento->modulo      = 8;
            $movimiento->descripcion = '<i class="fas fa-check text-success"></i> <b>Se creó la promesa de pago</b> Nro. ' . $promesa_pago->nro . ' para la factura ' . $factura->codigo . ' con fecha de vencimiento ' . date('d-m-Y', strtotime($request->promesa_pago)) . ' y hora ' . $request->hora_pago;
            $movimiento->created_by  = Auth::user()->id;
            $movimiento->empresa     = $factura->empresa;
            $movimiento->save();
        }

        $factura->promesa_pago  = date('Y-m-d', strtotime($request->promesa_pago));
        $factura->vencimiento   = date('Y-m-d', strtotime($request->promesa_pago));
        $factura->observaciones = ($factura->observaciones ?? '') . $mensajeObservaciones;
        $factura->save();

        /* VERIFICAR SI EL CONTRATO ESTÁ DESHABILITADO PARA HABILITARLO */

        $contratos = $factura->contratos();

        foreach($contratos as $contrato){

            $contrato = Contrato::where('nro',$contrato->contrato_nro)->first();

            if($contrato){

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
                /* * * API CATV * * */


                // Validar que el contrato tenga server_configuration_id antes de conectar a Mikrotik
                if($contrato->server_configuration_id != null) {
                    $mikrotik = Mikrotik::find($contrato->server_configuration_id);

                    if($mikrotik) {
                        $API = new RouterosAPI();
                        $API->port = $mikrotik->puerto_api;

                        if ($API->connect($mikrotik->ip,$mikrotik->usuario,$mikrotik->clave)) {
                            $API->write('/ip/firewall/address-list/print', TRUE);
                            $ARRAYS = $API->read();

                            #HABILITACION DEL SECRET#
                            if(isset($empresa->activeconn_secret) && $empresa->activeconn_secret == 1){

                                if($contrato->conexion == 1 && $contrato->usuario != null){
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
                                    }
                                }
                                #HABILITACION DEL SECRET#

                            }else{

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


                                        $mensaje = "- Se ha sacado la ip de morosos.";
                                        $contrato->state = 'enabled';
                                        $contrato->save();

                                    }else{
                                        Log::info('Contrato nro:' . $contrato->nro . ' no se pudo sacar de morosos desde promesa de pago');
                                    }
                                    #ELIMINAMOS DE MOROSOS#
                                }else{
                                    Log::info('Contrato nro:' . $contrato->nro . ' no estaba en morosos desde promesa de pago');
                                }
                                #ELIMINAMOS DE MOROSOS#
                            }

                            #AGREGAMOS A IP_AUTORIZADAS#
                            $API->comm("/ip/firewall/address-list/add", array(
                                "address" => $contrato->ip,
                                "list" => 'ips_autorizadas'
                                )
                            );
                            #AGREGAMOS A IP_AUTORIZADAS#

                            $contrato->state = 'enabled';
                            $contrato->save();
                            $API->disconnect();
                        }
                    }
                }
            }

        }

        return response()->json([
            'success' => true,
            'type' => 'success',
            'message' => 'Registrada con Éxito',
            'promesa_pago' => date('d-m-Y', strtotime($factura->promesa_pago))
        ]);
    }

    public function ImprimirElec($id, $tipo='original', $especialFe = false){
        $tipo1=$tipo;

        /**
         * * toma en cuenta que para ver los mismos
         * * datos debemos hacer la misma consulta
         **/

         $empresa = Auth::user()->empresaObj;

        $factura = ($especialFe) ? Factura::where('nonkey', $id)->first() : Factura::where('empresa',$empresa->id)->where('id', $id)->first();

        if($factura->tipo == 1){
            view()->share(['title' => 'Imprimir Factura']);
            if ($tipo<>'original') {
                $tipo='Copia Factura de Venta';
            }else{
                $tipo='Factura de Venta Original';
            }
        }elseif($factura->tipo == 3){
            view()->share(['title' => 'Imprimir Cuenta de Cobro']);
            if ($tipo<>'original') {
                $tipo='Cuenta de Cobro Copia';
            }else{
                $tipo='Cuenta de Cobro Original';
            }
        }


        $resolucion = ($especialFe) ? NumeracionFactura::where('empresa', $factura->empresa)->latest()->first() : NumeracionFactura::where('empresa',$empresa->id)->latest()->first();

        if ($factura) {
            $items = ItemsFactura::where('factura',$factura->id)->get();
            $itemscount=ItemsFactura::where('factura',$factura->id)->count();
            $retenciones = FacturaRetencion::where('factura', $factura->id)->get();
            $ingreso = IngresosFactura::where('factura',$factura->id)->first();
            if($factura->emitida == 1){
                $impTotal = 0;
                foreach ($factura->total()->imp as $totalImp){
                    if(isset($totalImp->total)){
                        $impTotal = $totalImp->total;
                    }
                }

                $CUFEvr = $factura->info_cufe($factura->id, $impTotal);
                $infoEmpresa = Empresa::find(Auth::user()->empresa);
                $data['Empresa'] = $infoEmpresa->toArray();
                $infoCliente = Contacto::find($factura->cliente);
                $data['Cliente'] = $infoCliente->toArray();

                /*..............................
                Construcción del código qr a la factura
                ................................*/

                $impuesto = 0;
                foreach ($factura->total()->imp as $key => $imp) {
                    if(isset($imp->total)){
                        $impuesto = $imp->total;
                    }
                }

                $codqr = "NumFac:" . $factura->codigo . "\n" .
                "NitFac:"  . $data['Empresa']['nit']   . "\n" .
                "DocAdq:" .  $data['Cliente']['nit'] . "\n" .
                "FecFac:" . Carbon::parse($factura->created_at)->format('Y-m-d') .  "\n" .
                "HoraFactura" . Carbon::parse($factura->created_at)->format('H:i:s').'-05:00' . "\n" .
                "ValorFactura:" .  number_format($factura->total()->subtotal, 2, '.', '') . "\n" .
                "ValorIVA:" .  number_format($impuesto, 2, '.', '') . "\n" .
                "ValorOtrosImpuestos:" .  0.00 . "\n" .
                "ValorTotalFactura:" .  number_format($factura->total()->subtotal + $factura->impuestos_totales(), 2, '.', '') . "\n" .
                "CUFE:" . $CUFEvr;

                /*..............................
                Construcción del código qr a la factura
                ................................*/
                if($empresa->formato_impresion == 1){
                    $pdf = PDF::loadView('pdf.electronica', compact('items', 'factura', 'itemscount', 'tipo', 'retenciones','resolucion','codqr','CUFEvr','ingreso'));
                }
                else{
                    $pdf = PDF::loadView('pdf.factura', compact('items', 'factura', 'itemscount', 'tipo', 'retenciones','resolucion','codqr','CUFEvr','ingreso'));
                }
            }else{
                $fechaActual = date("Y-m-d", strtotime(Carbon::now()));
                //traemos todas las facturas que el vencimiento haya pasado la fecha actual.
                $facturasVencidas = Factura::where('cliente',$factura->cliente)->where('vencimiento','<',$fechaActual)->get();
                $saldoMesAnterior=0;

                //sumamos todo lo que deba el cliente despues de la fecha de vencimiento
                foreach($facturasVencidas as $vencida){
                    $saldoMesAnterior+=$vencida->porpagar();
                }

                // return response()->json($factura->estadoCuenta()->saldoMesAnterior);

                // $codqr = "NumFac:" . $factura->codigo . "\n" .
                // "NitFac:"  . "121234234"   . "\n" .
                // "DocAdq:" .  "121234234" . "\n" .
                // "FecFac:" . Carbon::parse($factura->created_at)->format('Y-m-d') .  "\n" .
                // "HoraFactura" . Carbon::parse($factura->created_at)->format('H:i:s').'-05:00' . "\n" .
                // "ValorFactura:" .  number_format($factura->total()->subtotal, 2, '.', '') . "\n" .
                // "ValorIVA:" .  number_format(12000, 2, '.', '') . "\n" .
                // "ValorOtrosImpuestos:" .  0.00 . "\n" .
                // "ValorTotalFactura:" .  number_format($factura->total()->subtotal + $factura->impuestos_totales(), 2, '.', '') . "\n";
                //   return view('pdf.electronica', compact('items', 'factura', 'itemscount', 'tipo', 'retenciones','resolucion','codqr'));

                if($empresa->formato_impresion == 1){
                    $pdf = PDF::loadView('pdf.electronica', compact('items', 'factura', 'itemscount', 'tipo', 'retenciones','resolucion','ingreso'));
                }else{
                    $pdf = PDF::loadView('pdf.factura', compact('items', 'factura', 'itemscount', 'tipo', 'retenciones','resolucion','ingreso'));
                }
            }
            return  response ($pdf->stream())->withHeaders(['Content-Type' =>'application/pdf']);
        }
    }

    public function ImprimirMultiple($facturas, $tipo='original', $especialFe = false){
        /**
         * * toma en cuenta que para ver los mismos
         * * datos debemos hacer la misma consulta
         **/
        $empresa = Auth::user()->empresaObj;
        $facturas_id = explode(",", $facturas);
        $items = [];
        $facturas = [];
        $itemscount = [];
        $tipo = [];
        $retenciones = [];
        $resolucion = [];
        $ingreso = [];
        foreach ($facturas_id as $factura_id){
            $factura_obj = ($especialFe) ? Factura::where('nonkey', $factura_id)->first() : Factura::where('empresa',$empresa->id)->where('id', $factura_id)->first();
            $facturas[$factura_id] = $factura_obj;
            if($factura_obj->tipo == 1){
                view()->share(['title' => 'Imprimir Factura']);
                if ($tipo<>'original') {
                    $tipo[$factura_id]='Copia Factura de Venta';
                }else{
                    $tipo[$factura_id]='Factura de Venta Original';
                }
            }elseif($factura_obj->tipo == 3){
                view()->share(['title' => 'Imprimir Cuenta de Cobro']);
                if ($tipo<>'original') {
                    $tipo[$factura_id]='Cuenta de Cobro Copia';
                }else{
                    $tipo[$factura_id]='Cuenta de Cobro Original';
                }
            }
            $resolucion[$factura_id] = ($especialFe) ? NumeracionFactura::where('empresa', $factura_obj->empresa)->latest()->first() : NumeracionFactura::where('empresa',$empresa->id)->latest()->first();
            if ($factura_obj) {
                $items[$factura_id] = ItemsFactura::where('factura',$factura_obj->id)->get();
                $itemscount[$factura_id]=ItemsFactura::where('factura',$factura_obj->id)->count();
                $retenciones[$factura_id] = FacturaRetencion::where('factura', $factura_obj->id)->get();
                $ingreso[$factura_id] = IngresosFactura::where('factura',$factura_obj->id)->first();
            }
        }
        if(!Auth::user())
        {
            $empresa = Empresa::Find(1);
        }else{
            $empresa = Auth::user()->empresa();
        }
        if($empresa->formato_impresion == 1){
            $pdf = PDF::loadView('pdf.factura_multiple', compact('items', 'facturas', 'itemscount', 'tipo', 'retenciones','resolucion','ingreso','empresa'));
        }else{
            $pdf = PDF::loadView('pdf.factura_estandar_multiple', compact('items', 'facturas', 'itemscount', 'tipo', 'retenciones','resolucion','ingreso'));
        }
        return  response ($pdf->stream())->withHeaders(['Content-Type' =>'application/pdf']);
    }

    public function exportData(){
        $facturas =  Factura::join('contactos as c','c.id','factura.cliente')
        ->join('items_factura as if','if.factura','=','factura.id')
        ->select('factura.*','c.nombre','c.direccion', 'c.nit', 'c.celular','c.email','if.ref','if.precio','if.descripcion as nombreItem')
        ->get();
         $objPHPExcel = new PHPExcel();

        $tituloReporte = "Reporte de Contactos de Facturas";
        $titulosColumnas = array(
            'cliente',
            'cedula',
            'fecha',
            'vencimiento',
            'item',
            'ref',
            'email',
            'precio',
            'direccion',
            'Telefono',

        );
        $letras = array(
            'A',
            'B',
            'C',
            'D',
            'E',
            'F',
            'G',
            'H',
            'I',
            'J',
            'K',
            'L',
            'M',
            'N',
            'O',
            'P',
            'Q',
            'R',
            'S',
            'T',
            'U',
            'V',
            'W',
            'X',
            'Y',
            'Z'
        );

        $objPHPExcel->getProperties()->setCreator("Sistema") // Nombre del autor
            ->setLastModifiedBy("Sistema") //Ultimo usuario que lo modific
            ->setTitle("Reporte Excel Contactos") // Titulo
            ->setSubject("Reporte Excel Contactos") //Asunto
            ->setDescription("Reporte de Contactos") //Descripcin
            ->setKeywords("reporte Contactos") //Etiquetas
            ->setCategory("Reporte excel"); //Categorias
        // Se combinan las celdas A1 hasta D1, para colocar ah el titulo del reporte
        $objPHPExcel->setActiveSheetIndex(0)
            ->mergeCells('A1:D1');
        // Se agregan los titulos del reporte
        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', $tituloReporte);
        // Titulo del reporte
        $objPHPExcel->setActiveSheetIndex(0)
            ->mergeCells('A2:C2');
        // Se agregan los titulos del reporte
        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A2', 'Fecha ' . date('d-m-Y')); // Titulo del reporte

        $estilo = array(
            'font' => array('bold' => true, 'size' => 12, 'name' => 'Times New Roman'),
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            )
        );
        $objPHPExcel->getActiveSheet()->getStyle('A1:V3')->applyFromArray($estilo);

        $estilo = array(
            'fill' => array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => 'd08f50')
            )
        );
        $objPHPExcel->getActiveSheet()->getStyle('A3:V3')->applyFromArray($estilo);


        for ($i = 0; $i < count($titulosColumnas); $i++) {
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($letras[$i] . '3', utf8_decode($titulosColumnas[$i]));
        }

        $i = 4;
        $letra = 0;

        $empresa = Empresa::find(Auth::user()->empresa);
        foreach ($facturas as $factura) {
            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue($letras[0] . $i,  $factura->nombre)
                ->setCellValue($letras[1] . $i, $factura->nit)
                ->setCellValue($letras[2] . $i, $factura->fecha)
                ->setCellValue($letras[3] . $i, $factura->vencimiento)
                ->setCellValue($letras[4] . $i, $factura->nombreItem)
                ->setCellValue($letras[5] . $i, $factura->ref)
                ->setCellValue($letras[6] . $i, $factura->email)
                ->setCellValue($letras[7] . $i, $factura->precio)
                ->setCellValue($letras[8] . $i, $factura->direccion)
                ->setCellValue($letras[9] . $i, $factura->celular);


            $i++;
        }

        $estilo = array(
            'font' => array('size' => 12, 'name' => 'Times New Roman'),
            'borders' => array(
                'allborders' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN
                )
            ),
            'alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,)
        );
        $objPHPExcel->getActiveSheet()->getStyle('A3:V' . $i)->applyFromArray($estilo);

        for ($i = 'A'; $i <= $letras[20]; $i++) {
            $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension($i)->setAutoSize(true);
        }

        // Se asigna el nombre a la hoja
        $objPHPExcel->getActiveSheet()->setTitle('Reporte de Contactos');

        // Se activa la hoja para que sea la que se muestre cuando el archivo se abre
        $objPHPExcel->setActiveSheetIndex(0);

        // Inmovilizar paneles
        $objPHPExcel->getActiveSheet(0)->freezePane('A5');
        $objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0, 4);
        $objPHPExcel->setActiveSheetIndex(0);
        header("Pragma: no-cache");
        header('Content-type: application/vnd.ms-excel');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="Reporte_Contactos.xlsx"');
        header('Cache-Control: max-age=0');
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');
        exit;
    }

    public function updateContratoId(){
        $facturas = Factura::all();

        foreach($facturas as $factura){
            //a cada favtura vamos a buscarle su contrato si tiene se le asocia la nueva id si no se salta
            $contrato = Contrato::where('client_id',$factura->cliente)->first();

            if($contrato){
                $factura->contrato_id = $contrato->id;
                $factura->save();
            }
        }

        return "Actualizacion lista";
    }

    public function xmlFacturaVentaMasivo($id)
    {
        $FacturaVenta = Factura::find($id);
        if (!$FacturaVenta) {
            return redirect('/empresa/factura-index')->with('error', "No se ha encontrado la factura de venta, comuniquese con soporte.");
        }

        //Validacion de dia 00 en vencimiento
        if (substr($FacturaVenta->vencimiento, -2) == '00' || $FacturaVenta->vencimiento < Carbon::now()->format("Y-m-d")) {
            $anoMes = substr($FacturaVenta->vencimiento, 0, 7);
            $fecha = Carbon::createFromFormat('Y-m', $anoMes)->endOfMonth();
            $FacturaVenta->vencimiento = $fecha->toDateString();
            $FacturaVenta->save();
        }

        //Validacion de dia 00 en suspension
        if (substr($FacturaVenta->suspension, -2) == '00' || $FacturaVenta->suspension < Carbon::now()->format("Y-m-d")) {
            $anoMes = substr($FacturaVenta->suspension, 0, 7);
            $fecha = Carbon::createFromFormat('Y-m', $anoMes)->endOfMonth();
            $FacturaVenta->suspension = $fecha->toDateString();
            $FacturaVenta->save();
        }

        $ResolucionNumeracion = NumeracionFactura::where('empresa', Auth::user()->empresa)->
        where('num_equivalente', 0)->
        where('nomina',0)->where('tipo',2)->
        where('preferida', 1)->first();

        $ValidateInvoices = Factura::where('empresa', auth()->user()->empresa)->where('codigo', $FacturaVenta->codigo)
        ->where('id', '!=', $FacturaVenta->id)
        ->count();

        $empresaId = auth()->user()->empresa;

        if($ValidateInvoices > 0){
            while(Factura::where('empresa', $empresaId)->
            where('codigo', $FacturaVenta->codigo)->
            where('id', '!=', $FacturaVenta->id)->count() > 0){

                $FacturaVenta->codigo = $ResolucionNumeracion->prefijo . $ResolucionNumeracion->inicio;
                $FacturaVenta->save();

                $ResolucionNumeracion->inicio++;
                $ResolucionNumeracion->save();

                $ResolucionNumeracion->fresh();
                $FacturaVenta->fresh();
            }
        }

        $FacturaVenta->emitida = $FacturaVenta->emitida;
        $FacturaVenta->save();

        $infoEmpresa = Auth::user()->empresaObj;
        $data['Empresa'] = $infoEmpresa->toArray();

        $retenciones = FacturaRetencion::where('factura', $FacturaVenta->id)->get();

        $impTotal = 0;

        foreach ($FacturaVenta->total()->imp as $totalImp) {
            if (isset($totalImp->total)) {
                $impTotal += $totalImp->total;
            }
        }
        $items = ItemsFactura::where('factura', $id)->get();

        $decimal = explode(".", $impTotal);
        if (
            isset($decimal[1]) && $decimal[1] >= 50 || isset($decimal[1]) && $decimal[1] == 5 || isset($decimal[1]) && $decimal[1] == 4
            || isset($decimal[1]) && $decimal[1] == 3 || isset($decimal[1]) && $decimal[1] == 2 || isset($decimal[1]) && $decimal[1] == 1
        ) {
            $impTotal = round($impTotal);
        } else {
            $impTotal = round($impTotal);
        }


        $CUFEvr = $FacturaVenta->info_cufe($FacturaVenta->id, $impTotal);

        $infoCliente = Contacto::find($FacturaVenta->cliente);
        $data['Cliente'] = $infoCliente->toArray();


        $responsabilidades_empresa = DB::table('empresa_responsabilidad as er')
            ->join('responsabilidades_facturacion as rf', 'rf.id', '=', 'er.id_responsabilidad')
            ->select('rf.*')
            ->where('er.id_empresa', '=', Auth::user()->empresa)->where('er.id_responsabilidad', 5)
            ->orWhere('er.id_responsabilidad', 7)->where('er.id_empresa', '=', Auth::user()->empresa)
            ->orWhere('er.id_responsabilidad', 12)->where('er.id_empresa', '=', Auth::user()->empresa)
            ->orWhere('er.id_responsabilidad', 20)->where('er.id_empresa', '=', Auth::user()->empresa)
            ->orWhere('er.id_responsabilidad', 29)->where('er.id_empresa', '=', Auth::user()->empresa)->get();

        //-- Construccion del pdf a enviar con el código qr + el envío del archivo xml --//
        if ($FacturaVenta) {
            $emails = $FacturaVenta->cliente()->email;
            if ($FacturaVenta->cliente()->asociados('number') > 0) {
                $email = $emails;
                $emails = array();
                if ($email) {
                    $emails[] = $email;
                }
                foreach ($FacturaVenta->cliente()->asociados() as $asociado) {
                    if ($asociado->notificacion == 1 && $asociado->email) {
                        $emails[] = $asociado->email;
                    }
                }
            }

            $tituloCorreo =  $data['Empresa']['nit'] . ";" . $data['Empresa']['nombre'] . ";" . $FacturaVenta->codigo . ";01;" . $data['Empresa']['nombre'];

            $isImpuesto = 1;

            //-- Generación del XML a enviar a la DIAN -- //
            $xml = view('templates.xml.01', compact('CUFEvr', 'ResolucionNumeracion', 'FacturaVenta', 'data', 'items', 'retenciones', 'responsabilidades_empresa', 'emails', 'impTotal', 'isImpuesto'));

            //-- Envío de datos a la DIAN --//
            $res = $this->EnviarDatosDian($xml);

            //-- Decodificación de respuesta de la DIAN --//
            $res = json_decode($res, true);



            if (isset($res['errorType'])) {
                if ($res['errorType'] == "KeyError") {
                    return back()->with('message_denied', "La dian está presentando problemas para emitir documentos electrónicos, inténtelo más tarde.");
                }
            }

            if (!isset($res['statusCode']) && isset($res['message'])) {
                return redirect('/empresa/factura-index')->with('message_denied', $res['message']);
            }

            $statusCode = $res['statusCode'] ?? null; //200

            if (!isset($statusCode)) {
                return back()->with('message_denied', isset($res['message']) ? $res['message'] : 'Error en la emisión del docuemento, intente nuevamente en un momento');
            }

            //-- Guardamos la respuesta de la dian solo cuando son errores--//
            if ($statusCode != 200) {
                $FacturaVenta->dian_response = $res['statusCode'] ?? null;
                $FacturaVenta->save();
            }

            //-- Validación 1 del status code (Cuando hay un error) --//
            if ($statusCode != 200) {
                $message = $res['errorMessage'];
                $errorReason = $res['errorReason'];

                //Validamos si depronto la factura fue emitida pero no quedamos con ningun registro de ella.
                $saveNoJson = $statusJson = $this->validateStatusDian(auth()->user()->empresaObj->nit, $FacturaVenta->codigo, "01", $ResolucionNumeracion->prefijo);

                $statusJson = json_decode($statusJson, true);

                if ($statusJson["statusCode"] == 200) {

                    //linea comentada por ahorro de espacio en bd, ay que esta información de las facturas procesadas se puede obtener mediante consulta api.
                    // $FacturaVenta->dian_response = $saveNoJson;
                    $message = "Factura emitida correctamente por validación";
                    $FacturaVenta->emitida = 1;
                    $FacturaVenta->fecha_expedicion = Carbon::now();

                    //Llave unica para acceso por correo
                    $key = Hash::make(date("H:i:s"));
                    $toReplace = array('/', '$', '.');
                    $key = str_replace($toReplace, "", $key);
                    $FacturaVenta->nonkey = $key;

                    $FacturaVenta->save();

                    $this->generateXmlPdfEmail($statusJson['document'], $FacturaVenta, $emails, $data, $CUFEvr, $items, $ResolucionNumeracion, $tituloCorreo);
                } else {
                    return back()->with('message_denied', $message)->with('errorReason', $errorReason);
                }
            }

            $document = $res['document'];

            //-- estátus de que la factura ha sido aprobada --//
            if ($statusCode == 200) {

                //Llave unica para acceso por correo
                $key = Hash::make(date("H:i:s"));
                $toReplace = array('/', '$', '.');
                $key = str_replace($toReplace, "", $key);
                $FacturaVenta->nonkey = $key;
                $FacturaVenta->save();
                //

                $message = "Factura emitida correctamente";
                $FacturaVenta->emitida = 1;
                $FacturaVenta->fecha_expedicion = Carbon::now();
                $FacturaVenta->save();

                $this->generateXmlPdfEmail($document, $FacturaVenta, $emails, $data, $CUFEvr, $items, $ResolucionNumeracion, $tituloCorreo);
            }
        }
    }

    public function downloadXML($id)
    {
        $factura = Factura::find($id);
        $modoBTW = env('BTW_TEST_MODE') == 1 ? 'test' : 'prod';
        if(!$factura){
            return back()->with('error', 'No se ha encontrado la factura');
        }
        $empresa = Empresa::Find($factura->empresa);

        $btw = new BTWService();
        $data = [
            'nit'       => $empresa->nit,
            'codigo'    => $factura->codigo,
            'mode'      => $modoBTW,
            'btw_login' => $empresa->btw_login
        ];

        $response = $btw->downloadXML($data);

        if(!isset($response->status)){
            return back()->with('error', 'No se ha encontrado la factura (sin respuesta de BTW)');
        }

        if($response->status == 'success'){
            // Decodificar el XML desde base64
            $xmlContent = base64_decode($response->xml);

            // Remover el BOM UTF-8 si está presente (77u/ = BOM en base64)
            $xmlContent = preg_replace('/^\xEF\xBB\xBF/', '', $xmlContent);

            // Generar la respuesta de descarga
            $headers = [
                'Content-Type' => 'application/xml',
                'Content-Disposition' => 'attachment; filename="' . $factura->codigo . '.xml"',
            ];

            return response($xmlContent, 200, $headers);
        }
    }

    public function downloadEfecty(){
        $valor = 0;
        $facturas = Factura::query()
            ->join('contactos as c', 'factura.cliente', '=', 'c.id')
            ->join('items_factura as if', 'factura.id', '=', 'if.factura')
            ->leftJoin('contracts as cs', 'c.id', '=', 'cs.client_id')
            ->leftJoin('vendedores as v', 'factura.vendedor', '=', 'v.id')
            ->select('factura.id',
                DB::raw('c.nit as referencia'),
                DB::raw('SUM((if.cant*if.precio)-(if.precio*(if(if.desc,if.desc,0)/100)*if.cant)+(if.precio-(if.precio*(if(if.desc,if.desc,0)/100)))*(if.impuesto/100)*if.cant) as valor'),
                'factura.created_at',
                DB::raw('c.nombre as campo1'),
                DB::raw('c.apellido1 as campo2'),
                DB::raw('c.apellido2 as campo3'),
                'factura.codigo as campo5')
            ->groupBy('factura.id')
            ->where('factura.estatus', 1)
            ->get();

        $filePath = "efecty-".date('dmYHi').".txt";
        $file = fopen($filePath, "w");
        fputs($file, '"01"|REFERENCIA|VALOR|FECHA|CAMPO1|CAMPO2|CAMPO3|CAMPO4|CAMPO5'.PHP_EOL);
        foreach($facturas as $factura){
            $campo1 = ($factura->campo1) ? $factura->campo1 : 'NA';
            $campo2 = ($factura->campo2) ? $factura->campo2 : 'NA';
            $campo3 = ($factura->campo3) ? $factura->campo3 : 'NA';

            fputs($file, '"02"|"'.$factura->referencia.'"|'.round($factura->total()->total).'|'.$factura->created_at.'|"'.$campo1.'"|"'.$campo2.'"|"'.$campo3.'"|"NA"|"'.$factura->campo5.'"'.PHP_EOL);
            $valor += $factura->total()->total;
        }
        fputs($file, '"03"|'.count($facturas).'|'.round($valor).'|'.date('Y-m-d H:i:s').'|||||'.PHP_EOL);
        fclose($file);

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename='.basename($filePath));
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
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

    public function whatsapp($id, Request $request)
    {
        // 1️⃣ Buscar instancia de Meta Direct activa
        $instance = Instance::where('company_id', auth()->user()->empresa)
                            ->where('activo', 1)
                            ->where('type', 1) // 1 = Meta Direct
                            ->where('meta', 0) // 0 = Integración directa
                            ->whereNotNull('phone_number_id')
                            ->first();

        if (!$instance || empty($instance->phone_number_id)) {
            \Log::warning('No active Meta Direct instance or missing phone_number_id for company: ' . auth()->user()->empresa);
            return back()->with('danger', 'No se encontró una instancia de WhatsApp Meta activa o falta el ID de número de teléfono. Por favor, verifique su configuración.');
        }

        $factura = Factura::findOrFail($id);
        $contacto = $factura->cliente();
        
        // Determinar prefijo telefónico
        $prefijo = '57'; 
        if (!empty($contacto->fk_idpais)) {
            $prefijoData = \DB::table('prefijos_telefonicos')
                ->where('iso2', strtoupper($contacto->fk_idpais))
                ->first();
            if ($prefijoData && !empty($prefijoData->phone_code)) {
                $prefijo = $prefijoData->phone_code;
            }
        }

        $metaService = new \App\Services\MetaWhatsAppService();

        // 🧩 GENERAR Y GUARDAR PDF TEMPORALMENTE
        $token = config('app.key');
        $this->getFacturaTemp($id, $token);

        $fileName = 'Factura_' . $factura->codigo . '.pdf';
        $relativePath = 'temp/' . $fileName;
        $storagePath = storage_path('app/public/' . $relativePath);

        // Esperar hasta que el archivo exista (máx. 5 intentos)
        $attempts = 0;
        while (!file_exists($storagePath) && $attempts < 5) {
            usleep(300000); // 0.3 segundos
            $attempts++;
        }

        if (!file_exists($storagePath)) {
            return back()->with('danger', 'No se pudo generar el archivo PDF temporal.');
        }

        $urlFactura = url('storage/temp/' . $fileName);
        $empresaObj = auth()->user()->empresa();
        $estadoCuenta = $factura->estadoCuenta();
        $total = $factura->total()->total;
        $saldo = $estadoCuenta->saldoMesAnterior > 0 ? $estadoCuenta->saldoMesAnterior + $total : $total;

        // Buscar plantilla preferida para facturas
        $plantilla = Plantilla::where('preferida_cron_factura', 1)
            ->where('tipo', 3)
            ->where('status', 1)
            ->where('empresa', auth()->user()->empresa)
            ->first();
            
        if (!$plantilla) {
            return back()->with('danger', 'No tiene seleccionada una plantilla para facturas por defecto.');
        }
            
        // Procesar parámetros de la plantilla
        $bodyTextParams = [];
        if ($plantilla->body_dinamic) {
            $bodyDinamicArray = json_decode($plantilla->body_dinamic, true);
            if (is_array($bodyDinamicArray) && isset($bodyDinamicArray[0]) && is_array($bodyDinamicArray[0])) {
                $bodyDinamicArray = $bodyDinamicArray[0];
            }

            if (is_array($bodyDinamicArray)) {
                foreach ($bodyDinamicArray as $paramTemplate) {
                    $paramValue = is_string($paramTemplate) ? $paramTemplate : '';
                    $paramValue = CamposDinamicosHelper::procesarCamposDinamicos($paramValue, $contacto, $factura, $empresaObj);
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
                    $empresaObj->nombre,
                    number_format($saldo, 0, ',', '.')
                ];
            }
        }

        // Construir componentes para Meta
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

        $languageCode = $plantilla->language ?? 'es';
        if (is_array($languageCode)) {
            $languageCode = $languageCode['code'] ?? ($languageCode[0] ?? 'es');
        }
        
        $response = (object) $metaService->sendTemplate(
            $instance->phone_number_id,
            $prefijo . ltrim($contacto->celular, '0'),
            $plantilla->title,
            (string) $languageCode,
            $components
        );
        
        $responseData = json_decode(json_encode($response), true);
        
        $status = 'error';
        if (isset($responseData['success']) && $responseData['success']) {
            $status = 'success';
        } elseif (isset($responseData['messages'])) {
            $status = 'success';
        }

        // Construir el mensaje completo procesado para registro
        $mensajeProcesado = $plantilla->contenido ?? '';
        foreach ($bodyTextParams as $index => $paramValue) {
            $mensajeProcesado = str_replace('{{' . ($index + 1) . '}}', $paramValue, $mensajeProcesado);
        }

        WhatsappMetaLog::create([
            'status' => $status,
            'response' => json_encode($response),
            'factura_id' => $factura->id,
            'contacto_id' => $contacto->id,
            'empresa' => Auth::user()->empresa,
            'mensaje_enviado' => $mensajeProcesado ?: ("Meta Template: " . ($plantilla->title ?? 'Text')),
            'plantilla_id' => $plantilla ? $plantilla->id : null,
            'enviado_por' => Auth::user()->id
        ]);

        if ($status === 'success') {
            $factura->whatsapp = 1;
            $factura->save();

            // Sync con Chat System (Centralizado)
            $phone = $prefijo . ltrim($contacto->celular, '0');
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

            return back()->with('success', 'Mensaje enviado correctamente vía Meta.');
        } else {
            return back()->with('danger', 'Error al enviar mensaje Meta: ' . json_encode($responseData));
        }
    }
    
    public function whatsapp2($id,Request $request )
    {
        $factura = Factura::find($id);
        $empresa = Empresa::find($factura->empresa);
        $items = ItemsFactura::where('factura',$factura->id)->get();
        $itemscount=ItemsFactura::where('factura',$factura->id)->count();
        $retenciones = FacturaRetencion::where('factura', $factura->id)->get();
        $resolucion = NumeracionFactura::where('id',$factura->numeracion)->first();
        $tipo = $factura->tipo;

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
            $pdf = PDF::loadView('pdf.electronica', compact('items', 'factura', 'itemscount', 'tipo', 'retenciones','resolucion','codqr','CUFEvr', 'empresa'))->save(public_path() . "/convertidor/" . $factura->codigo . ".pdf")->stream();
        }else{
            $pdf = PDF::loadView('pdf.electronica', compact('items', 'factura', 'itemscount', 'tipo', 'retenciones','resolucion', 'empresa'))->save(public_path() . "/convertidor/" . $factura->codigo . ".pdf")->stream();
        }


        if(is_null($instancia) || empty($instancia)){
            return back()->with('danger', 'AUN NO HA CREADO UNA INSTANCIA CON WHATSAPP, POR FAVOR CREE UNA Y CONECTESE PARA HABILITAR ESTA OPCIÓN');
        }else{
            if($instancia->status == "0"){
                return back()->with('danger', 'LA INSTANCIA DE WHATSAPP ESTA DESCONECTADA, CONECTESE A WHATSAPP Y VUELVA A INTENTARLO');
            }
        }

        if($servicio){
            if($servicio->api_key && $servicio->numero){

                $factura = Factura::find($id);
                $plantilla = Plantilla::where('empresa', Auth::user()->empresa)->where('clasificacion', 'Facturacion')->where('tipo', 2)->where('status', 1)->get()->last();

                if($plantilla){
                    $mensaje = str_replace('{{ $company }}', Auth::user()->empresa()->nombre, $plantilla->contenido);
                    $mensaje = str_replace('{{ $name }}', ucfirst($factura->cliente()->nombre), $mensaje);
                    $mensaje = str_replace('{{ $factura->codigo }}', $factura->codigo, $mensaje);
                    $mensaje = str_replace('{{ $factura->parsear($factura->total()->total) }}', $factura->parsear($factura->total()->total), $mensaje);
                }else{
                    $mensaje = Auth::user()->empresa()->nombre.", le informa que su factura ha sido generada bajo el Nro. ".$factura->codigo.", por un monto de $".$factura->parsear($factura->total()->total);
                }


                $numero = str_replace('+','',$factura->cliente()->celular);
                $numero = str_replace(' ','',$numero);
                $numero = (substr($numero, 0, 2) == 57) ? $numero : '57'.$numero;

                $fields = [
                    "action"=>"sendFile",
                    "id"=>$numero."@c.us",
                    "file"=>public_path() . "/convertidor/" . $factura->codigo . ".pdf", // debe existir el archivo en la ubicacion que se indica aqui
                    "mime"=>"application/pdf",
                    "namefile"=>$factura->codigo,
                    "mensaje"=>$mensaje,
                    "cron"=>"true"
                ];

                $request = new Request();

                $request->merge($fields);
                $controller = new CRMController();
                $respuesta = $controller->whatsappActions($request);
                return back()->with('success', "EL MENSAJE HA SIDO ENVIADO DE MANERA EXITOSA");


            }else{
                return back()->with('danger', 'DISCULPE, EL SERVICIO CALLMEBOT NO ESTÁ CONFIGURADO. POR FAVOR CONFIGURE PARA DISFRUTAR DEL SERVICIO');
            }
        }else{
            return back()->with('danger', 'DISCULPE, NO POSEE NINGUN SERVICIO DE WHATSAPP HABILITADO. POR FAVOR HABILÍTELO PARA DISFRUTAR DEL SERVICIO');
        }
    }

    public function convertirelEctronica($facturaId, $masivo = false)
    {
        try {
            $factura = Factura::find($facturaId);

            if (!$factura) {
                $mensaje = 'Factura no encontrada con ID: ' . $facturaId;
                if (!$masivo) {
                    return back()->with('danger', $mensaje);
                }
                return [
                    'success' => false,
                    'message' => $mensaje,
                    'factura_id' => $facturaId,
                ];
            }

            $num = Factura::where('empresa', 1)->orderBy('nro', 'asc')->get()->last();
            $numero = $num ? $num->nro + 1 : 1;

            $nro = NumeracionFactura::where('empresa', Auth::user()->empresa)
                ->where('preferida', 1)
                ->where('estado', 1)
                ->where('tipo', 2)
                ->first();

            if (!$nro) {
                $mensaje = 'No se encontró una numeración para facturas DIAN preferida.';
                if (!$masivo) {
                    return back()->with('danger', $mensaje);
                }
                return [
                    'success' => false,
                    'message' => $mensaje,
                    'factura_id' => $facturaId,
                ];
            }

            // Guardar código anterior antes de actualizar
            $codigoAnterior = $factura->codigo;

            // Actualizar y guardar datos
            $inicio = $nro->inicio;
            $nro->inicio += 1;
            $nro->save();

            $factura->codigo = $nro->prefijo . $inicio;

            $codigoUsado = Factura::where('empresa', 1)
            ->where('codigo', $factura->codigo)
            ->where('id', '!=', $facturaId)
            ->where('numeracion', $nro->id)
            ->first();

            if($codigoUsado){

                if (!$masivo) {
                    return back()->with('danger', 'Revisar la ultima factura del segmento y modificar la numeracion. Razon: Codigo duplicado');
                }

                return [
                    'success' => false,
                    'message' => 'Revisar la ultima factura del segmento y modificar la numeracion. Razon: Codigo duplicado',
                    'factura_id' => $facturaId,
                ];

            }

            // Guardar código anterior antes de actualizar
            $codigoAnterior = $factura->codigo;

            // Calcular la diferencia de días entre la fecha y el vencimiento original
            $fechaOriginal = Carbon::parse($factura->fecha);
            $vencimientoOriginal = Carbon::parse($factura->vencimiento);

            $diferenciaDias = $fechaOriginal->diffInDays($vencimientoOriginal, false);

            $factura->nro = $numero;
            $factura->numeracion = $nro->id;
            $factura->tipo = 2;
            $factura->fecha = Carbon::now()->format('Y-m-d');
            // Aplicar la diferencia de días a la nueva fecha de vencimiento
            
            if($vencimientoOriginal <= $factura->fecha){
                $factura->vencimiento = Carbon::parse($factura->fecha)->addDays($diferenciaDias)->format('Y-m-d');    
            }
            
            $factura->save();

            // Crear log para la conversión (solo si no es masivo, porque en masivo se crea después)
            if (!$masivo) {
                $codigoNuevo = $factura->codigo;
                $descripcion = '<i class="fas fa-check text-success"></i> Factura convertida a electrónica por <b>'.Auth::user()->nombres.'</b>. Código anterior: <b>'.$codigoAnterior.'</b>, código nuevo: <b>'.$codigoNuevo.'</b>';
                $movimiento = new MovimientoLOG();
                $movimiento->contrato    = $factura->id;
                $movimiento->modulo      = 8;
                $movimiento->descripcion = $descripcion;
                $movimiento->created_by  = Auth::user()->id;
                $movimiento->empresa     = Auth::user()->empresa;
                $movimiento->save();
            }

            if (!$masivo) {
                return back()->with('success', 'Factura con el nuevo código: ' . $factura->codigo . ' convertida correctamente.');
            }

            return [
                'success' => true,
                'message' => 'Factura convertida exitosamente.',
                'factura_id' => $facturaId,
                'codigo' => $factura->codigo,
            ];
        } catch (\Exception $e) {
            $mensajeError = 'Ocurrió un error al convertir la factura: ' . $e->getMessage();

            if (!$masivo) {
                return back()->with('danger', $mensajeError);
            }

            return [
                'success' => false,
                'message' => $mensajeError,
                'factura_id' => $facturaId,
            ];
        }
    }

    public function convertirEstandar($facturaId, $masivo = false)
    {
        try {
            $factura = Factura::find($facturaId);

            if (!$factura) {
                $mensaje = 'Factura no encontrada con ID: ' . $facturaId;
                if (!$masivo) {
                    return back()->with('danger', $mensaje);
                }
                return [
                    'success' => false,
                    'message' => $mensaje,
                    'factura_id' => $facturaId,
                ];
            }

            // Validar que la factura no esté emitida
            if ($factura->emitida != 0) {
                $mensaje = 'No se puede convertir una factura electrónica que ya ha sido emitida.';
                if (!$masivo) {
                    return back()->with('danger', $mensaje);
                }
                return [
                    'success' => false,
                    'message' => $mensaje,
                    'factura_id' => $facturaId,
                ];
            }

            $num = Factura::where('empresa', Auth::user()->empresa)->orderBy('nro', 'asc')->get()->last();
            $numero = $num ? $num->nro + 1 : 1;

            $nro = NumeracionFactura::where('empresa', Auth::user()->empresa)
                ->where('preferida', 1)
                ->where('estado', 1)
                ->where('tipo', 1)
                ->first();

            if (!$nro) {
                $mensaje = 'No se encontró una numeración para facturas estándar preferida.';
                if (!$masivo) {
                    return back()->with('danger', $mensaje);
                }
                return [
                    'success' => false,
                    'message' => $mensaje,
                    'factura_id' => $facturaId,
                ];
            }

            // Guardar código anterior antes de actualizar
            $codigoAnterior = $factura->codigo;

            // Actualizar y guardar datos
            $inicio = $nro->inicio;
            $nro->inicio += 1;
            $nro->save();

            $factura->codigo = $nro->prefijo . $inicio;

            $codigoUsado = Factura::where('empresa', Auth::user()->empresa)
            ->where('codigo', $factura->codigo)
            ->where('id', '!=', $facturaId)
            ->where('numeracion', $nro->id)
            ->first();

            if($codigoUsado){

                if (!$masivo) {
                    return back()->with('danger', 'Revisar la ultima factura del segmento y modificar la numeracion. Razon: Codigo duplicado');
                }

                return [
                    'success' => false,
                    'message' => 'Revisar la ultima factura del segmento y modificar la numeracion. Razon: Codigo duplicado',
                    'factura_id' => $facturaId,
                ];

            }

            // Calcular la diferencia de días entre la fecha y el vencimiento original
            $fechaOriginal = Carbon::parse($factura->fecha);
            $vencimientoOriginal = Carbon::parse($factura->vencimiento);
            $diferenciaDias = $fechaOriginal->diffInDays($vencimientoOriginal, false);

            $factura->nro = $numero;
            $factura->numeracion = $nro->id;
            $factura->tipo = 1;
            $factura->fecha = Carbon::now()->format('Y-m-d');
            // Aplicar la diferencia de días a la nueva fecha de vencimiento
            $factura->vencimiento = Carbon::parse($factura->fecha)->addDays($diferenciaDias)->format('Y-m-d');
            $factura->save();

            // Crear log para la conversión (solo si no es masivo, porque en masivo se crea después)
            if (!$masivo) {
                $codigoNuevo = $factura->codigo;
                $descripcion = '<i class="fas fa-check text-success"></i> Factura convertida a estándar por <b>'.Auth::user()->nombres.'</b>. Código anterior: <b>'.$codigoAnterior.'</b>, código nuevo: <b>'.$codigoNuevo.'</b>';
                $movimiento = new MovimientoLOG();
                $movimiento->contrato    = $factura->id;
                $movimiento->modulo      = 8;
                $movimiento->descripcion = $descripcion;
                $movimiento->created_by  = Auth::user()->id;
                $movimiento->empresa     = Auth::user()->empresa;
                $movimiento->save();
            }

            if (!$masivo) {
                return back()->with('success', 'Factura con el nuevo código: ' . $factura->codigo . ' convertida correctamente.');
            }

            return [
                'success' => true,
                'message' => 'Factura convertida exitosamente.',
                'factura_id' => $facturaId,
                'codigo' => $factura->codigo,
            ];
        } catch (\Exception $e) {
            $mensajeError = 'Ocurrió un error al convertir la factura: ' . $e->getMessage();

            if (!$masivo) {
                return back()->with('danger', $mensajeError);
            }

            return [
                'success' => false,
                'message' => $mensajeError,
                'factura_id' => $facturaId,
            ];
        }
    }


    public function conversionmasivaElectronica($facturas){
        $facturas = explode(",", $facturas);
        for ($i=0; $i < count($facturas) ; $i++) {
            // Obtener el código anterior antes de la conversión
            $facturaAntes = Factura::find($facturas[$i]);
            $codigoAnterior = $facturaAntes ? $facturaAntes->codigo : null;

            $response = $this->convertirelEctronica($facturas[$i],1);

            // Crear log para cada conversión exitosa
            if(isset($response['success']) && $response['success'] == true){
                $factura = Factura::find($facturas[$i]);
                if($factura){
                    $codigoNuevo = $factura->codigo;
                    $descripcion = '<i class="fas fa-check text-success"></i> Factura convertida a electrónica por <b>'.Auth::user()->nombres.'</b>. Código anterior: <b>'.$codigoAnterior.'</b>, código nuevo: <b>'.$codigoNuevo.'</b>';
                    $movimiento = new MovimientoLOG();
                    $movimiento->contrato    = $factura->id;
                    $movimiento->modulo      = 8;
                    $movimiento->descripcion = $descripcion;
                    $movimiento->created_by  = Auth::user()->id;
                    $movimiento->empresa     = Auth::user()->empresa;
                    $movimiento->save();
                }
            }
        }

        if(isset($response['success']) && $response['success'] == false){
            return response()->json([
                'success' => false,
                'text'    => $response['message'],
            ]);
        }else{
            return response()->json([
                'success' => true,
                'text'    => 'Conversión masiva de facturas electrónicas terminada',
            ]);
        }
    }

    public function conversionmasivaEstandar($facturas){
        $facturas = explode(",", $facturas);
        $resultados = [];
        $exitosos = 0;
        $fallidos = 0;

        for ($i=0; $i < count($facturas) ; $i++) {
            // Obtener el código anterior antes de la conversión
            $facturaAntes = Factura::find($facturas[$i]);
            $codigoAnterior = $facturaAntes ? $facturaAntes->codigo : null;

            $response = $this->convertirEstandar($facturas[$i], 1);

            // Crear log para cada conversión exitosa
            if(isset($response['success']) && $response['success'] == true){
                $factura = Factura::find($facturas[$i]);
                if($factura){
                    $codigoNuevo = $factura->codigo;
                    $descripcion = '<i class="fas fa-check text-success"></i> Factura convertida a estándar por <b>'.Auth::user()->nombres.'</b>. Código anterior: <b>'.$codigoAnterior.'</b>, código nuevo: <b>'.$codigoNuevo.'</b>';
                    $movimiento = new MovimientoLOG();
                    $movimiento->contrato    = $factura->id;
                    $movimiento->modulo      = 8;
                    $movimiento->descripcion = $descripcion;
                    $movimiento->created_by  = Auth::user()->id;
                    $movimiento->empresa     = Auth::user()->empresa;
                    $movimiento->save();
                    $exitosos++;
                }
            } else {
                $fallidos++;
                $resultados[] = [
                    'factura_id' => $facturas[$i],
                    'codigo' => $codigoAnterior,
                    'error' => isset($response['message']) ? $response['message'] : 'Error desconocido'
                ];
            }
        }

        if($fallidos > 0 && $exitosos == 0){
            $mensaje = 'No se pudo convertir ninguna factura. ';
            if(count($resultados) > 0){
                $mensaje .= 'Errores: ' . implode(', ', array_column($resultados, 'error'));
            }
            return response()->json([
                'success' => false,
                'text'    => $mensaje,
            ]);
        }else{
            $mensaje = 'Conversión masiva de facturas estándar terminada. ';
            $mensaje .= 'Exitosas: ' . $exitosos;
            if($fallidos > 0){
                $mensaje .= ', Fallidas: ' . $fallidos;
            }
            return response()->json([
                'success' => true,
                'text'    => $mensaje,
            ]);
        }
    }

    public function emisionMasivaXml($facturas){

        try {
            $empresa = Auth::user()->empresaObj;
            $facturas = explode(",", $facturas);
            set_time_limit(0);

            // Validar correos de los clientes antes de procesar
            $erroresValidacion = [];
            foreach ($facturas as $idFactura) {
                $factura = Factura::where('empresa', $empresa->id)->where('id', $idFactura)->first();
                if ($factura && $factura->cliente) {
                    $cliente = $factura->cliente();
                    if (!$cliente || !isset($cliente->email) || empty($cliente->email)) {
                        $factura->dian_response = 401;
                        $factura->save();
                        $erroresValidacion[] = "La factura #{$factura->codigo} no se puede emitir porque el cliente " . ($cliente && isset($cliente->nombre) ? $cliente->nombre : 'Desconocido') . " no tiene correo electrónico configurado.";
                    }
                }
            }

            if (count($erroresValidacion) > 0) {
                return response()->json([
                    'success' => false,
                    'text' => 'Se encontraron errores de validación:',
                    'errores' => count($erroresValidacion),
                    'detalles_errores' => $erroresValidacion
                ]);
            }

            $exitosas = 0;
            $errores = [];
            $totalFacturas = count($facturas);

            for ($i=0; $i < count($facturas) ; $i++) {
                try {
                    $factura = Factura::where('empresa', $empresa->id)->where('emitida', 0)->where('tipo',2)->where('id', $facturas[$i])->first();

                    if(isset($factura)){
                        $factura->modificado = 1;

                        // Calcular la diferencia de días entre la fecha y el vencimiento original
                        $fechaOriginal = Carbon::parse($factura->fecha);
                        $vencimientoOriginal = Carbon::parse($factura->vencimiento);
                        $diferenciaDias = $fechaOriginal->diffInDays($vencimientoOriginal, false);

                        $factura->fecha = Carbon::now()->format('Y-m-d');
                        // Aplicar la diferencia de días a la nueva fecha de vencimiento
                        $factura->vencimiento = Carbon::parse($factura->fecha)->addDays($diferenciaDias)->format('Y-m-d');
                        $factura->save();

                        if($empresa->proveedor == 2){
                            try {
                                // Guardar el estado inicial de emitida
                                $emitidaAntes = $factura->emitida;

                                $resultado = $this->jsonDianFacturaVenta($factura->id);

                                // Recargar la factura para verificar si quedó emitida
                                $factura->refresh();

                                // Si la factura quedó marcada como emitida, fue exitosa
                                if($factura->emitida == 1 && $factura->emitida != $emitidaAntes){
                                    $exitosas++;
                                } else {
                                    // Verificar si la respuesta es un error
                                    if($resultado instanceof \Illuminate\Http\JsonResponse){
                                        $data = json_decode($resultado->getContent(), true);
                                        if(isset($data['status']) && ($data['status'] == 'error' || $data['status'] == 0)){
                                            $errores[] = "Factura #{$factura->codigo}: " . ($data['message'] ?? $data['mensaje'] ?? 'Error desconocido');
                                        } elseif(isset($data['status']) && $data['status'] == 'success'){
                                            // Si el status es success pero no se marcó como emitida, verificar UUID
                                            if($factura->uuid){
                                                $exitosas++;
                                            } else {
                                                $errores[] = "Factura #{$factura->codigo}: Procesada pero no se pudo confirmar la emisión";
                                            }
                                        } else {
                                            // Si no hay status claro, verificar por UUID
                                            if($factura->uuid){
                                                $exitosas++;
                                            } else {
                                                $errores[] = "Factura #{$factura->codigo}: No se pudo determinar el resultado";
                                            }
                                        }
                                    } else {
                                        // Si es un redirect, verificar si quedó emitida
                                        if($factura->emitida == 1 || $factura->uuid){
                                            $exitosas++;
                                        } else {
                                            $errores[] = "Factura #{$factura->codigo}: No se pudo confirmar la emisión";
                                        }
                                    }
                                }
                            } catch (\Exception $e) {
                                // Verificar si a pesar del error, la factura quedó emitida
                                $factura->refresh();
                                if($factura->emitida == 1 || $factura->uuid){
                                    $exitosas++;
                                } else {
                                    $mensajeError = $e->getMessage();
                                    if(strpos($mensajeError, "Trying to get property 'nombre' of non-object") !== false){
                                        $mensajeError = "El vendedor asociado a la factura no existe o no está configurado correctamente";
                                    }
                                    $errores[] = "Factura #{$factura->codigo}: {$mensajeError}";
                                }
                            }
                        }else{
                            try {
                                $this->xmlFacturaVentaMasivo($factura->id, $empresa->id);
                                $exitosas++;
                            } catch (\Exception $e) {
                                $errores[] = "Factura #{$factura->codigo}: {$e->getMessage()}";
                            }
                        }
                    } else {
                        $errores[] = "Factura ID {$facturas[$i]}: No encontrada o ya emitida";
                    }
                } catch (\Throwable $th) {
                    $facturaCodigo = isset($factura) ? $factura->codigo : "ID {$facturas[$i]}";
                    $mensajeError = $th->getMessage();

                    // Mensajes más descriptivos para errores comunes
                    if(strpos($mensajeError, "Trying to get property 'nombre' of non-object") !== false){
                        if(strpos($th->getFile(), 'InvoiceJsonBuilder.php') !== false){
                            $mensajeError = "Error: El vendedor asociado a la factura no existe o no está configurado correctamente";
                        } else {
                            $mensajeError = "Error: Falta información requerida (objeto no encontrado)";
                        }
                    }

                    $errores[] = "Factura #{$facturaCodigo}: {$mensajeError}";
                }
            }

            $mensaje = "Emisión masiva completada. ";
            $mensaje .= "Exitosas: {$exitosas} de {$totalFacturas}. ";

            if(count($errores) > 0){
                $mensaje .= "Errores: " . count($errores) . ". ";
                $mensaje .= "Detalles: " . implode(" | ", array_slice($errores, 0, 5));
                if(count($errores) > 5){
                    $mensaje .= " y " . (count($errores) - 5) . " más...";
                }
            }

            return response()->json([
                'success' => count($errores) == 0,
                'text'    => $mensaje,
                'exitosas' => $exitosas,
                'errores' => count($errores),
                'detalles_errores' => $errores
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'text'    => 'Error general en emisión masiva: ' . $th->getMessage(),
                'errores' => [$th->getMessage()]
            ]);
        }
    }

    public function exportar(Request $request){
        $this->getAllPermissions(Auth::user()->id);
        $objPHPExcel = new PHPExcel();
        $tituloReporte = "Reporte de Facturas de Ventas";
        $titulosColumnas = array('Codigo', 'Fecha', 'Cliente', 'Identificacion', 'Subtotal', 'Impuesto', 'Total', 'Abono', 'Saldo', 'Forma de Pago');

        $letras= array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z');

        $objPHPExcel->getProperties()->setCreator("Sistema") // Nombre del autor
        ->setLastModifiedBy("Sistema") //Ultimo usuario que lo modific�1�7�1�7�1�7
        ->setTitle("Reporte Excel Factura de Ventas") // Titulo
        ->setSubject("Reporte Excel Factura de Ventas") //Asunto
        ->setDescription("Reporte de Factura de Ventas") //Descripci�1�7�1�7�1�7n
        ->setKeywords("reporte Factura de Ventas") //Etiquetas
        ->setCategory("Reporte excel"); //Categorias
        // Se combinan las celdas A1 hasta D1, para colocar ah�1�7�1�7�1�7 el titulo del reporte
        $objPHPExcel->setActiveSheetIndex(0)
            ->mergeCells('A1:J1');
        // Se agregan los titulos del reporte
        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1',$tituloReporte);
        // Titulo del reporte
        $objPHPExcel->setActiveSheetIndex(0)
            ->mergeCells('A2:J2');
        // Se agregan los titulos del reporte
        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A2','Fecha '.date('d-m-Y')); // Titulo del reporte

        $estilo = array('font'  => array('bold'  => true, 'size'  => 12, 'name'  => 'Times New Roman' ), 'alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
        ));
        $objPHPExcel->getActiveSheet()->getStyle('A1:J3')->applyFromArray($estilo);

        $estilo =array('fill' => array(
            'type' => PHPExcel_Style_Fill::FILL_SOLID,
            'color' => array('rgb' => 'd08f50')));
        $objPHPExcel->getActiveSheet()->getStyle('A3:J3')->applyFromArray($estilo);

        $estilo =array(
            'fill' => array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => substr(Auth::user()->empresa()->color,1))
            ),
            'font'  => array(
                'bold'  => true,
                'size'  => 12,
                'name'  => 'Times New Roman',
                'color' => array(
                    'rgb' => 'FFFFFF'
                ),
            ),
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER
            )
        );
        $objPHPExcel->getActiveSheet()->getStyle('A3:J3')->applyFromArray($estilo);

        for ($i=0; $i <count($titulosColumnas) ; $i++) {
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($letras[$i].'3', utf8_decode($titulosColumnas[$i]));
        }

        $i=4;
        $letra=0;

        $facturas = Factura::query()
            ->join('contactos as c', 'factura.cliente', '=', 'c.id')
            ->join('items_factura as if', 'factura.id', '=', 'if.factura')
            ->leftJoin('contracts as cs', 'c.id', '=', 'cs.client_id')
            ->leftJoin('vendedores as v', 'factura.vendedor', '=', 'v.id')
            ->select('factura.tipo','factura.promesa_pago','factura.id', 'factura.correo', 'factura.mensaje', 'factura.codigo', 'factura.nro', DB::raw('c.nombre as nombrecliente'), DB::raw('c.apellido1 as ape1cliente'), DB::raw('c.apellido2 as ape2cliente'), DB::raw('c.email as emailcliente'), DB::raw('c.celular as celularcliente'), DB::raw('c.nit as nitcliente'), 'factura.cliente', 'factura.fecha', 'factura.vencimiento', 'factura.estatus', 'factura.vendedor','factura.emitida', DB::raw('v.nombre as nombrevendedor'),DB::raw('SUM((if.cant*if.precio)-(if.precio*(if(if.desc,if.desc,0)/100)*if.cant)+(if.precio-(if.precio*(if(if.desc,if.desc,0)/100)))*(if.impuesto/100)*if.cant) as total'), DB::raw('((Select SUM(pago) from ingresos_factura where factura=factura.id) + (Select if(SUM(valor), SUM(valor), 0) from ingresos_retenciones where factura=factura.id)) as pagado'),         DB::raw('(SUM((if.cant*if.precio)-(if.precio*(if(if.desc,if.desc,0)/100)*if.cant) + (if.precio-(if.precio*(if(if.desc,if.desc,0)/100)))*(if.impuesto/100)*if.cant) - ((Select SUM(pago) from ingresos_factura where factura=factura.id) + (Select if(SUM(valor), SUM(valor), 0) from ingresos_retenciones where factura=factura.id)) - (Select if(SUM(pago), SUM(pago), 0) from notas_factura where factura=factura.id)) as porpagar'))
            ->groupBy('factura.id');

        if($request->codigo!=null){
            $facturas->where(function ($query) use ($request) {
                $query->orWhere('factura.codigo', 'like', "%{$request->codigo}%");
            });
        }
        if($request->cliente!=null){
            $facturas->where(function ($query) use ($request) {
                $query->orWhere('factura.cliente', $request->cliente);
            });
        }
        if($request->corte!=null){
            $facturas->where(function ($query) use ($request) {
                $query->orWhere('cs.fecha_corte', $request->corte);
            });
        }
        if($request->creacion!=null){
            $facturas->where(function ($query) use ($request) {
                $query->orWhere('factura.fecha', $request->creacion);
            });
        }
        if($request->vencimiento!=null){
            $facturas->where(function ($query) use ($request) {
                $query->orWhere('factura.vencimiento', $request->vencimiento);
            });
        }
        if($request->prorrateo!=null){
            if($request->prorrateo == '1'){
                $facturas->where('factura.prorrateo_aplicado', 1);
            }else{
                $facturas->where(function ($query) {
                    $query->where('factura.prorrateo_aplicado', 0)->orWhereNull('factura.prorrateo_aplicado');
                });
            }
        }
        if($request->estado!=null){
            $facturas->where(function ($query) use ($request) {
                $query->orWhere('factura.estatus', $request->estado);
            });
        }
        if($request->municipio!=null){
            $facturas->where(function ($query) use ($request) {
                $query->orWhere('c.fk_idmunicipio', $request->municipio);
            });
        }
        $facturas->where('factura.tipo', $request->tipo)->where('factura.lectura',1);

        if(Auth::user()->empresa()->oficina){
            if(auth()->user()->oficina){
                $facturas->where('cs.oficina', auth()->user()->oficina);
            }
        }

        $facturas = $facturas->get();
        $moneda = auth()->user()->empresa()->moneda;

        foreach ($facturas as $factura) {

            $total = $factura->total();
            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue($letras[0].$i, $factura->codigo)
                ->setCellValue($letras[1].$i, date('d-m-Y', strtotime($factura->fecha)))
                ->setCellValue($letras[2].$i, $factura->nombrecliente.' '.$factura->ape1cliente.' '.$factura->ape2cliente)
                ->setCellValue($letras[3].$i, $factura->cliente()->tip_iden('true').' '.$factura->nitcliente)
                ->setCellValue($letras[4].$i, $moneda.' '.$factura->parsear(($total->subtotal)))
                ->setCellValue($letras[5].$i, $moneda.' '.$factura->parsear(($factura->impuestos_totales())))
                ->setCellValue($letras[6].$i, $moneda.' '.$factura->parsear(($total->total)))
                ->setCellValue($letras[7].$i, $moneda.' '.$factura->parsear(($factura->pagado)))
                ->setCellValue($letras[8].$i, $moneda.' '.$factura->parsear(($factura->porpagar)))
                ->setCellValue($letras[9].$i, ($factura->cuenta_id) ?$factura->formaPago()->nombre:'');
            $i++;
        }

        $estilo =array('font'  => array('size'  => 12, 'name'  => 'Times New Roman' ),
            'borders' => array(
                'allborders' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN
                )
            ), 'alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,));
        $objPHPExcel->getActiveSheet()->getStyle('A3:J'.$i)->applyFromArray($estilo);

        for($i = 'A'; $i <= $letras[20]; $i++){
            $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension($i)->setAutoSize(TRUE);
        }

        // Se asigna el nombre a la hoja
        if($request->tipo==1){
            $objPHPExcel->getActiveSheet()->setTitle('Facturas de Ventas');
        }elseif($request->tipo==2){
            $objPHPExcel->getActiveSheet()->setTitle('Facturas de Ventas Electrónicas');
        }

        // Se activa la hoja para que sea la que se muestre cuando el archivo se abre
        $objPHPExcel->setActiveSheetIndex(0);

        // Inmovilizar paneles
        $objPHPExcel->getActiveSheet(0)->freezePane('A5');
        $objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0,4);
        $objPHPExcel->setActiveSheetIndex(0);
        header("Pragma: no-cache");
        header('Content-type: application/vnd.ms-excel');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        if($request->tipo==1){
            header('Content-Disposition: attachment;filename="Reporte_Facturas_Ventas.xlsx"');
        }elseif($request->tipo==2){
            header('Content-Disposition: attachment;filename="Reporte_Facturas_Ventas_Electronicas.xlsx"');
        }
        header('Cache-Control: max-age=0');
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');
        exit;
    }

    function xml($nro, $optional = null){
        $empresa = auth()->user()->empresaObj;

        $factura = Factura::where('empresa', $empresa->id)->where('id', $nro)->first();

        $path = public_path() . '/xml/empresa' . $empresa->id . "/FV" . "/FV-" . $factura->codigo . ".xml";

        if (!File::exists($path)) {

            $numeracion = NumeracionFactura::where('empresa', $empresa->id)
                ->where('num_equivalente', 0)
                ->where('nomina', 0)
                ->where('preferida', 1)
                ->first();

            $response = $this->electronicBillingService->electronicInvoiceStatus($empresa->nit, $factura->codigo, $numeracion->prefijo);

            if (isset($response->statusCode) && $response->statusCode == 200) {

                $xmlFactura = base64_decode($response->document);

                $rutaXml = "/xml/empresa{$empresa->id}/FV/FV-{$factura->codigo}.xml";

                // Storage::disk('public_2')->put($rutaXml, $xmlFactura);
            } else {
                return back()->with('error', "No se ha encontrado el xml perteneciente al documento");
            }
        }

        $headers = array(
            'Content-Type: application/xml',
        );

        return Response::download($path, "FV-{$factura->codigo}.xml", $headers);
    }

    public function facturasWhatsappIndex(Request $request){

        view()->share(['seccion' => 'facturas', 'title' => 'Envío Whatsapp', 'icon' =>'fas fa-plus', 'subseccion' => 'venta']);
        $this->getAllPermissions(Auth::user()->id);

        $empresa = Empresa::find(Auth::user()->empresa);

        if(!$request->dia){
            $dia = Carbon::now()->format('d');
        }

        if(!$request->fecha){
            $request->fecha = Carbon::now()->format('Y-m-d');
        }else{
            $request->fecha = Carbon::parse($request->fecha)->format('Y-m-d');
            $dia = Carbon::parse($request->fecha)->format('d');
        }

        if(isset($empresa->cron_fecha_whatsapp) && $empresa->cron_fecha_whatsapp != null){
            $request->dia = $dia = Carbon::parse($empresa->cron_fecha_whatsapp)->format('d');
            $request->fecha = Carbon::parse($empresa->cron_fecha_whatsapp)->format('Y-m-d');

            $empresa->cron_fecha_whatsapp = $request->fecha;
            $empresa->save();
        }

        $totalFaltantes = Factura::
            where('factura.fecha', $request->fecha)
            ->where('factura.whatsapp', 0)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('ingresos_factura as i')
                  ->whereColumn('i.factura', 'factura.id');
            })
            ->count('factura.id');

        $facturas = Factura::
            leftJoin('contracts as c','c.id','=','factura.contrato_id')
            ->where('factura.fecha', $request->fecha)
            ->where('factura.whatsapp', 0)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('ingresos_factura as i')
                  ->whereColumn('i.factura', 'factura.id'); // i.factura = factura.id
            })
            ->select('factura.*')
            ->paginate();

        $sinTelefono = Factura::
            leftJoin('facturas_contratos as fcs', 'fcs.factura_id', '=', 'factura.id')
            ->leftJoin('contracts as cs', function ($join) {
                $join->on('cs.nro', '=', 'fcs.contrato_nro');
            })
            ->join('contactos as con', 'con.id', 'cs.client_id')
            ->where(function ($query) {
                $query->whereNull('con.celular')
                      ->whereNull('con.telefono1');
            })
            ->where('factura.fecha', $request->fecha)
            ->where('factura.whatsapp', 0)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('ingresos_factura as i')
                  ->whereColumn('i.factura', 'factura.id');
            })
            ->count();

        $request->fecha = Carbon::parse($request->fecha)->format('d-m-Y');
        return view('cronjobs.envio-whatsapp', compact('request','facturas','totalFaltantes','empresa','sinTelefono'));
    }

    public function facturasWhastappSave(Request $request){
        if($request->fecha){
            $request->fecha = Carbon::parse($request->fecha)->format('Y-m-d');
            $empresa = Empresa::Find(Auth::user()->empresa);
            if($empresa){
                $empresa->cron_fecha_whatsapp = $request->fecha;
                $empresa->save();


            }
            return redirect('empresa/facturas/facturas-whatsapp-index')->with('success', 'Se ha guardado la fecha de envío de whatsapp correctamente');
        }else{
            return redirect('empresa/facturas/facturas-whatsapp-index')->with('danger', 'No se ha podido guardar la fecha de envío de whatsapp');
        }
    }

    public function facturasWhastappEnvio(Request $request)
    {
        try {
            $controller = new CronController();
            $controller->envioFacturaWpp();

            if ($request->ajax()) {
                return response()->json(['status' => 'success', 'message' => 'Mensajes enviados con éxito.']);
            }

            return redirect()->route('facturas.whatsapp.index')
                ->with('success', 'Mensajes enviados con éxito.');
        } catch (\Throwable $th) {
            \Log::error('Error WhatsApp: ' . $th->getMessage());

            if ($request->ajax()) {
                return response()->json(['status' => 'error', 'message' => 'Error al enviar mensajes.'], 500);
            }

            return redirect()->route('facturas.whatsapp.index')
                ->with('error', 'Ocurrió un error al enviar mensajes.');
        }
    }

    public function facturasWhastappReiniciar(Request $request){

        if($request->fecha){
            Factura::where('fecha',date('Y-m-d',strtotime($request->fecha)))->where('empresa',Auth::user()->empresa)->update(['whatsapp' => 0]);
            return redirect('empresa/facturas/facturas-whatsapp-index')->with('success', 'Se ha reiniciado el envio de facturas de la fecha ' . $request->fecha);
        }else{
            return redirect('empresa/facturas/facturas-whatsapp-index')->with('danger', 'No se ha podido reiniciar el envio de facturas');
        }
    }

    public function getModalDescuento(Request $request){

        $descuento = Descuento::where('f.empresa', Auth::user()->empresa)
            ->join('factura as f', 'descuentos.factura', '=', 'f.id')
            ->where('f.id', $request->factura_id)
            ->select('descuentos.*', 'f.codigo as factura_codigo')
            ->first();

        return response()->json([
            'status' => 200,
            'descuento' => $descuento,
            'message' => 'Descuento obtenido correctamente'
        ]);
    }

    public function sendDescuento(Request $request){

        $descuento = Descuento::where('factura', $request->factura_id)
        ->first();

        if($descuento && $descuento->estado == 1){
            return response()->json([
                'status' => 400,
                'error' => 'Ya existe un descuento activo para esta factura'
            ]);
        }

        if(!$descuento){
            $descuento = new Descuento;
        }

        $descuento->factura    = $request->factura_id;
        $descuento->descuento  = $request->descuento;
        $descuento->comentario  = $request->observacion;
        $descuento->created_by = Auth::user()->id;
        $descuento->save();

        return response()->json([
            'status' => 200,
            'message' => 'Descuento guardado correctamente',
            'descuento' => $descuento
        ]);

    }

    public function logs(Request $request, $factura)
    {

        $facturas = MovimientoLOG::query();
        $facturas->where('contrato', $factura)
        ->where('modulo', 8);

        return datatables()->eloquent($facturas)
            ->editColumn('created_at', function (MovimientoLOG $factura) {
                return date('d-m-Y g:i:s A', strtotime($factura->created_at));
            })
            ->editColumn('created_by', function (MovimientoLOG $factura) {
                return $factura->created_by();
            })
            ->editColumn('descripcion', function (MovimientoLOG $factura) {
                return $factura->descripcion;
            })
            ->rawColumns(['created_at', 'created_by', 'descripcion'])
            ->toJson();
    }

    public function log($id)
    {
        $this->getAllPermissions(Auth::user()->id);

        $factura = Factura::Find($id);
        $factura_log = DB::table('log_movimientos')
        ->where('contrato', $factura->id)->where('modulo',8)->get();

        if ($factura) {
            view()->share(['icon' => 'fas fa-chart-area', 'title' => 'Log | Factura: ' . $factura->codigo]);
            return view('facturas.log')->with(compact('factura', 'factura_log'));
        } else {
            $mensaje = 'NO SE HA PODIDO OBTENER EL LOG DE LA FACTURA';
            return redirect('empresa/facturas/' . $factura->id)->with('danger', $mensaje);
        }

        return redirect('empresa/factura-index')->with('danger', 'LA FACTURA DE SERVICIOS NO HA ENCONTRADO');
    }

    public function eliminarMasivaFacturas($facturas){
        try {
            // Validar permiso 44
            $this->getAllPermissions(Auth::user()->id);
            if(!isset($_SESSION['permisos']['44'])){
                return response()->json([
                    'success' => false,
                    'message' => 'No tiene permisos para eliminar facturas'
                ], 403);
            }

            $empresa = Auth::user()->empresaObj;
            $facturas = explode(",", $facturas);
            $eliminadas = 0;
            $errores = [];

            DB::beginTransaction();

            for ($i = 0; $i < count($facturas); $i++) {
                $factura = Factura::where('empresa', $empresa->id)->where('id', $facturas[$i])->first();

                if(!$factura){
                    $errores[] = "Factura ID {$facturas[$i]}: No encontrada";
                    continue;
                }

                // Validar que no tenga pagos
                if($factura->pagado() != 0){
                    $errores[] = "Factura {$factura->codigo}: No se puede eliminar porque tiene pagos registrados o abonos";
                    continue;
                }

                // Validar que no tenga notas de crédito asociadas
                $tieneNotasCredito = NotaCreditoFactura::where('factura', $factura->id)->exists();
                if($tieneNotasCredito){
                    $errores[] = "Factura {$factura->codigo}: No se puede eliminar porque tiene notas de crédito asociadas";
                    continue;
                }

                // Validar que no esté emitida (legal ante la dian)
                if($factura->emitida == 1){
                    $errores[] = "Factura {$factura->codigo}: No se puede eliminar porque ya es legal ante la DIAN (está emitida)";
                    continue;
                }

                // Si pasa todas las validaciones, eliminar factura y registros relacionados
                try {
                    // Usar savepoint para que un error en una factura no revierta las demás
                    DB::statement('SAVEPOINT factura_'.$i);

                    // Generar MovimientoLog
                    $factura_contrato = DB::table('facturas_contratos')->where('factura_id', $factura->id)->first();
                    if ($factura_contrato) {
                        $contrato = Contrato::where('nro', $factura_contrato->contrato_nro)->first();
                        if ($contrato) {
                            $movimiento = new MovimientoLOG;
                            $movimiento->contrato    = $contrato->id;
                            $movimiento->modulo      = 5;
                            $movimiento->descripcion = "Factura {$factura->codigo} eliminada";
                            $movimiento->created_by  = Auth::user()->id;
                            $movimiento->empresa     = Auth::user()->empresa;
                            $movimiento->save();
                        }
                    }

                    // Eliminar registros de todas las tablas que puedan tener llaves foráneas a factura
                    DB::table('items_factura')->where('factura', $factura->id)->delete();
                    DB::table('factura_retenciones')->where('factura', $factura->id)->delete();
                    DB::table('ingresos_retenciones')->where('factura', $factura->id)->delete();
                    DB::table('ingresos_factura')->where('factura', $factura->id)->delete();
                    DB::table('notas_factura')->where('factura', $factura->id)->delete();
                    DB::table('factura_contacto')->where('factura', $factura->id)->delete();
                    DB::table('puc_movimiento')->where('documento_id', $factura->id)->where('tipo_comprobante', 3)->delete();
                    DB::table('facturas_contratos')->where('factura_id', $factura->id)->delete();
                    DB::table('descuentos')->where('factura', $factura->id)->delete();
                    DB::table('crm')->where('factura', $factura->id)->delete();
                    DB::table('promesa_pago')->where('factura', $factura->id)->delete();

                    // Eliminar factura en OnePay si existe
                    if ($factura->onepay_invoice_id) {
                        $onePayService = new OnePayService();
                        $onePayService->deleteInvoice($factura);
                    }

                    // Eliminar la factura misma
                    $factura->delete();
                    $eliminadas++;

                    DB::statement('RELEASE SAVEPOINT factura_'.$i);

                } catch (\Exception $e) {
                    DB::statement('ROLLBACK TO SAVEPOINT factura_'.$i);
                    $errores[] = "Factura {$factura->codigo}: Error al eliminar - " . $e->getMessage();
                }
            }

            DB::commit();

            $text = "Se eliminaron exitosamente {$eliminadas} factura(s)";
            if(count($errores) > 0){
                $text .= ". " . count($errores) . " factura(s) no pudieron eliminarse";
            }

            return response()->json([
                'success' => true,
                'text' => $text,
                'eliminadas' => $eliminadas,
                'errores' => $errores
            ]);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar facturas: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * Validar si un código de factura está disponible
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function validarCodigoFactura(Request $request)
    {
        $request->validate([
            'numeracion_id' => 'required|integer',
            'codigo' => 'required|string',
            'factura_id' => 'nullable|integer'
        ]);

        $numeracionId = $request->numeracion_id;
        $codigo = $request->codigo;
        $facturaId = $request->factura_id;

        // Verificar que la numeración existe
        $numeracion = NumeracionFactura::find($numeracionId);
        if (!$numeracion) {
            return response()->json([
                'success' => false,
                'message' => 'La numeración no existe'
            ], 400);
        }

        // Verificar que el código no exista en otra factura con la misma numeración
        $query = Factura::where('numeracion', $numeracionId)
            ->where('codigo', $codigo);

        // Si es edición, excluir la factura actual
        if ($facturaId) {
            $query->where('id', '!=', $facturaId);
        }

        $existe = $query->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe otra factura con ese número en esta numeración'
            ], 200);
        }

        return response()->json([
            'success' => true,
            'message' => 'El código está disponible'
        ], 200);
    }

    /**
     * Actualizar el código de una factura
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function actualizarCodigoFactura(Request $request)
    {
        // Verificar permiso 43
        $this->getAllPermissions(Auth::user()->id);
        if (!isset($_SESSION['permisos']['43'])) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permisos para realizar esta acción'
            ], 403);
        }

        $request->validate([
            'factura_id' => 'nullable|integer',
            'codigo' => 'required|string',
            'numeracion_id' => 'required|integer'
        ]);

        $codigo = $request->codigo;
        $numeracionId = $request->numeracion_id;
        $facturaId = $request->factura_id;

        // Verificar que la numeración existe
        $numeracion = NumeracionFactura::find($numeracionId);
        if (!$numeracion) {
            return response()->json([
                'success' => false,
                'message' => 'La numeración no existe'
            ], 400);
        }

        // Validar que el código no exista en otra factura con la misma numeración
        $query = Factura::where('numeracion', $numeracionId)
            ->where('codigo', $codigo);

        if ($facturaId) {
            $query->where('id', '!=', $facturaId);
        }

        $existe = $query->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe otra factura con ese número en esta numeración'
            ], 200);
        }

        // Si es edición, actualizar la factura existente
        if ($facturaId) {
            $factura = Factura::find($facturaId);
            if (!$factura) {
                return response()->json([
                    'success' => false,
                    'message' => 'La factura no existe'
                ], 404);
            }

            $factura->codigo = $codigo;
            $factura->save();

            return response()->json([
                'success' => true,
                'message' => 'Código actualizado correctamente',
                'codigo' => $codigo
            ], 200);
        }

        // Si es creación, solo retornar el código validado
        // El código se asignará cuando se guarde la factura
        // Si es creación, solo retornar el código validado
        // El código se asignará cuando se guarde la factura
        return response()->json([
            'success' => true,
            'message' => 'Código validado correctamente',
            'codigo' => $codigo
        ], 200);
    }

    /**
     * Obtener el prefijo de una numeración
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNumeracionPrefijo($id)
    {
        $numeracion = NumeracionFactura::find($id);

        if (!$numeracion) {
            return response()->json([
                'success' => false,
                'message' => 'La numeración no existe'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'prefijo' => $numeracion->prefijo
        ], 200);
    }

    public function importarSaldos()
    {
        $this->getAllPermissions(Auth::user()->id);
        view()->share(['title' => 'Importar Saldos Iniciales', 'full' => true]);
        return view('facturas.importar_saldos');
    }

    public function ejemploImportarSaldos()
    {
        $titulosColumnas = array(
            'Identificacion', 'Tipo factura', 'Fecha factura', 'Fecha vencimiento', 'Fecha suspension', 'Saldo inicial'
        );

        $comentarios = array(
            'A' => 'NIT o Cédula del cliente (Obligatorio)',
            'B' => 'Seleccione "Estandar" o "Electronica"',
            'C' => 'Fecha factura (dd-mm-AAAA)',
            'D' => 'Fecha de vencimiento (dd-mm-AAAA)',
            'E' => 'Fecha de suspensión (dd-mm-AAAA)',
            'F' => 'Saldo inicial (valor numérico)'
        );

        $objPHPExcel = new \PHPExcel();
        $tituloReporte = "Importación Saldos Iniciales - " . Auth::user()->empresa()->nombre;

        $letras = array('A', 'B', 'C', 'D', 'E', 'F');
        $ultimaColumna = $letras[count($titulosColumnas) - 1];

        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('A1:' . $ultimaColumna . '1');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $tituloReporte);
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('A2:' . $ultimaColumna . '2');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A2', 'Fecha ' . date('d-m-Y'));

        $estilo = array(
            'font'  => array('bold'  => true, 'size'  => 12, 'name'  => 'Times New Roman'),
            'alignment' => array('horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER)
        );
        $objPHPExcel->getActiveSheet()->getStyle('A1:' . $ultimaColumna . '3')->applyFromArray($estilo);

        $estilo = array(
            'fill' => array(
                'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => substr(Auth::user()->empresa()->color, 1))
            ),
            'font'  => array('bold'  => true, 'size'  => 12, 'name'  => 'Times New Roman', 'color' => array('rgb' => 'FFFFFF')),
            'alignment' => array('horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER)
        );
        $objPHPExcel->getActiveSheet()->getStyle('A3:' . $ultimaColumna . '3')->applyFromArray($estilo);

        for ($i = 0; $i < count($titulosColumnas); $i++) {
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($letras[$i] . '3', utf8_decode($titulosColumnas[$i]));
        }

        foreach ($comentarios as $columna => $texto) {
            $objPHPExcel->getActiveSheet()->getComment($columna . '3')->setAuthor('Integra Colombia')->getText()->createTextRun($texto);
        }

        $estilo = array(
            'font'  => array('size'  => 12, 'name'  => 'Times New Roman'),
            'borders' => array('allborders' => array('style' => \PHPExcel_Style_Border::BORDER_THIN)),
            'alignment' => array('horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER)
        );
        $objPHPExcel->getActiveSheet()->getStyle('A3:' . $ultimaColumna . '3')->applyFromArray($estilo);

        for ($i = 'A'; $i <= $ultimaColumna; $i++) {
            $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension($i)->setAutoSize(TRUE);
        }

        // Validación desplegable para Tipo de Factura (B)
        for ($row = 4; $row <= 200; $row++) {
            $validation = $objPHPExcel->getActiveSheet()->getCell('B' . $row)->getDataValidation();
            $validation->setType(\PHPExcel_Cell_DataValidation::TYPE_LIST);
            $validation->setAllowBlank(false);
            $validation->setShowDropDown(true);
            $validation->setFormula1('"Estandar,Electronica"');
        }

        $objPHPExcel->getActiveSheet()->setTitle('Saldos Iniciales');
        $objPHPExcel->setActiveSheetIndex(0);
        $objPHPExcel->getActiveSheet(0)->freezePane('A4');

        header("Pragma: no-cache");
        header('Content-type: application/vnd.ms-excel');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="Plantilla_Importacion_Saldos_Iniciales.xlsx"');
        header('Cache-Control: max-age=0');
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');
        exit;
    }

    public function importarCargandoSaldos(Request $request)
    {
        $request->validate([
            'archivo' => 'required|mimes:xlsx',
        ], [
            'archivo.mimes' => 'El archivo debe ser de extensión xlsx'
        ]);

        $usar_fechas_corte = $request->has('usar_fechas_corte') ? true : false;

        $imagen = $request->file('archivo');
        $nombre_imagen = 'saldos_' . time() . '.' . $imagen->getClientOriginalExtension();
        $path = public_path() . '/images/Empresas/Empresa' . Auth::user()->empresa;
        $imagen->move($path, $nombre_imagen);
        ini_set('max_execution_time', 500);
        $fileWithPath = $path . "/" . $nombre_imagen;

        $inputFileType = \PHPExcel_IOFactory::identify($fileWithPath);
        $objReader = \PHPExcel_IOFactory::createReader($inputFileType);
        $objPHPExcel = $objReader->load($fileWithPath);
        $sheet = $objPHPExcel->getSheet(0);
        $highestRow = $sheet->getHighestRow();

        $creados = 0;
        $errores = [];

        // Asegurar que existe el item inventario "saldo_inicial"
        $inventarioItem = Inventario::where('empresa', Auth::user()->empresa)->where('ref', 'saldo_inicial')->first();
        if (!$inventarioItem) {
            $inventarioItem = new Inventario();
            $inventarioItem->empresa = Auth::user()->empresa;
            $inventarioItem->producto = 'Saldo Inicial';
            $inventarioItem->ref = 'saldo_inicial';
            $inventarioItem->descripcion = 'Item automático creado para gestionar importaciones de saldos iniciales';
            $inventarioItem->precio = 0;
            $inventarioItem->id_impuesto = 2; // Por defecto Ninguno o algo de 0
            $inventarioItem->impuesto = 0;
            $inventarioItem->tipo_producto = 2;
            $inventarioItem->unidad = 1;
            $inventarioItem->nro = 0;
            $inventarioItem->type = 'SERVICIO';
            $inventarioItem->save();
        }

        // Obtener numeraciones preferidas
        $numeracionEst = NumeracionFactura::where('empresa', Auth::user()->empresa)->where('prefijo', '!=', '0')->where('tipo', 1)->where('estado', 1)->first();
        $numeracionElec = NumeracionFactura::where('empresa', Auth::user()->empresa)->where('preferida', 1)->where('tipo', 2)->where('estado', 1)->first();

        if (!$numeracionEst) {
            $errores[] = "No hay numeración estándar activa definida.";
            return back()->withErrors($errores)->with('danger', 'Complete la configuración de numeración estándar');
        }

        for ($row = 4; $row <= $highestRow; $row++) {
            $identificacion = trim($sheet->getCell("A" . $row)->getValue());
            if (empty($identificacion)) {
                break;
            }

            $tipo_factura = strtoupper(trim($sheet->getCell("B" . $row)->getValue()));
            $fecha_factura = $sheet->getCell("C" . $row)->getFormattedValue();
            $fecha_vcto = $sheet->getCell("D" . $row)->getFormattedValue();
            $fecha_suspension = $sheet->getCell("E" . $row)->getFormattedValue();
            $saldo_inicial = trim($sheet->getCell("F" . $row)->getValue());

            if (empty($tipo_factura) || empty($saldo_inicial) || $saldo_inicial == 0) {
                $errores[] = "Fila $row: Faltan datos (Tipo factura y Saldo inicial) o el saldo inicial es 0";
                continue;
            }

            $contacto = Contacto::where('empresa', Auth::user()->empresa)->where('nit', $identificacion)->first();
            if (!$contacto) {
                $errores[] = "Fila $row: No se encontró contacto asociado a la cédula/NIT: $identificacion";
                continue;
            }

            $tipo = 1;
            $numeracionToUse = $numeracionEst;
            if ($tipo_factura == 'ELECTRONICA') {
                $tipo = 2;
                $numeracionToUse = $numeracionElec;
                if (!$numeracionElec) {
                    $errores[] = "Fila $row: Se ha seleccionado electrónica pero no hay numeración electrónica activa.";
                    continue;
                }
            }

            // Manejo de Fechas
            $fecha = null; $vencimiento = null; $suspensionDate = null;

            if ($usar_fechas_corte) {
                // Obtener grupo de corte desde el contrato
                $contrato = Contrato::where('client_id', $contacto->id)->where('status', 1)->first();
                if ($contrato && $contrato->grupo_corte) {
                    $grupo = $contrato->grupo_corte();
                    if ($grupo) {
                        $currentMonth = date('m');
                        $currentYear = date('Y');
                        
                        // Parsear las fechas considerando si el día es del mismo mes o siguiente
                        $fecha = Carbon::createFromFormat('Y-m-d', $currentYear . '-' . $currentMonth . '-' . min($grupo->fecha_factura, Carbon::now()->daysInMonth))->format('Y-m-d');
                        
                        // Vencimiento mes o siguiente (dependiendo del tipo)
                        $mesVencimiento = ($grupo->fecha_factura > $grupo->fecha_suspension) ? date('m', strtotime('+1 month')) : $currentMonth;
                        $yearVencimiento = ($grupo->fecha_factura > $grupo->fecha_suspension && $currentMonth == '12') ? $currentYear + 1 : $currentYear;
                        $cantDiasMesVenc = Carbon::create($yearVencimiento, $mesVencimiento, 1)->daysInMonth;
                        $vencimiento = Carbon::createFromFormat('Y-m-d', $yearVencimiento . '-' . $mesVencimiento . '-' . min($grupo->fecha_suspension, $cantDiasMesVenc))->format('Y-m-d');
                        $suspensionDate = $vencimiento;
                    }
                }
            }

            if (!$fecha) {
                // Usar Fechas Excel  si no se calculó arriba o form value = off
                try {
                    $fecha = Carbon::parse(str_replace('/', '-', $fecha_factura))->format('Y-m-d');
                    $vencimiento = Carbon::parse(str_replace('/', '-', $fecha_vcto))->format('Y-m-d');
                    $suspensionDate = Carbon::parse(str_replace('/', '-', $fecha_suspension))->format('Y-m-d');
                } catch (\Exception $e) {
                    $errores[] = "Fila $row: Error en el formato de fechas ($fecha_factura) - Use MM-DD-YYYY o DD-MM-YYYY válido";
                    continue;
                }
            }

            $inicio = $numeracionToUse->inicio;
            $numeracionToUse->inicio += 1;
            $numeracionToUse->save();

            $codigo = $numeracionToUse->prefijo . $inicio;

            $factura = new Factura();
            $factura->nro = $inicio; // Aquí asumo que nro es similar a inicio o autoincremental simple
            $factura->codigo = $codigo;
            $factura->numeracion = $numeracionToUse->id;
            $factura->empresa = Auth::user()->empresa;
            $factura->cliente = $contacto->id;
            $factura->fecha = $fecha;
            $factura->vencimiento = $vencimiento;
            $factura->suspension = $suspensionDate;
            $factura->tipo = $tipo;
            $factura->factura_mes_manual = 0;
            $factura->estatus = 1;
            // $factura->contrato_id // dejamos null porque no pertenece a un mes de contrato regular
            $factura->save();

            $item = new ItemsFactura();
            $item->factura = $factura->id;
            $item->producto = $inventarioItem->id;
            $item->precio = $saldo_inicial;
            $item->cant = 1;
            $item->desc = 0;
            $item->impuesto = 0;
            $item->save();

            $creados++;
        }

        if (file_exists($fileWithPath)) {
            unlink($fileWithPath);
        }

        if (count($errores) > 0) {
            return redirect()->route('saldos_iniciales.importar')->withErrors($errores)->with('success', "Se han creado $creados facturas de saldos iniciales existosamente. Hubieron algunos errores.");
        }

        return redirect('empresa/factura-index')->with('success', "Se han importado $creados saldos iniciales existosamente!");
    }
}
