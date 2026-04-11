<?php

namespace App\Builders\JsonBuilders;

use App\Empresa;
use App\Http\Controllers\Controller;
use App\Impuesto;
use App\Model\Ingresos\FacturaRetencion;
use App\NotaRetencion;
use App\Vendedor;
use Carbon\Carbon;
use DB;
use InvalidArgumentException;

class InvoiceJsonBuilder
{

    public static function buildFromHeadInvoice($factura,$resolucion, $modoBTW, $operacionCodigo){

        $empresa = Empresa::Find($factura->empresa);
        $totales = $factura->total();
        $forma_pago = $factura->forma_pago();
        $plazo = intval(Carbon::parse($factura->fecha)->diffInDays($factura->vencimiento));
        $resolucion_vigencia_meses = intval(Carbon::parse($resolucion->desde)->diffInMonths($resolucion->hasta));
        $resolucion_msj = 'Resolución DIAN No. ' . $resolucion->nroresolucion . ' de ' . $resolucion->desde . ' Prefijo ' . $resolucion->prefijo . ' - Numeración ' . $resolucion->inicioverdadero . ' a la ' . $resolucion->final . ', vigencia ' . $resolucion_vigencia_meses . ' meses.';

        $totalIva = 0;
        $totalInc = 0;
        $retenciones = FacturaRetencion::where('factura', $factura->id)->get();
        $totalRetenciones = 0;

        foreach($retenciones as $retencion){
            $totalRetenciones+= $retencion->valor;
        }

        foreach ($totales->imp as $key => $imp) {

            if (isset($imp->total) && $imp->tipo == 1) {
                $totalIva+= round($imp->total,2);
            } elseif (isset($imp->total) && $imp->tipo == 3 || isset($imp->total) && $imp->tipo == 4) {
                $totalInc+= round($imp->total,2);
            }
        }

        if($modoBTW == 'test'){
            $empresa->nit = '901548158';
            $resolucion->nroresolucion = '18762008997356';
        }

        $moneda = 'COP';
        $monedaCambio  = 'COP';
        if($factura->tipo == 4 && isset($factura->datosExportacion)){
            $monedaCambio = $factura->datosExportacion->data_moneda->codigo;
        }

        $codigo = $factura->codigo;
        $prefijo = $resolucion->prefijo;

        if (str_starts_with($codigo, $prefijo)) {
            $nroFactura = substr($codigo, strlen($prefijo));
        } else {
            $nroFactura = $codigo;
        }

        if (preg_match('/\d/', $prefijo)) {
            $legalNumber = $prefijo . '-' . $nroFactura;
        } else {
            $legalNumber = $prefijo . $nroFactura;
        }

        return [
            'head' => [
                'company' => $empresa->nit,
                'custNum' => $empresa->nit,
                'invoiceType' => 'InvoiceType',
                'invoiceNum' => $factura->codigo,
                'legalNumber' => $legalNumber,
                'invoiceDate' => $factura->fecha,
                'dueDate' => $factura->vencimiento,
                'operationType_c' => $operacionCodigo,
                'dspDocSubTotal' => round($totales->subtotal - $totales->descuento, 2),
                'docTaxAmt' => round($totalIva,2),
                'docWHTaxAmt' => round($totalRetenciones,2),
                'dspDocInvoiceAmt' => round($totalIva,2) + round($totales->subtotal - $totales->descuento,2),
                'discount' => round($totales->descuento),
                'currencyCodeCurrencyID' => $monedaCambio,
                'currencyCode' => $moneda,
                'salesRepCode1' => null,
                'salesRepName1' => self::getVendedorNombre($factura, $empresa),
                'invoiceComment' => $factura->observaciones,
                'resolution1' => $resolucion_msj,
                'resolution2' => '',
                'resolutionDateInvoice' => $resolucion->desde,
                'resolutionNumber' => $resolucion->nroresolucion,
                'paymentMeansID_c' => $forma_pago,
                'paymentMeansDescription' => $forma_pago == 1 ? 'Contado' : 'Crédito',
                'paymentMeansCode_c' => $forma_pago == 1 ? '10' : '1',
                'paymentDurationMeasure' => $plazo,
                'paymentDueDate' => $factura->vencimiento,
                'invoiceComment' => $factura->tipo_operacion == 3
                ? $factura->notaDetalleXml()
                : $factura->nota,
                'contingencyInvoiceDian_c' => false,
                'contingencyInvoiceOF_c' => false,
                'issueDateContingency' => null,
                'invoiceRefDate' => null,
                'calculationRate_c' => isset($factura->trmActual) ? round($factura->trmActual->valor_cop) : null,
                'dateCalculationRate_c' => isset($factura->trmActual) ? $factura->trmActual->fecha : null,
                'netWeight' => isset($factura->datosExportacion) ? $factura->datosExportacion->peso_neto : null,
                'grossWeight' => isset($factura->datosExportacion) ? $factura->datosExportacion->peso_bruto : null,
                'portofEntry' => isset($factura->datosExportacion) ? $factura->datosExportacion->destino : null,
                'shipViaCodeDescription' => isset($factura->datosExportacion) ? $factura->datosExportacion->medio_transporte : null,
            ]
        ];
    }

