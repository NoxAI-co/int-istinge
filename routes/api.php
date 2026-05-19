<?php

use App\Contacto;
use App\Contrato;
use App\ContratoDigital;
use App\Empresa;
use App\Http\Controllers\ContratosController;
use App\Model\Ingresos\Factura;
use App\Model\Ingresos\FacturaRetencion;
use App\Model\Ingresos\ItemsFactura;
use App\Model\Ingresos\NotaCredito;
use App\MovimientoLOG;
use App\NumeracionFactura;
use App\Radicado;
use App\RadicadoLOG;
use App\Servicio;
use App\User;
use Barryvdh\DomPDF\Facade as PDF;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::post('contrato-digital/{key}', function (Request $request, $key) {
    
    $contrato = Contrato::where('nro', $key)->first();
    $contacto = Contacto::where('nit', $key)->first();

    if ($contrato) {
        $contratoDigital = ContratoDigital::where('contrato_id', $contrato->id)->first();
        if (!$contratoDigital) {
            $contratoDigital = new ContratoDigital();
            $contratoDigital->contrato_id = $contrato->id;
            $contratoDigital->cliente_id = $contrato->client_id;
        }
    } else if ($contacto) {
        $contratoDigital = ContratoDigital::where('cliente_id', $contacto->id)->whereNull('contrato_id')->first();
        if (!$contratoDigital) {
            $contratoDigital = new ContratoDigital();
            $contratoDigital->cliente_id = $contacto->id;
        }
    }

    if (isset($contratoDigital)) {
        $contratoDigital->firma            = $request->firma_isp;
        $contratoDigital->fecha_firma      = date('Y-m-d');
        $contratoDigital->estado_firma     = 1;
        $contratoDigital->save();

        $empresa    = Empresa::find($contrato ? $contrato->empresa : 1);
        $formulario = false;
        $title      = $empresa->nombre;

        $contacto = $contacto ?: Contacto::where('id', $contrato->client_id)->first();

        view()->share([
            'seccion'    => 'contratos',
            'subseccion' => 'asignaciones',
            'title'      => 'Asignaciones',
            'icon'       => 'fas fa-file-contract'
        ]);
        $digital = $contratoDigital;
        return view('asignaciones.firma')
            ->with(compact('contacto', 'title', 'empresa', 'formulario','contrato', 'digital'));
    }
    \Log::warning('Contrato digital 403: no se encontró contrato/contacto para key', ['key' => $key]);
    abort(403, 'ACCIÓN NO AUTORIZADA');
})->name('asignaciones.store_firma');

Route::post('token-combopay', 'CronController@tokenComboPay');
Route::post('combopay/payment-link', 'CronController@crearLinkPago');


Route::get('getInterfaces/{mikrotik}', 'Controller@getInterfaces');
Route::get('getDetails/{cliente}/{contrato?}', 'Controller@getDetails');
Route::get('getPlanes/{mikrotik}', 'Controller@getPlanes');
Route::get('{empresa}/getDataSearch/{key}', 'Controller@getAllData');
Route::get('getMigracion/{mikrotik}', 'Controller@getMigracion');
Route::get('getPing/{rango}', 'Controller@getPing');
Route::get('getNotificaciones', 'Controller@getNotificaciones');
Route::get('getIps/{mikrotik}', 'Controller@getIps');
Route::get('getGrupo/{grupo}', 'Controller@getGrupo');
Route::get('getSegmentos/{mikrotik}', 'Controller@getSegmentos');
Route::get('getContracts/{id}', 'Controller@getContracts');
Route::get('getSubnetting/{ip_address}/{prefijo}', 'Controller@getSubnetting');
Route::get('habilitarContratos/{fecha}', 'CronController@habilitarContratos');
Route::get('getMAC/{mk}/{ip}', 'Controller@getMAC');

/* api whatsive */

Route::post('whatsapp/{action}', 'WhatsappController@whatsappApi');
Route::post('uploadfile', 'WhatsappController@whatsappUpload')->name("uploadFile");

/** EVENTOS WOMPI **/
Route::post('pagos/wompi', 'CronController@eventosWompi');

/** EVENTOS PAYU **/
Route::post('pagos/payu', 'CronController@eventosPayu');

/** EVENTOS EPAYCO **/
Route::post('pagos/epayco', 'CronController@eventosEpayco');

