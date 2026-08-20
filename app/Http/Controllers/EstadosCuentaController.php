<?php

namespace App\Http\Controllers;

use App\Services\EstadoCuentaClienteService;
use Barryvdh\DomPDF\Facade as PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Estados de cuenta: la relación económica completa de UN cliente con la empresa.
 *
 * Gemelo del módulo de integra2.0 y sobre la MISMA base de datos, así que las
 * cifras tienen que coincidir peso por peso entre los dos sistemas. Por eso
 * ambos consumen EstadoCuentaClienteService con las mismas fórmulas, en vez de
 * escribir consultas nuevas aquí.
 *
 * No confundir con el reporte de contactos (Reportes → Contactos), que responde
 * la pregunta contraria: cómo está la cartera de TODOS.
 */
class EstadosCuentaController extends Controller
{
    /** Permiso del módulo. Es el mismo id que en integra2.0: la BD es una sola. */
    const PERMISO = 905;

    public function __construct()
    {
        $this->middleware('auth');

        // layouts.app lee $title, $icon y $seccion SIN isset(): si no se comparten,
        // la vista revienta con "Undefined variable: title" antes de pintar nada.
        // $subseccion es la que deja marcado el ítem del submenú (id estados-cuenta).
        // El PDF no se ve afectado: pasa su propio 'title' en los datos de la vista,
        // y los datos de la vista le ganan a lo compartido con share().
        view()->share([
            'seccion'    => 'contactos',
            'subseccion' => 'estados-cuenta',
            'title'      => 'Estados de Cuenta',
            'icon'       => 'fas fa-file-invoice-dollar',
        ]);
    }

    private function autorizado()
    {
        if (Auth::user()->rol < 2) {
            return true;
        }

        return DB::table('permisos_usuarios')
            ->where('id_usuario', Auth::id())
            ->where('id_permiso', self::PERMISO)
            ->exists();
    }

    public function index(Request $request)
    {
        $this->getAllPermissions(Auth::user()->id);
        abort_unless($this->autorizado(), 403, 'No tiene permiso para ver estados de cuenta.');

        $empresa = (int) Auth::user()->empresa;
        $clienteId = (int) $request->input('cliente', 0);

        // El período acota SOLO el detalle. Las cifras de resumen son históricas:
        // "cuánto le ha pagado a la empresa" no es de un período.
        $desde = $request->input('desde') ?: date('Y-m-01', strtotime('-11 months'));
        $hasta = $request->input('hasta') ?: date('Y-m-d');

        $datos = null;
        if ($clienteId > 0) {
            $datos = app(EstadoCuentaClienteService::class)
                ->detalleCliente($clienteId, $empresa, $desde, $hasta);
            if (empty($datos)) {
                $datos = null;
            }
        }

        $empresaRow = DB::table('empresas')->where('id', $empresa)->first();
        $moneda = $empresaRow && ! empty($empresaRow->moneda) ? $empresaRow->moneda : '$';

        return view('estados-cuenta.index')
            ->with(compact('datos', 'desde', 'hasta', 'clienteId', 'moneda'));
    }

    /**
     * Buscador de clientes contra el servidor.
     *
     * A propósito NO se manda la lista completa a la vista: hay empresas con más
     * de 3.000 clientes. Se busca por nombre, identificación, celular o número de
     * contrato, que es como la gente busca cuando el cliente llama.
     */
    public function buscar(Request $request)
    {
        abort_unless($this->autorizado(), 403);

        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $empresa = (int) Auth::user()->empresa;
        $like = '%'.$q.'%';

        $clientes = DB::table('contactos as c')
            ->where('c.empresa', $empresa)
            ->where(function ($w) use ($like, $q) {
                $w->whereRaw("TRIM(CONCAT(COALESCE(c.nombre,''),' ',COALESCE(c.apellido1,''),' ',COALESCE(c.apellido2,''))) LIKE ?", [$like])
                    ->orWhere('c.nit', 'like', $like)
                    ->orWhere('c.celular', 'like', $like)
                    ->orWhereExists(function ($sub) use ($q) {
                        $sub->select(DB::raw(1))->from('contracts as ct')
                            ->whereColumn('ct.client_id', 'c.id')
                            ->where('ct.nro', $q);
                    });
            })
            ->orderBy('c.nombre')
            ->limit(25)
            ->get([
                'c.id',
                DB::raw("TRIM(CONCAT(COALESCE(c.nombre,''),' ',COALESCE(c.apellido1,''),' ',COALESCE(c.apellido2,''))) AS nombre"),
                DB::raw("COALESCE(c.nit,'') AS identificacion"),
                DB::raw("COALESCE(c.celular,'') AS celular"),
            ]);

        return response()->json($clientes);
    }

