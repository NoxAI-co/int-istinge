<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class LogsController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        view()->share(['inicio' => 'empresa', 'seccion' => 'Logs', 'title' => 'Logs', 'icon' => 'fa fa-file-alt']);
    }

    /**
     * Lista todos los archivos .log de la carpeta storage/logs y muestra
     * el contenido del archivo seleccionado.
     */
    public function index(Request $request)
    {
        $logPath = storage_path('logs');

        $logs = [];

        if (File::isDirectory($logPath)) {
            foreach (File::glob($logPath . DIRECTORY_SEPARATOR . '*.log') as $file) {
                $logs[] = [
                    'nombre'       => basename($file),
                    'tamano'       => $this->formatBytes(filesize($file)),
                    'modificado'   => Carbon::createFromTimestamp(filemtime($file))->format('Y-m-d H:i:s'),
                ];
            }
        }

        // Ordenar por fecha de modificación (más reciente primero).
        usort($logs, function ($a, $b) {
            return strcmp($b['modificado'], $a['modificado']);
        });

        $seleccionado = $request->get('archivo');
        $contenido    = null;

        if ($seleccionado) {
            // Evitar que se acceda a rutas fuera de storage/logs.
            $seleccionado = basename($seleccionado);
            $rutaArchivo  = $logPath . DIRECTORY_SEPARATOR . $seleccionado;

            if (substr($seleccionado, -4) === '.log' && File::exists($rutaArchivo)) {
                $contenido = File::get($rutaArchivo);
            } else {
                $seleccionado = null;
            }
        }

        return view('master.logs.index', compact('logs', 'seleccionado', 'contenido'));
    }

    /**
     * Descarga un archivo de log.
     */
    public function descargar($archivo)
    {
        $archivo = basename($archivo);
        $ruta    = storage_path('logs') . DIRECTORY_SEPARATOR . $archivo;

        if (substr($archivo, -4) !== '.log' || !File::exists($ruta)) {
            abort(404);
        }

        return response()->download($ruta);
    }

    /**
     * Convierte bytes a una representación legible.
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow   = $bytes ? floor(log($bytes, 1024)) : 0;
        $pow   = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