/** EVENTOS COMBOPAY **/
Route::post('pagos/combopay', 'CronController@eventosCombopay');

/** EVENTOS TOPPAY **/
Route::post('pagos/toppay', 'CronController@eventosTopPay');

/** EVENTOS ONEPAY **/
Route::post('pagos/onepay', 'CronController@eventosOnePayWebhook');

/**
 * Mostrar los datos de la factura mediante la llave unica asignada en el método
 * facturasController@enviar
 */
Route::get('facturaElectronica/{key}', function ($key) {
    $factura     = Factura::where('nonkey', $key)->get()->first();
    if(!$factura){
        return abort(419);
    }
    $limitDate   = (Carbon::parse($factura->created_at))->addHour();
    $actualDate  = Carbon::now();
    $noEdit      = (( $limitDate->greaterThanOrEqualTo($actualDate) && $factura->modificado == 0)? false: true);
    $mody        = $factura->modificado == 1 ? true : false;
    if ($factura){
        $factura = Factura::find($factura->id);
        $empresa = Empresa::find($factura->empresa);
        return view('facturas.landing')->with(compact('factura', 'empresa', 'key', 'noEdit', 'mody'));
    }
    return abort(419);
});

Route::group(['prefix' => 'v1', 'middleware' => 'auth.master_token', 'namespace' => 'API\V1'], function () {
    // API Creadas
    Route::get('/deudacontrato', 'GeneralController@deudacontrato');
    Route::get('/medios-pago', 'GeneralController@mediosPago');
    Route::post('/create-radicado', 'GeneralController@createRadicado');
    Route::get('/tipos-servicio', 'GeneralController@tiposServicio');
    Route::get('/contactos', 'ContactosController@index');
    
    // API Existentes adaptadas (asumiendo se redirijan o se use el anterior controlador si no se crearon nuevos). 
    // Debido a que se indicó mover los planes a v1, usamos la ruta:
    Route::get('/planes', '\App\Http\Controllers\API\ExternalApiController@getPlanes');
    Route::get('/mikrotiks', '\App\Http\Controllers\API\ExternalApiController@getMikrotiks');
    Route::get('/nodos', '\App\Http\Controllers\API\ExternalApiController@getNodos');
    Route::get('/grupos-corte', '\App\Http\Controllers\API\ExternalApiController@getGruposCorte');
    Route::get('/access-points', '\App\Http\Controllers\API\ExternalApiController@getAccessPoints');
    Route::get('/cajas-nap', '\App\Http\Controllers\API\ExternalApiController@getCajasNap');
    Route::get('/oficinas', '\App\Http\Controllers\API\ExternalApiController@getOficinas');
    Route::get('/canales', '\App\Http\Controllers\API\ExternalApiController@getCanales');
});

Route::get('facturaElectronica/{key}/pdf', function ($key) {
    $tipo1=$tipo = 'original';

    /**
     * toma en cuenta que para ver los mismos
     * datos debemos hacer la misma consulta
     **/

    $factura = Factura::where('nonkey', $key)->first();
    $empresa = Empresa::find($factura->empresa);
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

    $resolucion =  NumeracionFactura::where('empresa', $factura->empresa)->latest()->first();

    if ($factura) {

        $items = ItemsFactura::where('factura',$factura->id)->get();
        $itemscount=ItemsFactura::where('factura',$factura->id)->count();
        $retenciones = FacturaRetencion::where('factura', $factura->id)->get();

        $pdf = PDF::loadView('pdf.facturaAPI', compact('items', 'factura', 'itemscount', 'tipo', 'retenciones',
            'resolucion', 'empresa'));
        return $pdf->stream('factura.pdf');

    }
})->name('imprimirFe');

Route::get('factura/{key}/pdf-onepay', function ($key) {
    // Si no hay usuario autenticado, usar el primer usuario con rol != 1
    if (!\Illuminate\Support\Facades\Auth::check()) {
        $user = User::where('rol', '!=', 1)->first();
        if ($user) {
            \Illuminate\Support\Facades\Auth::login($user);
        }
    }

    $factura = Factura::where('nonkey', $key)->first();
    
    if (!$factura) {
        return abort(404, 'Factura no encontrada');
    }

    // Usar el método Imprimir del FacturasController
    return \App\Http\Controllers\FacturasController::Imprimir($factura->id, 'original', true, false, false);
})->name('factura.pdf.onepay');

