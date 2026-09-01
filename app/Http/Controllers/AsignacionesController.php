<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Radicado;
use App\Servicio;
use App\User;
use App\Contacto;
use App\TipoIdentificacion;
use App\Vendedor;
use App\Model\Inventario\ListaPrecios;
use App\TipoEmpresa;
use App\Contrato;
use App\ContratoDigital;
use App\Funcion;
use App\PlanesVelocidad;
use App\Mikrotik;
use Validator;
use Auth;
use DB;
use Carbon\Carbon;
use Session;
use Barryvdh\DomPDF\Facade as PDF;
use Illuminate\Support\Facades\Storage;
use App\Empresa;
use App\ServidorCorreo;
use App\Services\ContaboS3Service;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth as FacadesAuth;
use Mail;

class AsignacionesController extends Controller
{
    public function __construct()
    {
      //  $this->middleware('auth');
        set_time_limit(300);
        view()->share(['seccion' => 'contratos', 'subseccion' => 'asignaciones', 'title' => 'Asignaciones', 'icon' =>'fas fa-file-contract']);
    }

    public function index()
    {
        $this->getAllPermissions(Auth::user()->id);
        view()->share(['invert' => true]);
        $contratos = ContratoDigital::orderBy('id', 'DESC')->get();
        return view('asignaciones.index')->with(compact('contratos'));
    }

    public function create()
    {
        $this->getAllPermissions(Auth::user()->id);
        // $planes = PlanesVelocidad::where('status', 1)->where('empresa', Auth::user()->empresa)->get();
        // $servidores = Mikrotik::where('status', 1)->where('empresa', Auth::user()->empresa)->get();
        $clientes = Contacto::where('fecha_isp', null)->where('empresa', Auth::user()->empresa)->OrderBy('nombre')->get();
        $clientes = (Auth::user()->empresa()->oficina) ? Contacto::whereIn('tipo_contacto', [0,2])->where('status', 1)->where('empresa', Auth::user()->empresa)->where('oficina', Auth::user()->oficina)->orderBy('nombre', 'ASC')->get() : Contacto::whereIn('tipo_contacto', [0,2])->where('status', 1)->where('empresa', Auth::user()->empresa)->orderBy('nombre', 'ASC')->get();
        $empresa = Empresa::find(Auth::user()->empresa);
        $contrato = Contrato::where('id', request()->contrato)->where('empresa', Auth::user()->empresa)->first();
        if($contrato){
            $idCliente = $contrato->client_id;
       }else if(request('id')){
            $idCliente = request('id');
       }else{
            $idCliente = '';
       }
        view()->share(['title' => 'Asignación de Contrato de Internet']);
        return view('asignaciones.create')->with(compact('clientes', 'empresa', 'contrato', 'idCliente'));
    }

