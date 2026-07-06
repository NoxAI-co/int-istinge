<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ContaboS3Service;

/**
 * Migra los archivos que quedaron en la carpeta legacy "documentos" del bucket
 * de Contabo hacia la carpeta configurada en ADJUNTOS_FOLDER (por defecto
 * "adjuntos"). Corre en el contexto de UN cliente (usa su propio .env, que
 * define CLIENTE y ADJUNTOS_FOLDER). El .sh de despliegue lo invoca por cliente.
 *
 * Uso:
 *   php artisan contabo:migrar-documentos-adjuntos            # dry-run (solo lista)
 *   php artisan contabo:migrar-documentos-adjuntos --run      # copia a adjuntos
 *   php artisan contabo:migrar-documentos-adjuntos --run --delete  # copia y borra el origen
 */
class MigrarDocumentosAdjuntos extends Command
{
    protected $signature = 'contabo:migrar-documentos-adjuntos
                            {--run : Ejecuta la copia (sin este flag es dry-run)}
                            {--delete : Borra el archivo de la carpeta origen tras copiar OK}
                            {--from=documentos : Carpeta origen}';

    protected $description = 'Copia archivos de la carpeta legacy "documentos" a ADJUNTOS_FOLDER en Contabo';

    public function handle()
    {
        $s3     = app(ContaboS3Service::class);
        $from   = (string) $this->option('from');
        $to     = (string) env('ADJUNTOS_FOLDER', 'adjuntos');
        $run    = (bool) $this->option('run');
        $delete = (bool) $this->option('delete');

        $this->info("[{$s3->cliente()}] Migrando '{$from}' -> '{$to}' " . ($run ? '(EJECUTANDO)' : '(dry-run)'));

        if ($from === $to) {
            $this->warn("Origen y destino son iguales ('{$from}'). Nada que hacer.");
            return 0;
        }

        $keys = $s3->list($from); // keys completas: CLIENTE/documentos/archivo
        if (empty($keys)) {
            $this->line('No hay archivos en la carpeta origen. Nada que migrar.');
            return 0;
        }

        $prefix = $s3->key($from); // CLIENTE/documentos/
        $ok = 0; $skip = 0; $fail = 0;

        foreach ($keys as $key) {
            // Nombre del archivo relativo a la carpeta origen.
            $filename = substr($key, strlen($prefix));
            if ($filename === '' || substr($filename, -1) === '/') {
                continue; // placeholder de carpeta
            }

            // Si ya existe en destino, no re-copiamos (idempotente).
            if ($s3->exists($to, $filename)) {
                $this->line("  = ya existe en destino: {$filename}");
                if ($delete && $run) {
                    $s3->delete($from, $filename);
                }
                $skip++;
                continue;
            }

            if (!$run) {
                $this->line("  + (dry) copiaría: {$filename}");
                $ok++;
                continue;
            }

            if ($s3->copy($from, $to, $filename)) {
                // Verificamos que quedó en destino antes de borrar el origen.
                if ($s3->exists($to, $filename)) {
                    $this->info("  + copiado: {$filename}");
                    if ($delete) {
                        $s3->delete($from, $filename);
                    }
                    $ok++;
                } else {
                    $this->error("  ! copiado sin verificar (no existe en destino): {$filename}");
                    $fail++;
                }
            } else {
                $this->error("  ! FALLÓ copia: {$filename}");
                $fail++;
            }
        }

        $this->info("[{$s3->cliente()}] Listo. OK={$ok} omitidos={$skip} fallos={$fail}");
        return $fail > 0 ? 1 : 0;
    }
}
