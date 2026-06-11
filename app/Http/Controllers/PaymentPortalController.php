<?php

namespace App\Http\Controllers;

use App\Contacto;
use App\Empresa;
use App\Model\Ingresos\Factura;
use App\Integracion;
use Illuminate\Http\Request;

class PaymentPortalController extends Controller
{
    public function index()
    {
        $empresa = Empresa::first();
        if (!$empresa) {
            abort(404, 'Empresa no configurada');
        }

        $nom_empresa = $empresa->codigo ?? strtoupper(substr($empresa->nombre, 0, 3));
        $whatsapp = str_replace(['+', ' '], '', $empresa->whatsapp ?? '');

        // Obtener logo y favicon del sistema
        if (function_exists('contabo_url')) {
            $logoUrl = contabo_url(env('LOGOS_FOLDER', 'logos'), 'logo.png');
            $faviconUrl = contabo_url(env('LOGOS_FOLDER', 'logos'), 'favicon.png');
        } else {
            $logoUrl = $empresa->logo ? asset('images/Empresas/Empresa1/'.$empresa->logo) : asset('images/logo.png');
            $faviconUrl = asset('images/favicon2.png');
        }

        $datosEmpresa = [
            'nombre'    => $empresa->nombre,
            'direccion' => $empresa->direccion,
            'telefono'  => $empresa->telefono,
            'email'     => $empresa->email,
            'whatsapp'  => $whatsapp,
            'logo'      => $logoUrl,
            'favicon'   => $faviconUrl,
            'prefix'    => $nom_empresa,
            'moneda'    => $empresa->moneda ?? '$',
        ];

        return view('pagos.portal', compact('datosEmpresa'));
    }

    public function consultarFacturas(Request $request)
    {
        $request->validate(['identificacion' => 'required|string']);

        $empresa = Empresa::first();
        $empresaId = $empresa->id ?? 1;

        $contacto = Contacto::where('nit', $request->identificacion)
            ->where('empresa', $empresaId)
            ->first();

        if (!$contacto) {
            return response()->json(['contrato' => []]);
        }

        $facturas = Factura::where('cliente', $contacto->id)
            ->where('empresa', $empresaId)
            ->where('estatus', 1) // Abiertas/Pendientes
            ->orderBy('vencimiento', 'asc')
            ->get();

        if ($facturas->isEmpty()) {
            return response()->json(['contrato' => []]);
        }

        $contratos = [];
        foreach ($facturas as $factura) {
            $porPagar = $factura->porpagar();

            if ($porPagar > 0) {
                $contratos[] = [
                    'facturaId'   => (string) $factura->id,
                    'factura'     => $factura->codigo,
                    'nit'         => $contacto->nit,
                    'nombre'      => $contacto->nombre,
                    'apellido1'   => $contacto->apellido1 ?? '',
                    'apellido2'   => $contacto->apellido2 ?? '',
                    'email'       => $contacto->email ?? '',
                    'celular'     => $contacto->celular ?? '',
                    'direccion'   => $contacto->direccion ?? '',
                    'emision'     => $factura->fecha,
                    'vencimiento' => $factura->vencimiento,
                    'price'       => (float) $porPagar,
                    'tip_iden'    => $contacto->tip_iden,
                ];
            }
        }

        if (empty($contratos)) {
            return response()->json(['contrato' => []]);
        }

        $pasarelas = Integracion::where('empresa', $empresaId)
            ->where('tipo', 'PASARELA')
            ->where('status', 1)
            ->where('web', 1)
            ->get()
            ->map(function ($p) {
                $data = ['nombre' => $p->nombre];

                switch ($p->nombre) {
                    case 'WOMPI':
                        $data['api_key']   = $p->api_key;
                        $data['integrity'] = $p->integrity ?? $p->api_event ?? '';
                        break;

                    case 'PayU':
                        $data['api_key']    = $p->api_key;
                        $data['merchantId'] = $p->merchantId;
                        $data['accountId']  = $p->accountId;
                        break;

                    case 'ePayco':
                        $data['api_key']            = $p->api_key;
                        $data['p_cust_id_cliente']  = $p->p_cust_id_cliente ?? '';
                        $data['p_key']              = $p->p_key ?? '';
                        break;

                    case 'ComboPay':
                        $data['client_id']     = $p->accountId;
                        $data['client_secret'] = $p->merchantId;
                        $data['user']          = $p->user ?? '';
                        $data['pass']          = $p->pass ?? '';
                        break;

                    default:
                        $data['api_key'] = $p->api_key;
                        break;
                }

                $data['cobro_extra'] = (float) ($p->cobro_extra ?? 0);

                return $data;
            })
            ->values();

        return response()->json([
            'contrato'  => $contratos,
            'pasarelas' => $pasarelas,
        ]);
    }

    public function hashPayu(Request $request)
    {
        $request->validate([
            'api_key'       => 'required',
            'merchantId'    => 'required',
            'referenceCode' => 'required',
            'amount'        => 'required',
            'currency'      => 'required',
        ]);

        $string = $request->api_key . '~' . $request->merchantId . '~'
                . $request->referenceCode . '~' . $request->amount . '~'
                . $request->currency;

        return response()->json(['hash' => md5($string)]);
    }
}