    // ─── Salidas ────────────────────────────────────────────────────────────

    /** Datos + membrete para la plantilla del PDF, compartidos por descarga y envío. */
    private function datosPdf($clienteId, $empresa, $desde, $hasta)
    {
        $datos = app(EstadoCuentaClienteService::class)->detalleCliente($clienteId, $empresa, $desde, $hasta);
        if (empty($datos)) {
            return null;
        }

        $empresaRow = DB::table('empresas')->where('id', $empresa)->first();

        // dompdf no trae imágenes remotas: el logo va incrustado en base64.
        $logo = null;
        if ($empresaRow && ! empty($empresaRow->logo)) {
            $ruta = public_path('images/Empresas/Empresa'.$empresaRow->id.'/'.$empresaRow->logo);
            if (is_file($ruta)) {
                $logo = 'data:image/'.pathinfo($ruta, PATHINFO_EXTENSION).';base64,'.base64_encode(file_get_contents($ruta));
            }
        }

        return array_merge($datos, [
            'empresa' => $empresaRow,
            'logo' => $logo,
            'color' => $empresaRow && ! empty($empresaRow->color) ? $empresaRow->color : '#334155',
            'moneda' => $empresaRow && ! empty($empresaRow->moneda) ? $empresaRow->moneda : '$',
            'desde' => $desde,
            'hasta' => $hasta,
            'title' => 'Estado de cuenta',
        ]);
    }

    private function nombreArchivo($datos, $ext)
    {
        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', $datos['cliente']['nombre']);

        return 'Estado-de-cuenta-'.trim($slug, '-').'-'.date('Ymd').'.'.$ext;
    }

    public function pdf(Request $request, $cliente)
    {
        abort_unless($this->autorizado(), 403);

        $empresa = (int) Auth::user()->empresa;
        $desde = $request->input('desde') ?: date('Y-m-01', strtotime('-11 months'));
        $hasta = $request->input('hasta') ?: date('Y-m-d');

        $datos = $this->datosPdf((int) $cliente, $empresa, $desde, $hasta);
        abort_if($datos === null, 404, 'Cliente no encontrado.');

        return PDF::loadView('pdf.estado-cuenta', $datos)
            ->setPaper('letter', 'portrait')
            ->download($this->nombreArchivo($datos, 'pdf'));
    }

