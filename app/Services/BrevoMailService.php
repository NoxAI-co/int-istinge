<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class BrevoMailService
{
    /**
     * API Key de Brevo (se almacena en el campo 'password' de ServidorCorreo)
     */
    protected $apiKey;

    /**
     * URL base de la API transaccional de Brevo
     */
    protected $apiUrl = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Enviar un correo transaccional a través de la API de Brevo
     *
     * @param array|string $to        Destinatario(s) - email o array de emails
     * @param string       $subject   Asunto del correo
     * @param string       $htmlContent Contenido HTML del correo
     * @param string       $senderName  Nombre del remitente
     * @param string       $senderEmail Email del remitente
     * @param array        $attachments Adjuntos [['name' => 'file.pdf', 'content' => 'base64...']]
     * @return array       Resultado con 'success' y 'message'
     */
    public function send($to, string $subject, string $htmlContent, string $senderName, string $senderEmail, array $attachments = []): array
    {
        // Normalizar destinatarios
        if (!is_array($to)) {
            $to = [$to];
        }

        $recipients = [];
        foreach ($to as $email) {
            if (!empty($email)) {
                $recipients[] = ['email' => trim($email)];
            }
        }

        if (empty($recipients)) {
            return [
                'success' => false,
                'message' => 'No se encontraron destinatarios válidos'
            ];
        }

        $payload = [
            'sender'      => [
                'name'  => $senderName,
                'email' => $senderEmail,
            ],
            'to'          => $recipients,
            'subject'     => $subject,
            'htmlContent' => '<html>' . $htmlContent . '</html>',
        ];

        // Agregar adjuntos si existen
        if (!empty($attachments)) {
            $payload['attachment'] = $attachments;
        }

        try {
            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL            => $this->apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_HTTPHEADER     => [
                    'accept: application/json',
                    'api-key: ' . $this->apiKey,
                    'content-type: application/json',
                ],
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                Log::error('Brevo cURL error: ' . $curlError);
                return [
                    'success' => false,
                    'message' => 'Error de conexión con Brevo: ' . $curlError
                ];
            }

            $responseData = json_decode($response, true);

            if ($httpCode >= 200 && $httpCode < 300) {
                Log::info('Brevo email enviado exitosamente', [
                    'to'      => array_column($recipients, 'email'),
                    'subject' => $subject,
                    'messageId' => $responseData['messageId'] ?? null,
                ]);

                return [
                    'success'   => true,
                    'message'   => 'Correo enviado exitosamente vía Brevo',
                    'messageId' => $responseData['messageId'] ?? null,
                ];
            }

            $errorMsg = $responseData['message'] ?? ($responseData['code'] ?? 'Error desconocido');
            Log::error('Brevo API error', [
                'httpCode' => $httpCode,
                'response' => $responseData,
                'to'       => array_column($recipients, 'email'),
            ]);

            return [
                'success' => false,
                'message' => 'Error de Brevo: ' . $errorMsg
            ];

        } catch (\Exception $e) {
            Log::error('Brevo exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Error al enviar con Brevo: ' . $e->getMessage()
            ];
        }
    }
}
