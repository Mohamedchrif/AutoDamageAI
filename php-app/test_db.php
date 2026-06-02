<?php
require_once 'config.php';

$t0 = microtime(true);
$analyses = $pdo->query("SELECT id, timestamp, cost_min, cost_max, is_undamaged FROM analyses")->fetchAll();
$t1 = microtime(true);
echo "Query SELECT without JSON/images took: " . ($t1 - $t0) . " seconds. Row count: " . count($analyses) . "\n";

$t0 = microtime(true);
$analyses2 = $pdo->query("SELECT id, timestamp, cost_min, cost_max, is_undamaged, JSON_EXTRACT(result_json, '$.detected_issues') as detected_issues_json FROM analyses")->fetchAll();
$t1 = microtime(true);
echo "Query SELECT with JSON_EXTRACT took: " . ($t1 - $t0) . " seconds. Row count: " . count($analyses2) . "\n";

if (!empty($analyses2)) {
    echo "First row detected_issues_json snippet: " . substr($analyses2[0]['detected_issues_json'], 0, 100) . "\n";
}

$t0 = microtime(true);
try {
    $analyses3 = $pdo->query("SELECT * FROM analyses")->fetchAll();
    $t1 = microtime(true);
    echo "Query SELECT * (with base64 images) took: " . ($t1 - $t0) . " seconds. Row count: " . count($analyses3) . "\n";
} catch (Exception $e) {
    echo "Query SELECT * failed: " . $e->getMessage() . "\n";
}