    public function store(Request $request)
    {

        //validaciones
        if (!$request->id) {
            $mensaje='Debe seleccionar un cliente para la asignación del contrato digital';
            return back()->with('danger', $mensaje);
        }

        if (!$request->id || !$request->file('documento')) {
            $mensaje='Debe adjuntar la documentación para la asignación del contrato digital';
            return back()->with('danger', $mensaje);
        }

        /*if(!$request->contrato){
        $mensaje='Debe seleccionar un contrato para la asignación del contrato digital';
        return back()->with('danger', $mensaje);
    }*/

        if($request->contrato){
            if(ContratoDigital::where('contrato_id', $request->contrato)->where('cliente_id', $request->id)->first()) {
                $mensaje='El contrato digital ya se encuentra asignado a este cliente.';
                return back()->with('danger', $mensaje);
            }
        }

        $digital = new ContratoDigital;

        if($request->firma_isp) {
            $digital->firma = $request->firma_isp;
        }

        if($request->contrato){
            $contrato = Contrato::Find($request->contrato);
            if($contrato){
                $idContrato = $contrato->id;
                $digital->contrato_id = $contrato->id;
            }else{
                $idContrato = $request->id;
                $digital->contrato_id = null;
            }
        }else{
            $idContrato = $request->id;
            $digital->contrato_id = null;
        }
        
        $digital->cliente_id = $request->id;
        $digital->nro = ContratoDigital::count() + 1;
        $cliente = Contacto::find($request->id);

        $digital->fecha_firma = date('Y-m-d');

        // Documento principal: campo requerido, ya validado arriba.
        $nombreDoc = $this->subirAdjunto($request->file('documento'), $idContrato, $cliente, 'doc_');
        if ($nombreDoc !== null) {
            $digital->documento = $nombreDoc;
        }

        // Imágenes opcionales imgA..imgH siguen el mismo patrón.
        $imagenesOpcionales = [
            'imgA' => 'imgA_',
            'imgB' => 'imgB_',
            'imgC' => 'imgC_',
            'imgD' => 'imgD_',
            'imgE' => 'imgE_',
            'imgF' => 'imgF_',
            'imgG' => 'imgG_',
            'imgH' => 'imgH_',
        ];
        foreach ($imagenesOpcionales as $campo => $prefix) {
            if ($request->file($campo)) {
                $nombre = $this->subirAdjunto($request->file($campo), $idContrato, $cliente, $prefix);
                if ($nombre !== null) {
                    $digital->$campo = $nombre;
                }
            }
        }

        // Audio: sin resize, pero también va a Contabo.
        if ($request->file('adjunto_audio')) {
            $nombreAudio = $this->subirAdjunto($request->file('adjunto_audio'), $idContrato, $cliente, 'adjunto_audio');
            if ($nombreAudio !== null) {
                $digital->adjunto_audio = $nombreAudio;
            }
        }

        $digital->save();
        return redirect('empresa/asignaciones')->with('success', 'SE HA REGISTRADO SATISFACTORIAMENTE LA ASIGNACIÓN DEL CONTRATO DIGITAL.');
    }

    public function edit($id)
    {
        $this->getAllPermissions(Auth::user()->id);
        $texto = '';
        $asignacion = ContratoDigital::find($id);

        if ($asignacion) {
            $contacto = Contacto::find($asignacion->cliente_id);
            if(request('contrato')){
                $contrato = Contrato::find(request('contrato'));
            }else{
                $contrato = Contrato::find($asignacion->contrato_id);
            }
            // Fetch all contracts for this client to populate the selector
            $contratos = Contrato::where('client_id', $contacto->id)->get();
        } else {
             return redirect('empresa/asignaciones')->with('danger', 'LA ASIGNACION NO EXISTE');
        }
        $empresa = Empresa::find(Auth::user()->empresa);
        view()->share(['title' => 'Editar Asignación de Contrato de Internet']);
        return view('asignaciones.edit')->with(compact('contacto', 'empresa', 'contrato', 'asignacion', 'contratos'));
    }

    public function update(Request $request, $id)
    {
        $digital = ContratoDigital::find($id);

        if ($digital) {
            if($request->contrato){
                $digital->contrato_id = $request->contrato;
            }

            if($request->firma_isp) {
                $digital->firma = $request->firma_isp;
            }
            
            if(!$digital->fecha_firma){
                $digital->fecha_firma = date('Y-m-d');
            }

            $campos_imagen = [
                'documento' => 'doc_',
                'imgA' => 'imgA_',
                'imgB' => 'imgB_',
                'imgC' => 'imgC_',
                'imgD' => 'imgD_',
                'imgE' => 'imgE_',
                'imgF' => 'imgF_',
                'imgG' => 'imgG_',
                'imgH' => 'imgH_',
            ];

            foreach ($campos_imagen as $campo => $prefix) {
                if ($request->file($campo)) {
                    $nombre = $this->subirAdjunto($request->file($campo), $digital->contrato_id, $digital->cliente, $prefix);
                    if ($nombre !== null) {
                        $digital->$campo = $nombre;
                    }
                }
            }

            if ($request->file('adjunto_audio')) {
                $nombreAudio = $this->subirAdjunto($request->file('adjunto_audio'), $digital->contrato_id, $digital->cliente, 'adjunto_audio');
                if ($nombreAudio !== null) {
                    $digital->adjunto_audio = $nombreAudio;
                }
            }

            $digital->save();
            return redirect('empresa/asignaciones')->with('success', 'SE HA ACTUALIZADO SATISFACTORIAMENTE LA ASIGNACIÓN DEL CONTRATO DIGITAL.');
        } 
        return redirect('empresa/asignaciones')->with('danger', 'LA ASIGNACION NO EXISTE');
    }


