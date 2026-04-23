<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Contacto;
use App\SensibilizacionLog;
use App\Services\OnePayService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Funcion;

class SensibilizacionController extends Controller
{
    public function index()
    {
        $title = 'IntegraPay - Sensibilización';
        $icon = 'fas fa-credit-card';
        $seccion = 'integrapay';
        $subseccion = 'sensibilizacion';

        // Obtener la imagen actual si existe
        $imageUrl = null;
        if (File::exists(public_path('images/sensibilizacion.png'))) {
            $imageUrl = asset('images/sensibilizacion.png') . '?v=' . time();
        }

        return view('integrapay.sensibilizacion.index', compact('title', 'icon', 'seccion', 'subseccion', 'imageUrl'));
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png|max:2048', // 2MB max
        ]);

        $image = $request->file('image');

        try {
            // Asegurar que el directorio existe
            $path = public_path('images');
            if (!File::exists($path)) {
                File::makeDirectory($path, 0755, true);
            }

            // Redimensionar a 1280x960 usando Intervention Image
            $img = \Image::make($image);
            $img->fit(1280, 960);
            $img->save($path . '/sensibilizacion.png', 90);

            return response()->json([
                'success' => true,
                'message' => 'Imagen subida y redimensionada a 1280x960px exitosamente.',
                'url' => asset('images/sensibilizacion.png') . '?v=' . time()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar la imagen: ' . $e->getMessage()
            ], 500);
        }
    }


    public function getContactos()
    {
        // Obtener contactos que tengan celular
        $contactos = Contacto::whereNotNull('celular')
            ->where('celular', '!=', '')
            ->where('status', 1)
            ->get(['id', 'nombre', 'apellido1', 'apellido2', 'celular', 'nit']);

        $validos = [];
        $invalidos = [];
        $uniqueNumbers = [];

        foreach ($contactos as $contacto) {
            $rawPhone = $contacto->celular;
            $formattedPhone = $this->formatPhone($rawPhone);

            if ($formattedPhone && $this->isValidE164($formattedPhone)) {
                if (!in_array($formattedPhone, $uniqueNumbers)) {
                    $uniqueNumbers[] = $formattedPhone;
                    $validos[] = [
                        'id' => $contacto->id,
                        'nombre' => $contacto->nombre . ' ' . $contacto->apellido1 . ' ' . $contacto->apellido2,
                        'nit' => $contacto->nit,
                        'celular_original' => $rawPhone,
                        'celular_formateado' => $formattedPhone
                    ];
                }
            } else {
                $invalidos[] = [
                    'id' => $contacto->id,
                    'nombre' => $contacto->nombre . ' ' . $contacto->apellido1 . ' ' . $contacto->apellido2,
                    'celular_original' => $rawPhone
                ];
            }
        }

        return response()->json([
            'total' => count($contactos),
            'validos' => $validos,
            'invalidos' => $invalidos,
            'total_validos' => count($validos),
            'total_invalidos' => count($invalidos)
        ]);
    }

    public function sendCampaign(Request $request)
    {
        $request->validate([
            'template' => 'required|in:sensibilizacion_primera_comunicacion,sensibilizacion_primera_comunicacion_v2',
            'optional_1' => 'nullable|string',
            'contactos' => 'required|array|min:1'
        ]);

        $template = $request->template;
        $optional1 = $request->optional_1;
        $phones = $request->contactos; // Array de strings formatados +57...
        
        $imageUrl = asset('images/sensibilizacion.png');
        if (!File::exists(public_path('images/sensibilizacion.png'))) {
            return response()->json(['success' => false, 'message' => 'Primero debes subir la imagen de sensibilización.'], 422);
        }

        $batchId = (string) Str::uuid();
        $onePayService = new OnePayService();
        $batchSize = 250;
        $chunks = array_chunk($phones, $batchSize);
        
        $results = [
            'total_sent' => 0,
            'total_failed' => 0,
            'batches' => count($chunks)
        ];

        foreach ($chunks as $index => $chunk) {
            $batchNumber = $index + 1;
            $idempotencyKey = "campaign-sensibilizacion-{$batchId}-{$batchNumber}";

            try {
                $response = $onePayService->sendSensibilizacionCampaign(
                    $chunk,
                    $template,
                    $imageUrl,
                    $idempotencyKey,
                    $optional1
                );

                $status = $response['success'] ? 'sent' : 'failed';
                
                // Registrar log para cada número del lote
                foreach ($chunk as $phone) {
                    SensibilizacionLog::create([
                        'celular' => $phone,
                        'template' => $template,
                        'batch_id' => $batchId,
                        'batch_number' => $batchNumber,
                        'idempotency_key' => $idempotencyKey . '-' . $phone, // Unique per phone in logs
                        'status' => $status,
                        'api_response' => json_encode($response['data'] ?? []),
                        'error_message' => $response['message'] ?? null,
                        'image_url' => $imageUrl,
                        'optional_1' => $optional1,
                        'campaign_date' => Carbon::now(),
                        'created_by' => Auth::id()
                    ]);
                }

                if ($response['success']) {
                    $results['total_sent'] += count($chunk);
                } else {
                    $results['total_failed'] += count($chunk);
                }

            } catch (\Exception $e) {
                Log::error("Error enviando lote {$batchNumber} de campaña sensibilización: " . $e->getMessage());
                
                foreach ($chunk as $phone) {
                    SensibilizacionLog::create([
                        'celular' => $phone,
                        'template' => $template,
                        'batch_id' => $batchId,
                        'batch_number' => $batchNumber,
                        'idempotency_key' => $idempotencyKey . '-' . $phone . '-err',
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                        'image_url' => $imageUrl,
                        'optional_1' => $optional1,
                        'campaign_date' => Carbon::now(),
                        'created_by' => Auth::id()
                    ]);
                }
                $results['total_failed'] += count($chunk);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Campaña procesada.',
            'results' => $results,
            'batch_id' => $batchId
        ]);
    }

    public function campaignHistory()
    {
        $logs = SensibilizacionLog::select('batch_id', 'campaign_date', 'template', 'image_url', 'optional_1', 'created_by')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) as sent')
            ->selectRaw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed')
            ->groupBy('batch_id', 'campaign_date', 'template', 'image_url', 'optional_1', 'created_by')
            ->orderBy('campaign_date', 'desc')
            ->get();

        return datatables()->of($logs)
            ->editColumn('campaign_date', function($log) {
                return Carbon::parse($log->campaign_date)->format('d/m/Y H:i:s');
            })
            ->addColumn('created_by_name', function($log) {
                return $log->creator ? $log->creator->nombres : 'Sistema';
            })
            ->rawColumns(['image_url'])
            ->make(true);
    }

    private function formatPhone($phone)
    {
        // Eliminar todo lo que no sea digito
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (empty($phone)) return null;

        // Si tiene 10 dígitos y empieza por 3, es un celular colombiano sin prefijo
        if (strlen($phone) == 10 && substr($phone, 0, 1) == '3') {
            return '+57' . $phone;
        }

        // Si tiene 12 dígitos y empieza por 573, es un celular colombiano con prefijo 57
        if (strlen($phone) == 12 && substr($phone, 0, 3) == '573') {
            return '+' . $phone;
        }

        return null;
    }

    private function isValidE164($phone)
    {
        // Regex para +573 + 9 digitos
        return preg_match('/^\+573[0-9]{9}$/', $phone);
    }
}
