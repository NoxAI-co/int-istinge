<?php

namespace App\Traits;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

trait ConsumesExternalServices
{

    public function makeRequest(string $method, string $requestUrl, array $queryParams = [], array $formParams = [], array $headers = [], bool $isJsonRequest = false)
    {

        try {
            if (!isset($this->options)) {
                $this->options = [];
            }

            $client = new Client(array_merge($this->options, [
                'base_uri' => $this->baseUri,
            ]));

            if (method_exists($this, 'resolveAuthorization')) {
                $this->resolveAuthorization($queryParams, $formParams, $headers);
            }

            // dd($requestUrl);
            // dd($this->baseUri . "/" .$requestUrl, $queryParams, $formParams, $headers, $isJsonRequest);

            $response = $client->request($method, $requestUrl, [
                $isJsonRequest ? 'json' : 'form_params' => $formParams,
                'headers' => $headers,
                'query' => $queryParams,
            ]);

            $response = $response->getBody()->getContents();

            if (method_exists($this, 'decodeResponse')) {
                $response =  $this->decodeResponse($response);
            }

            return $response;

        } catch (\Throwable $th) {
            $body = null;
            $tieneRespuesta = $th instanceof RequestException && $th->hasResponse();
            if ($tieneRespuesta) {
                $body = json_decode($th->getResponse()->getBody()->getContents(), true);
            }

            // Distinguir error de conexión (sin respuesta HTTP) de un rechazo del servidor.
            // Sin respuesta => problema de red/TLS/timeout, NO un rechazo DIAN.
            $esErrorConexion = !$tieneRespuesta;

            Log::error('ConsumesExternalServices error', [
                'url'              => $requestUrl,
                'exceptionClass'  => get_class($th),
                'code'            => $th->getCode(),
                'message'         => $th->getMessage(),
                'esErrorConexion' => $esErrorConexion,
                'body'            => $body,
            ]);

            return [
                'statusCode'      => $th->getCode() ?: 400,
                'errorMessage'    => "Error al realizar la petición",
                'esErrorConexion' => $esErrorConexion,
                'errorReal'       => $th->getMessage(),
                'th'              => $body,
            ];
        }
    }

    public function makeRequestTwo(string $method, string $requestUrl, array $queryParams = [], array $formParams = [], array $headers = [], bool $isJsonRequest = false)
    {

        if (!isset($this->options)) {
            $this->options = [];
        }

        $client = new Client(array_merge($this->options, [
            'base_uri' => $this->baseUri,
        ]));

        // dd($formParams);

        if (method_exists($this, 'resolveAuthorization')) {
            $this->resolveAuthorization($queryParams, $formParams, $headers);
        }

        // if($isJsonRequest){
        //     dd($this->baseUri,$queryParams,$formParams,$headers,$requestUrl);
        // }


        try {
            $response = $client->request($method, $requestUrl, [
                $isJsonRequest ? 'json' : 'form_params' => $formParams,
                'headers' => $headers,
                'query' => $queryParams,
            ]);
        } catch (\Throwable $th) {
            Log::error($th);
            return $response = array(
                'statusCode' => 400,
                'errorMessage' => "Error al realizar la petición",
                'th' => $th
            );
            // return $this->decodeResponse($response);
        }

        $response = $response->getBody()->getContents();

        if (method_exists($this, 'decodeResponse')) {
            $response =  $this->decodeResponse($response);
        }


        return $response;
    }
}