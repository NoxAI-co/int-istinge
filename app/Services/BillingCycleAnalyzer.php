<?php

namespace App\Services;

use App\GrupoCorte;
use App\Contrato;
use App\Empresa;
use App\Model\Ingresos\Factura;
use App\NumeracionFactura;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class BillingCycleAnalyzer
{
    /**
     * Limpiar caché de un ciclo específico
     */
    public function setGeneratingFlag($grupoCorteId, $state = true)
    {
        $key = "generating_cycle_stats_{$grupoCorteId}";
        if ($state) {
            Cache::put($key, true, 600); // 10 mins max
        } else {
            Cache::forget($key);
        }
    }

    public function clearCycleCache($grupoCorteId, $periodo)
    {
        $cacheKey = "cycle_stats_v28_{$grupoCorteId}_{$periodo}";
        \Log::info("Clearing Cycle Cache: {$cacheKey}");
        Cache::forget($cacheKey);
    }

    /**
     * Obtiene estadísticas completas de un ciclo de facturación
     * 
     * @param int $grupoCorteId
     * @param string $periodo Formato: Y-m (ej: 2026-02)
     * @return array
     */
    public function getCycleStats($grupoCorteId, $periodo, $forceRefresh = false)
    {
        // v28: Optimización profunda - rangos fecha, batch preload, agregar histórico
        $cacheKey = "cycle_stats_v28_{$grupoCorteId}_{$periodo}";
        
        $isGenerating = Cache::has("generating_cycle_stats_{$grupoCorteId}");
        
        if ($forceRefresh || $isGenerating) {
            Cache::forget($cacheKey);
            if ($isGenerating) {
                \Log::info("CycleStats [{$grupoCorteId}-{$periodo}]: Bypassing cache because generation is in progress.");
            }
        }

        return Cache::remember($cacheKey, 300, function () use ($grupoCorteId, $periodo) {
            $grupoCorte = GrupoCorte::find($grupoCorteId);
            if (!$grupoCorte) {
                return null;
            }

            // Calcular fecha del ciclo
            $fechaCiclo = $this->calcularFechaCiclo($grupoCorte, $periodo);
            $empresaId = $grupoCorte->empresa;

            // Total real de contratos del grupo (sin scopes para coincidir con SQL manual)
            $potentialContracts = Contrato::withoutGlobalScopes()
                ->where('contracts.grupo_corte', $grupoCorteId)
                ->where('contracts.status', 1)
                ->get();
            
            $totalContratosGrupo = $potentialContracts->count();
            
            // Obtener contratos que deberían facturar (filtrados por fecha del ciclo)
            $contratosEsperados = $this->getContractsExpectedToInvoice($grupoCorteId, $periodo);
            
            // Obtener facturas generadas en el ciclo
            $facturasGeneradas = $this->getGeneratedInvoices($grupoCorteId, $periodo);
            
            \Log::info("CycleStats [{$grupoCorteId}-{$periodo}]: Esperados=" . $contratosEsperados->count() . ", Generadas=" . $facturasGeneradas->count());
            
            // Análisis de facturas faltantes (pasamos colecciones ya obtenidas para evitar re-queries)
            $missingAnalysis = $this->getMissingInvoicesAnalysis($grupoCorteId, $periodo, $contratosEsperados, $facturasGeneradas);
            
            // Reporte de Cronología
            $onDateCount = 0;
            $onDateManualCount = 0;
            $onDateAutoCount = 0;
            
            $outDateCount = 0;
            $outDateManualCount = 0;
            $outDateAutoCount = 0;
            $diaEsperado = $this->calcularDiaEsperado($grupoCorte, $periodo);
            
            $whatsappStats = [
                'sent' => 0,
                'pending' => 0
            ];

            foreach ($facturasGeneradas as $factura) {
                // Si el día esperado es 0 (No aplica), las contamos todas en el primer contador (Detectadas)
                if ($diaEsperado == 0 || Carbon::parse($factura->fecha)->day == $diaEsperado) {
                    $onDateCount++;
                    if ($factura->factura_mes_manual == 1) {
                        $onDateManualCount++;
                    } else {
                        $onDateAutoCount++;
                    }
                } else {
                    $outDateCount++;
                    if ($factura->factura_mes_manual == 1) {
                        $outDateManualCount++;
                    } else {
                        $outDateAutoCount++;
                    }
                }

                if ($factura->whatsapp == 1) {
                    $whatsappStats['sent']++;
                } else {
                    $whatsappStats['pending']++;
                }
            }
            
            return [
                'grupo_corte' => $grupoCorte,
                'periodo' => $periodo,
                'fecha_ciclo' => $fechaCiclo,
                'total_contratos_ciclo' => $totalContratosGrupo,
                'total_activos' => $potentialContracts->where('state', 'enabled')->count(),
                'total_deshabilitados' => $potentialContracts->where('state', 'disabled')->count(),
                'facturas_generadas' => ($onDateManualCount + $outDateManualCount),
                'facturas_esperadas' => $contratosEsperados->count(),
                'facturas_faltantes' => $missingAnalysis['total'],
                'facturas_en_fecha' => $onDateManualCount,
                'facturas_en_fecha_manual' => $onDateManualCount,
                'facturas_en_fecha_auto' => $onDateAutoCount,
                'facturas_fuera_fecha' => $outDateManualCount,
                'facturas_fuera_fecha_manual' => $outDateManualCount,
                'facturas_fuera_fecha_auto' => $outDateAutoCount,
                'whatsapp_stats' => $whatsappStats,
                'dia_esperado' => $diaEsperado,
                'tasa_exito' => $contratosEsperados->count() > 0 
                    ? round((($onDateManualCount + $outDateManualCount) / $contratosEsperados->count()) * 100, 2) 
                    : 0,
                'facturas' => $facturasGeneradas,
                'missing_reasons' => $missingAnalysis['reasons'],
                'missing_details' => $missingAnalysis['details'],
                'missing_breakdown' => $missingAnalysis['missing_breakdown'],
                'duplicates_analysis' => $this->getDuplicateInvoicesAnalysis($facturasGeneradas, $fechaCiclo, $empresaId),
                'numbering_health' => $this->checkNumberingHealth($contratosEsperados->count()),
                'prorrateo_stats' => [
                    'con_prorrateo' => $contratosEsperados->where('prorrateo', 1)->count(),
                    'sin_prorrateo' => $contratosEsperados->where('prorrateo', 0)->count(),
                ]
            ];
        });
    }

    /**
     * Calcula el día esperado para el ciclo, manejando fin de mes
     */
    private function calcularDiaEsperado($grupoCorte, $periodo)
    {
        list($year, $month) = explode('-', $periodo);
        $dia = $grupoCorte->fecha_factura;
        
        if ($dia == 0) {
            return 0; // No aplica
        }
        
        $ultimoDiaMes = Carbon::create($year, $month, 1)->endOfMonth()->day;
        return ($dia > $ultimoDiaMes) ? $ultimoDiaMes : $dia;
    }

    /**
     * Calcula la fecha del ciclo basándose en la fecha_factura del grupo
     * Maneja correctamente meses con diferentes días (28, 30, 31)
     */
    private function calcularFechaCiclo($grupoCorte, $periodo)
    {
        list($year, $month) = explode('-', $periodo);
        $dia = $grupoCorte->fecha_factura;
        
        if ($dia == 0) {
            $dia = 1; // Si no aplica, usamos el día 1 para referenciar el mes correctamente
        }
        
        // Obtener último día del mes
        $ultimoDiaMes = Carbon::create($year, $month, 1)->endOfMonth()->day;
        
        // Si el día de facturación es mayor al último día del mes, usar el último día
        if ($dia > $ultimoDiaMes) {
            $dia = $ultimoDiaMes;
        }
        
        return Carbon::create($year, $month, $dia)->format('Y-m-d');
    }

    /**
     * Obtiene todos los contratos que deberían haber facturado en el ciclo
     */
    public function getContractsExpectedToInvoice($grupoCorteId, $periodo)
    {
        $grupoCorte = GrupoCorte::find($grupoCorteId);
        $fechaCiclo = $this->calcularFechaCiclo($grupoCorte, $periodo);
        
        $empresa = Empresa::find($grupoCorte->empresa);
        $state = ['enabled'];
        if ($empresa->factura_contrato_off == 1) {
            $state[] = 'disabled';
        }

        // Calcular fin de mes del periodo analizado
        $yearMonth = explode('-', $periodo);
        $fechaFinMes = Carbon::create($yearMonth[0], $yearMonth[1], 1)->endOfMonth()->format('Y-m-d');

        $empresa = Empresa::find($grupoCorte->empresa);
        $state = ['enabled'];
        if ($empresa->factura_contrato_off == 1) {
            $state[] = 'disabled';
        }

        // Obtener contratos del grupo (sin scopes para ver el total real del grupo)
        $contratos = Contrato::withoutGlobalScopes()->leftJoin('contactos as c', 'c.id', '=', 'contracts.client_id')
            ->select('contracts.*', 'c.nombre as cli_nombre', 'c.apellido1 as cli_ap1', 'c.apellido2 as cli_ap2', 'c.nit as cli_nit')
            ->where('contracts.grupo_corte', $grupoCorteId)
            ->where('contracts.status', 1) 
            ->whereIn('contracts.state', $state) // AHORA SÍ: Filtramos por los que realmente esperamos facturar
            ->get();

        return $contratos;
    }

    /**
     * Obtiene las facturas generadas en el ciclo
     */
    private function getGeneratedInvoices($grupoCorteId, $periodo)
    {
        $query = $this->getGeneratedInvoicesQuery($grupoCorteId, $periodo);
        
        if (!$query) {
            return collect();
        }

        return $query->get()->unique(function($item) {
            return $item->id . '-' . $item->contrato_id;
        });
    }

    /**
     * Análisis completo de facturas faltantes con razones específicas
     */
    public function getMissingInvoicesAnalysis($grupoCorteId, $periodo, $contratosEsperados = null, $facturasGeneradas = null)
    {
        $grupoCorte = GrupoCorte::find($grupoCorteId);
        $fechaCiclo = $this->calcularFechaCiclo($grupoCorte, $periodo);
        $empresa = Empresa::find($grupoCorte->empresa);
        
        // Reutilizar colecciones si ya fueron obtenidas (evitar queries duplicadas)
        if ($contratosEsperados === null) {
            $contratosEsperados = $this->getContractsExpectedToInvoice($grupoCorteId, $periodo);
        }
        if ($facturasGeneradas === null) {
            $facturasGeneradas = $this->getGeneratedInvoices($grupoCorteId, $periodo);
        }
        // Obtener IDs de contratos que ya facturaron (aseguramos casting a string para in_array)
        $idsGenerados = $facturasGeneradas->map(function($f) {
            return (string)($f->contrato_id ?? $f->getAttribute('contrato_id'));
        })->unique()->filter()->toArray();
        
        // Contratos que no facturaron
        $contratosSinFactura = $contratosEsperados->filter(function($contrato) use ($idsGenerados) {
            return !in_array((string)$contrato->id, $idsGenerados);
        });

        $esperadosCount = $contratosEsperados->count();
        $generadosCount = count($idsGenerados);
        $missingCount = $contratosSinFactura->count();
        $matched = $esperadosCount - $missingCount;

        \Log::info("MissingAnalysis [{$grupoCorteId}-{$periodo}]: Esperados={$esperadosCount}, UniqueContractIdsInGeneradas={$generadosCount}, MatchedWithEsperados={$matched}, Missing={$missingCount}");

        // ============================================================
        // BATCH PRE-LOAD: Cargar todos los datos necesarios para el
        // análisis de validaciones en queries batch, evitando N+1
        // ============================================================
        
        $contratoIds = $contratosSinFactura->pluck('id')->toArray();
        $contratoNros = $contratosSinFactura->pluck('nro')->toArray();

        if (empty($contratoIds)) {
            return [
                'total' => 0,
                'reasons' => [],
                'details' => [],
                'missing_breakdown' => ['standard' => 0, 'electronic' => 0]
            ];
        }

        // 1. Pre-load: Última factura de cada contrato (directo + pivot) en 2 queries
        $ultimasFacturasMap = $this->preloadUltimasFacturas($contratoIds, $contratoNros);

        // 2. Pre-load: Existencia en facturas_contratos (1 query)
        $contratosConPivot = DB::table('facturas_contratos')
            ->whereIn('contrato_nro', $contratoNros)
            ->pluck('contrato_nro')
            ->unique()
            ->flip()
            ->toArray();

        // 3. Pre-load: Facturas existentes para la fecha del ciclo - directas (1 query)
        $facturasEnFechaDirectas = DB::table('factura')
            ->whereDate('fecha', $fechaCiclo)
            ->whereIn('contrato_id', $contratoIds)
            ->where('estatus', '!=', 2)
            ->pluck('contrato_id')
            ->flip()
            ->toArray();

        // 4. Pre-load: Facturas existentes para la fecha del ciclo - pivot (1 query)
        $facturasEnFechaPivot = DB::table('facturas_contratos')
            ->join('factura', 'factura.id', '=', 'facturas_contratos.factura_id')
            ->whereDate('factura.fecha', $fechaCiclo)
            ->whereIn('facturas_contratos.contrato_nro', $contratoNros)
            ->where('factura.estatus', '!=', 2)
            ->pluck('facturas_contratos.contrato_nro')
            ->flip()
            ->toArray();
        
        // Empaquetar lookups pre-cargados
        $lookups = [
            'ultimas_facturas' => $ultimasFacturasMap,
            'contratos_con_pivot' => $contratosConPivot,
            'facturas_fecha_directas' => $facturasEnFechaDirectas,
            'facturas_fecha_pivot' => $facturasEnFechaPivot,
        ];
        
        $reasons = [];
        $details = [];
        
        foreach ($contratosSinFactura as $contrato) {
            $razon = $this->analyzeContractValidations($contrato, $fechaCiclo, $empresa, $grupoCorte, $lookups);
            
            if (!isset($reasons[$razon['code']])) {
                $reasons[$razon['code']] = [
                    'code' => $razon['code'],
                    'title' => $razon['title'],
                    'count' => 0,
                    'color' => $razon['color']
                ];
            }
            
            $reasons[$razon['code']]['count']++;
            
            $details[] = [
                'contrato_nro' => $contrato->nro,
                'contrato_id' => $contrato->id,
                'cliente_id' => $contrato->client_id,
                'cliente_nombre' => trim("{$contrato->cli_nombre} {$contrato->cli_ap1} {$contrato->cli_ap2}"),
                'cliente_nit' => $contrato->cli_nit,
                'razon_code' => $razon['code'],
                'razon_title' => $razon['title'],
                'razon_description' => $razon['description'],
                'factura_id' => $razon['factura_id'] ?? null,
                'factura_nro' => $razon['factura_nro'] ?? null
            ];
        }
        
        return [
            'total' => $contratosSinFactura->count(),
            'reasons' => array_values($reasons),
            'details' => $details,
            'missing_breakdown' => $this->calculateMissingBreakdown($contratosSinFactura)
        ];
    }

    /**
     * Pre-carga las últimas facturas de múltiples contratos en 2 queries batch
     * Reemplaza N llamadas individuales a getUltimoHistorialFacturacion
     */
    private function preloadUltimasFacturas($contratoIds, $contratoNros)
    {
        // Query 1: Últimas facturas directas (por contrato_id) - usando subquery MAX
        $directas = DB::table('factura')
            ->join(DB::raw('(SELECT contrato_id, MAX(id) as max_id FROM factura WHERE estatus != 2 AND contrato_id IS NOT NULL GROUP BY contrato_id) as latest'), function($join) {
                $join->on('factura.id', '=', 'latest.max_id');
            })
            ->whereIn('factura.contrato_id', $contratoIds)
            ->select('factura.contrato_id', 'factura.id', 'factura.fecha', 'factura.nro', 'factura.codigo', 
                     'factura.factura_mes_manual', 'factura.facturacion_automatica', 'factura.tipo', 
                     'factura.prorrateo_aplicado', 'factura.created_at', 'factura.estatus', 'factura.vencimiento')
            ->get()
            ->keyBy('contrato_id');

        // Query 2: Últimas facturas via pivot (por contrato_nro) - usando subquery MAX
        $viaPivot = DB::table('factura')
            ->join('facturas_contratos as fc', 'factura.id', '=', 'fc.factura_id')
            ->join(DB::raw('(SELECT fc2.contrato_nro, MAX(factura.id) as max_id FROM factura JOIN facturas_contratos as fc2 ON factura.id = fc2.factura_id WHERE factura.estatus != 2 GROUP BY fc2.contrato_nro) as latest'), function($join) {
                $join->on('factura.id', '=', 'latest.max_id')
                     ->on('fc.contrato_nro', '=', 'latest.contrato_nro');
            })
            ->whereIn('fc.contrato_nro', $contratoNros)
            ->select('fc.contrato_nro', 'factura.id', 'factura.fecha', 'factura.nro', 'factura.codigo', 
                     'factura.factura_mes_manual', 'factura.facturacion_automatica', 'factura.tipo', 
                     'factura.prorrateo_aplicado', 'factura.created_at', 'factura.estatus', 'factura.vencimiento')
            ->get()
            ->keyBy('contrato_nro');

        // Combinar: para cada contrato, elegir la factura más reciente entre directa y pivot
        $map = [];
        
        foreach ($contratoIds as $index => $contratoId) {
            $nro = $contratoNros[$index] ?? null;
            $directa = $directas->get($contratoId);
            $pivot = $nro ? $viaPivot->get($nro) : null;

            if ($directa && $pivot) {
                // Elegir la más reciente por fecha, luego por ID
                $map[$contratoId] = ($directa->fecha >= $pivot->fecha) ? $directa : $pivot;
            } elseif ($directa) {
                $map[$contratoId] = $directa;
            } elseif ($pivot) {
                $map[$contratoId] = $pivot;
            }
        }

        return $map;
    }

    /**
     * Calcula el desglose de contratos por tipo de facturación
     */
    private function calculateMissingBreakdown($contratos)
    {
        $breakdown = [
            'standard' => 0,
            'electronic' => 0
        ];

        foreach ($contratos as $contrato) {
            // Lógica basada en NumeracionFactura::tipoNumeracion
            if (isset($contrato->facturacion) && $contrato->facturacion == 3) {
                $breakdown['electronic']++;
            } else {
                $breakdown['standard']++;
            }
        }

        return $breakdown;
    }

    /**
     * Analiza las validaciones del CronController para determinar por qué no se generó la factura
     * Usa lookups pre-cargados para evitar queries por contrato (0 queries)
     */
    private function analyzeContractValidations($contrato, $fechaCiclo, $empresa, $grupoCorte, $lookups = [])
    {
        // 0. Validación PRIORITARIA: Grupo de corte deshabilitado
        if ($grupoCorte->status != 1) {
            return [
                'code' => 'billing_group_disabled',
                'title' => 'Grupo de corte deshabilitado',
                'description' => 'El grupo de corte no está activo',
                'color' => 'danger'
            ];
        }

        // 1. Validación: Primera factura del contrato
        $creacion_contrato = Carbon::parse($contrato->created_at);
        $dia_creacion_contrato = $creacion_contrato->day;
        $dia_creacion_factura = $grupoCorte->fecha_factura;
        
        if ($dia_creacion_contrato <= $dia_creacion_factura) {
            $primer_fecha_factura = $creacion_contrato->copy()->day($dia_creacion_factura);
        } else {
            $primer_fecha_factura = $creacion_contrato->copy()->addMonth()->day($dia_creacion_factura);
        }
        $primer_fecha_factura = Carbon::parse($primer_fecha_factura)->format("Y-m-d");
        
        // Usar lookup pre-cargado en vez de query
        $tienePivot = isset($lookups['contratos_con_pivot'][$contrato->nro]);
        if (!$tienePivot) {
            if (isset($primer_fecha_factura) && 
                Carbon::parse($fechaCiclo)->format("Y-m-d") == $primer_fecha_factura && 
                $contrato->fact_primer_mes == 0) {
                return [
                    'code' => 'first_invoice_skip',
                    'title' => 'Primera factura no corresponde',
                    'description' => 'El contrato tiene fact_primer_mes = 0 y es su primer ciclo de facturación',
                    'color' => 'info'
                ];
            }
        }
        
        // 2. Validación: Factura del mes ya existe — usar lookup pre-cargado
        $ultimaFactura = $lookups['ultimas_facturas'][$contrato->id] ?? null;
        
        $mesActualFactura = date('Y-m', strtotime($fechaCiclo));
        
        if ($ultimaFactura) {
            if ($ultimaFactura->tipo == 2) {
                $mesUltimaFactura = date('Y-m', strtotime($ultimaFactura->created_at));
            } else {
                $mesUltimaFactura = date('Y-m', strtotime($ultimaFactura->fecha));
            }
            
            if ($mesActualFactura == $mesUltimaFactura) {
                if ($ultimaFactura->factura_mes_manual == 1) {
                    return [
                        'code' => 'invoice_month_exists',
                        'title' => 'Factura del mes ya existe',
                        'description' => 'Ya tiene una factura generada para este mes con "Factura del Mes" = SI',
                        'color' => 'warning'
                    ];
                } else {
                    $fechaFormateada = Carbon::parse($ultimaFactura->fecha)->translatedFormat('j \d\e F - Y');
                    $tipoFactura = $ultimaFactura->facturacion_automatica == 1 ? 'automática' : 'manual';
                    
                    if (isset($ultimaFactura->prorrateo_aplicado) && $ultimaFactura->prorrateo_aplicado == 1) {
                        return [
                            'code' => 'prorated_unflagged_invoice',
                            'title' => 'Factura del mes sin marcar con prorrateo',
                            'description' => "Se detectó una factura prorrateada creada en la fecha {$fechaFormateada} con 'Factura del Mes' = NO.",
                            'color' => 'warning',
                            'factura_id' => $ultimaFactura->id,
                            'factura_nro' => $ultimaFactura->nro
                        ];
                    }
                    
                    return [
                        'code' => 'unflagged_invoice',
                        'title' => 'Factura en el mes sin marcar',
                        'description' => "Se detectó una factura {$tipoFactura} creada en la fecha {$fechaFormateada} pero tiene 'Factura del Mes' = NO. El sistema no la vincula al ciclo.",
                        'color' => 'danger',
                        'factura_id' => $ultimaFactura->id,
                        'factura_nro' => $ultimaFactura->nro
                    ];
                }
            }
            
            // 3. Validación: Factura abierta vigente
            if ($mesActualFactura != $mesUltimaFactura || 
                ($mesActualFactura == $mesUltimaFactura && $ultimaFactura->factura_mes_manual == 0 && $ultimaFactura->facturacion_automatica == 0)) {
                
                if ($ultimaFactura->estatus == 1 && $ultimaFactura->vencimiento > $fechaCiclo) {
                    return [
                        'code' => 'open_invoice_active',
                        'title' => 'Factura abierta vigente',
                        'description' => 'Tiene una factura abierta con vencimiento mayor a la fecha del ciclo',
                        'color' => 'primary'
                    ];
                }

                // 4. Validación: Facturas abiertas bloquean nueva facturación (cron_fact_abiertas = 0)
                if ($ultimaFactura->estatus == 1 && $empresa->cron_fact_abiertas == 0) {
                    return [
                        'code' => 'open_invoices_blocked',
                        'title' => 'Facturación bloqueada por facturas abiertas',
                        'description' => 'El contrato tiene una factura abierta antigua y la empresa (cron_fact_abiertas = 0) no permite generar nuevas facturas.',
                        'color' => 'warning'
                    ];
                }
            }
        }
        
        // 5. Validación: Ya se creó factura para esta fecha — usar lookups pre-cargados
        $existeDirecto = isset($lookups['facturas_fecha_directas'][$contrato->id]);
        $existePivot = isset($lookups['facturas_fecha_pivot'][$contrato->nro]);

        if ($existeDirecto || $existePivot) {
            return [
                'code' => 'duplicate_today',
                'title' => 'Factura ya generada',
                'description' => 'Ya existe una factura para este contrato con la fecha del ciclo',
                'color' => 'success'
            ];
        }
        
        // 6. Validación: Estado deshabilitado (state)
        if ($contrato->state == 'disabled' && $empresa->factura_contrato_off != 1) {
            return [
                'code' => 'contract_disabled_off',
                'title' => 'El contrato tiene estado deshabilitado',
                'description' => 'El contrato está deshabilitado y la empresa no permite facturar contratos OFF (factura_contrato_off = 0)',
                'color' => 'danger',
                'action_required' => 'enable_off_billing'
            ];
        }

        // 6b. Validación: Contrato deshabilitado por TV (OLT CATV)
        if (!empty($contrato->olt_sn_mac) && $contrato->state_olt_catv == 0) {
            return [
                'code' => 'contract_disabled_tv',
                'title' => 'Contrato deshabilitado por TV',
                'description' => 'El contrato tiene OLT/MAC asignado pero el servicio de TV está deshabilitado (state_olt_catv = 0)',
                'color' => 'warning'
            ];
        }
        
        // 8. Validación: Numeración
        $nro_numeracion = NumeracionFactura::tipoNumeracion($contrato);
        if (is_null($nro_numeracion)) {
            return [
                'code' => 'no_valid_numbering',
                'title' => 'Numeración no asignada o vencida',
                'description' => 'El contrato no tiene una numeración de facturas asignada, está vencida o no es preferida',
                'color' => 'danger',
                'action_required' => 'fix_numbering'
            ];
        }
        
        // 9. Verificación final en tiempo real: buscar factura del mes directamente en BD
        // Esto atrapa casos donde la factura existe pero el preload/caché no la detectó
        $facturaRealTimeDirecta = DB::table('factura')
            ->where('contrato_id', $contrato->id)
            ->where('estatus', '!=', 2)
            ->where('factura_mes_manual', 1)
            ->where(function($q) use ($fechaCiclo) {
                $q->whereYear('fecha', date('Y', strtotime($fechaCiclo)))
                  ->whereMonth('fecha', date('m', strtotime($fechaCiclo)));
            })
            ->first();

        if ($facturaRealTimeDirecta) {
            return [
                'code' => 'invoice_month_exists',
                'title' => 'Factura del mes ya existe',
                'description' => 'Ya tiene una factura generada para este mes (detectada por verificación directa)',
                'color' => 'warning',
                'factura_id' => $facturaRealTimeDirecta->id,
                'factura_nro' => $facturaRealTimeDirecta->nro
            ];
        }

        $facturaRealTimePivot = DB::table('factura')
            ->join('facturas_contratos as fc', 'factura.id', '=', 'fc.factura_id')
            ->where('fc.contrato_nro', $contrato->nro)
            ->where('factura.estatus', '!=', 2)
            ->where('factura.factura_mes_manual', 1)
            ->where(function($q) use ($fechaCiclo) {
                $q->whereYear('factura.fecha', date('Y', strtotime($fechaCiclo)))
                  ->whereMonth('factura.fecha', date('m', strtotime($fechaCiclo)));
            })
            ->first();

        if ($facturaRealTimePivot) {
            return [
                'code' => 'invoice_month_exists',
                'title' => 'Factura del mes ya existe',
                'description' => 'Ya tiene una factura generada para este mes (detectada vía pivot)',
                'color' => 'warning',
                'factura_id' => $facturaRealTimePivot->id,
                'factura_nro' => $facturaRealTimePivot->nro
            ];
        }

        // Si llegamos aquí, problema no identificado
        return [
            'code' => 'unidentified_issue',
            'title' => 'Problema no identificado',
            'description' => 'El contrato cumple con las condiciones básicas para generar factura pero el sistema no detectó una razón específica de omisión. Se recomienda intentar la generación manual.',
            'color' => 'secondary',
            'action_required' => 'manual_generation'
        ];
    }

    /**
     * Obtiene datos históricos de ciclos para gráficas (últimos N meses)
     */
    public function getHistoricalData($grupoCorteId, $months = 6)
    {
        $grupoCorte = GrupoCorte::find($grupoCorteId);
        if (!$grupoCorte) {
            return [];
        }

        // Pre-calcular todos los rangos de fecha para los N meses
        $periodos = [];
        $globalStart = null;
        $globalEnd = null;

        for ($i = $months - 1; $i >= 0; $i--) {
            $periodo = Carbon::now()->subMonths($i)->format('Y-m');
            $fechaCiclo = $this->calcularFechaCiclo($grupoCorte, $periodo);
            $startOfMonth = Carbon::parse($fechaCiclo)->startOfMonth();
            $startOfNextMonth = $startOfMonth->copy()->addMonth();
            $fechaFinMes = $startOfMonth->copy()->endOfMonth()->format('Y-m-d');

            $periodos[] = [
                'periodo' => $periodo,
                'fechaFinMes' => $fechaFinMes,
                'startOfMonth' => $startOfMonth->format('Y-m-d'),
                'startOfNextMonth' => $startOfNextMonth->format('Y-m-d'),
            ];

            if (!$globalStart || $startOfMonth->format('Y-m-d') < $globalStart) {
                $globalStart = $startOfMonth->format('Y-m-d');
            }
            if (!$globalEnd || $startOfNextMonth->format('Y-m-d') > $globalEnd) {
                $globalEnd = $startOfNextMonth->format('Y-m-d');
            }
        }

        // Query 1: Contratos por mes (1 query agregada con CASE WHEN)
        // Para cada periodo, contar contratos creados antes del fin de mes con status=1 y que tengan cliente existente
        $contratosCountByPeriodo = [];
        foreach ($periodos as $p) {
            $contratosCountByPeriodo[$p['periodo']] = Contrato::join('contactos as c', 'c.id', '=', 'contracts.client_id')
                ->where('contracts.grupo_corte', $grupoCorteId)
                ->where('contracts.created_at', '<=', $p['fechaFinMes'])
                ->where('contracts.status', 1)
                ->count();
        }

        // Query 2: IDs de facturas generadas agrupadas por mes (2 queries: directa + pivot)
        // Usar rango global para filtrar y luego agrupar en PHP
        $directas = DB::table('factura')
            ->join('contracts as c', 'c.id', '=', 'factura.contrato_id')
            ->where('c.grupo_corte', $grupoCorteId)
            ->where('factura.estatus', '!=', 2)
            ->where('factura.fecha', '>=', $globalStart)
            ->where('factura.fecha', '<', $globalEnd)
            ->where('factura.factura_mes_manual', 1)
            ->select('factura.id', 'factura.fecha')
            ->get();

        $pivots = DB::table('factura')
            ->join('facturas_contratos as fc', 'factura.id', '=', 'fc.factura_id')
            ->join('contracts as c', 'fc.contrato_nro', '=', 'c.nro')
            ->where('c.grupo_corte', $grupoCorteId)
            ->where('factura.estatus', '!=', 2)
            ->where('factura.fecha', '>=', $globalStart)
            ->where('factura.fecha', '<', $globalEnd)
            ->where('factura.factura_mes_manual', 1)
            ->select('factura.id', 'factura.fecha')
            ->get();

        // Agrupar facturas únicas por periodo
        $allFacturas = $directas->merge($pivots)->unique('id');

        $data = [];
        foreach ($periodos as $p) {
            // Contar facturas que caen en el rango de este periodo
            $generadas = $allFacturas->filter(function($f) use ($p) {
                return $f->fecha >= $p['startOfMonth'] && $f->fecha < $p['startOfNextMonth'];
            })->count();

            $esperadas = $contratosCountByPeriodo[$p['periodo']] ?? 0;
            $tasaExito = $esperadas > 0 ? round(($generadas / $esperadas) * 100, 2) : 0;

            $data[] = [
                'periodo' => $p['periodo'],
                'periodo_label' => Carbon::parse($p['periodo'] . '-01')->locale('es')->isoFormat('MMM Y'),
                'generadas' => $generadas,
                'esperadas' => $esperadas,
                'tasa_exito' => $tasaExito
            ];
        }
        
        return $data;
    }

    /**
     * Obtiene lista de todos los períodos disponibles desde el primer ciclo
     */
    public function getAvailableCycles($grupoCorteId)
    {
        $grupoCorte = GrupoCorte::find($grupoCorteId);
        
        // Obtener la fecha del contrato más antiguo del grupo
        $primerContrato = Contrato::where('grupo_corte', $grupoCorteId)
            ->orderBy('created_at', 'asc')
            ->first();
        
        if (!$primerContrato) {
            return [];
        }
        
        $fechaInicio = Carbon::parse($primerContrato->created_at)->startOfMonth();
        $fechaActual = Carbon::now()->addMonth()->endOfMonth();
        
        $ciclos = [];
        $fecha = $fechaInicio->copy();
        
        while ($fecha <= $fechaActual) {
            $ciclos[] = [
                'value' => $fecha->format('Y-m'),
                'label' => $fecha->locale('es')->isoFormat('MMMM YYYY')
            ];
            $fecha->addMonth();
        }
        
        return array_reverse($ciclos); // Más recientes primero
    }

    /**
     * Verifica la salud de las numeraciones de facturación con proyección de consumo
     * 
     * @param int $consumoEstimadoMensual Cantidad de facturas promedio que se generan por mes (base contratos activos)
     */
    public function checkNumberingHealth($consumoEstimadoMensual = 0)
    {
        $empresaId = 1; 
        if (auth()->check()) {
            $empresaId = auth()->user()->empresa;
        }

        // Si el consumo estimado es 0 (ej: grupo vacío/nuevo), asumimos un mínimo de 1 para evitar división por cero
        // O mejor, usamos un valor base razonable si no hay contratos, pero aquí la proyección es contextual
        $consumoBase = $consumoEstimadoMensual > 0 ? $consumoEstimadoMensual : 100;

        $health = [
            'standard' => ['status' => 'ok', 'message' => 'Numeración Estándar OK'],
            'electronic' => ['status' => 'ok', 'message' => 'Numeración Electrónica OK']
        ];

        // 1. Numeración Estándar (Tipo 1)
        $estandar = NumeracionFactura::where('empresa', $empresaId)
            ->where('preferida', 1)
            ->where('estado', 1)
            ->where('tipo', 1)
            ->where('num_equivalente', 0)
            ->where('nomina', 0)
            ->first();

        $health['standard'] = $this->analyzeNumbering($estandar, 'Estándar', $consumoBase);

        // 2. Numeración Electrónica (Tipo 2)
        $electronica = NumeracionFactura::where('empresa', $empresaId)
            ->where('preferida', 1)
            ->where('estado', 1)
            ->where('tipo', 2)
            ->where('num_equivalente', 0)
            ->where('nomina', 0)
            ->first();

        $health['electronic'] = $this->analyzeNumbering($electronica, 'Electrónica', $consumoBase);

        return $health;
    }

    /**
     * Analiza una numeración específica y genera su reporte de salud
     */
    private function analyzeNumbering($numeracion, $tipo, $consumoEstimado)
    {
        if (!$numeracion) {
            return [
                'status' => 'error', 
                'message' => "No hay numeración $tipo preferida activa",
                'details' => null
            ];
        }

        $restantes = $numeracion->final - $numeracion->inicio;
        $diasVencimiento = Carbon::now()->diffInDays(Carbon::parse($numeracion->hasta), false);
        $status = 'ok';
        $message = "Numeración $tipo operativa";
        $recommendation = "La numeración es suficiente para la operación actual.";

        // Proyección de suficiencia (meses)
        $mesesSuficiencia = $consumoEstimado > 0 ? floor($restantes / $consumoEstimado) : 999;

        // Validaciones
        if ($diasVencimiento < 0) {
            $status = 'error';
            $message = "Resolución vencida (Venció el " . Carbon::parse($numeracion->hasta)->format('d/m/Y') . ")";
            $recommendation = "Solicitar nueva resolución inmediatamente.";
        } elseif ($numeracion->inicio >= $numeracion->final) {
            $status = 'error';
            $message = "Consecutivos agotados (Llegó al límite: {$numeracion->final})";
            $recommendation = "Solicitar nueva resolución inmediatamente.";
        } elseif ($diasVencimiento < 30) {
            $status = 'warning';
            $message = "Resolución vence pronto ({$diasVencimiento} días)";
            $recommendation = "Tramitar renovación antes del " . Carbon::parse($numeracion->hasta)->format('d/m/Y') . ".";
        } elseif ($restantes < $consumoEstimado) {
            // No alcanza para un ciclo completo estimado
            $status = 'warning';
            $message = "Insuficiente para próximo ciclo completo";
            $recommendation = "Quedan $restantes folios. Se requieren aprox $consumoEstimado para un ciclo completo.";
        } elseif ($restantes < 50) {
            $status = 'warning';
            $message = "Quedan pocos consecutivos ($restantes)";
            $recommendation = "Solicitar nueva resolución pronto.";
        }

        // Si todo está OK, dar una proyección positiva
        if ($status == 'ok') {
            if ($mesesSuficiencia < 3) {
                $recommendation = "Suficiente para aprox. $mesesSuficiencia ciclos de facturación.";
            } else {
                $recommendation = "Numeración saludable. Cobertura estimada para +3 ciclos.";
            }
        }

        return [
            'status' => $status,
            'message' => $message,
            'recommendation' => $recommendation,
            'details' => [
                'expiration' => Carbon::parse($numeracion->hasta)->format('d/m/Y'),
                'current' => $numeracion->inicio,
                'limit' => $numeracion->final,
                'remaining' => $restantes,
                'sufficiency_months' => $mesesSuficiencia
            ]
        ];
    }

    /**
     * Marca en lote las facturas manuales detectadas como "no vinculadas"
     * 
     * @param int $grupoCorteId
     * @param string $periodo
     * @return int Cantidad de facturas marcadas
     */
    public function marcarFacturasMesLote($grupoCorteId, $periodo)
    {
        $missingAnalysis = $this->getMissingInvoicesAnalysis($grupoCorteId, $periodo);
        $idsToFix = [];

        foreach ($missingAnalysis['details'] as $detail) {
            if ($detail['razon_code'] === 'unflagged_invoice' && !empty($detail['factura_id'])) {
                $idsToFix[] = $detail['factura_id'];
            }
        }

        if (empty($idsToFix)) {
            return 0;
        }

        return \App\Models\Factura::whereIn('id', $idsToFix)->update(['factura_mes_manual' => 1]);
    }

    /**
     * Actualiza el contrato a fact_primer_mes = 1 cuando se saltó la primera factura
     */
    public function actualizarContratosPrimerMes($grupoCorteId, $periodo)
    {
        $missingAnalysis = $this->getMissingInvoicesAnalysis($grupoCorteId, $periodo);
        $idsToFix = [];

        foreach ($missingAnalysis['details'] as $detail) {
            if ($detail['razon_code'] === 'first_invoice_skip' && !empty($detail['contrato_id'])) {
                $idsToFix[] = $detail['contrato_id'];
            }
        }

        if (empty($idsToFix)) {
            return 0;
        }

        // Actualizar fact_primer_mes usando DB query directo por eficiencia
        \Illuminate\Support\Facades\DB::table('contracts')
            ->whereIn('id', $idsToFix)
            ->update(['fact_primer_mes' => 1]);

        return count($idsToFix);
    }

    /**
     * Analiza si existen contratos con múltiples facturas en el mismo ciclo
     * 
     * @param Collection $facturasGeneradas
     * @return array
     */
    private function getDuplicateInvoicesAnalysis($facturasGeneradas, $fechaCiclo = null, $empresaId = null)
    {
        $duplicates = [];
        $totalExcedentes = 0;

        // Filtrar solo facturas del mes del ciclo (excluir facturas del mes siguiente)
        if ($fechaCiclo) {
            $mesCiclo = Carbon::parse($fechaCiclo)->format('Y-m');
            $facturasDelMes = $facturasGeneradas->filter(function($f) use ($mesCiclo) {
                return Carbon::parse($f->fecha)->format('Y-m') === $mesCiclo;
            });
        } else {
            $facturasDelMes = $facturasGeneradas;
        }
        
        // Agrupar por contrato_id
        $grouped = $facturasDelMes->groupBy('contrato_id');
        
        foreach ($grouped as $contratoId => $facturas) {
            if ($facturas->count() > 1) {
                $totalExcedentes += ($facturas->count() - 1);
                $duplicates[] = [
                    'contrato_id' => $contratoId,
                    'contrato_nro' => $facturas->first()->contrato_nro,
                    'cliente_id' => $facturas->first()->cliente,
                    'cliente_nombre' => $facturas->first()->nombre_cliente,
                    'cantidad' => $facturas->count(),
                    'facturas' => $facturas->map(function($f) use ($empresaId) {
                        // Calcular total si tenemos empresaId
                        $total = 0;
                        if ($empresaId) {
                            $facturaModel = Factura::find($f->id);
                            if ($facturaModel) {
                                $total = $facturaModel->totalAPI($empresaId)->total ?? 0;
                            }
                        }

                        $estatus = $f->getAttributeValue('estatus');
                        $estatusTexto = ($estatus == 1) ? 'Abierta' : ($estatus == 0 ? 'Cerrada' : 'Anulada');
                        $estatusClase = ($estatus == 1) ? 'success' : ($estatus == 0 ? 'secondary' : 'danger');

                        return [
                            'id' => $f->id,
                            'nro' => $f->nro,
                            'codigo' => $f->codigo,
                            'fecha' => $f->fecha,
                            'estatus' => $estatus,
                            'estatus_texto' => $estatusTexto,
                            'estatus_clase' => $estatusClase,
                            'total' => $total,
                            'tipo_operacion' => $f->tipo_operacion == 1 ? 'Estandar' : 'Electronica',
                            'factura_mes_manual' => $f->factura_mes_manual,
                            'facturacion_automatica' => $f->facturacion_automatica
                        ];
                    })->toArray()
                ];
            }
        }
        
        return [
            'total_excedentes' => $totalExcedentes,
            'contratos_duplicados' => $duplicates,
            'conteo_duplicados' => count($duplicates)
        ];
    }

    /**
     * Obtiene el query builder para las facturas generadas (para DataTables)
     */
    public function getGeneratedInvoicesQuery($grupoCorteId, $periodo, $search = null)
    {
        $grupoCorte = GrupoCorte::find($grupoCorteId);
        if (!$grupoCorte) {
            return null;
        }
        
        $fechaCiclo = $this->calcularFechaCiclo($grupoCorte, $periodo);
        $startOfMonth = Carbon::parse($fechaCiclo)->startOfMonth()->format('Y-m-d');
        $startOfNextMonth = Carbon::parse($fechaCiclo)->startOfMonth()->addMonth()->format('Y-m-d');

        // Closure reutilizable para filtro de tipo (ESTRICTO: Solo Factura del Mes)
        $tipoFilter = function($query) {
            $query->where('factura.factura_mes_manual', 1);
        };

        // Closure para búsqueda
        $searchFilter = function($query) use ($search) {
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('factura.codigo', 'like', "%{$search}%")
                      ->orWhere('factura.nro', 'like', "%{$search}%")
                      ->orWhere('cli.nombre', 'like', "%{$search}%")
                      ->orWhere('cli.nit', 'like', "%{$search}%")
                      ->orWhere('c.nro', 'like', "%{$search}%");
                });
            }
        };
        
        // Query 1: Facturas vinculadas directamente
        $query1 = Factura::withoutGlobalScopes()->join('contracts as c', 'c.id', '=', 'factura.contrato_id')
            ->join('contactos as cli', 'cli.id', '=', 'factura.cliente')
            ->select(
                'factura.id', 
                'factura.nro', 
                'factura.codigo', 
                'factura.fecha', 
                'factura.vencimiento', 
                'factura.estatus', 
                'factura.whatsapp', 
                'factura.cliente',
                'factura.factura_mes_manual',
                'factura.facturacion_automatica',
                'factura.tipo_operacion',
                DB::raw("CONCAT_WS(' ', cli.nombre, cli.apellido1, cli.apellido2) as nombre_cliente"),
                'cli.nit as nit_cliente',
                'c.id as contrato_id',
                'c.nro as contrato_nro'
            )
            ->where('c.grupo_corte', $grupoCorteId)
            ->where('factura.estatus', '!=', 2)
            ->where('factura.fecha', '>=', $startOfMonth)
            ->where('factura.fecha', '<', $startOfNextMonth)
            ->where($tipoFilter)
            ->where($searchFilter);

        // Query 2: Facturas vinculadas por pivot
        $query2 = Factura::withoutGlobalScopes()->join('facturas_contratos as fc', 'factura.id', '=', 'fc.factura_id')
            ->join('contracts as c', 'fc.contrato_nro', '=', 'c.nro')
            ->join('contactos as cli', 'cli.id', '=', 'factura.cliente')
            ->select(
                'factura.id', 
                'factura.nro', 
                'factura.codigo', 
                'factura.fecha', 
                'factura.vencimiento', 
                'factura.estatus', 
                'factura.whatsapp', 
                'factura.cliente',
                'factura.factura_mes_manual',
                'factura.facturacion_automatica',
                'factura.tipo_operacion',
                DB::raw("CONCAT_WS(' ', cli.nombre, cli.apellido1, cli.apellido2) as nombre_cliente"),
                'cli.nit as nit_cliente',
                'c.id as contrato_id',
                'c.nro as contrato_nro'
            )
            ->where('c.grupo_corte', $grupoCorteId)
            ->where('factura.estatus', '!=', 2)
            ->where('factura.fecha', '>=', $startOfMonth)
            ->where('factura.fecha', '<', $startOfNextMonth)
            ->where($tipoFilter)
            ->where($searchFilter);

        return $query1->union($query2);
    }
}
