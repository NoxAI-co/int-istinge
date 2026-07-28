<?php

namespace App\Services;

use App\Contrato;
use App\PlanesVelocidad;
use DB;
use Illuminate\Support\Facades\Log;

include_once(app_path() . '/../public/routeros_api.class.php');

/**
 * Envía (sincroniza) UN contrato a una MikroTik reutilizando una conexión ya
 * abierta (\RouterosAPI). Es la misma secuencia que ContratosController::enviar_mk_lote
 * (limpiar → recrear queue/secret/lease/arp → ips_autorizadas), extraída para que
 * el módulo de Sincronización Masiva pueda reutilizar UNA sola conexión por tanda
 * (mucho más rápido y estable que abrir una conexión por contrato).
 *
 * NO conecta ni desconecta: el llamador administra la conexión.
 */
class MikrotikContratoSyncService
{
    /**
     * @param  \App\Contrato  $contrato
     * @param  object         $mikrotik  Fila/modelo mikrotik (id, amarre_mac, ...).
     * @param  \RouterosAPI   $API       Conexión YA abierta a la MikroTik.
     * @param  int|null       $userId
     * @param  int            $empresaId
     * @return array{ok: bool, mensaje: string}
     */
    public function enviar(Contrato $contrato, $mikrotik, $API, $userId, $empresaId)
    {
        if (empty($contrato->ip)) {
            return ['ok' => false, 'mensaje' => 'Contrato sin IP asignada'];
        }

        $plan = PlanesVelocidad::where('id', $contrato->plan_id)->first();
        if (! $plan) {
            return ['ok' => false, 'mensaje' => 'Sin plan asignado'];
        }

        try {
            $cliente  = $contrato->cliente();
            $servicio = $cliente
                ? trim($cliente->nombre . ' ' . ($cliente->apellido1 ?? '') . ' ' . ($cliente->apellido2 ?? ''))
                : 'Servicio';
            $nombreCola = $this->normaliza($servicio) . '-' . $contrato->nro;

            // ── 1. Remover configuración previa (idempotencia) ──────────────────
            if ($contrato->conexion == 1) {
                $mkUser = $API->comm('/ppp/secret/getall', ['?remote-address' => $contrato->ip]);
                if ($mkUser) {
                    $API->comm('/ppp/secret/remove', ['.id' => $mkUser[0]['.id']]);
                }
            }
            if ($contrato->conexion == 2) {
                $lease = $API->comm('/ip/dhcp-server/lease/getall', ['?address' => $contrato->ip]);
                if ($lease) {
                    $API->comm('/ip/dhcp-server/lease/remove', ['.id' => $lease[0]['.id']]);
                }
            }
            if ($contrato->conexion == 3) {
                $arp = $API->comm('/ip/arp/getall', ['?address' => $contrato->ip]);
                if ($arp) {
                    $API->comm('/ip/arp/remove', ['.id' => $arp[0]['.id']]);
                }
            }

            // Todas las colas de esa IP (no solo la primera): evita duplicados.
            $queues = $API->comm('/queue/simple/getall', ['?target' => $contrato->ip . '/32']);
            if (is_array($queues)) {
                foreach ($queues as $q) {
                    if (isset($q['.id'])) {
                        $API->comm('/queue/simple/remove', ['.id' => $q['.id']]);
                    }
                }
            }

            $arrays = $API->comm('/ip/firewall/address-list/getall', [
                '?address' => $contrato->ip,
                '?list'    => 'ips_autorizadas',
            ]);
            if (is_array($arrays) && count($arrays) > 0) {
                $API->comm('/ip/firewall/address-list/remove', ['.id' => $arrays[0]['.id']]);
            }

            // ── 2. Armar rate-limit ────────────────────────────────────────────
            // priority por defecto 8: RouterOS RECHAZA /queue/simple/add con
            // priority vacío y el fallo pasa desapercibido (queue nunca se crea).
            $priority        = (isset($plan->prioridad) && !empty($plan->prioridad)) ? $plan->prioridad : 8;
            $burst_limit     = (strlen((string) $plan->burst_limit_subida) > 1) ? $plan->burst_limit_subida . '/' . $plan->burst_limit_bajada : 0;
            $burst_threshold = (strlen((string) $plan->burst_threshold_subida) > 1) ? $plan->burst_threshold_subida . '/' . $plan->burst_threshold_bajada : 0;
            $burst_time      = ($plan->burst_time_subida) ? $plan->burst_time_subida . '/' . $plan->burst_time_bajada : 0;
            $limit_at        = (strlen((string) $plan->limit_at_subida) > 1) ? $plan->limit_at_subida . '/' . $plan->limit_at_bajada : 0;

            $rate_limit = $plan->upload . '/' . $plan->download;
            if (strlen((string) $burst_limit) > 3)     { $rate_limit .= ' ' . $burst_limit; }
            if (strlen((string) $burst_threshold) > 3) { $rate_limit .= ' ' . $burst_threshold; }
            if (strlen((string) $burst_time) > 3)      { $rate_limit .= ' ' . $burst_time; }
            if ($priority)                             { $rate_limit .= ' ' . $priority; }
            if ($limit_at)                             { $rate_limit .= ' ' . $limit_at; }

            // ── 3. Crear configuración nueva ───────────────────────────────────
            /* PPPOE */
            if ($contrato->conexion == 1) {
                $API->comm('/ppp/secret/add', [
                    'name'           => $contrato->usuario,
                    'password'       => $contrato->password,
                    'profile'        => 'default',
                    'local-address'  => $contrato->ip,
                    'remote-address' => $contrato->ip,
                    'service'        => 'pppoe',
                    'comment'        => $nombreCola,
                ]);
            }

            /* DHCP */
            if ($contrato->conexion == 2) {
                if (! isset($plan->dhcp_server) || ! $plan->dhcp_server) {
                    return ['ok' => false, 'mensaje' => 'El plan ' . $plan->name . ' no tiene servidor DHCP definido'];
                }
                if ($contrato->simple_queue == 'dinamica') {
                    $API->comm("/ip/dhcp-server/set\n=name=" . $plan->dhcp_server . "\n=address-pool=static-only\n=parent-queue=" . $plan->parenta);
                    $API->comm('/ip/dhcp-server/lease/add', [
                        'comment'     => $nombreCola,
                        'address'     => $contrato->ip,
                        'server'      => $plan->dhcp_server,
                        'mac-address' => $contrato->mac_address,
                        'rate-limit'  => $rate_limit,
                    ]);
                } else {
                    $API->comm('/ip/dhcp-server/lease/add', [
                        'comment'     => $nombreCola,
                        'address'     => $contrato->ip,
                        'server'      => $plan->dhcp_server,
                        'mac-address' => $contrato->mac_address,
                    ]);
                }
            }

            /* IP ESTÁTICA */
            if ($contrato->conexion == 3 && $mikrotik->amarre_mac == 1) {
                $API->comm('/ip/arp/add', [
                    'comment'     => $nombreCola,
                    'address'     => $contrato->ip,
                    'interface'   => $contrato->interfaz,
                    'mac-address' => $contrato->mac_address,
                ]);
            }

            // Queue: en DHCP dinámica el rate-limit viaja en el lease, no en queue simple.
            if (! ($contrato->conexion == 2 && $contrato->simple_queue == 'dinamica')) {
                $queueData = [
                    'name'            => $nombreCola,
                    'target'          => $contrato->ip,
                    'max-limit'       => $plan->upload . '/' . $plan->download,
                    'burst-limit'     => $burst_limit,
                    'burst-threshold' => $burst_threshold,
                    'burst-time'      => $burst_time,
                    'priority'        => $priority,
                    'limit-at'        => $limit_at,
                ];

                if ($contrato->conexion == 3) {
                    $queueData['queue'] = (!empty($plan->queue_type_subida) && !empty($plan->queue_type_bajada))
                        ? $plan->queue_type_subida . '/' . $plan->queue_type_bajada
                        : 'default-small/default-small';
                }

                $res = $API->comm('/queue/simple/add', $queueData);
                if (is_array($res) && isset($res[0]['!trap'])) {
                    $motivo = $res[0]['message'] ?? 'RouterOS rechazó la cola';
                    return ['ok' => false, 'mensaje' => 'queue/simple/add: ' . $motivo];
                }
            }

            /* IPS AUTORIZADAS */
            $API->comm('/ip/firewall/address-list/add', [
                'address' => $contrato->ip,
                'list'    => 'ips_autorizadas',
            ]);

            // ── 4. Marcar el contrato como enviado ─────────────────────────────
            $contrato->mk = 1;
            $contrato->state = 'enabled';
            $contrato->servicio = $nombreCola;
            $contrato->server_configuration_id = $mikrotik->id;
            $contrato->save();

            $this->log(
                $contrato->id,
                $empresaId,
                $userId,
                '<i class="fas fa-check text-success"></i> <b>Enviado a MikroTik</b> exitosamente (sincronización masiva).'
            );

            return ['ok' => true, 'mensaje' => 'Sincronizado'];
        } catch (\Throwable $e) {
            Log::error('MikrotikContratoSyncService: contrato ' . $contrato->id . ' -> ' . $e->getMessage());

            return ['ok' => false, 'mensaje' => substr($e->getMessage(), 0, 250)];
        }
    }

