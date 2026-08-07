<?php

namespace App\Http\Controllers;

use App\PortalComprobante;
use App\PortalNotificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * "Mi suscripción Integra" (réplica del módulo de integra2.0 en Laravel 7):
 * el admin adjunta el comprobante del pago mensual del software; viaja al
 * Integra Portal y el veredicto vuelve por master-api/comprobantes/estado.
 */
class MiSuscripcionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $comprobantes = Schema::hasTable('portal_comprobantes')
            ? PortalComprobante::orderByDesc('id')->limit(24)->get()
            : collect();

        $configurado = (string) config('services.portal.token') !== '';

        return view('mi-suscripcion.index', compact('comprobantes', 'configurado'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'archivo'       => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'periodo'       => ['required', 'date_format:Y-m'],
            'valor'         => ['nullable', 'numeric', 'min:0'],
            'referencia'    => ['nullable', 'string', 'max:100'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ]);

        $token = (string) config('services.portal.token');
        if ($token === '') {
            return back()->with('error', 'El envío al portal no está configurado. Contacta a soporte de Integra.');
        }

        $this->asegurarTabla();

        $portalEmpresaId = Schema::hasTable('portal_notificaciones')
            ? PortalNotificacion::whereNotNull('portal_empresa_id')->orderByDesc('id')->value('portal_empresa_id')
            : null;
        $nit = DB::table('empresas')->where('id', Auth::user()->empresa)->value('nit')
            ?? DB::table('empresas')->value('nit');

        $archivo = $request->file('archivo');

        try {
            $respuesta = Http::withToken($token)
                ->timeout(20)
                ->attach('archivo', file_get_contents($archivo->getRealPath()), $archivo->getClientOriginalName())
                ->post(rtrim((string) config('services.portal.url'), '/').'/api/master/comprobantes', array_filter([
                    'periodo'           => $data['periodo'],
                    'portal_empresa_id' => $portalEmpresaId,
                    'nit'               => $nit,
                    'valor'             => isset($data['valor']) ? $data['valor'] : null,
                    'referencia'        => isset($data['referencia']) ? $data['referencia'] : null,
                    'observaciones'     => isset($data['observaciones']) ? $data['observaciones'] : null,
                ], function ($v) { return $v !== null; }));
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo enviar el comprobante (portal no disponible). Inténtalo en unos minutos.');
        }

        if (! $respuesta->successful()) {
            return back()->with('error', 'El portal rechazó el envío: '.($respuesta->json('message') ?: 'HTTP '.$respuesta->status()));
        }

        $ruta = $archivo->store('portal-comprobantes');
        PortalComprobante::create([
            'portal_comprobante_id' => $respuesta->json('comprobante_id'),
            'periodo'               => $data['periodo'].'-01',
            'archivo'               => $ruta,
            'valor'                 => isset($data['valor']) ? $data['valor'] : null,
            'referencia'            => isset($data['referencia']) ? $data['referencia'] : null,
            'observaciones'         => isset($data['observaciones']) ? $data['observaciones'] : null,
            'estado'                => 'enviado',
            'created_by'            => Auth::id() ?: 0,
        ]);

        return back()->with('success', 'Comprobante enviado. El equipo de Integra lo revisará y aquí verás el resultado.');
    }

    private function asegurarTabla()
    {
        if (Schema::hasTable('portal_comprobantes')) {
            return;
        }

        Schema::create('portal_comprobantes', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('portal_comprobante_id')->nullable()->index();
            $table->date('periodo');
            $table->string('archivo');
            $table->decimal('valor', 12, 2)->nullable();
            $table->string('referencia', 100)->nullable();
            $table->text('observaciones')->nullable();
            $table->string('estado', 20)->default('enviado');
            $table->text('motivo_rechazo')->nullable();
            $table->unsignedBigInteger('created_by')->default(0);
            $table->timestamps();
        });
    }
}
