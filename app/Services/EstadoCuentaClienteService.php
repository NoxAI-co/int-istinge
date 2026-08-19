<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de cuenta de un conjunto de clientes: cartera, contratos y notas
 * crédito, resuelto en consultas AGREGADAS.
 *
 * Por qué existe: el reporte de contactos y su export necesitan lo mismo, y la
 * vía obvia —recorrer los clientes y llamar a los métodos del modelo— es un
 * N+1 brutal: Factura::total()/pagado()/devoluciones() son 3 consultas por
 * factura, así que un cliente con 24 facturas cuesta ~72 consultas. Con 3.000
 * clientes el export no termina.
 *
 * Aquí se hacen 3 consultas en total, sea cual sea la cantidad de clientes:
 * una de facturación, una de contratos y una de notas crédito. Siempre se
 * llama con un lote acotado de ids (la página, o un chunk del export).
 *
 * Las fórmulas son las mismas que usa el resto del sistema:
 *   total   = items con descuento e impuesto (idéntica al bloque 211 de fix-legacy)
 *   pagado  = ingresos no anulados + retenciones de esos ingresos
 *   notas   = notas crédito VIGENTES (nc.estatus = 1)
 *   saldo   = total − pagado − notas, con piso en 0
 */
class EstadoCuentaClienteService
{
    /** Total de la factura a partir de sus ítems (descuento + impuesto). */
    private const SQL_TOTAL_FACTURA = '(SELECT COALESCE(SUM((it.cant*it.precio)-(it.precio*(COALESCE(it.`desc`,0)/100)*it.cant)+(it.precio-(it.precio*(COALESCE(it.`desc`,0)/100)))*(it.impuesto/100)*it.cant),0) FROM items_factura it WHERE it.factura = f.id)';

    /** Pagos reales: ingresos no anulados + sus retenciones. */
    private const SQL_PAGADO_FACTURA = '((SELECT COALESCE(SUM(igf.pago),0) FROM ingresos_factura igf JOIN ingresos i ON i.id = igf.ingreso WHERE igf.factura = f.id AND i.estatus <> 2) + (SELECT COALESCE(SUM(ir.valor),0) FROM ingresos_retenciones ir JOIN ingresos i2 ON i2.id = ir.ingreso WHERE ir.factura = f.id AND i2.estatus <> 2))';

    /** Notas crédito vigentes aplicadas a la factura. */
    private const SQL_NOTAS_FACTURA = '(SELECT COALESCE(SUM(nf.pago),0) FROM notas_factura nf JOIN notas_credito nc ON nc.id = nf.nota WHERE nf.factura = f.id AND nc.estatus = 1)';

    /**
     * Devuelve, indexado por id de cliente, su estado de cuenta.
     *
     * @param  array<int>  $clienteIds
     * @return array<int, array<string, mixed>>
     */
    public function paraClientes(array $clienteIds, int $empresa): array
    {
        $clienteIds = array_values(array_unique(array_filter($clienteIds)));
        if (empty($clienteIds)) {
            return [];
        }

        $out = [];
        foreach ($clienteIds as $id) {
            $out[$id] = $this->vacio();
        }

        $this->agregarFacturacion($out, $clienteIds, $empresa);
        $this->agregarContratos($out, $clienteIds);
        $this->agregarNotasCredito($out, $clienteIds, $empresa);

        return $out;
    }

    /** Estructura por defecto: un cliente sin movimientos no debe romper el reporte. */
    private function vacio(): array
    {
        return [
            'facturas_total' => 0, 'facturas_abiertas' => 0,
            'facturado' => 0.0, 'pagado' => 0.0, 'notas_credito_aplicadas' => 0.0,
            'saldo' => 0.0, 'saldo_vencido' => 0.0, 'dias_mora' => 0,
            'primera_factura' => null, 'ultima_factura' => null,
            'ultimo_pago_fecha' => null, 'ultimo_pago_monto' => 0.0,
            'contratos_total' => 0, 'contratos_activos' => 0, 'contratos_cortados' => 0,
            'contratos_retirados' => 0, 'contratos_pausados' => 0, 'planes' => '',
            'nc_cantidad' => 0, 'nc_monto' => 0.0,
        ];
    }

