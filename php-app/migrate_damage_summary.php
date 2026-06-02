<?php
/**
 * Migration script: Add damage_summary column and backfill from result_json.
 * Run once: php migrate_damage_summary.php
 */
require_once 'config.php';

echo "=== AutoDamg: damage_summary Migration ===\n\n";

// Step 1: Add column if it doesn't exist
try {
    $cols = $pdo->query("SHOW COLUMNS FROM analyses LIKE 'damage_summary'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE analyses ADD COLUMN damage_summary TEXT DEFAULT NULL AFTER is_undamaged");
        echo "[OK] Column 'damage_summary' added.\n";
    } else {
        echo "[SKIP] Column 'damage_summary' already exists.\n";
    }
} catch (Exception $e) {
    echo "[ERROR] Could not add column: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 2: Backfill existing rows that have result_json but no damage_summary
// We process one row at a time to avoid loading all base64 data into memory
$rows = $pdo->query("SELECT id FROM analyses WHERE damage_summary IS NULL ORDER BY id")->fetchAll();
$total = count($rows);
echo "\n[INFO] Found $total rows to backfill.\n";

$updated = 0;
$errors = 0;

foreach ($rows as $i => $row) {
    $id = $row['id'];
    
    // Fetch result_json for this single row
    $stmt = $pdo->prepare("SELECT result_json FROM analyses WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();
    
    if (empty($data['result_json'])) {
        continue;
    }
    
    $result = json_decode($data['result_json'], true);
    if (!$result || !isset($result['detected_issues'])) {
        continue;
    }
    
    // Build lightweight summary: only class, severity, part (no images, no bboxes)
    $summary = [];
    foreach ($result['detected_issues'] as $issue) {
        $summary[] = [
            'class'    => $issue['class'] ?? 'unknown',
            'severity' => $issue['severity'] ?? 'minor',
            'part'     => $issue['part'] ?? 'unknown',
        ];
    }
    
    $summaryJson = json_encode($summary);
    
    $upd = $pdo->prepare("UPDATE analyses SET damage_summary = ? WHERE id = ?");
    if ($upd->execute([$summaryJson, $id])) {
        $updated++;
    } else {
        $errors++;
    }
    
    // Progress indicator every 10 rows
    if (($i + 1) % 10 === 0 || ($i + 1) === $total) {
        echo "  Progress: " . ($i + 1) . " / $total\n";
    }
}

echo "\n[DONE] Backfilled $updated rows. Errors: $errors.\n";

// Step 3: Verify
$t0 = microtime(true);
$test = $pdo->query("SELECT id, timestamp, cost_min, cost_max, is_undamaged, damage_summary FROM analyses")->fetchAll();
$t1 = microtime(true);
echo "\n[BENCHMARK] SELECT with damage_summary took: " . round($t1 - $t0, 4) . " seconds for " . count($test) . " rows.\n";
echo "  (Compare: JSON_EXTRACT was ~19 seconds, SELECT * was ~28 seconds)\n";
