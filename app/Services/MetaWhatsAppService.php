<?php

namespace App\Services;

use App\Traits\ConsumesExternalServices;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Instance;

class MetaWhatsAppService
{
    use ConsumesExternalServices;

    protected $baseUri;
    protected $accessToken;
    protected $apiVersion;

    public function __construct()
    {
        $this->apiVersion = config('services.meta.api_version', 'v25.0');
        $this->baseUri = "https://graph.facebook.com/{$this->apiVersion}";
        $this->accessToken = config('services.meta.access_token');
    }

    /**
     * Send a text message
     */
    public function sendMessage(string $phoneNumberId, string $to, string $message)
    {
        return $this->sendRequest($phoneNumberId, [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'text',
            'text'              => [
                'preview_url' => true,
                'body'        => $message
            ]
        ]);
    }

    /**
     * Send a template message
     */
    public function sendTemplate(string $phoneNumberId, string $to, string $templateName, string $languageCode = 'es', array $components = [])
    {
        $components = $this->sanitizeTemplateComponents($components);

        return $this->sendRequest($phoneNumberId, [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'template',
            'template'          => [
                'name'     => $templateName,
                'language' => [
                    'code' => $languageCode
                ],
                'components' => $components
            ]
        ]);
    }

    /**
     * Send an image
     */
    public function sendImage(string $phoneNumberId, string $to, string $imageUrl, string $caption = '')
    {
        return $this->sendRequest($phoneNumberId, [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'image',
            'image'             => [
                'link'    => $imageUrl,
                'caption' => $caption
            ]
        ]);
    }

    /**
     * Send a document
     */
    public function sendDocument(string $phoneNumberId, string $to, string $documentUrl, string $filename = '')
    {
        return $this->sendRequest($phoneNumberId, [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'document',
            'document'          => [
                'link'     => $documentUrl,
                'filename' => $filename
            ]
        ]);
    }

    /**
     * Send an audio file
     */
    public function sendAudio(string $phoneNumberId, string $to, string $audioUrl)
    {
        return $this->sendRequest($phoneNumberId, [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'audio',
            'audio'             => [
                'link' => $audioUrl
            ]
        ]);
    }

    /**
     * Send a video
     */
    public function sendVideo(string $phoneNumberId, string $to, string $videoUrl, string $caption = '')
    {
        return $this->sendRequest($phoneNumberId, [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'video',
            'video'             => [
                'link'    => $videoUrl,
                'caption' => $caption
            ]
        ]);
    }

    /**
     * Mark message as read
     */
    public function markAsRead(string $phoneNumberId, string $messageId)
    {
        return $this->sendRequest($phoneNumberId, [
            'messaging_product' => 'whatsapp',
            'status'            => 'read',
            'message_id'        => $messageId
        ]);
    }

    /**
     * Download media from WhatsApp
     */
    public function downloadMedia(string $mediaId)
    {
        try {
            // Paso 1: Obtener URL del media
            $url = "{$this->baseUri}/{$mediaId}";
            $response = Http::withToken($this->accessToken)->get($url);

            if (!$response->successful()) {
                Log::error('Error getting media URL', [
                    'media_id' => $mediaId,
                    'response' => $response->json()
                ]);
                return null;
            }

            $mediaData = $response->json();
            $mediaUrl = $mediaData['url'];
            $mimeType = $mediaData['mime_type'];

            // Paso 2: Descargar el archivo
            $mediaResponse = Http::withToken($this->accessToken)->get($mediaUrl);

            if (!$mediaResponse->successful()) {
                Log::error('Error downloading media file', [
                    'media_url' => $mediaUrl
                ]);
                return null;
            }

            // Generar nombre único
            $extension = $this->getExtensionFromMime($mimeType);
            $filename = uniqid('wa_') . '_' . time() . '.' . $extension;
            $path = "whatsapp/media/{$filename}";

            // Guardar en storage
            Storage::disk('public')->put($path, $mediaResponse->body());

            return [
                'filename'  => $filename,
                'path'      => $path,
                'url'       => Storage::disk('public')->url($path),
                'mime_type' => $mimeType,
                'size'      => strlen($mediaResponse->body())
            ];

        } catch (\Exception $e) {
            Log::error('Exception downloading media', [
                'media_id' => $mediaId,
                'error'    => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get extension from MIME type
     */
    private function getExtensionFromMime(string $mimeType): string
    {
        $mimeMap = [
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/gif'       => 'gif',
            'image/webp'      => 'webp',
            'audio/ogg'       => 'ogg',
            'audio/mpeg'      => 'mp3',
            'audio/amr'       => 'amr',
            'audio/mp4'       => 'm4a',
            'video/mp4'       => 'mp4',
            'video/3gpp'      => '3gp',
            'application/pdf' => 'pdf',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'text/plain'      => 'txt',
        ];

        return $mimeMap[$mimeType] ?? 'bin';
    }

    /**
     * Generic method to send requests to the Messages endpoint
     */
    protected function sendRequest(string $phoneNumberId, array $data)
    {
        try {
            $url = "{$this->baseUri}/{$phoneNumberId}/messages";

            $response = Http::withToken($this->accessToken)
                ->timeout(30)
                ->post($url, $data);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data'    => $response->json()
                ];
            }

            Log::error('WhatsApp API Error', [
                'url'      => $url,
                'data'     => $data,
                'status'   => $response->status(),
                'response' => $response->json()
            ]);

            return [
                'success' => false,
                'error'   => $response->json()
            ];

        } catch (\Exception $e) {
            Log::error('WhatsApp API Exception', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error'   => $e->getMessage()
            ];
        }
    }

    /**
     * Validate webhook signature
     */
    public function validateWebhookSignature(string $payload, string $signature): bool
    {
        $appSecret = config('services.meta.app_secret');
        
        if (empty($appSecret)) {
            Log::warning('App secret not configured for webhook validation');
            return true; // Por seguridad, podrías retornar false aquí
        }

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $appSecret);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Sanitize template components to comply with Meta API restrictions:
     * - No new-line/tab characters
     * - No more than 4 consecutive spaces
     */
    private function sanitizeTemplateComponents(array $components): array
    {
        foreach ($components as &$component) {
            if (isset($component['parameters']) && is_array($component['parameters'])) {
                foreach ($component['parameters'] as &$parameter) {
                    if (isset($parameter['type']) && $parameter['type'] === 'text' && isset($parameter['text'])) {
                        // 1. Remove newlines and tabs (replace with a space)
                        $text = str_replace(["\n", "\r", "\t"], " ", strval($parameter['text']));
                        
                        // 2. Replace 2 or more consecutive spaces with a single space
                        // This handles the "more than 4 consecutive spaces" restriction proactively
                        $text = preg_replace('/ {2,}/', ' ', $text);
                        
                        $parameter['text'] = trim($text);
                    }
                }
            }
        }
        return $components;
    }
}
