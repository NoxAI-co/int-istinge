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

        $ext_permitidas = array('jpeg','png','gif');
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
        $nombreDoc = $this->moverYRedimensionar($request->file('documento'), $idContrato, $cliente, 'doc_');
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
                $nombre = $this->moverYRedimensionar($request->file($campo), $idContrato, $cliente, $prefix);
                if ($nombre !== null) {
                    $digital->$campo = $nombre;
                }
            }
        }

        // Audio: solo se mueve, no se procesa como imagen.
        if ($request->file('adjunto_audio')) {
            $audio = $request->file('adjunto_audio');
            $nombreAudio = $idContrato.'adjunto_audio'.$cliente->nit.'.'.$audio->getClientOriginalExtension();
            $rutaAudio = public_path('/adjuntos/documentos/');
            try {
                if (!is_dir($rutaAudio)) {
                    @mkdir($rutaAudio, 0755, true);
                }
                $audio->move($rutaAudio, $nombreAudio);
                $digital->adjunto_audio = $nombreAudio;
            } catch (\Throwable $e) {
                \Log::error('asignaciones: move adjunto_audio falló: '.$e->getMessage());
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
        $ext_permitidas = array('jpeg','png','gif');
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

            $campos_imagen = ['documento', 'imgA', 'imgB', 'imgC', 'imgD', 'imgE', 'imgF', 'imgG', 'imgH'];
            $ruta = public_path('/adjuntos/documentos/');

            foreach ($campos_imagen as $campo) {
                if ($request->file($campo)) {
                    $file = $request->file($campo);
                    $ext = $file->getClientOriginalExtension();
                    $nombre = $digital->contrato_id . $campo . '_' . $digital->cliente->nit . '.' . $ext;

                    try {
                        if (!is_dir($ruta)) {
                            @mkdir($ruta, 0755, true);
                        }
                        $file->move($ruta, $nombre);
                        $digital->$campo = $nombre;
                    } catch (\Throwable $e) {
                        \Log::error('asignaciones update: move '.$campo.' falló: '.$e->getMessage());
                        continue;
                    }

                    if (in_array($ext, $ext_permitidas) && file_exists($ruta . $nombre)) {
                        try {
                            $sourcePath = $ruta . $nombre;
                            $image = null;
                            switch ($ext) {
                                case 'jpeg': $image = @imagecreatefromjpeg($sourcePath); break;
                                case 'png':  $image = @imagecreatefrompng($sourcePath); break;
                                case 'gif':  $image = @imagecreatefromgif($sourcePath); break;
                            }
                            if ($image) {
                                imagejpeg($image, $sourcePath, 50);
                                imagedestroy($image);
                            }
                        } catch (\Throwable $e) {
                            \Log::error('asignaciones update: recomprimir '.$campo.' falló: '.$e->getMessage());
                        }
                    }
                }
            }

            if ($request->file('adjunto_audio')) {
                $audio = $request->file('adjunto_audio');
                $nombreAudio = $digital->contrato_id.'adjunto_audio'.$digital->cliente->nit.'.'.$audio->getClientOriginalExtension();
                try {
                    if (!is_dir($ruta)) {
                        @mkdir($ruta, 0755, true);
                    }
                    $audio->move($ruta, $nombreAudio);
                    $digital->adjunto_audio = $nombreAudio;
                } catch (\Throwable $e) {
                    \Log::error('asignaciones update: move adjunto_audio falló: '.$e->getMessage());
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

        $contact = $digital->cliente;
        $company = Empresa::first(); // Or Auth::user()->empresa() if preferred, but first() is safe for PDF generation
        $contract = $digital->contrato;
        $contractDetails = $digital->contrato;

        if ($contact->firma_isp != null && $digital->firma == null) {
            $digital->firma = $contact->firma_isp;
            $digital->estado_firma = 1;
            $digital->save();
        }

        view()->share(['title' => 'Contrato de Internet']);
        $pdf = Pdf::loadView('pdf.contrato', compact([
            'contact',
            'company',
            'contract',
            'contractDetails',
            'digital'
        ]));
        return response($pdf->stream())->withHeaders(['Content-Type' => 'application/pdf',]);
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
                'anexo_4'          => $empresa->anexo_4
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
        $pdf = Pdf::loadView('pdf.contrato', compact([
            'contact',
            'company',
            'contract',
            'contractDetails',
            'digital'
        ]))->output();

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
     * Mueve un archivo subido a public/adjuntos/documentos/ y, si es imagen
     * (jpeg/png/gif), la redimensiona in-place. Crea la carpeta si no existe.
     * Es tolerante a fallos: ante cualquier error de filesystem o de GD,
     * loguea y sigue — nunca tira excepción a quien llama. Retorna el nombre
     * del archivo guardado, o null si ni siquiera el move pudo hacerse.
     *
     * $prefix se concatena entre $idContrato y $cliente->nit, por ejemplo
     * 'doc_', 'imgA_', 'imgB_'.
     */
    private function moverYRedimensionar($file, $idContrato, $cliente, $prefix, $xmax = 1080, $ymax = 720)
    {
        $extPermitidas = ['jpeg', 'png', 'gif'];
        $ext = $file->getClientOriginalExtension();
        $nombre = $idContrato . $prefix . $cliente->nit . '.' . $ext;
        $ruta = public_path('/adjuntos/documentos/');

        try {
            if (!is_dir($ruta)) {
                @mkdir($ruta, 0755, true);
            }
            $file->move($ruta, $nombre);
        } catch (\Throwable $e) {
            \Log::error('asignaciones: move falló para '.$nombre.': '.$e->getMessage());
            return null;
        }

        $rutaArchivo = $ruta . $nombre;

        if (!in_array($ext, $extPermitidas) || !file_exists($rutaArchivo)) {
            return $nombre;
        }

        try {
            $imagen = null;
            switch ($ext) {
                case 'jpeg': $imagen = @imagecreatefromjpeg($rutaArchivo); break;
                case 'png':  $imagen = @imagecreatefrompng($rutaArchivo); break;
                case 'gif':  $imagen = @imagecreatefromgif($rutaArchivo); break;
            }

            if (!$imagen) {
                \Log::warning('asignaciones: imagecreatefrom* falló para '.$rutaArchivo);
                return $nombre;
            }

            $x = imagesx($imagen);
            $y = imagesy($imagen);

            if ($x <= $xmax && $y <= $ymax) {
                switch ($ext) {
                    case 'jpeg': imagejpeg($imagen, $rutaArchivo, 5); break;
                    case 'png':  imagepng($imagen, $rutaArchivo, 5); break;
                    case 'gif':  imagegif($imagen, $rutaArchivo); break;
                }
            } else {
                if ($x >= $y) {
                    $nuevax = $xmax;
                    $nuevay = $nuevax * $y / $x;
                } else {
                    $nuevay = $ymax;
                    $nuevax = $x / $y * $nuevay;
                }
                $img2 = imagecreatetruecolor($nuevax, $nuevay);
                imagecopyresized($img2, $imagen, 0, 0, 0, 0, floor($nuevax), floor($nuevay), $x, $y);
                switch ($ext) {
                    case 'jpeg': imagejpeg($img2, $rutaArchivo, 90); break;
                    case 'png':  imagepng($img2, $rutaArchivo, 9); break;
                    case 'gif':  imagegif($img2, $rutaArchivo); break;
                }
                imagedestroy($img2);
            }
            imagedestroy($imagen);
        } catch (\Throwable $e) {
            \Log::error('asignaciones: redimensionar falló para '.$rutaArchivo.': '.$e->getMessage());
        }

        return $nombre;
    }
}