    public function destroy($id)
    {
        $contrato = ContratoDigital::find($id);
        if($contrato) {
            $contrato->delete();
            return redirect('empresa/asignaciones')->with('success', 'SE HA ELIMINADO SATISFACTORIAMENTE LA ASIGNACIÓN DEL CONTRATO DIGITAL.');
        }

        return redirect('empresa/asignaciones')->with('success', 'No existe un registro con ese id');
    }

    public function imprimir($id)
    {
        if (request('type') == 'contract') {
            $digital = ContratoDigital::where('contrato_id', $id)->orderBy('id', 'DESC')->first();

            if (!$digital) {
                $contrato = Contrato::find($id);
                if ($contrato && $contrato->cliente() && $contrato->cliente()->firma_isp) {
                    $digital = new ContratoDigital();
                    $digital->cliente_id = $contrato->client_id;
                    $digital->contrato_id = $contrato->id;
                    $digital->firma = $contrato->cliente()->firma_isp;
                    $digital->estado_firma = 1;
                    $digital->fecha_firma = date('Y-m-d H:i:s');
                    $digital->save();
                }
            }
        } else {
            $digital = ContratoDigital::find($id);
        }

        if (!$digital) {
            $digital = ContratoDigital::where('cliente_id', $id)->orderBy('id', 'DESC')->first();
        }

        if (!$digital) {
            $digital = ContratoDigital::where('contrato_id', $id)->orderBy('id', 'DESC')->first();
        }

        if (!$digital) {
            abort(404, 'No se encontró el contrato digital.');
        }

        // El guard va ANTES de tocar $contact: con un cliente borrado, leer
        // firma_isp reventaba con un error de propiedad sobre null en vez de
        // dar el 404 previsto.
        if (! $digital->cliente) {
            abort(404, 'Cliente no encontrado.');
        }

        $contact = $digital->cliente;

        if ($contact->firma_isp != null && $digital->firma == null) {
            $digital->firma = $contact->firma_isp;
            $digital->estado_firma = 1;
            $digital->save();
        }

        view()->share(['title' => 'Contrato de Internet']);
        return response($this->construirPdfContrato($digital), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="contrato_digital_'.$digital->id.'.pdf"',
        ]);
    }

    /**
     * Arma el PDF del Contrato Único Convergente, con el pie «Página N de M»
     * numerado de verdad.
     *
     * El número NO puede ir escrito en la plantilla: la sección «Otras
     * condiciones» es texto libre del operador y ocupa las páginas que haga
     * falta, así que el total varía por empresa. Se estampa con page_text(),
     * que dompdf resuelve una vez conocido el total real.
     *
     * El papel se fija a CARTA a propósito: el default del paquete es A4
     * (595x842pt) y la plantilla del modelo CRC dibuja cada hoja a 612x792pt
     * con las columnas posicionadas en absoluto. En A4 el contenido se
     * descuadra sin avisar.
     */
    private function construirPdfContrato($digital): string
    {
        $pdf = Pdf::loadView('pdf.contrato', $this->datosVistaContrato($digital));

        $dom = $pdf->getDomPDF();

        // Las opciones se tocan sobre el objeto que ya trae la configuración de
        // Laravel; NO se usa $pdf->setOptions([...]), que lo reemplaza por uno
        // nuevo. Y ojo con el orden: dompdf solo recrea el canvas si el papel
        // pedido difiere del default de las OPCIONES (a4, config/dompdf.php).
        // Reemplazando las opciones, ese default pasa a ser 'letter', queda
        // igual al papel pedido, el canvas no se recrea y el contrato salía en
        // A4 con la maquetación descuadrada.
        $dom->getOptions()->setIsRemoteEnabled(true);   // el logo se descarga por HTTP
        $dom->getOptions()->setIsHtml5ParserEnabled(true);
        $dom->setPaper('letter');

        // Se renderiza sobre la instancia cruda de dompdf y se devuelven sus
        // bytes, en vez de usar el output() del wrapper: ese vuelve a llamar a
        // render() porque no se enteró de este, y el documento saldría
        // renderizado dos veces.
        $dom->render();

        try {
            // page_text() aplica el texto a TODAS las páginas al hacer output(),
            // que es cuando ya se conoce {PAGE_COUNT}. La fuente hay que
            // resolverla: con null, dompdf 0.8 arma el nombre de archivo
            // ".afm" y no encuentra nada que dibujar.
            $fuente = $dom->getFontMetrics()->getFont('DejaVu Sans', 'normal');
            // Centrado sobre el ancho de Letter (612pt) y a 22pt del borde.
            $dom->getCanvas()->page_text(
                266, 770, 'Página {PAGE_NUM} de {PAGE_COUNT}',
                $fuente, 8, [0.5, 0.5, 0.5]
            );
        } catch (\Throwable $e) {
            // Si no se puede numerar, el contrato sale igual: sin pie.
            \Log::warning('[Asignaciones] no se pudo numerar el contrato: '.$e->getMessage());
        }

        return $dom->output();
    }

    /**
     * Todas las variables que necesita la vista `pdf.contrato`.
     *
     * Vive en un solo sitio a propósito: antes imprimir() y enviar() pasaban a
     * la MISMA vista las mismas cinco variables copiadas, y la plantilla del
     * modelo CRC necesita treinta. Con el armado repetido, cualquier dato nuevo
     * habría que acordarse de agregarlo en los dos sitios.
     */
    private function datosVistaContrato($digital): array
    {
        $contact  = $digital->cliente;
        $contract = $digital->contrato;
        $company  = Empresa::first();

        // Alias histórico: la plantilla anterior usaba los dos nombres.
        $contractDetails = $contract;

        $contactoNombreCompleto = trim($contact->nombre.' '.$contact->apellidos());

        // tip_iden('corta') revienta si el tipo de identificación no existe.
        try {
            $tipoIden = $contact->tip_iden('corta');
        } catch (\Throwable $e) {
            $tipoIden = 'CC';
        }

        $departamento = $contact->departamento()->nombre ?? '';
        $municipio    = $contact->municipio()->nombre ?? '';

        // Plan de internet y de televisión, con el mismo cálculo que traía la
        // plantilla anterior (el impuesto de TV va incluido en el precio).
        $tieneInternet = isset($contract->server_configuration_id);
        $tieneTV       = isset($contract->servicio_tv);
        $tieneIva      = $contract && $contract->iva_factura == 1;

        $planInternet = $tieneInternet ? $contract->plan() : null;
        $planTV       = $tieneTV ? $contract->plan('true') : null;

        $planDownload = $planInternet->download ?? '';
        $planUpload   = $planInternet->upload ?? '';

        $totalInternet = $tieneInternet ? (float) ($planInternet->price ?? 0) : 0;
        $totalTV = 0;
        if ($tieneTV) {
            $precioTV = (float) ($planTV->precio ?? 0);
            $totalTV  = $precioTV + ($precioTV * (float) ($planTV->impuesto ?? 0) / 100);
        }
        if ($tieneIva) {
            $totalInternet *= 1.19;
            $totalTV       *= 1.19;
        }

        $internetStr = Funcion::Parsear($totalInternet);
        $tvStr       = Funcion::Parsear($totalTV);
        $totalStr    = Funcion::Parsear($totalInternet + $totalTV);

        // Permanencia
        $permanenciaMeses = $contract->contrato_permanencia_meses ?? 12;
        $fechaCreacion = isset($contract->created_at)
            ? Carbon::parse($contract->created_at)->format('d/m/Y') : null;
        $fechaFinPermanencia = isset($contract->created_at)
            ? Carbon::parse($contract->created_at)->addMonths($permanenciaMeses)->format('d/m/Y') : null;

        // Empresa
        $color = $company->color ?? '#3490dc';
        $moneda = $company->moneda ?? '$';
        $nombreEmpresa = $company->nombre ?? '';
        $webEmpresa = $company->web ?? '';
        $emailEmpresa = $company->email ?? '';
        $clausula = (float) ($company->clausula_permanencia ?? 0);
        $contratoDigitalTexto = $company->contrato_digital ?? null;
        $costoReconexion = (float) ($contract->costo_reconexion ?? 0);
        $tecnologia = $contract->tecnologia ?? null;
        $contratoNro = $contract->nro ?? '';

        // El logo se sirve por el proxy de Contabo, igual que en la plantilla
        // anterior: dompdf lo descarga con enable_remote (que ya viene activo).
        $logoSrc = contabo_url(env('LOGOS_FOLDER', 'logos'), 'logo.png');

        // Equipos entregados: el contrato CRC los imprime en tabla. La tabla la
        // crea la migración del bloque CRC, así que se comprueba antes de
        // tocarla — las BDs de clientes no corren `migrate`.
        $equiposContrato = [];
        if ($contract && \Schema::hasTable('contratos_equipos')) {
            $equiposContrato = DB::table('contratos_equipos')
                ->where('contrato_id', $contract->id)->orderBy('id')->get()->all();
        }

        return compact(
            'digital', 'contact', 'company', 'contract', 'contractDetails',
            'contactoNombreCompleto', 'tipoIden',
            'departamento', 'municipio',
            'planDownload', 'planUpload',
            'tieneInternet', 'tieneTV', 'tieneIva',
            'internetStr', 'tvStr', 'totalStr',
            'permanenciaMeses', 'fechaCreacion', 'fechaFinPermanencia',
            'color', 'moneda', 'nombreEmpresa', 'webEmpresa', 'emailEmpresa',
            'clausula', 'contratoDigitalTexto', 'costoReconexion',
            'tecnologia', 'contratoNro', 'logoSrc', 'equiposContrato'
        );
    }

    // funcion que permita imprimir el contrato en firma de asignaciones
    public function imprimir_firma($id)
    {
        $digital = ContratoDigital::find($id);
        if (!$digital) {
            $digital = ContratoDigital::where('cliente_id', $id)->orderBy('id', 'DESC')->first();
        }

        if (!$digital) {
            abort(404, 'No se encontró el contrato digital.');
        }

        $contact = $digital->cliente;
        $contract = $digital->contrato;
        $company = Empresa::first();
        $empresa = $company;

        view()->share(['title' => 'Contrato de Internet']);
        $pdf = Pdf::loadView('pdf.contrato_firma', compact([
            'contact',
            'company',
            'empresa',
            'digital',
            'contract'
        ]));
        return response($pdf->stream())->withHeaders(['Content-Type' => 'application/pdf',]);
    }

    public function show_campos_asignacion()
    {
        $empresa = Empresa::find(Auth::user()->empresa);
        return json_encode($empresa);
    }

    public function campos_asignacion(Request $request)
    {
        $empresa = Empresa::find(Auth::user()->empresa);
        if($empresa) {
            $empresa->campo_a = $request->campo_a;
            $empresa->campo_b = $request->campo_b;
            $empresa->campo_c = $request->campo_c;
            $empresa->campo_d = $request->campo_d;
            $empresa->campo_e = $request->campo_e;
            $empresa->campo_f = $request->campo_f;
            $empresa->campo_g = $request->campo_g;
            $empresa->campo_h = $request->campo_h;
            $empresa->campo_1 = $request->campo_1;
            $empresa->contrato_digital = $request->contrato_digital;
            $empresa->anexo_1 = $request->anexo_1;
            $empresa->anexo_2 = $request->anexo_2;
            $empresa->anexo_3 = $request->anexo_3;
            $empresa->anexo_4 = $request->anexo_4;

            // Datos del Contrato Único Convergente (CRC 7811 de 2025). Las
            // columnas las agrega la migración del bloque CRC y las BDs de
            // clientes no corren `migrate`: en una base sin actualizar,
            // asignarlas a pelo tumbaría el guardado de TODO el modal.
            if (\Schema::hasColumn('empresas', 'registro_tic')) {
                $empresa->registro_tic = $request->registro_tic ?: null;
            }
            if (\Schema::hasColumn('empresas', 'incremento_tarifario')) {
                $empresa->incremento_tarifario = $request->filled('incremento_tarifario')
                    ? $request->incremento_tarifario : null;
            }

            $empresa->save();
            return response()->json([
                'success'          => true,
                'campo_a'          => $empresa->campo_a,
                'campo_b'          => $empresa->campo_b,
                'campo_c'          => $empresa->campo_c,
                'campo_d'          => $empresa->campo_d,
                'campo_e'          => $empresa->campo_e,
                'campo_f'          => $empresa->campo_f,
                'campo_g'          => $empresa->campo_g,
                'campo_h'          => $empresa->campo_h,
                'campo_1'          => $empresa->campo_1,
                'contrato_digital' => $empresa->contrato_digital,
                'anexo_1'          => $empresa->anexo_1,
                'anexo_2'          => $empresa->anexo_2,
                'anexo_3'          => $empresa->anexo_3,
                'anexo_4'          => $empresa->anexo_4,
                'registro_tic'         => $empresa->registro_tic ?? '',
                'incremento_tarifario' => $empresa->incremento_tarifario ?? ''
            ]);
        }
        return response()->json(['success' => false]);
    }

    public function enviar($id)
    {
        $digital = ContratoDigital::findOrFail($id);
        $contact = $digital->cliente;
        $contract = $digital->contrato;
        $contractDetails = $digital->contrato;

        if (!$contact->email) {
            return back()->with('danger', 'EL CLIENTE NO TIENE UN CORREO ELECTRÓNICO REGISTRADO');
        }

        if (!$contract) {
            return back()->with('danger', 'El contacto no tiene un contrato asociado.');
        }

        $company = Empresa::first();

        $host = ServidorCorreo::where('estado', 1)->where('empresa', $company->id)->first();
        if ($host) {
            $existing = config('mail');
            $new = array_merge(
                $existing,
                [
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
            config(['mail' => $new]);
        }

        view()->share(['title' => 'Contrato de Internet']);
        $pdf = $this->construirPdfContrato($digital);

        $email = $contact->email;
        $cliente = $contact->nombre;
        self::sendMail('emails.contrato', compact('contact'), compact('pdf', 'contact', 'email', 'cliente'), function ($message) use ($pdf, $contact, $company) {
            $message->attachData($pdf, 'contrato_digital_servicios.pdf', ['mime' => 'application/pdf']);
            $message->to($contact->email)->subject("Contrato Digital de Servicios - " . $company->nombre);
        });

        return back()->with('success', strtoupper('EL CONTRATO DIGITAL DE SERVICIOS HA SIDO ENVIADO CORRECTAMENTE A ' . $contact->nombre . ' ' . $contact->apellidos()));
    }

    public function generar_link($id)
    {
        $contrato = Contrato::find($id);
        $empresa = Empresa::first();

        if (!$contrato) {
            $contacto = Contacto::find(request()->cliente);
            if ($contacto) {
                $ref = $contacto->nit;
                $link = config('app.url') . "/api/contrato-digital/" . $ref;

                return response()->json([
                    'success'  => true,
                    'contacto' => $contacto->id,
                    'text'     => "<a href='" . config('app.url') . "/api/contrato-digital/" . $ref . "' target='_blank'>" . config('app.url') . "/api/contrato-digital/" . $ref . "</a><br><br><button class='btn btn-primary btn-lg' data-clipboard-text='" . $link . "'>COPIAR URL</button>
                    ",
                    'link'     => config('app.url') . "/api/contrato-digital/" . $ref,
                    'type'     => 'success'
                ]);
            }
        }

        if ($contrato) {
            $ref = $contrato->nro;
            $contacto = Contacto::find($contrato->client_id);
            $link = config('app.url') . "/api/contrato-digital/" . $ref;

            return response()->json([
                'success'  => true,
                'contacto' => $contacto->id,
                'text'     => "<a href='" . config('app.url') . "/api/contrato-digital/" . $ref . "' target='_blank'>" . config('app.url') . "/api/contrato-digital/" . $ref . "</a><br><br><button class='btn btn-primary btn-lg' data-clipboard-text='" . $link . "'>COPIAR URL</button>
                ",
                'link'     => config('app.url') . "/api/contrato-digital/" . $ref,
                'type'     => 'success'
            ]);
        }
        return response()->json(['success' => false, 'text' => 'Algo falló, intente nuevamente', 'type' => 'error']);
    }

    /**
     * Redimensiona el archivo (si es imagen) en el temp file de PHP y lo sube
     * a Contabo bajo CLIENTE/<ADJUNTOS_FOLDER>/<nombre>. Las vistas lo leen
     * después con contabo_url(env('ADJUNTOS_FOLDER'), $nombre). No toca el
     * filesystem local del server.
     *
     * $prefix se concatena entre $idContrato y $cliente->nit, por ejemplo
     * 'doc_', 'imgA_', 'imgB_'. Retorna el nombre final o null si la subida
     * falló.
     */
    private function subirAdjunto($file, $idContrato, $cliente, $prefix, $xmax = 1080, $ymax = 720)
    {
        if (!$file) {
            return null;
        }

        $extPermitidas = ['jpeg', 'png', 'gif'];
        $ext = strtolower($file->getClientOriginalExtension());
        $nombre = $idContrato . $prefix . $cliente->nit . '.' . $ext;

        // Si es imagen, redimensionamos in-place en el temp file que PHP creó
        // para el upload. Si falla cualquier parte del resize, seguimos con el
        // archivo original (mejor subir grande que no subir nada).
        if (in_array($ext, $extPermitidas)) {
            try {
                $tempPath = $file->getRealPath();
                $imagen = null;
                switch ($ext) {
                    case 'jpeg': $imagen = @imagecreatefromjpeg($tempPath); break;
                    case 'png':  $imagen = @imagecreatefrompng($tempPath); break;
                    case 'gif':  $imagen = @imagecreatefromgif($tempPath); break;
                }

                if ($imagen) {
                    $x = imagesx($imagen);
                    $y = imagesy($imagen);

                    if ($x > $xmax || $y > $ymax) {
                        if ($x >= $y) {
                            $nuevax = $xmax;
                            $nuevay = $nuevax * $y / $x;
                        } else {
                            $nuevay = $ymax;
                            $nuevax = $x / $y * $nuevay;
                        }
                        $img2 = imagecreatetruecolor((int) $nuevax, (int) $nuevay);
                        imagecopyresized($img2, $imagen, 0, 0, 0, 0, (int) floor($nuevax), (int) floor($nuevay), $x, $y);
                        switch ($ext) {
                            case 'jpeg': imagejpeg($img2, $tempPath, 90); break;
                            case 'png':  imagepng($img2, $tempPath, 9); break;
                            case 'gif':  imagegif($img2, $tempPath); break;
                        }
                        imagedestroy($img2);
                    }
                    imagedestroy($imagen);
                }
            } catch (\Throwable $e) {
                \Log::warning('asignaciones: redimensionar falló ('.$nombre.'): '.$e->getMessage());
            }
        }

        try {
            app(ContaboS3Service::class)->upload(
                env('ADJUNTOS_FOLDER', 'adjuntos'),
                $file,
                $nombre,
                'public-read'
            );
            return $nombre;
        } catch (\Throwable $e) {
            \Log::error('asignaciones: subir a Contabo falló ('.$nombre.'): '.$e->getMessage());
            return null;
        }
    }
}