    /** Cartera: facturado, pagado, acreditado, saldo y mora. */
    private function agregarFacturacion(array &$out, array $ids, int $empresa): void
    {
        $total = self::SQL_TOTAL_FACTURA;
        $pagado = self::SQL_PAGADO_FACTURA;
        $notas = self::SQL_NOTAS_FACTURA;
        // Saldo por factura, con piso en 0: un sobrepago en una factura no debe
        // "pagar" la deuda de otra, que es lo que pasaría al sumar negativos.
        $saldo = "GREATEST({$total} - {$pagado} - {$notas}, 0)";

        $rows = DB::table('factura as f')
            ->whereIn('f.cliente', $ids)
            ->where('f.empresa', $empresa)
            ->where('f.estatus', '<>', 2) // las anuladas no son cartera
            ->groupBy('f.cliente')
            ->select([
                'f.cliente',
                DB::raw('COUNT(*) AS facturas_total'),
                DB::raw('SUM(f.estatus = 1) AS facturas_abiertas'),
                DB::raw("ROUND(SUM({$total})) AS facturado"),
                DB::raw("ROUND(SUM({$pagado})) AS pagado"),
                DB::raw("ROUND(SUM({$notas})) AS notas_credito_aplicadas"),
                DB::raw("ROUND(SUM({$saldo})) AS saldo"),
                DB::raw("ROUND(SUM(CASE WHEN f.vencimiento < CURDATE() THEN {$saldo} ELSE 0 END)) AS saldo_vencido"),
                // Vencimiento más antiguo que todavía se debe → días de mora.
                DB::raw("MIN(CASE WHEN f.vencimiento < CURDATE() AND {$saldo} > 1 THEN f.vencimiento END) AS vencido_desde"),
                DB::raw('MIN(f.fecha) AS primera_factura'),
                DB::raw('MAX(f.fecha) AS ultima_factura'),
            ])
            ->get();

        foreach ($rows as $r) {
            $cid = (int) $r->cliente;
            if (! isset($out[$cid])) {
                continue;
            }
            $out[$cid]['facturas_total'] = (int) $r->facturas_total;
            $out[$cid]['facturas_abiertas'] = (int) $r->facturas_abiertas;
            $out[$cid]['facturado'] = (float) $r->facturado;
            $out[$cid]['pagado'] = (float) $r->pagado;
            $out[$cid]['notas_credito_aplicadas'] = (float) $r->notas_credito_aplicadas;
            $out[$cid]['saldo'] = (float) $r->saldo;
            $out[$cid]['saldo_vencido'] = (float) $r->saldo_vencido;
            $out[$cid]['primera_factura'] = $r->primera_factura;
            $out[$cid]['ultima_factura'] = $r->ultima_factura;
            $out[$cid]['dias_mora'] = $r->vencido_desde
                ? max(0, (int) floor((strtotime('today') - strtotime($r->vencido_desde)) / 86400))
                : 0;
        }

        // Último pago recibido, en su propia consulta: mezclarlo con los
        // agregados de arriba obligaría a un GROUP BY que falsea las sumas.
        $pagos = DB::table('ingresos as i')
            ->join('ingresos_factura as igf', 'igf.ingreso', '=', 'i.id')
            ->join('factura as fa', 'fa.id', '=', 'igf.factura')
            ->whereIn('fa.cliente', $ids)
            ->where('fa.empresa', $empresa)
            ->where('i.estatus', '<>', 2)
            ->groupBy('fa.cliente')
            ->select([
                'fa.cliente',
                DB::raw('MAX(i.fecha) AS ultimo_pago_fecha'),
            ])
            ->get();

        foreach ($pagos as $p) {
            $cid = (int) $p->cliente;
            if (isset($out[$cid])) {
                $out[$cid]['ultimo_pago_fecha'] = $p->ultimo_pago_fecha;
            }
        }
    }

    /** Contratos del cliente, desglosados por estado. */
    private function agregarContratos(array &$out, array $ids): void
    {
        $tienePausa = Schema::hasColumn('contracts', 'no_facturar');

        $select = [
            'c.client_id',
            DB::raw('COUNT(*) AS contratos_total'),
            DB::raw("SUM(c.status = 1 AND c.state = 'enabled') AS contratos_activos"),
            DB::raw("SUM(c.status = 1 AND c.state = 'disabled') AS contratos_cortados"),
            DB::raw('SUM(c.status <> 1) AS contratos_retirados'),
            DB::raw("GROUP_CONCAT(DISTINCT NULLIF(p.name,'') ORDER BY p.name SEPARATOR ' · ') AS planes"),
        ];
        if ($tienePausa) {
            $select[] = DB::raw('SUM(c.no_facturar = 1) AS contratos_pausados');
        }

        $rows = DB::table('contracts as c')
            ->leftJoin('planes_velocidad as p', 'p.id', '=', 'c.plan_id')
            ->whereIn('c.client_id', $ids)
            ->groupBy('c.client_id')
            ->select($select)
            ->get();

        foreach ($rows as $r) {
            $cid = (int) $r->client_id;
            if (! isset($out[$cid])) {
                continue;
            }
            $out[$cid]['contratos_total'] = (int) $r->contratos_total;
            $out[$cid]['contratos_activos'] = (int) $r->contratos_activos;
            $out[$cid]['contratos_cortados'] = (int) $r->contratos_cortados;
            $out[$cid]['contratos_retirados'] = (int) $r->contratos_retirados;
            $out[$cid]['contratos_pausados'] = $tienePausa ? (int) ($r->contratos_pausados ?? 0) : 0;
            $out[$cid]['planes'] = (string) ($r->planes ?? '');
        }
    }

    /** Notas crédito emitidas al cliente (documento completo, no lo aplicado). */
    private function agregarNotasCredito(array &$out, array $ids, int $empresa): void
    {
        if (! Schema::hasTable('notas_credito') || ! Schema::hasTable('items_notas')) {
            return;
        }

        $totalNota = '(SELECT COALESCE(SUM((inn.cant*inn.precio)-(inn.precio*(COALESCE(inn.`desc`,0)/100)*inn.cant)+(inn.precio-(inn.precio*(COALESCE(inn.`desc`,0)/100)))*(COALESCE(inn.impuesto,0)/100)*inn.cant),0) FROM items_notas inn WHERE inn.nota = nc.id)';

        $rows = DB::table('notas_credito as nc')
            ->whereIn('nc.cliente', $ids)
            ->where('nc.empresa', $empresa)
            ->where('nc.estatus', 1)
            ->groupBy('nc.cliente')
            ->select([
                'nc.cliente',
                DB::raw('COUNT(*) AS nc_cantidad'),
                DB::raw("ROUND(SUM({$totalNota})) AS nc_monto"),
            ])
            ->get();

        foreach ($rows as $r) {
            $cid = (int) $r->cliente;
            if (isset($out[$cid])) {
                $out[$cid]['nc_cantidad'] = (int) $r->nc_cantidad;
                $out[$cid]['nc_monto'] = (float) $r->nc_monto;
            }
        }
    }
}
