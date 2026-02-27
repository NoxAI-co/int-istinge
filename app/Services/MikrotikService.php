<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use App\Mikrotik;

// Include the local RouterOS API class
include_once(app_path() . '/../public/routeros_api.class.php');
use RouterosAPI;

class MikrotikService
{
    protected $client;

    public function __construct()
    {
        // Constructor no longer needs to setup default config from .env
    }

    /**
     * Connect to Mikrotik
     * 
     * @param int $mikrotikId
     * @return void
     * @throws Exception
     */
    protected function connect($mikrotikId)
    {
        try {
            $mikrotik = Mikrotik::find($mikrotikId);

            if (!$mikrotik) {
                throw new Exception('Mikrotik no encontrado');
            }

            $this->client = new RouterosAPI();
            $this->client->port = (int) $mikrotik->puerto_api;
            
            // Optional: Enable debug if needed
            // $this->client->debug = config('app.debug');

            if (!$this->client->connect($mikrotik->ip, $mikrotik->usuario, $mikrotik->clave)) {
                 throw new Exception('No se pudo conectar al Mikrotik: ' . $mikrotik->ip);
            }

        } catch (Exception $e) {
            Log::error('Mikrotik Connection Error: ' . $e->getMessage());
            throw new Exception('Error conectando con MikroTik: ' . $e->getMessage());
        }
    }

