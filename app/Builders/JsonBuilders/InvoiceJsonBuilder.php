<?php

namespace App\Builders\JsonBuilders;

use App\Empresa;
use Carbon\Carbon;
use DB;

class InvoiceJsonBuilder
{

    public static function buildFromHeadInvoice($factura,$resolucion, $modoBTW){

        $empresa = Empresa::Find($factura->empresa);
        $totales = $factura->total();
        $forma_pago = $factura->forma_pago();
        $plazo = intval(Carbon::parse($factura->fecha)->diffinDays($factura->vencimiento));
        $resolucion_vigencia_meses = intval(Carbon::parse($resolucion->desde)->diffInMonths($resolucion->hasta));
        $resolucion_msj = 'Resolución DIAN No. ' . $resolucion->nroresolucion . ' de ' . $resolucion->desde . ' Prefijo ' . $resolucion->prefijo . ' - Numeración ' . $resolucion->inicioverdadero . ' a la ' . $resolucion->final . ', vigencia ' . $resolucion_vigencia_meses . ' meses.';
        $totalIva = 0;
        $totalInc = 0;

        foreach ($factura->total()->imp as $key => $imp) {
            if (isset($imp->total) && $imp->tipo == 1) {
                $totalIva = round($imp->total,2);
            } elseif (isset($imp->total) && $imp->tipo == 3 || isset($imp->total) && $imp->tipo == 4) {
                $totalInc = round($imp->total,2);
            }
        }

        if($modoBTW == 'test'){
            $empresa->nit = '901548158';
            $resolucion->nroresolucion = '18762008997356';
        }

        return [
            'head' => [
                'company' => $empresa->nit,
                'custNum' => $empresa->nit,
                'invoiceType' => 'InvoiceType',
                'invoiceNum' => $factura->codigo,
                'legalNumber' => $factura->codigo,
                'invoiceDate' => $factura->fecha,
                'dueDate' => $factura->vencimiento,
                'dspDocSubTotal' => round($totales->subtotal - $totales->descuento,2),
                'docTaxAmt' => round($totalIva,2),
                'dspDocInvoiceAmt' => round($totalIva,2) + round($totales->subtotal - $totales->descuento,2),
                'discount' => round($totales->descuento),
                'currencyCodeCurrencyID' => 'COP',
                'currencyCode' => 'COP',
                'salesRepCode1' => null,
                'salesRepName1' => $factura->vendedorObj->nombre,
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
                'contingencyInvoiceDian_c' => false,
                'contingencyInvoiceOF_c' => false,
                'issueDateContingency' => null,
                'invoiceRefDate' => null,
                'calculationRate_c' => null,
                'dateCalculationRate_c' => null
            ]
        ];
    }

    public static function buildFromHeadCreditNote($nota, $factura, $resolucion, $modoBTW){
        $empresa = Empresa::Find($factura->empresa);
        $totales = $nota->total();
        $forma_pago = $factura->forma_pago();
        $plazo = intval(Carbon::parse($factura->fecha)->diffinDays($factura->vencimiento));
        $resolucion_vigencia_meses = intval(Carbon::parse($resolucion->desde)->diffInMonths($resolucion->hasta));
        $resolucion_msj = 'Resolución DIAN No. ' . $resolucion->nroresolucion . ' de ' . $resolucion->desde . ' Prefijo ' . $resolucion->prefijo . ' - Numeración ' . $resolucion->inicioverdadero . ' a la ' . $resolucion->final . ', vigencia ' . $resolucion_vigencia_meses . ' meses.';
        $totalIva = 0;
        $totalInc = 0;

        foreach ($factura->total()->imp as $key => $imp) {
            if (isset($imp->total) && $imp->tipo == 1) {
                $totalIva = round($imp->total,2);
            } elseif (isset($imp->total) && $imp->tipo == 3 || isset($imp->total) && $imp->tipo == 4) {
                $totalInc = round($imp->total,2);
            }
        }

        if($modoBTW == 'test'){
            $empresa->nit = '901548158';
            $resolucion->nroresolucion = '18762008997356';
        }

        return [
            'head' => [
                'company' => $empresa->nit,
                'custNum' => $empresa->nit,
                'invoiceType' => 'CreditNoteType',
                'invoiceNum' => (string) $nota->nro,
                'legalNumber' => (string) $nota->nro,
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
                'salesRepName1' => $factura->vendedorObj->nombre,
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

        return [
            'details' => array_map(function ($item,$index) use ($nit,$factura){

                return [
                    'company' => $nit,
                    'invoiceNum' => $factura->codigo,
                    'invoiceLine' => $index + 1,
                    'partNum' => $item->ref,
                    'lineDesc' => $item->descripcion ?? "código del item: " . $item->ref,
                    'taxAmtLineIVA' => round(($item->impuesto / 100) * ($item->precio * $item->cant)),
                    'sellingShipQty' => round($item->cant),
                    'salesUM' => 'UND',
                    'unitPrice' => round($item->precio),
                    'docExtPrice' => round($item->precio * $item->cant),
                    'discountPercent' => $item->desc,
                    'discount' => round(($item->desc / 100) * ($item->precio * $item->cant)),
                    'currencyCode' => 'COP',
                    'idSupplier' => null,
                    'codInvima' => null,
                    'lineDesc3' => null,
                    'lineDesc2' => null,
                    'standardItemID' => null,
                    'brandName' => null
                ];
            },$items->all(), array_keys($items->all()))
        ];
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
                'operationType_c' => '20', //anulacion de factura electronica
                'companyType_c' => $empresa->tipo_persona == 'j' ? 1 : 2,
                'state' => $departamento->nombre,
                'stateNum' => $departamento->codigo,
                'city' => $municipio->nombre,
                'cityNum' => $municipio->codigo_completo,
                'industryClassificationCode_c'=> "",
                'identificationType'=> $empresa->tipoIdentificacion->codigo_dian,
                'address1'=> $empresa->direccion,
                'country'=> $empresa->fk_idpais,
                'postalZone_c'=> $municipio->codigo_completo,
                'phoneNum'=> $empresa->telefono,
                'email' => $empresa->email,
                'attrOperationType_c' => "",
                'faxNum' =>  '',
                'webPage' => '',
                'companyOrigin' => $empresa->fk_idpais,
                'shareholder'=> '',
                'participationPercent' => ''
            ]
        ];

    }

    public static function buildFromCustomer($cliente,$empresa, $modoBTW){

        $municipio = $cliente->municipio();
        $departamento = $cliente->departamento();

        if($modoBTW == 'test'){
            $empresa->nit = '901548158';
        }

        return [
            'customer' => [
                'company' => $empresa->nit,
                'custID' => $cliente->nit,
                'resaleID' => $cliente->nit,
                'custNum' => $cliente->nit,
                'name' => $cliente->nombre,
                'identificationType' => $cliente->identificacion->codigo_dian,
                'address1' => $cliente->direccion,
                'email' => $cliente->email,
                'phoneNum' => $cliente->telefono1,
                'country' => $cliente->fk_idpais,
                'state' => $departamento->nombre,
                'stateNum' => $departamento->codigo,
                'city' => $municipio->nombre,
                'cityNum' => $municipio->codigo_completo,
                'codPostal' => $municipio->codigo_completo,
                'currencyCode' => 'COP',
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

    public static function buildFullInvoice($data)
    {
        return [
            'head'     => $data['head']['head'] ?? [],
            'company'  => $data['company']['company'] ?? [],
            'customer' => $data['customer']['customer'] ?? [],
            'details'  => $data['details']['details'] ?? [],
            'mode'     => $data['mode'] ?? 'no',
        ];
    }

}