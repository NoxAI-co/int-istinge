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
     * Si no existe (o falla la consulta) devuelve un PNG 1x1 transparente — así
     * nunca se muestra un logo de otra empresa como placeholder mientras la
     * empresa actual no haya subido el suyo.
     */
    public function show(string $folder, string $filename, ContaboS3Service $s3)
    {
        $cached = Cache::get(self::cacheKey($folder, $filename));

        if ($cached === null) {
            $cached = $this->resolve($s3, $folder, $filename);
        }

        if ($cached === self::MISSING_SENTINEL) {
            return $this->transparentPng();
        }

        return redirect()->away($cached, 302);
    }

    private function resolve(ContaboS3Service $s3, string $folder, string $filename): string
    {
        try {
            if ($s3->exists($folder, $filename)) {
                $url = $s3->signedUrl($folder, $filename);
                $ttl = max(1, (int) config('contabo.url_ttl', 60) - 5);
                Cache::put(self::cacheKey($folder, $filename), $url, now()->addMinutes($ttl));
                return $url;
            }
        } catch (\Throwable $e) {
            \Log::warning('Contabo asset resolve falló: '.$e->getMessage(), ['folder' => $folder, 'filename' => $filename]);
        }

        Cache::put(self::cacheKey($folder, $filename), self::MISSING_SENTINEL, now()->addMinutes(2));
        return self::MISSING_SENTINEL;
    }

    private function transparentPng(): Response
    {
        return new Response(base64_decode(self::TRANSPARENT_PNG_BASE64), 200, [
            'Content-Type'  => 'image/png',
            'Cache-Control' => 'public, max-age=120',
        ]);
    }
}
