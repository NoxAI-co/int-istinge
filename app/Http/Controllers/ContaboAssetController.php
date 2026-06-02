<?php

namespace App\Http\Controllers;

use App\Services\ContaboS3Service;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ContaboAssetController extends Controller
{
    /**
     * Devuelve un redirect 302 a una URL firmada de Contabo. Permite que las
     * vistas (login, favicon, etc.) usen una URL estable de la app y que el
     * browser sea el que vaya a buscar el archivo a Contabo con la URL firmada.
     */
    public function show(string $folder, string $filename, ContaboS3Service $s3): RedirectResponse
    {
        return redirect()->away($s3->signedUrl($folder, $filename), 302);
    }
}
