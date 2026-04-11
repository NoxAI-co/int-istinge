<?php
namespace App\Services;

use App\Models\ApiToken;
use App\Traits\ConsumesExternalServices;
use GuzzleHttp\Exception\ClientException;

class BTWService
{
    use ConsumesExternalServices;

    protected $baseUri;
    protected $secretKey;
    protected $options;

    public function __construct()
    {
        $this->baseUri = env("BTW_TEST_MODE") == 1 ? env('BTW_URL_TEST') : env('BTW_URL_PROD');
        $this->secretKey =  env("BTW_TEST_CREDENTIAL");
        $this->options = [
            'timeout' => 90,
            'connect_timeout' => 90,
        ];
    }

    public function resolveAuthorization(&$queryParams, &$formParams, &$headers)
    {
        $headers["Authorization"] = "Bearer " . $this->secretKey;
    }

    public function decodeResponse($response)
    {
        return json_decode($response);
    }


    public function dianAuthorization(array $data)
    {
        return $this->makeRequest('POST', '/sendDianAuthorization', [], $data, [], true);
    }

    public function saveResolution(array $data)
    {
        try {
            return $this->makeRequest('POST', '/api/resolution', [], $data, [], true);
        } catch (\Throwable $th) {
            return response()->json([
                'statusCode' => 400,
                'errorMessage' => "Error al guardar la resolución",
                'th' => $th
            ]);
        }
    }

    public function updateResolution(array $data, $resolutionId)
    {
        try {
            return $this->makeRequest('PUT', '/api/resolution/' . $resolutionId, [], $data, [], true);
        } catch (\Throwable $th) {
            return response()->json([
                'statusCode' => 400,
                'errorMessage' => "Error al actualizar la resolución",
                'th' => $th
            ]);
        }
    }

    public function deleteResolution($resolutionId)
    {
        try {
            return $this->makeRequest('DELETE', '/api/resolution/' . $resolutionId, [], [], [], true);
        } catch (\Throwable $th) {
            return response()->json([
                'statusCode' => 400,
                'errorMessage' => "Error al eliminar la resolución",
                'th' => $th
            ]);
        }
    }

    public function sendInvoiceBTW($json){
        try {
            return $this->makeRequest('POST', '/api/sendinvoice', [], $json, [], true);
        } catch (\Throwable $th) {

            return ["statusCode" => 400, "errorMessage" => "Error al enviar la factura", "th" => ["message" => $th->getMessage()]];        }
    }

    public function sendPOSInvoiceBTW($json){
        try {
            return $this->makeRequest('POST', '/api/sendpos', [], $json, [], true);
        } catch (\Throwable $th) {

            return response()->json([
                'statusCode' => 400,
                'errorMessage' => "Error al enviar la factura",
                'th' => $th
            ]);
        }
    }



    public function sendDocumentBTW($json){
        try {
            return $this->makeRequest('POST', 'api/senddocument', [], $json, [], true);
        } catch (\Throwable $th) {
            return response()->json([
                'statusCode' => 400,
                'errorMessage' => "Error al enviar la factura",
                'th' => $th
            ]);
        }
    }

    public function sendPayroll($json){
        try {
            return $this->makeRequest('POST', 'api/sendpayroll', [], $json, [], true);
        } catch (\Throwable $th) {
            return response()->json([
                'statusCode' => 400,
                'errorMessage' => "Error al enviar la nómina",
                'th' => $th
            ]);
        }
    }

    public function sendPdfEmail($data)
    {
        try {

            return $this->makeRequest(
                'POST',
                '/api/sendpdfemail',
                ['Content-Type' => 'application/json'], // headers
                $data,                              // body en JSON
                [],                                     // query params
                true                                    // auth si es necesario
            );

        } catch (\Throwable $th) {
            return [
                'status'  => 'error',
                'message' => 'Error al enviar la factura',
                'error'   => $th->getMessage(),
            ];
        }
    }

    public function downloadXML($data)
    {
        try {
            return $this->makeRequest(
                'POST',
                'api/downloadxml',
                ['Content-Type' => 'application/json'],
                $data,
                [],
                true
            );
        } catch (\Throwable $th) {
            return response()->json([
                'statusCode' => 400,
                'errorMessage' => "Error al descargar el xml",
                'th' => $th
            ]);
        }
    }



}