    public static function buildFromHeadCreditNote($nota, $factura, $resolucion, $modoBTW, $operacionCodigo){
        $empresa = Empresa::Find($factura->empresa);
        $totales = $nota->total();
        $forma_pago = $factura->forma_pago();
        $plazo = intval(Carbon::parse($factura->fecha)->diffInDays($factura->vencimiento));
        $resolucion_vigencia_meses = intval(Carbon::parse($resolucion->desde)->diffInMonths($resolucion->hasta));
        $resolucion_msj = 'Resolución DIAN No. ' . $resolucion->nroresolucion . ' de ' . $resolucion->desde . ' Prefijo ' . $resolucion->prefijo . ' - Numeración ' . $resolucion->inicioverdadero . ' a la ' . $resolucion->final . ', vigencia ' . $resolucion_vigencia_meses . ' meses.';

        $totalIva = 0;
        $totalInc = 0;
        $retenciones = NotaRetencion::where('notas', $nota->id)->where('tipo',1)->get();
        $totalRetenciones = 0;

        foreach($retenciones as $retencion){
            $totalRetenciones+= $retencion->valor;
        }

        foreach ($totales->imp as $key => $imp) {

            if (isset($imp->total) && $imp->tipo == 1) {
                $totalIva+= round($imp->total,2);
            } elseif (isset($imp->total) && $imp->tipo == 3 || isset($imp->total) && $imp->tipo == 4) {
                $totalInc+= round($imp->total,2);
            }
        }
        if($modoBTW == 'test'){
            $empresa->nit = '901548158';
            $resolucion->nroresolucion = '18762008997356';
        }

        //Validaciones cuando la factura no es de BTW.
        if($factura->fecha < '2025-11-05'){

            if($factura->uuid != ""){
                $newsFields = [
                    'invoiceRefCufe' => $factura->uuid,
                    'invoiceRefDate' => $factura->fecha,
                    'documentRefType' => '01'
                ];
            }else {
                $CUFEvr = $factura->info_cufe($factura->id);
                $newsFields = [
                    'invoiceRefCufe' => $CUFEvr,
                    'invoiceRefDate' => $factura->fecha,
                    'documentRefType' => '01'
                ];
            }
        }

        return [
            'head' => ($newsFields ?? []) + [
                'company' => $empresa->nit,
                'custNum' => $empresa->nit,
                'invoiceType' => 'CreditNoteType',
                'invoiceNum' => (string) ($nota->prefijo . $nota->nro),
                'legalNumber' => (string) ($nota->prefijo . $nota->nro),
                'operationType_c' => $operacionCodigo,
                'invoiceDate' => $nota->fecha,
                "invoiceRef" => $factura->codigo,
                "cmReasonCode_c" => $nota->tipo,
                "cmReasonDesc_c" => $nota->tipo(),
                'dueDate' => $nota->fecha,
                'dspDocSubTotal' => round($totales->subtotal - $totales->descuento,2),
                'docTaxAmt' => round($totalIva,2),
                'dspDocInvoiceAmt' => round($totalIva,2) + round($totales->subtotal - $totales->descuento,2),
                'discount' => round($totales->descuento),
                'currencyCodeCurrencyID' => 'COP',
                'currencyCode' => 'COP',
                'salesRepCode1' => null,
                'docWHTaxAmt' => round($totalRetenciones,2),
                'salesRepName1' => isset($factura->vendedorObj->nombre) ? $factura->vendedorObj->nombre : "Vendedor General",
                'invoiceComment' => $nota->observaciones,
                'resolution1' => $resolucion_msj,
                'resolution2' => '',
                'resolutionDateInvoice' => $resolucion->desde,
                'resolutionNumber' => $resolucion->nroresolucion,
                'paymentMeansID_c' => $forma_pago,
                'paymentMeansDescription' => $forma_pago == 1 ? 'Contado' : 'Crédito',
                'paymentMeansCode_c' => $forma_pago == 1 ? '10' : '1',
                'paymentDurationMeasure' => $plazo,
                'paymentDueDate' => $factura->vencimiento,
                'contingencyInvoiceDian_c' => false,
                'contingencyInvoiceOF_c' => false,
                'issueDateContingency' => null,
                'invoiceRefDate' => null,
                'calculationRate_c' => null,
                'dateCalculationRate_c' => null
            ]
        ];
    }