    /**
     * Get morosos list from Mikrotik
     * 
     * @param int $mikrotikId
     * @return array
     */
    public function getMorosos($mikrotikId)
    {
        try {
            $this->connect($mikrotikId);

            // Default list name, could also be made dynamic if needed in future
            $listName = env('MIKROTIK_LIST_NAME', 'morosos');

            // Use the comm method from RouterosAPI
            $response = $this->client->comm('/ip/firewall/address-list/print', [
                "?list" => $listName
            ]);
            
            $this->client->disconnect();

            return $this->formatResponse($response, $listName);

        } catch (Exception $e) {
            Log::error('Mikrotik Query Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Remove IP from morosos address-list
     * 
     * @param int $mikrotikId
     * @param string $ip
     * @return bool
     */
    public function removerMoroso($mikrotikId, $ip)
    {
        try {
            $this->connect($mikrotikId);
            $listName = 'morosos'; // User code used hardcoded 'morosos', sticking to it or env if user prefers, but user showed 'morosos'

            // Lógica solicitada por el usuario (RAW API)
            $this->client->write('/ip/firewall/address-list/print', false);
            $this->client->write('?address='.$ip, false);
            $this->client->write("?list=".$listName, false);
            $this->client->write('=.proplist=.id');
            $ARRAYS = $this->client->read();

            if(count($ARRAYS)>0){
                $this->client->write('/ip/firewall/address-list/remove', false);
                $this->client->write('=.id='.$ARRAYS[0]['.id']);
                $this->client->read();
            }
            
            $this->client->disconnect();
            return true;

        } catch (Exception $e) {
            Log::error('Mikrotik remove moroso error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Add IP to morosos address-list
     * 
     * @param int $mikrotikId
     * @param string $ip
     * @param string $comment
     * @return bool
     */
    public function agregarMoroso($mikrotikId, $ip, $comment)
    {
        try {
            $this->connect($mikrotikId);
            $listName = env('MIKROTIK_LIST_NAME', 'morosos');

            // Verificar si ya existe
            $exists = $this->client->comm('/ip/firewall/address-list/print', [
                "?address" => $ip,
                "?list" => $listName
            ]);

            if (empty($exists)) {
                $this->client->comm('/ip/firewall/address-list/add', [
                    "address" => $ip,
                    "list" => $listName,
                    "comment" => $comment
                ]);
            }
            
            return true;
        } catch (Exception $e) {
            Log::error('Mikrotik add moroso error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Add IP to morosos address-list (Direct Logic requested by user)
     * 
     * @param int $mikrotikId
     * @param string $ip
     * @param string $comment
     * @return bool
     */
    public function agregarMorosoDirecto($mikrotikId, $ip, $comment)
    {
        try {
            $this->connect($mikrotikId);
            
            // Lógica solicitada por el usuario: Directamente agregar sin verificar
            $this->client->comm("/ip/firewall/address-list/add", array(
                "address" => $ip,
                "comment" => $comment,
                "list" => 'morosos'
            ));
            
            $this->client->disconnect();
            return true;

        } catch (Exception $e) {
            Log::error('Mikrotik add moroso direct error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Format the response from Mikrotik
     * 
     * @param array $data
     * @param string $listName
     * @return array
     */
    protected function formatResponse($data, $listName)
    {
        if (empty($data) || isset($data['!trap'])) {
             // Handle errors returned by the API
             if (isset($data['!trap'])) {
                 Log::error('Mikrotik API Error: ' . json_encode($data));
             }
            return [
                'success' => true,
                'total' => 0,
                'data' => []
            ];
        }

        $formatted = [];
        foreach ($data as $item) {
            if (isset($item['list']) && $item['list'] == $listName) {
                
                $ip = $item['address'] ?? null;
                $contratoData = null;
                $ultimaFacturaData = null;
                $mensajeDiscrepancia = null;
                $estadoSistema = 'No encontrado'; // Default
                $tieneDiscrepancia = false;

                if ($ip) {
                    // Buscar contrato activo por IP
                    // Se asume que la IP es única para contratos activos o se toma el primero encontrado
                    $contrato = \App\Contrato::where('ip', $ip)->where('status', 1)->first();

                    if ($contrato) {
                        $contratoData = [
                            'nro' => $contrato->nro,
                            'id' => $contrato->id,
                            'nombre_cliente' => $contrato->cliente()->nombre ?? 'N/A'
                        ];

                        // Obtener la última factura del contrato
                        // Usando la relación definida en el modelo o query manual si fuera necesario
                        // El usuario especificó: "pasar por la tabla facturas_contratos"
                        // El modelo Contrato tiene la relación facturas() mapeada a facturas_contratos
                        
                        $ultimaFactura = $contrato->facturas()->orderBy('created_at', 'desc')->first();

                        if ($ultimaFactura) {
                            $ultimaFacturaData = [
                                'id' => $ultimaFactura->id,
                                'codigo' => $ultimaFactura->codigo,
                                'estatus' => $ultimaFactura->estatus
                            ];

                            $estadoString = $ultimaFactura->estatus();

                            // Dependemos del método estatus() de Factura que retorna el estado real como string.
                            // Posibles retornos: 'Abierta', 'Cerrada', 'Anulada', 'Abonada', 'Cerrada con nota crédito', etc.
                            if ($estadoString === 'Cerrada' || $estadoString === 'Cerrada con nota crédito') { // Pagada
                                $tieneDiscrepancia = true;
                                $estadoSistema = 'Pagada';
                                $mensajeDiscrepancia = "El cliente aparece en morosos (Mikrotik) pero su última factura en el sistema figura como PAGADA/CERRADA.";
                            } else if ($estadoString === 'Anulada') { // Anulada
                                $estadoSistema = 'Anulada';
                                $tieneDiscrepancia = false;
                            } else {
                                // 'Abierta', 'Abonada', 'Abierta con nota crédito' son considerados con deuda
                                $estadoSistema = 'En Mora'; 
                            }
                        } else {
                            $estadoSistema = 'Sin Facturas';
                        }
                    }
                }

                 $formatted[] = [
                    'id' => $item['.id'] ?? null,
                    'ip' => $ip,
                    'comentario' => $item['comment'] ?? '',
                    'fecha_creacion' => $item['creation-time'] ?? null,
                    
                    // Datos Sistema
                    'contrato' => $contratoData,
                    'ultima_factura' => $ultimaFacturaData,
                    'estado_sistema' => $estadoSistema,
                    'tiene_discrepancia' => $tieneDiscrepancia,
                    'mensaje_discrepancia' => $mensajeDiscrepancia
                ];
            }
        }

        return [
            'success' => true,
            'total' => count($formatted),
            'data' => $formatted
        ];
    }
}