Route::get('facturaElectronica/{key}/xml', function ($key) {
    $FacturaVenta = Factura::where('nonkey', $key)->first();
    $ResolucionNumeracion = NumeracionFactura::where('empresa',$FacturaVenta->empresa)->where('preferida',1)->first();

    $infoEmpresa = Empresa::find($FacturaVenta->empresa);
    $data['Empresa'] = $infoEmpresa->toArray();

    $retenciones = FacturaRetencion::where('factura', $FacturaVenta->id)->get();

    $impTotal = 0;

    foreach ($FacturaVenta->totalAPI($FacturaVenta->empresa)->imp as $totalImp){
        if(isset($totalImp->total)){
            $impTotal = $totalImp->total;
        }
    }
    $items = ItemsFactura::where('factura',$FacturaVenta->id)->get();

    $CUFEvr = $FacturaVenta->info_cufeAPI($FacturaVenta->id, $impTotal, $FacturaVenta->empresa);

    $infoCliente = Contacto::find($FacturaVenta->cliente);
    $data['Cliente'] = $infoCliente->toArray();

    $responsabilidades_empresa = DB::table('empresa_responsabilidad as er')
        ->join('responsabilidades_facturacion as rf','rf.id','=','er.id_responsabilidad')
        ->select('rf.*')
        ->where('er.id_empresa',$FacturaVenta->empresa)
        ->get();

    return $xml = response()->view('templates.xmlAPI.01',compact('infoEmpresa','CUFEvr','ResolucionNumeracion','FacturaVenta', 'data','items','retenciones','responsabilidades_empresa'))->header('Cache-Control', 'public')
        ->header('Content-Description', 'File Transfer')
        ->header('Content-Disposition', 'attachment; filename=FV-'.$FacturaVenta->codigo.'.xml')
        ->header('Content-Transfer-Encoding', 'binary')
        ->header('Content-Type', 'text/xml');
})->name('xmlFe');

/**
 * Se realizan los cambios correspondientes a seleccionar un estatus de respuesta la factura
 * En cualquier caso, la factura ha sido modificada por el usuario, posteriormente ya no podrá
 */
Route::post('response', function (Request $request){
    $factura = Factura::where('nonkey', $request->nonkey)->get()->first();
    $factura = Factura::find($factura->id);
    $empresa = Empresa::find($factura->empresa);
    if ($request->statusdian == 0){
        $factura->statusdian        = $request->statusdian;
        $factura->observacionesdian = $request->observacionesdian;
        $status = "RECHAZADO";
    }else{
        $factura->statusdian        = $request->statusdian;
        $status = "ACEPTADO";
    }
    $factura->modificado = 1;
    $save = $factura->save();
    $empresa = $empresa->nombre;
    return view('facturas.messageLanding')->with(compact('empresa', 'status'));
})->name('saveFe');

Route::get('NotaCreditoElectronica/{id}', function ($id) {
    $nota = NotaCredito::find($id);
    $empresa = Empresa::find($nota->empresa);
    return view('notascredito.landing')->with(compact('nota', 'empresa'));
});

/**
 * FIRMA DIGITAL
 */
Route::get('contrato-digital/{key}', function ($key) {
    $contrato = Contrato::where('nro', $key)->first();
    $contacto = Contacto::where('nit', $key)->first();

    if($contrato){
        $contacto = Contacto::find($contrato->client_id);
    }

    if ($contacto) {
        $digital = null;
        if ($contrato) {
            $digital = ContratoDigital::where('contrato_id', $contrato->id)->first();
        } else {
            $digital = ContratoDigital::where('cliente_id', $contacto->id)->whereNull('contrato_id')->first();
        }

        $empresa = Empresa::find(1);
        $title   = $empresa->nombre;
        view()->share([
            'seccion'    => 'contratos',
            'subseccion' => 'asignaciones',
            'title'      => 'Asignaciones',
            'icon'       => 'fas fa-file-contract'
        ]);
        $formulario = true;
        return view('asignaciones.firma')
            ->with(compact('contacto', 'title', 'empresa', 'formulario', 'contrato', 'digital'));
    }
    abort(403, 'ACCIÓN NO AUTORIZADA');

});