    public static function buildFromDetails($factura, $modoBTW){

        $items = $factura->items;

        if($modoBTW == 'test'){
            $nit = '901548158';
        }else{
            $nit = Empresa::Find($factura->empresa)->nit;
        }

        $monedaCambio  = 'COP';
        $datosExportacion = null;

        if($factura->tipo == 4 && isset($factura->datosExportacion)){
            $datosExportacion = $factura->datosExportacion;
            $monedaCambio = $datosExportacion->data_moneda->codigo;
        }


        return [
            'details' => array_map(function ($item,$index) use ($nit,$factura,$monedaCambio){

                $subtotal = $item->precio * $item->cant;
                $discount = round(($item->desc / 100) * $subtotal, 2);
                $neto = $subtotal - $discount;

                //Fact. Exportacion.
                $precioExtranjero = "0.00";
                $precioExntranjeroCompleto = "0.00";
                if($factura->tipo == 4 && isset($factura->trmActual) && is_object($factura->trmActual)){
                    $precioExtranjero = self::convertirPrecioUSD($item->precio, $factura->trmActual);
                    $precioExntranjeroCompleto = self::convertirPrecioUSD($item->precio * $item->cant - $discount, $factura->trmActual);
                }


                return [
                    'company' => $nit,
                    'invoiceNum' => isset($factura->codigo) ? $factura->codigo : (isset($factura->prefijo) ? $factura->prefijo . $factura->nro : $factura->nro),
                    'invoiceLine' => $index + 1,
                    'partNum' => $item->ref,
                    'lineDesc' => $item->producto() ?? $item->descripcion,
                    'taxAmtLineIVA' => round(($item->impuesto / 100) * $neto, 2),
                    'sellingShipQty' => round($item->cant),
                    'salesUM' => '94',
                    'unitPrice' => round($item->precio),
                    'docUnitPrice' => $precioExtranjero,
                    'extPrice' => round($item->precio * $item->cant) - $discount,
                    'docExtPrice' => $precioExntranjeroCompleto,
                    'discountPercent' => $item->desc ?? 0,
                    'discount' => $discount,
                    'currencyCode' => $monedaCambio,
                    'idSupplier' => null,
                    'codInvima' => null,
                    'lineDesc3' => isset($item->ex_codigo_arancelario) ? $item->ex_codigo_arancelario : null,
                    'lineDesc2' => isset($item->ex_modelo) ? $item->ex_modelo : null,
                    'standardItemID' => '999',
                    'brandName' => isset($item->ex_marca) ? $item->ex_marca : null,
                ];
            },$items->all(), array_keys($items->all()))
        ];
    }

    public static function convertirPrecioUSD(float $precioCOP, object $trm): float {
        if ($trm->valor_cop <= 0) {
            throw new InvalidArgumentException("El valor_cop no puede ser 0 o negativo");
        }
        return round($precioCOP / $trm->valor_cop, 2); // con 2 decimales
    }

    public static function buildFromAdditionalTags($factura, $empresa, $modoBTW)
    {
        $additionalTags = [];

        $additionalTags[] = (object)[
            'company'          => $empresa->nit,
            'invoiceNum'       => isset($factura->codigo) ? $factura->codigo : (isset($factura->prefijo) ? $factura->prefijo . $factura->nro : $factura->nro),
            'name'             => 'Note',
            'value'            => $factura->tipo_operacion == 3
                                    ? $factura->notaDetalleXml()
                                    : $factura->nota,
            'nodoPersonalizado'=> 'terceros',
        ];

        return $additionalTags;
    }