    /** Nombres de colas/comentarios sin tildes ni caracteres raros (igual que ContratosController). */
    private function normaliza($cadena)
    {
        $cadena = trim($cadena);
        $cadena = str_replace(['á','à','ä','â','ª','Á','À','Â','Ä'], ['a','a','a','a','a','A','A','A','A'], $cadena);
        $cadena = str_replace(['é','è','ë','ê','É','È','Ê','Ë'], ['e','e','e','e','E','E','E','E'], $cadena);
        $cadena = str_replace(['í','ì','ï','î','Í','Ì','Ï','Î'], ['i','i','i','i','I','I','I','I'], $cadena);
        $cadena = str_replace(['ó','ò','ö','ô','Ó','Ò','Ö','Ô'], ['o','o','o','o','O','O','O','O'], $cadena);
        $cadena = str_replace(['ú','ù','ü','û','Ú','Ù','Û','Ü'], ['u','u','u','u','U','U','U','U'], $cadena);
        $cadena = str_replace(['ñ','Ñ','ç','Ç'], ['n','N','c','C'], $cadena);

        return $cadena;
    }

    private function log($contratoId, $empresaId, $userId, $descripcion)
    {
        try {
            DB::table('log_movimientos')->insert([
                'contrato'    => $contratoId,
                'modulo'      => 5,
                'descripcion' => $descripcion,
                // log_movimientos.created_by es NOT NULL en varias BDs: fallback a usuario sistema.
                'created_by'  => $userId ?: 1,
                'empresa'     => $empresaId,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // el log es best-effort; nunca debe tumbar la sincronización.
        }
    }
}
