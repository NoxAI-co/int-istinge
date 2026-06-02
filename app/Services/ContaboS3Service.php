<?php

namespace App\Services;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class ContaboS3Service
{
    /** @var S3Client|null */
    protected $client;

    /** @var string Nombre corto del cliente actual (prefijo en el bucket). */
    protected $cliente;

    public function __construct()
    {
        $this->cliente = (string) env('CLIENTE', '');
        if ($this->cliente === '') {
            throw new RuntimeException('Falta CLIENTE en el .env. ContaboS3Service necesita saber bajo qué prefijo guardar los archivos.');
        }
    }

    public function client(): S3Client
    {
        if ($this->client) {
            return $this->client;
        }

        $cfg = config('contabo');

        return $this->client = new S3Client([
            'version'                 => 'latest',
            'region'                  => $cfg['region'],
            'endpoint'                => $cfg['endpoint'],
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key'    => $cfg['key'],
                'secret' => $cfg['secret'],
            ],
        ]);
    }

    public function bucket(): string
    {
        return config('contabo.bucket');
    }

    public function cliente(): string
    {
        return $this->cliente;
    }

    /**
     * Construye la key completa en el bucket. Ej: "cablenet/logos/login.png".
     * $folder es la subcarpeta (típicamente env('LOGOS_FOLDER'), 'adjuntos', etc.).
     */
    public function key(string $folder, string $filename = ''): string
    {
        $folder   = trim($folder, '/');
        $filename = ltrim($filename, '/');
        $base     = $this->cliente . '/' . $folder;
        return $filename === '' ? $base . '/' : $base . '/' . $filename;
    }

    public function exists(string $folder, string $filename): bool
    {
        try {
            return $this->client()->doesObjectExist($this->bucket(), $this->key($folder, $filename));
        } catch (S3Exception $e) {
            return false;
        }
    }

    /**
     * Devuelve una URL firmada de lectura. $minutes = TTL; null usa config('contabo.url_ttl').
     */
    public function signedUrl(string $folder, string $filename, ?int $minutes = null): string
    {
        $minutes = $minutes ?? (int) config('contabo.url_ttl', 60);
        $cmd = $this->client()->getCommand('GetObject', [
            'Bucket' => $this->bucket(),
            'Key'    => $this->key($folder, $filename),
        ]);
        return (string) $this->client()->createPresignedRequest($cmd, "+{$minutes} minutes")->getUri();
    }

    /**
     * Sube un archivo. Acepta UploadedFile (Laravel) o ruta absoluta a un archivo del disco.
     * Devuelve la key final dentro del bucket.
     */
    public function upload(string $folder, $file, ?string $filename = null, string $acl = 'private'): string
    {
        if ($file instanceof UploadedFile) {
            $stream     = fopen($file->getRealPath(), 'rb');
            $filename   = $filename ?: $file->getClientOriginalName();
            $mime       = $file->getMimeType() ?: 'application/octet-stream';
        } elseif (is_string($file) && is_file($file)) {
            $stream     = fopen($file, 'rb');
            $filename   = $filename ?: basename($file);
            $mime       = function_exists('mime_content_type') ? (mime_content_type($file) ?: 'application/octet-stream') : 'application/octet-stream';
        } else {
            throw new RuntimeException('upload(): $file debe ser UploadedFile o ruta a un archivo existente.');
        }

        $this->ensureClientFolderExists($folder);

        $key = $this->key($folder, $filename);
        $this->client()->putObject([
            'Bucket'      => $this->bucket(),
            'Key'         => $key,
            'Body'        => $stream,
            'ContentType' => $mime,
            'ACL'         => $acl,
        ]);

        if (is_resource($stream)) {
            fclose($stream);
        }
        return $key;
    }

    public function delete(string $folder, string $filename): bool
    {
        try {
            $this->client()->deleteObject([
                'Bucket' => $this->bucket(),
                'Key'    => $this->key($folder, $filename),
            ]);
            return true;
        } catch (S3Exception $e) {
            return false;
        }
    }

    /**
     * Lista objetos bajo CLIENTE/folder/. Devuelve un array de keys completas.
     */
    public function list(string $folder): array
    {
        $prefix = $this->key($folder);
        $out    = [];
        $result = $this->client()->listObjectsV2([
            'Bucket' => $this->bucket(),
            'Prefix' => $prefix,
        ]);
        foreach ($result['Contents'] ?? [] as $obj) {
            if (substr($obj['Key'], -1) === '/') {
                continue;
            }
            $out[] = $obj['Key'];
        }
        return $out;
    }

    /**
     * S3 no tiene "carpetas" reales; pero podemos crear un objeto vacío con clave
     * "CLIENTE/folder/" para que aparezca en la UI de Contabo y para asegurar que
     * el prefijo está reservado antes de empezar a subir archivos.
     */
    public function ensureClientFolderExists(string $folder): void
    {
        $key = $this->key($folder);
        if ($this->client()->doesObjectExist($this->bucket(), $key)) {
            return;
        }
        $this->client()->putObject([
            'Bucket' => $this->bucket(),
            'Key'    => $key,
            'Body'   => '',
        ]);
    }
}