    public static function buildFromCompany($empresa, $modoBTW){

        $municipio = $empresa->municipio();
        $departamento = $empresa->departamento();

        $responsabilidades_empresa = DB::table('empresa_responsabilidad as er')
            ->join('responsabilidades_facturacion as rf', 'rf.id', '=', 'er.id_responsabilidad')
            ->select('rf.*')
            ->where('er.id_empresa', $empresa->id)
            ->get();

        $responsabilidades = "";
        $re_cont = $responsabilidades_empresa->count();
        $i = 1;
        foreach($responsabilidades_empresa as $re){
            if($re_cont == $i){
                $responsabilidades .= $re->codigo;
            }else{
                $responsabilidades .= $re->codigo . ";";
            }
            $i++;
        }

        if($modoBTW == 'test'){
            $empresa->nit = '901548158';
        }

        return [
            'company' => [
                'company' => $empresa->nit,
                'stateTaxID' => $empresa->nit,
                'name' => $empresa->nombre,
                'regimeType_c' => '05',
                'fiscalResposability_c' => $responsabilidades,
                'operationType_c' => 10, //Persona residente 10 - no residente 11
                'companyType_c' => $empresa->tipo_persona == 'j' ? 1 : 2,
                'state' => $departamento->nombre,
                'stateNum' => $departamento->codigo,
                'city' => $municipio->nombre,
                'cityNum' => $municipio->codigo_completo,
                'industryClassificationCode_c'=> "",
                'identificationType'=> $empresa->tipoIdentificacion->codigo_dian,
                'address1'=> $empresa->direccion,
                'country'=> $empresa->fk_idpais == 'CO' ? 'COLOMBIA' : $empresa->fk_idpais,
                'postalZone_c'=> $municipio->codigo_completo,
                'phoneNum'=> $empresa->telefono,
                'email' => $empresa->email,
                'attrOperationType_c' => "",
                'faxNum' =>  '',
                'webPage' => '',
                'companyOrigin' => $empresa->fk_idpais == 'CO' ? 'COLOMBIA' : $empresa->fk_idpais,
                'shareholder'=> '',
                'participationPercent' => ''
            ]
        ];

    }

    public static function buildFromCustomer($cliente,$empresa, $modoBTW, $factura){

        $municipio = $cliente->municipio();
        $departamento = $cliente->departamento();

        if($modoBTW == 'test'){
            $empresa->nit = '901548158';
        }

        $monedaCambio  = 'COP';
        if($factura->tipo == 4 && isset($factura->datosExportacion)){
            $monedaCambio = $factura->datosExportacion->data_moneda->codigo;
        }

        return [
            'customer' => [
                'company' => $empresa->nit,
                'custID' => $cliente->nit,
                'resaleID' => $cliente->nit,
                'custNum' => $cliente->nit,
                'name' => $cliente->nombre . ' ' . $cliente->apellidos(),
                'identificationType' => $cliente->identificacion->codigo_dian,
                'address1' => $cliente->direccion,
                'email' => $cliente->email,
                'phoneNum' => $cliente->telefono1,
                'country' => $cliente->fk_idpais == 'CO' ? 'COLOMBIA' : $cliente->fk_idpais,
                'state' => $departamento->nombre,
                'stateNum' => $departamento->codigo,
                'city' => $municipio->nombre,
                'cityNum' => $municipio->codigo_completo,
                'codPostal' => $municipio->codigo_completo,
                'currencyCode' => $monedaCambio,
                'regimeType_c' => 'No aplica',
                'fiscalResposability_c' => 'R-99-PN',
                'termsDescription' => null,
                'territoryTerritoryDesc' => null,
                'contactsNumber' => null,
                'shipToCity' => null,
                'shipToEmail' => null,
                'shipToPhoneNum' => null,
                'shipToAddress' => null,
                'shipToId' => null,
                'shipToName' => null
            ]
        ];

    }

