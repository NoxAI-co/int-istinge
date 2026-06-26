<?php

namespace App\Http\Controllers;

use App\Services\ContaboS3Service;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ContaboAssetController extends Controller
{
    const MISSING_SENTINEL = '__MISSING__';

    // PNG 1x1 totalmente transparente (43 bytes). Sirve como placeholder
    // neutro cuando la empresa no tiene logo cargado — se estira al tamaño
    // del <img> sin mostrar marca ajena.
    const TRANSPARENT_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR4nGNgAAIAAAUAAXpeqz8AAAAASUVORK5CYII=';

    public static function cacheKey(string $folder, string $filename): string
    {
        return 'contabo:asset:'.trim($folder, '/').'/'.ltrim($filename, '/');
    }

    /**
     * Si el objeto existe en Contabo redirige (302) a la URL firmada (cacheada).
     * Si no existe (o falla la consulta) el fallback depende del folder:
     *   - logos  → PNG 1x1 transparente (placeholder neutro, no muestra marca ajena)
     *   - resto  → 404 honesto (un PNG de 43 bytes haciéndose pasar por documento
     *              abre una pestaña "blanca" inutilizable para el usuario).
     */
    public function show(string $folder, string $filename, ContaboS3Service $s3)
    {
        $cached = Cache::get(self::cacheKey($folder, $filename));

        if ($cached === null) {
            $cached = $this->resolve($s3, $folder, $filename);
        }

        if ($cached === self::MISSING_SENTINEL) {
            if ($this->esFolderDeLogos($folder)) {
                return $this->transparentPng();
            }
            abort(404, 'Archivo no encontrado en Contabo: '.trim($folder, '/').'/'.$filename);
        }

        return redirect()->away($cached, 302);
    }

    private function esFolderDeLogos(string $folder): bool
    {
        return trim($folder, '/') === env('LOGOS_FOLDER', 'logos');
    }

    private function resolve(ContaboS3Service $s3, string $folder, string $filename): string
    {
        try {
            // Probamos el nombre exacto primero y, si no existe, variantes con
            // extensión equivalente (jpg ↔ jpeg ↔ jpe, en minúscula/mayúscula).
            // Hay registros legacy donde la extensión guardada en BD no coincide
            // con la del objeto real en Contabo (p. ej. doc_*.jpeg en BD pero
            // doc_*.jpg en storage).
            foreach ($this->candidateFilenames($filename) as $candidate) {
                if ($s3->exists($folder, $candidate)) {
                    $url = $s3->signedUrl($folder, $candidate);
                    $ttl = max(1, (int) config('contabo.url_ttl', 60) - 5);
                    Cache::put(self::cacheKey($folder, $filename), $url, now()->addMinutes($ttl));
                    return $url;
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Contabo asset resolve falló: '.$e->getMessage(), ['folder' => $folder, 'filename' => $filename]);
        }

        Cache::put(self::cacheKey($folder, $filename), self::MISSING_SENTINEL, now()->addMinutes(2));
        return self::MISSING_SENTINEL;
    }

    /**
     * Devuelve el nombre solicitado seguido de variantes con extensión
     * equivalente. Tolera el desfase legacy entre la extensión guardada en la
     * BD y la del objeto realmente subido a Contabo, sin necesidad de migrar
     * datos. El nombre exacto siempre va primero para no penalizar el caso sano.
     */
    private function candidateFilenames(string $filename): array
    {
        $dot = strrpos($filename, '.');
        if ($dot === false) {
            return [$filename];
        }

        $base = substr($filename, 0, $dot);
        $ext  = strtolower(substr($filename, $dot + 1));

        // Familias de extensiones intercambiables.
        $familias = [
            ['jpg', 'jpeg', 'jpe'],
        ];

        $exts = [$ext];
        foreach ($familias as $familia) {
            if (in_array($ext, $familia, true)) {
                $exts = array_merge($exts, $familia);
                break;
            }
        }

        $candidates = [$filename]; // nombre exacto primero
        foreach (array_unique($exts) as $e) {
            $candidates[] = $base.'.'.$e;
            $candidates[] = $base.'.'.strtoupper($e);
        }

        return array_values(array_unique($candidates));
    }

    private function transparentPng(): Response
    {
        return new Response(base64_decode(self::TRANSPARENT_PNG_BASE64), 200, [
            'Content-Type'  => 'image/png',
            'Cache-Control' => 'public, max-age=120',
        ]);
    }
}
