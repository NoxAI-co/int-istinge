<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\BillingCycleAnalyzer;
use Illuminate\Support\Facades\Log;

$analyzer = new BillingCycleAnalyzer();
$grupoId = 11;
$periodo = '2026-05';

echo "Triggering Analysis for Group {$grupoId}, Period {$periodo}...\n";
$stats = $analyzer->getCycleStats($grupoId, $periodo);

echo "Stats:\n";
print_r($stats);