    public static function buildFromTaxes($isNota, $factura, $empresa, $modoBTW){

        $total = $factura->total();
        $withholdingTaxInvoice = $total->reten;

        $items = $factura->items;

        $k = 1;
        $totalImpuestos = 0;
        $rateCode = '01';
        $taxes = [];

        foreach($items as $item){

            if($item->impuesto > 0){

                $item->tipo = Impuesto::Find($item->id_impuesto)->tipo;
                $descuento = ($item->desc / 100) * ($item->precio * $item->cant);
                $totalSobre = $item->precio * $item->cant - $descuento;
                $totalImp = ($item->impuesto / 100) * $totalSobre;

                $totalImpuestos = $totalImpuestos + $totalImp;

                    if($modoBTW == 'test'){
                        $nit = '901548158';
                    }else{
                        $nit = $empresa->nit;
                    }

                    if ($item->tipo == 1) {
                        $rateCode = '01'; //IVA
                    } elseif ($item->tipo == 3 || isset($item->total) && $item->tipo == 4) {
                        $rateCode = '04'; //INC
                    }

                    $taxes[] = (object) [
                        'company' => $nit,
                        'invoiceNum' => isset($factura->codigo) ? $factura->codigo : (isset($factura->prefijo) ? $factura->prefijo . $factura->nro : $factura->nro),
                        'invoiceLine' => $k,
                        'currencyCode' => 'COP',
                        'rateCode' => $rateCode,
                        'idImpDIAN_c' => $rateCode,
                        'docTaxableAmt' => round($totalSobre,2),
                        'taxAmt' => round($totalImp,2),
                        'docTaxAmt' => round($totalImp,2),
                        'percent' => round($item->impuesto,2),
                        'withholdingTax_c' => false
                    ];
                    $k++;
            }else{
                $k++;
            }
        }

        if(!$isNota){
            foreach($withholdingTaxInvoice as $retencion){
                if(isset($retencion->total)){

                    if($modoBTW == 'test'){
                        $nit = '901548158';
                    }else{
                        $nit = $empresa->nit;
                    }

                    $sobre_quien_retiene = 0;

                    if($retencion->tipo == 1){
                        $sobre_quien_retiene = round($totalImpuestos,2);
                    }else{
                        $sobre_quien_retiene = round($total->resul,2);
                    }

                    if($retencion->tipo == 1){
                        $rateCode = '05'; //ReteIva
                    }
                    else if($retencion->tipo == 2){
                        $rateCode = '06'; //ReteFuente
                    }
                    else {
                        $rateCode = 'ZZ'; //Indefinido
                    }


                    $taxes[] = (object) [
                        'company' => $nit,
                        'invoiceNum' => isset($factura->codigo) ? $factura->codigo : (isset($factura->prefijo) ? $factura->prefijo . $factura->nro : $factura->nro),
                        'invoiceLine' => 0,
                        'currencyCode' => 'COP',
                        'rateCode' => $rateCode,
                        'idImpDIAN_c' => $rateCode,
                        'docTaxableAmt' => $sobre_quien_retiene,
                        'taxAmt' => round($retencion->total,2),
                        'docTaxAmt' => round($retencion->total,2),
                        'percent' => $retencion->porcentaje,
                        'withholdingTax_c' => true,
                    ];
                }
            }
        }

        return $taxes;
    }

    public static function buildFullInvoice($data)
    {
        return [
            'head'     => $data['head']['head'] ?? [],
            'company'  => $data['company']['company'] ?? [],
            'customer' => $data['customer']['customer'] ?? [],
            'details'  => $data['details']['details'] ?? [],
            'taxes'    => $data['taxes'] ?? [],
            'additionalTags'   => $data['additionalTags'] ?? [],
            'mode'     => $data['mode'] ?? 'no',
            'btw_login'=> $data['btw_login'] ?? '',
            'software' => $data['software'] ?? '',
        ];
    }

    /**
     * Obtiene el nombre del vendedor asociado a la factura.
     * Si no existe un vendedor asociado, retorna el nombre del primer vendedor activo de la empresa.
     *
     * @param \App\Model\Ingresos\Factura $factura
     * @param \App\Empresa $empresa
     * @return string
     */
    private static function getVendedorNombre($factura, $empresa)
    {
        // Intentar obtener el vendedor asociado a la factura
        $vendedor = $factura->vendedorObj;

        // Si existe el vendedor asociado, retornar su nombre
        if ($vendedor && isset($vendedor->nombre)) {
            return $vendedor->nombre;
        }

        // Si no existe, obtener el primer vendedor activo de la empresa
        $primerVendedor = Vendedor::where('empresa', $empresa->id)
            ->where('estado', 1)
            ->orderBy('id', 'ASC')
            ->first();

        // Si existe un vendedor activo, retornar su nombre
        if ($primerVendedor && isset($primerVendedor->nombre)) {
            return $primerVendedor->nombre;
        }

        // Si no hay vendedores, retornar string vacío
        return '';
    }

}