    /**
     * Excel del estado de cuenta.
     *
     * Se genera como CSV con BOM y no con PHPExcel: la librería de este proyecto
     * es la vieja (PHPExcel, sin mantenimiento) y para tres bloques de datos el
     * CSV abre igual en Excel sin arrastrar esa dependencia. El separador es el
     * punto y coma, que es lo que espera el Excel en español.
     */
    public function excel(Request $request, $cliente)
    {
        abort_unless($this->autorizado(), 403);

        $empresa = (int) Auth::user()->empresa;
        $desde = $request->input('desde') ?: date('Y-m-01', strtotime('-11 months'));
        $hasta = $request->input('hasta') ?: date('Y-m-d');

        $datos = app(EstadoCuentaClienteService::class)->detalleCliente((int) $cliente, $empresa, $desde, $hasta);
        abort_if(empty($datos), 404, 'Cliente no encontrado.');

        $nombre = $this->nombreArchivo($datos, 'csv');
        $fecha = function ($v) {
            return $v ? date('d/m/Y', strtotime($v)) : '';
        };

        return response()->streamDownload(function () use ($datos, $desde, $hasta, $fecha) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM: sin él Excel rompe las tildes

            $c = $datos['cliente'];
            $r = $datos['resumen'];

            fputcsv($out, ['ESTADO DE CUENTA'], ';');
            fputcsv($out, ['Generado', date('d/m/Y H:i')], ';');
            fputcsv($out, ['Detalle del período', $fecha($desde).' al '.$fecha($hasta)], ';');
            fputcsv($out, [], ';');
            fputcsv($out, ['CLIENTE'], ';');
            fputcsv($out, ['Nombre', $c['nombre']], ';');
            fputcsv($out, ['Identificación', $c['identificacion']], ';');
            fputcsv($out, ['Dirección', $c['direccion']], ';');
            fputcsv($out, ['Celular', $c['celular']], ';');
            fputcsv($out, [], ';');
            fputcsv($out, ['CUENTA'], ';');
            fputcsv($out, ['Saldo pendiente', round($r['saldo'])], ';');
            fputcsv($out, ['Saldo vencido', round($r['saldo_vencido'])], ';');
            fputcsv($out, ['Días de mora', $r['dias_mora']], ';');
            fputcsv($out, ['Total pagado (histórico)', round($r['pagado_historico'])], ';');
            fputcsv($out, ['Saldo a favor', round($r['saldo_favor'])], ';');
            fputcsv($out, ['Total facturado', round($r['facturado'])], ';');
            fputcsv($out, ['Aplicado en notas crédito', round($r['notas_credito_aplicadas'])], ';');
            fputcsv($out, [], ';');

            fputcsv($out, ['FACTURAS'], ';');
            fputcsv($out, ['Factura', 'Fecha', 'Vencimiento', 'Estado', 'Pagada el', 'Total', 'Pagado', 'Notas crédito', 'Saldo'], ';');
            foreach ($datos['facturas'] as $f) {
                fputcsv($out, [
                    $f['codigo'], $fecha($f['fecha']), $fecha($f['vencimiento']), $f['estado'],
                    $fecha($f['fecha_pago']), round($f['total']), round($f['pagado']),
                    round($f['notas']), round($f['saldo']),
                ], ';');
            }
            fputcsv($out, [], ';');

            fputcsv($out, ['PAGOS'], ';');
            fputcsv($out, ['Recibo', 'Fecha', 'Medio de pago', 'Aplicado a', 'Monto'], ';');
            foreach ($datos['pagos'] as $p) {
                fputcsv($out, [$p['nro'], $fecha($p['fecha']), $p['caja'], $p['facturas'], round($p['monto'])], ';');
            }

            fclose($out);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Envía el estado de cuenta al celular del cliente como documento PDF.
     *
     * Mismo camino que el resto de envíos del sistema: se genera el PDF, se sube
     * como media a Meta y se manda como documento con sendDocumentById.
     */
    public function whatsapp(Request $request, $cliente)
    {
        abort_unless($this->autorizado(), 403);

        $empresa = (int) Auth::user()->empresa;
        $desde = $request->input('desde') ?: date('Y-m-01', strtotime('-11 months'));
        $hasta = $request->input('hasta') ?: date('Y-m-d');

        $datos = $this->datosPdf((int) $cliente, $empresa, $desde, $hasta);
        if ($datos === null) {
            return back()->with('error', 'Cliente no encontrado.');
        }

        $celular = preg_replace('/\D+/', '', $datos['cliente']['celular']);
        if (strlen($celular) < 10) {
            return back()->with('error', 'El cliente no tiene un número de celular válido registrado.');
        }

        // Mismo criterio que el resto de envíos: instancia Meta Direct activa.
        $instancia = \App\Instance::where('company_id', $empresa)
            ->where('activo', 1)->where('type', 1)->where('meta', 0)
            ->whereNotNull('phone_number_id')->first();
        if (! $instancia) {
            return back()->with('error', 'Esta empresa no tiene configurada la integración de WhatsApp.');
        }

        $ruta = storage_path('app/'.uniqid('estado-cuenta-').'.pdf');

        try {
            PDF::loadView('pdf.estado-cuenta', $datos)->setPaper('letter', 'portrait')->save($ruta);

            $meta = app(\App\Services\MetaWhatsAppService::class);
            $mediaId = $meta->uploadMedia($instancia->phone_number_id, $ruta, 'application/pdf');
            if (! $mediaId) {
                return back()->with('error', 'No se pudo preparar el archivo para WhatsApp.');
            }

            $resp = $meta->sendDocumentById(
                $instancia->phone_number_id,
                $celular,
                $mediaId,
                $this->nombreArchivo($datos, 'pdf'),
                'Hola, te compartimos tu estado de cuenta.'
            );

            if (! (is_array($resp) && ! empty($resp['success']))) {
                return back()->with('error', 'WhatsApp no aceptó el envío. Revisa el número del cliente y la integración.');
            }

            return back()->with('success', 'Estado de cuenta enviado al '.$celular.'.');
        } catch (\Throwable $e) {
            Log::error('[EstadosCuenta] fallo enviando por WhatsApp: '.$e->getMessage());

            return back()->with('error', 'No se pudo enviar el estado de cuenta: '.$e->getMessage());
        } finally {
            if (is_file($ruta)) {
                @unlink($ruta);
            }
        }
    }
}
