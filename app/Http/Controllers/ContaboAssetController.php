<?php

namespace App\Http\Controllers;

use App\Services\ContaboS3Service;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ContaboAssetController extends Controller
{
    const MISSING_SENTINEL = '__MISSING__';

    public static function cacheKey(string $folder, string $filename): string
    {
        return 'contabo:asset:'.trim($folder, '/').'/'.ltrim($filename, '/');
    }

    /**
     * Devuelve un redirect 302. Si el objeto existe en Contabo, redirige a la
     * URL firmada (cacheada). Si no existe (o falla la consulta), redirige a un
     * placeholder local — así nunca quedan íconos rotos cuando la empresa no
     * tiene logo cargado todavía.
     */
    public function show(string $folder, string $filename, ContaboS3Service $s3): RedirectResponse
    {
        $cached = Cache::get(self::cacheKey($folder, $filename));

        if ($cached === null) {
            $cached = $this->resolve($s3, $folder, $filename);
        }

        if ($cached === self::MISSING_SENTINEL) {
            return redirect()->away(asset('images/'.$this->fallbackFor($filename)), 302);
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

    private function fallbackFor(string $filename): string
    {
        $base = strtolower(basename($filename));
        if (strpos($base, 'favicon') !== false) {
            return 'favicon2.png';
        }
        return 'logo1.png';
    }
}
